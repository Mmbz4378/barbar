<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\DB;
use App\Core\RateLimiter;
use App\Domain\System\SiteSettings;
use App\Support\IranMobile;
use App\Support\Jalali;

/**
 * ورود با نام کاربری (یا موبایل) و رمز.
 *
 * نکته‌های امنیتی:
 *   - پیام خطا همیشه یکی است و برای کاربرِ ناموجود هم password_verify روی
 *     هش ساختگی اجرا می‌شود، تا نه از متن و نه از زمان پاسخ نشود فهمید
 *     حسابی وجود دارد یا نه.
 *   - پس از چند تلاش ناموفقِ پیاپی، حساب چند دقیقه قفل می‌شود. قفل فقط
 *     جلوی رمز را می‌گیرد؛ صاحب حساب با کد پیامکی همچنان وارد می‌شود، پس
 *     کسی نمی‌تواند با قفل کردن عمدی، دیگری را بیرون نگه دارد.
 *   - تلاش‌های ناموفق از یک IP هم سقف دارد (در حافظه و در دیتابیس).
 */
final class PasswordAuth
{
    /** bcrypt بیش از ۷۲ بایت را نادیده می‌گیرد؛ طولانی‌تر یعنی امنیتِ موهوم. */
    public const MAX_BYTES = 72;

    /** سقف تلاش ناموفق از یک IP در ساعت (پشت CGNAT هزاران نفر یک IP دارند). */
    private const IP_FAILURES_PER_HOUR = 100;

    private const IP_ATTEMPTS_PER_MINUTE = 30;

    private const COMMON = [
        '12345678', '123456789', '1234567890', '87654321', '11111111', '00000000', '12341234',
        '11223344', '123123123', 'password', 'password1', 'password123', 'qwerty123', 'qwertyui',
        'iloveyou', '1q2w3e4r', '1qaz2wsx', 'abcd1234', 'aa123456', 'asdf1234', 'zxcvbnm1',
        'admin123', 'admin1234', 'administrator', 'reshen123', 'barber123', 'salon123',
    ];

    private static ?string $dummyHash = null;

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** نام کاربری به شکل یکتا: کوچک، بی‌فاصله، ارقام لاتین. */
    public static function normalizeUsername(string $username): string
    {
        return strtolower(trim(Jalali::fromPersianDigits($username)));
    }

    public static function usernameError(string $username): ?string
    {
        if (!preg_match('/^[a-z][a-z0-9._-]{2,39}$/', $username)) {
            return 'نام کاربری ۳ تا ۴۰ نویسهٔ لاتین باشد و با حرف شروع شود؛ فقط حرف، عدد، نقطه، خط تیره و زیرخط.';
        }

        return null;
    }

    /** آیا این نام کاربری مال کس دیگری است؟ */
    public static function usernameTaken(string $username, ?int $exceptUserId = null): bool
    {
        $row = DB::selectOne('SELECT id FROM users WHERE username = ?', [$username]);

        return $row !== null && (int) $row['id'] !== (int) $exceptUserId;
    }

    /**
     * رمز پیشنهادی را می‌سنجد.
     *
     * @return string|null پیام خطا، یا null یعنی پذیرفتنی
     */
    public static function policyError(string $password, ?string $phoneE164 = null, ?string $username = null, ?int $minLength = null): ?string
    {
        $min = $minLength ?? SiteSettings::minPasswordLength();
        // نویسهٔ کنترلی (مثل null که bcrypt نمی‌پذیرد) از صفحه‌کلید نمی‌آید
        if (preg_match('/[\x00-\x1F\x7F]/', $password) === 1 || !mb_check_encoding($password, 'UTF-8')) {
            return 'رمز نویسهٔ نامعتبر دارد.';
        }
        if (mb_strlen($password) < $min) {
            return sprintf('رمز دست‌کم %s نویسه باشد.', Jalali::toPersianDigits((string) $min));
        }
        if (strlen($password) > self::MAX_BYTES) {
            return 'رمز بیش از حد طولانی است.';
        }
        $plain = strtolower(Jalali::fromPersianDigits($password));
        if (in_array($plain, self::COMMON, true) || preg_match('/^(.)\1+$/u', $plain)) {
            return 'این رمز بسیار رایج یا ساده است؛ رمز دیگری انتخاب کنید.';
        }
        if (ctype_digit($plain) && strlen($plain) < 10) {
            return 'رمزِ فقط‌عددی دست‌کم ۱۰ رقم باشد؛ بهتر است حرف هم داشته باشد.';
        }
        if ($phoneE164 !== null) {
            $digits = preg_replace('/\D/', '', $phoneE164) ?? '';
            $local = '0' . substr($digits, 2);
            if ($plain === $digits || $plain === $local || $plain === '+' . $digits) {
                return 'رمز نباید همان شمارهٔ موبایل باشد.';
            }
        }
        if ($username !== null && $username !== '' && $plain === strtolower($username)) {
            return 'رمز نباید همان نام کاربری باشد.';
        }

        return null;
    }

