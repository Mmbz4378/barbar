<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Core\Cache;
use App\Core\Config;
use App\Core\DB;
use Throwable;

/**
 * تنظیمات سایت که مدیر کل از پنل عوض می‌کند: برند، سئو، لینک‌ها، ورود،
 * پیامک، پرداخت و حالت تعمیر.
 *
 * روی جدول system_settings با پیشوند «site.» می‌نشیند و در کش مشترک (APCu)
 * نگه داشته می‌شود؛ نام برند در هر صفحه لازم است و نباید هر بازدید یک
 * کوئری بزند. هر ذخیره نسخهٔ کش را بالا می‌برد، پس تغییر بی‌درنگ دیده
 * می‌شود.
 *
 * رمزها (پیامک، درگاه) با کلیدی مشتق از APP_KEY رمزگذاری می‌شوند؛ نشت
 * پشتیبان دیتابیس به‌تنهایی آن‌ها را لو نمی‌دهد.
 */
final class SiteSettings
{
    public const REG_CLOSED = 'closed';
    public const REG_OPEN = 'open';
    public const REG_APPROVAL = 'approval';

    public const DEFAULT_BRAND = 'رشن';

    /** شبکه‌های اجتماعی: کلید ← [برچسب، الگوی پیشوند نشانی برای نام کاربری خالی] */
    public const SOCIAL = [
        'instagram' => ['اینستاگرام', 'https://instagram.com/'],
        'telegram' => ['تلگرام', 'https://t.me/'],
        'whatsapp' => ['واتساپ', 'https://wa.me/'],
        'eitaa' => ['ایتا', 'https://eitaa.com/'],
        'bale' => ['بله', 'https://ble.ir/'],
        'rubika' => ['روبیکا', 'https://rubika.ir/'],
        'aparat' => ['آپارات', 'https://www.aparat.com/'],
    ];

    /** کلیدهای الگوی پیامک (همان SmsTemplates) */
    public const SMS_PATTERNS = [
        'otp' => 'کد ورود',
        'booking_confirmed' => 'تأیید نوبت',
        'booking_cancelled' => 'لغو نوبت',
        'reminder_24h' => 'یادآوری ۲۴ ساعته',
        'reminder_2h' => 'یادآوری ۲ ساعته',
        'queue_chair_ready' => 'صف: نوبت شماست',
        'queue_nearly_up' => 'صف: نزدیک نوبت',
        'queue_delayed' => 'صف: تأخیر',
    ];

    private const SECRET_PREFIX = 'enc:v1:';

    /** @var array<string,string>|null */
    private static ?array $map = null;

    // ─── دسترسی پایه ──────────────────────────────────────────────────

