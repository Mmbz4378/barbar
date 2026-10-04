<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\Config;
use App\Core\DB;
use App\Core\RateLimiter;
use App\Domain\Messaging\SmsBreaker;
use App\Domain\Messaging\SmsManager;
use App\Support\IranMobile;
use App\Support\Jalali;
use App\Support\Str;

final class OtpService
{
    private const TTL_SECONDS = 120;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_COOLDOWN_SECONDS = 45;

    /** @return array{ok:bool,error:?string,retry_after:?int} retry_after: ثانیه تا تلاش بعدی */
    public function request(IranMobile $phone, string $purpose = 'login'): array
    {
        $ip = self::clientIp();

        // سیلِ ربات پیش از هر کوئری پس زده می‌شود (فقط با APCu؛ سقف بالا به‌خاطر CGNAT)
        if ($ip !== null && !RateLimiter::allow('otp-ip:' . $ip, (int) Config::get('reshen.sms.otp_burst_per_ip_per_minute', 60), 60)) {
            return ['ok' => false, 'error' => 'از این شبکه درخواست زیادی رسید. یک دقیقهٔ دیگر دوباره تلاش کنید.', 'retry_after' => 60];
        }

        $recent = DB::selectOne(
            'SELECT created_at FROM otp_codes WHERE phone = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$phone->e164, $purpose]
        );

        if ($recent !== null) {
            $elapsed = time() - strtotime($recent['created_at']);
            if ($elapsed < self::RESEND_COOLDOWN_SECONDS) {
                $wait = self::RESEND_COOLDOWN_SECONDS - $elapsed;

                return [
                    'ok' => false,
                    'error' => sprintf('برای ارسال دوباره، %s ثانیه دیگر صبر کنید.', Jalali::toPersianDigits((string) $wait)),
                    'retry_after' => $wait,
                ];
            }
        }

        $limitError = $this->checkHourlyLimits($phone, $ip);
        if ($limitError !== null) {
            return ['ok' => false, 'error' => $limitError, 'retry_after' => null];
        }

        $this->cleanupExpired();

        $code = Str::otp(5);

        // هر کد استفاده‌نشدهٔ قبلیِ این شماره همان لحظه باطل می‌شود.
        // وگرنه دو کد زنده هم‌زمان، شانس حدس زدن را دو برابر می‌کند.
        DB::update(
            'otp_codes',
            ['consumed_at' => date('Y-m-d H:i:s')],
            'phone = :phone AND purpose = :purpose AND consumed_at IS NULL',
            ['phone' => $phone->e164, 'purpose' => $purpose]
        );

        $otpId = DB::insert('otp_codes', [
            'phone' => $phone->e164,
            'code_hash' => password_hash($code, PASSWORD_DEFAULT),
            'purpose' => $purpose,
            'ip_address' => $ip,
            'attempts' => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TTL_SECONDS),
        ]);

        // اول الگوی تأییدشده. متن آزاد فقط وقتی استفاده می‌شود که هیچ
        // الگویی تنظیم نشده باشد — که در عمل یعنی سالن خط اختصاصی دارد،
        // تنها حالتی که متن آزاد واقعاً تحویل داده می‌شود.
        /*
         * OTP همزمان می‌ماند — کاربر باید بداند کد رفت یا نه. ولی اگر اپراتور
         * همین الان گیر کرده (مدار باز است)، به‌جای معطلی تا سقف مهلت، بی‌درنگ
         * پیام روشن می‌دهیم؛ زیر هجوم این معطلی‌ها همهٔ پردازش‌ها را می‌گرفت.
         */
        $result = SmsBreaker::isOpen()
            ? ['ok' => false, 'error' => 'سامانهٔ پیامک لحظاتی در دسترس نیست. یک دقیقهٔ دیگر دوباره تلاش کن.']
            : SmsBreaker::send(static fn (): array => SmsManager::sendPattern(
                $phone->e164,
                'otp',
                [$code],
                "کد ورود شما به رشن: {$code}\nتا ۲ دقیقه معتبر است."
            ));