    /** کاربر با موبایل یا نام کاربری. */
    public static function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim(Jalali::fromPersianDigits($identifier));
        if ($identifier === '') {
            return null;
        }
        $phone = IranMobile::tryParse($identifier);
        if ($phone !== null) {
            return DB::selectOne('SELECT * FROM users WHERE phone = ?', [$phone->e164]);
        }

        return DB::selectOne('SELECT * FROM users WHERE username = ?', [self::normalizeUsername($identifier)]);
    }

    /**
     * @return array{ok:bool,user:?array,error:?string}
     */
    public function attempt(string $identifier, string $password): array
    {
        $identifier = mb_substr(trim($identifier), 0, 80);
        $generic = 'نام کاربری یا رمز درست نیست. اگر هنوز رمز تعیین نکرده‌اید، با کد پیامکی وارد شوید.';
        $ip = OtpService::clientIp();

        if ($ip !== null && (
            !RateLimiter::allow('pw-ip:' . $ip, self::IP_ATTEMPTS_PER_MINUTE, 60)
            || LoginEvents::recentFailuresFromIp($ip, 60) >= self::IP_FAILURES_PER_HOUR
        )) {
            LoginEvents::record(null, $identifier, LoginEvents::PASSWORD, false, 'rate_limited');

            return ['ok' => false, 'user' => null, 'error' => 'از این شبکه تلاش ناموفق زیادی شده است. کمی بعد دوباره تلاش کنید یا با کد پیامکی وارد شوید.'];
        }

        $user = self::findByIdentifier($identifier);

        if ($user === null || empty($user['password_hash'])) {
            // هم‌زمان با مسیر واقعی، تا زمان پاسخ چیزی لو ندهد
            password_verify($password, self::dummyHash());
            LoginEvents::record($user !== null ? (int) $user['id'] : null, $identifier, LoginEvents::PASSWORD, false, $user !== null ? 'no_password' : 'bad_credentials');

            return ['ok' => false, 'user' => null, 'error' => $generic];
        }

        $userId = (int) $user['id'];
        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            password_verify($password, self::dummyHash());
            LoginEvents::record($userId, $identifier, LoginEvents::PASSWORD, false, 'locked');

            return ['ok' => false, 'user' => null, 'error' => self::lockedMessage((string) $user['locked_until'])];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            DB::statement('UPDATE users SET failed_logins = failed_logins + 1 WHERE id = ?', [$userId]);
            $max = SiteSettings::maxLoginAttempts();
            $until = date('Y-m-d H:i:s', time() + SiteSettings::lockMinutes() * 60);
            $locked = DB::statement(
                'UPDATE users SET locked_until = ?, failed_logins = 0 WHERE id = ? AND failed_logins >= ?',
                [$until, $userId, $max]
            )->rowCount() > 0;
            LoginEvents::record($userId, $identifier, LoginEvents::PASSWORD, false, 'bad_credentials');

            return ['ok' => false, 'user' => null, 'error' => $locked ? self::lockedMessage($until) : $generic];
        }

        if ((int) ($user['is_active'] ?? 1) !== 1) {
            LoginEvents::record($userId, $identifier, LoginEvents::PASSWORD, false, 'blocked');

            return ['ok' => false, 'user' => null, 'error' => 'این حساب غیرفعال شده است. با مدیر سامانه تماس بگیرید.'];
        }

        $update = ['failed_logins' => 0, 'locked_until' => null];
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $update['password_hash'] = self::hash($password);
        }
        DB::update('users', $update, 'id = :id', ['id' => $userId]);
        LoginEvents::record($userId, $identifier, LoginEvents::PASSWORD, true);

        return ['ok' => true, 'user' => $user, 'error' => null];
    }

    /**
     * رمز تازه می‌گذارد. auth_version بالا می‌رود، پس همهٔ نشست‌های قبلیِ
     * این کاربر بسته می‌شوند (نشست جاری را فراخوان تازه می‌کند).
     */
    public static function setPassword(int $userId, string $password, bool $mustChange = false): void
    {
        DB::statement(
            'UPDATE users SET password_hash = ?, must_change_password = ?, password_changed_at = ?,
                    failed_logins = 0, locked_until = NULL, auth_version = auth_version + 1
              WHERE id = ?',
            [self::hash($password), $mustChange ? 1 : 0, date('Y-m-d H:i:s'), $userId]
        );
    }

    /** رمز تصادفیِ خوانا برای وقتی مدیر می‌خواهد رمز بسازد (یک بار نشان داده می‌شود). */
    public static function generate(int $length = 12): string
    {
        // بدون نویسه‌های هم‌شکل (0/O، 1/l/I) تا خواندن و تایپش راحت باشد
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    private static function lockedMessage(string $until): string
    {
        $minutes = max(1, (int) ceil((strtotime($until) - time()) / 60));

        return sprintf(
            'به‌خاطر چند تلاش ناموفق، ورود با رمز برای این حساب %s دقیقه بسته است. می‌توانید با کد پیامکی وارد شوید.',
            Jalali::toPersianDigits((string) $minutes)
        );
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }
}