    public static function str(string $key, string $default = ''): string
    {
        $map = self::map();

        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::map());
    }

    public static function bool(string $key, bool $default): bool
    {
        return self::has($key) ? self::str($key) === '1' : $default;
    }

    public static function int(string $key, int $default, int $min, int $max): int
    {
        $value = self::str($key, '');
        if ($value === '' || !is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    /** @return array<mixed>|null */
    public static function json(string $key): ?array
    {
        $data = json_decode(self::str($key, ''), true);

        return is_array($data) ? $data : null;
    }

    /** مقدار تهی یعنی «برگرد به پیش‌فرض» (ردیف پاک می‌شود). */
    public static function set(string $key, ?string $value): void
    {
        self::setMany([$key => $value]);
    }

    /** @param array<string,?string> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                try {
                    DB::delete('system_settings', 'setting_key = :k', ['k' => 'site.' . $key]);
                } catch (Throwable) {
                    // جدول هنوز نیست
                }
            } else {
                SystemSettings::set('site.' . $key, $value);
            }
        }
        SystemSettings::flush();
        self::$map = null;
        Cache::bump('site_settings');
    }

    // ─── رمزها ────────────────────────────────────────────────────────

    public static function canEncrypt(): bool
    {
        return function_exists('sodium_crypto_secretbox') && (string) Config::get('app.key', '') !== '';
    }

    public static function setSecret(string $key, string $plain): void
    {
        if ($plain === '') {
            self::set($key, null);

            return;
        }
        if (!self::canEncrypt()) {
            throw new \RuntimeException('برای ذخیرهٔ رمز در پنل، افزونهٔ sodium و APP_KEY لازم است؛ این مقدار را در .env بگذارید.');
        }
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, self::secretKey());
        self::set($key, self::SECRET_PREFIX . base64_encode($nonce . $cipher));
    }

    /** مقدار رمزگشایی‌شده؛ اگر نیست یا با APP_KEY فعلی باز نمی‌شود، رشتهٔ خالی. */
    public static function secret(string $key): string
    {
        $stored = self::str($key, '');
        if (!str_starts_with($stored, self::SECRET_PREFIX) || !self::canEncrypt()) {
            return '';
        }
        $raw = base64_decode(substr($stored, strlen(self::SECRET_PREFIX)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $plain = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            self::secretKey()
        );

        return $plain === false ? '' : $plain;
    }

    private static function secretKey(): string
    {
        return sodium_crypto_generichash('reshen-site-settings|' . (string) Config::get('app.key', ''), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    // ─── ورود و ثبت‌نام ───────────────────────────────────────────────

    public static function passwordLoginEnabled(): bool
    {
        return self::bool('auth.password', true);
    }

    public static function otpLoginEnabled(): bool
    {
        // هر دو خاموش یعنی هیچ‌کس وارد نمی‌شود؛ ذخیره‌سازی جلویش را می‌گیرد،
        // ولی اگر دستی در دیتابیس شد، کد پیامکی باز می‌ماند.
        return self::bool('auth.otp', true) || !self::passwordLoginEnabled();
    }

    /** closed: فقط مدیر سالن و صاحبش را می‌سازد. open: هر کسی. approval: با تأیید مدیر. */
    public static function registrationMode(): string
    {
        $mode = self::str('auth.registration', self::REG_CLOSED);

        return in_array($mode, [self::REG_CLOSED, self::REG_OPEN, self::REG_APPROVAL], true) ? $mode : self::REG_CLOSED;
    }

    public static function minPasswordLength(): int
    {
        return self::int('auth.min_password', 8, 6, 64);
    }

    public static function maxLoginAttempts(): int
    {
        return self::int('auth.max_attempts', 5, 3, 20);
    }

    public static function lockMinutes(): int
    {
        return self::int('auth.lock_minutes', 15, 1, 1440);
    }

    // ─── برند و تماس ──────────────────────────────────────────────────

    public static function brandName(): string
    {
        $name = trim(self::str('brand.name'));

        return $name !== '' ? $name : self::DEFAULT_BRAND;
    }

    public static function tagline(): string
    {
        $tagline = trim(self::str('brand.tagline'));

        return $tagline !== '' ? $tagline : 'نوبت‌دهی آرایشگاه‌ها و سالن‌های زیبایی';
    }

    /** نشانی لوگو (نسبت به public)، یا null. */
    public static function logoPath(): ?string
    {
        $path = self::str('brand.logo');

        return $path !== '' && is_file(BASE_PATH . '/public/' . $path) ? $path : null;
    }

    /** آیکون سفارشیِ ساخته‌شده از لوگو، یا null (آیکون پیش‌فرض). */
    public static function iconPath(int $size): ?string
    {
        $base = self::str('brand.icon_base');
        $path = $base !== '' ? $base . '-' . $size . '.png' : '';

        return $path !== '' && is_file(BASE_PATH . '/public/' . $path) ? $path : null;
    }

    // ─── سئو و گوگل ───────────────────────────────────────────────────

    public static function indexingAllowed(): bool
    {
        return self::bool('seo.indexing', true);
    }

    /**
     * کد تأیید Search Console: کل تگ meta یا فقط مقدار content پذیرفته می‌شود.
     */
    public static function parseVerification(string $input): string
    {
        $input = trim($input);
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $input, $m)) {
            $input = $m[1];
        }

        return preg_match('/^[A-Za-z0-9_\-]{8,100}$/', $input) ? $input : '';
    }

    /** شناسهٔ Google Analytics 4 (G-XXXXXXX) یا رشتهٔ خالی. */
    public static function gaId(): string
    {
        $id = strtoupper(trim(self::str('seo.ga_id')));

        return preg_match('/^G-[A-Z0-9]{4,20}$/', $id) ? $id : '';
    }

    // ─── نماد اعتماد (اینماد) ─────────────────────────────────────────

    /**
     * از کد HTML ای که سایت اینماد می‌دهد فقط id و Code برداشته می‌شود و
     * نشانهٔ امن خودمان ساخته می‌شود؛ HTML دلخواه هرگز در صفحه نمی‌نشیند.
     *
     * @return array{id:string,code:string}|null
     */
    public static function parseEnamad(string $input): ?array
    {
        if (preg_match('/[?&]id=(\d{3,12})/i', $input, $id) && preg_match('/[?&]Code=([A-Za-z0-9]{6,64})/i', $input, $code)) {
            return ['id' => $id[1], 'code' => $code[1]];
        }

        return null;
    }

    /** @return array{id:string,code:string}|null */
    public static function enamad(): ?array
    {
        $data = self::json('links.enamad');

        return isset($data['id'], $data['code']) ? ['id' => (string) $data['id'], 'code' => (string) $data['code']] : null;
    }

    // ─── لینک‌ها ──────────────────────────────────────────────────────

    /** @return array<string,array{label:string,url:string}> شبکه‌های پرشده */
    public static function socialLinks(): array
    {
        $saved = self::json('links.social') ?? [];
        $out = [];
        foreach (self::SOCIAL as $key => [$label]) {
            $url = trim((string) ($saved[$key] ?? ''));
            if ($url !== '') {
                $out[$key] = ['label' => $label, 'url' => $url];
            }
        }

        return $out;
    }

    /** @return array<int,array{label:string,url:string}> */
    public static function footerLinks(): array
    {
        $links = [];
        foreach (self::json('links.footer') ?? [] as $row) {
            if (is_array($row) && trim((string) ($row['label'] ?? '')) !== '' && self::safeUrl((string) ($row['url'] ?? '')) !== null) {
                $links[] = ['label' => (string) $row['label'], 'url' => (string) $row['url']];
            }
        }

        return $links;
    }

    /**
     * نشانیِ امن برای پیوند: http(s)، یا مسیر داخلی «/…». بقیه (javascript:،
     * data:، …) رد می‌شوند.
     */
    public static function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        return preg_match('#^https?://[^\s<>"\']+$#i', $url) ? $url : null;
    }

    // ─── حالت تعمیر ───────────────────────────────────────────────────

    public static function maintenanceOn(): bool
    {
        return self::bool('maintenance.on', false);
    }

    public static function maintenanceMessage(): string
    {
        $message = trim(self::str('maintenance.message'));

        return $message !== '' ? $message : 'در حال به‌روزرسانی و بهبود سایت هستیم. کمی بعد دوباره سر بزنید.';
    }

    // ─── لایهٔ پنل روی config (پیامک، پرداخت) ───────────────────────

    /**
     * فقط مقادیری که مدیر در پنل ذخیره کرده؛ بقیه همان .env می‌مانند.
     *
     * @return array<string,mixed>
     */
    public static function configOverlay(): array
    {
        try {
            $out = [];
            $driver = self::str('sms.driver');
            if (in_array($driver, ['log', 'melipayamak', 'kavenegar'], true)) {
                $out['reshen.sms.driver'] = $driver;
            }
            foreach (['melipayamak.username', 'melipayamak.sender'] as $key) {
                if (self::has('sms.' . $key)) {
                    $out['reshen.sms.credentials.' . $key] = self::str('sms.' . $key);
                }
            }
            foreach (['melipayamak.password', 'kavenegar.api_key'] as $key) {
                if (self::has('sms.' . $key)) {
                    $out['reshen.sms.credentials.' . $key] = self::secret('sms.' . $key);
                }
            }
            foreach (['melipayamak', 'kavenegar'] as $provider) {
                foreach (array_keys(self::SMS_PATTERNS) as $pattern) {
                    $key = 'sms.patterns.' . $provider . '.' . $pattern;
                    if (self::has($key)) {
                        $out['reshen.' . $key] = self::str($key);
                    }
                }
            }
            if (self::has('sms.dedicated_line')) {
                $out['reshen.sms.dedicated_line'] = self::bool('sms.dedicated_line', false);
            }
            $payment = self::str('payment.driver');
            if (in_array($payment, ['disabled', 'zarinpal'], true)) {
                $out['reshen.payment.driver'] = $payment;
            }
            if (self::has('payment.zarinpal.merchant_id')) {
                $out['reshen.payment.zarinpal.merchant_id'] = self::secret('payment.zarinpal.merchant_id');
            }
            if (self::has('payment.zarinpal.sandbox')) {
                $out['reshen.payment.zarinpal.sandbox'] = self::bool('payment.zarinpal.sandbox', false);
            }

            return $out;
        } catch (Throwable $e) {
            error_log('settings overlay skipped: ' . $e->getMessage());

            return [];
        }
    }

    /** پس از تغییر مستقیم دیتابیس (آزمون‌ها). */
    public static function flush(): void
    {
        self::$map = null;
        SystemSettings::flush();
    }

    /** @return array<string,string> */
    private static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }
        try {
            self::$map = Cache::remember('site_settings:v' . Cache::version('site_settings'), 3600, static function (): array {
                $map = [];
                foreach (DB::select("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'site.%'") as $row) {
                    if ($row['setting_value'] !== null) {
                        $map[substr((string) $row['setting_key'], 5)] = (string) $row['setting_value'];
                    }
                }

                return $map;
            });
        } catch (Throwable) {
            // پیش از نصب یا دیتابیسِ در دسترس نبود: پیش‌فرض‌ها
            self::$map = [];
        }

        return self::$map;
    }
}