        if (!$result['ok']) {
            // کد را بسوزان تا کاربر بتواند فوری دوباره تلاش کند و پشت
            // زمان انتظارِ پیامکی که اصلاً ارسال نشده گیر نیفتد.
            DB::update('otp_codes', ['consumed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $otpId]);

            return ['ok' => false, 'error' => 'ارسال پیامک ناموفق بود: ' . ($result['error'] ?? 'خطای نامشخص'), 'retry_after' => null];
        }

        return ['ok' => true, 'error' => null, 'retry_after' => null];
    }

    /** @return array{ok:bool,error:?string} */
    public function verify(IranMobile $phone, string $code, string $purpose = 'login'): array
    {
        return DB::transaction(fn() => $this->verifyLocked($phone,$code,$purpose));
    }
    private function verifyLocked(IranMobile $phone, string $code, string $purpose): array
    {
        $row = DB::selectOne(
            'SELECT * FROM otp_codes WHERE phone = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE',
            [$phone->e164, $purpose]
        );

        if ($row === null) {
            return ['ok' => false, 'error' => 'کدی برای این شماره درخواست نشده است.'];
        }

        if (strtotime($row['expires_at']) < time()) {
            return ['ok' => false, 'error' => 'کد منقضی شده است. دوباره درخواست کنید.'];
        }

        if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید بگیرید.'];
        }

        if (!password_verify(Jalali::fromPersianDigits($code), $row['code_hash'])) {
            $attempts = (int) $row['attempts'] + 1;
            DB::update('otp_codes', ['attempts' => $attempts], 'id = :id', ['id' => $row['id']]);
            $left = self::MAX_ATTEMPTS - $attempts;

            return [
                'ok' => false,
                'error' => $left > 0
                    ? sprintf('کد وارد شده اشتباه است. %s تلاش دیگر باقی مانده.', Jalali::toPersianDigits((string) $left))
                    : 'کد وارد شده اشتباه است. کد جدید درخواست کنید.',
            ];
        }

        DB::update('otp_codes', ['consumed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $row['id']]);

        return ['ok' => true, 'error' => null];
    }

    private function checkHourlyLimits(IranMobile $phone, ?string $ip): ?string
    {
        $since = date('Y-m-d H:i:s', time() - 3600);

        $byPhone = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM otp_codes WHERE phone = ? AND created_at > ?',
            [$phone->e164, $since]
        )['c'] ?? 0);

        if ($byPhone >= (int) Config::get('reshen.sms.otp_hourly_limit_phone', 5)) {
            return 'درخواست کد برای این شماره در یک ساعت گذشته زیاد بوده است. کمی بعد تلاش کنید.';
        }

        if ($ip !== null) {
            $byIp = (int) (DB::selectOne(
                'SELECT COUNT(*) AS c FROM otp_codes WHERE ip_address = ? AND created_at > ?',
                [$ip, $since]
            )['c'] ?? 0);

            if ($byIp >= (int) Config::get('reshen.sms.otp_hourly_limit_ip', 15)) {
                return 'تعداد درخواست‌ها زیاد بوده است. کمی بعد تلاش کنید.';
            }
        }

        return null;
    }

    private function cleanupExpired(): void
    {
        DB::delete('otp_codes', 'created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }

    public static function clientIp(): ?string
    {
        /*
         * پشت CDN (TRUST_PROXY=true) نشانی واقعی در سرآیند است؛ بدونش همهٔ
         * بازدیدکننده‌ها نشانیِ پروکسی را داشتند و سقف هر IP برای همه با هم پر
         * می‌شد. بدون پروکسی به سرآیندها اعتماد نمی‌کنیم چون جعل‌شدنی‌اند.
         */
        if (Config::get('app.trust_proxy', false)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
                $candidate = trim(explode(',', (string) ($_SERVER[$header] ?? ''))[0]);
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return substr($candidate, 0, 45);
                }
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? substr($ip, 0, 45) : null;
    }

    /**
     * کدی که در حالت توسعه روی صفحه نشان داده می‌شود.
     *
     * برای چه: کل مسیر ورود بدون اپراتور واقعی قابل آزمایش باشد.
     *
     * ولی هرگز برای شمارهٔ مدیر پلتفرم برنمی‌گردد. اگر یک بار حالت
     * دیباگ روی سرور واقعی روشن بماند، هر کسی که شمارهٔ مدیر را بداند
     * می‌تواند کدش را از روی صفحه بخواند و با دسترسی کامل پشتیبانی
     * وارد شود. آزمایش با شمارهٔ معمولی سر جایش است.
     */
    public static function devHint(string $e164Phone): ?string
    {
        if (!Config::get('app.debug') || Config::get('reshen.sms.driver', 'log') !== 'log') {
            return null;
        }

        $user = DB::selectOne('SELECT is_platform_admin FROM users WHERE phone = ?', [$e164Phone]);
        if ($user !== null && (int) $user['is_platform_admin'] === 1) {
            return null;
        }

        $path = BASE_PATH . '/storage/logs/sms.log';
        if (!is_file($path)) {
            return null;
        }

        $lines = file($path) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (str_contains($lines[$i], $e164Phone)) {
                return trim($lines[$i]);
            }
        }

        return null;
    }
}
