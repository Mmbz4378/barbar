<?php

declare(strict_types=1);

namespace App\Core;

/**
 * نشست، تنبل و بدون قفلِ ماندگار.
 *
 * چرا این‌طور: گردانندهٔ پیش‌فرض فایلیِ PHP، از لحظهٔ session_start تا پایان
 * اسکریپت یک قفل انحصاری روی فایل نشست نگه می‌دارد. یعنی اگر یک کاربر چند
 * درخواست هم‌زمان بفرستد — تازه‌سازی خودکار، چند زبانه، مراحل رزرو — آن‌ها
 * پشت سر هم صف می‌کشند. زیر بار، همین قفل، تأخیر را چند برابر می‌کند.
 *
 * راه‌حل:
 *   ۱. خواندن با read_and_close: داده خوانده می‌شود و قفل همان لحظه آزاد.
 *   ۲. نوشتن‌ها (کم‌اند: ورود، flash، توکن) هرکدام نشست را کوتاه باز می‌کنند،
 *      می‌نویسند و فوراً می‌بندند (mutate).
 *   ۳. بازدیدکنندهٔ ناشناس که کوکی ندارد، اصلاً نشست و کوکی نمی‌گیرد — تا
 *      صفحه‌های عمومی قابل کش بمانند و I/O بی‌جا نشود.
 *
 * چون هر دسترسی به نشست از همین کلاس می‌گذرد (هیچ‌جای دیگر $_SESSION مستقیم
 * دست‌کاری نمی‌شود)، این الگو یک‌جا و امن اعمال می‌شود.
 */
final class Session
{
    private static bool $booted = false;

    /** کوکی نشست هنگام ورود درخواست وجود داشت؟ */
    private static bool $hadCookie = false;

    /** در این درخواست چیزی روی نشست نوشته شد؟ */
    private static bool $wrote = false;

    /** نشست را کد دیگری (مثل نصاب) پیش از ما باز کرده و صاحبش است؟ */
    private static bool $external = false;

    public static function start(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        // در CLI (تست، کرون، مهاجرت) نشست معنی ندارد؛ فقط آرایهٔ حافظه‌ای کار می‌کند.
        if (PHP_SAPI === 'cli') {
            return;
        }

        // نصاب نشست خودش را باز کرده و بعد برنامه را بالا می‌آورد. نام و کوکیِ
        // نشستِ باز را نمی‌شود عوض کرد (هشدار PHP، و روی هاستی که خطا نمایش
        // داده می‌شود، هدایت پایان نصب را می‌شکند)؛ همان را به کار بگیر.
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$external = true;

            return;
        }

        $name = (string) Config::get('app.session_name', 'reshen_session');
        session_name($name);
        self::applyCookieParams();

        if (isset($_COOKIE[$name])) {
            self::$hadCookie = true;
            // داده را بخوان و قفل را همان لحظه آزاد کن.
            @session_start(['read_and_close' => true]);
        }
        // بدون کوکی: هیچ نشستی باز نمی‌شود. اولین نوشتن (اگر پیش بیاید)
        // در mutate() نشست و کوکی را می‌سازد.
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return (isset($_SESSION) ? $_SESSION : [])[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::mutate(static function () use ($key, $value): void {
            $_SESSION[$key] = $value;
        });
    }

    public static function forget(string $key): void
    {
        // چیزی برای برداشتن نیست — نشست را بی‌جا باز نکن.
        if (!isset($_SESSION[$key])) {
            return;
        }
        self::mutate(static function () use ($key): void {
            unset($_SESSION[$key]);
        });
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if (func_num_args() === 1) {
            if (!isset($_SESSION['_flash'][$key])) {
                return null;
            }
            $value = $_SESSION['_flash'][$key];
            self::mutate(static function () use ($key): void {
                unset($_SESSION['_flash'][$key]);
            });

            return $value;
        }
        self::mutate(static function () use ($key, $value): void {
            $_SESSION['_flash'][$key] = $value;
        });

        return null;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            self::mutate(static function (): void {
                if (empty($_SESSION['_csrf'])) {
                    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
                }
            });
        }

        return (string) ($_SESSION['_csrf'] ?? '');
    }

    /**
     * فقط می‌خواند — توکن تازه نمی‌سازد و نشست را باز نمی‌کند. پس مسیر POST
     * تأیید، قفل نشست نمی‌گیرد و درخواست‌های موازی را معطل نمی‌کند.
     */
    public static function verifyCsrf(?string $token): bool
    {
        $stored = (string) ($_SESSION['_csrf'] ?? '');

        return $stored !== '' && is_string($token) && hash_equals($stored, $token);
    }

    public static function regenerate(): void
    {
        self::mutate(static function (): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        });
    }

    public static function destroy(): void
    {
        if (PHP_SAPI === 'cli') {
            $_SESSION = [];

            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];
        @session_destroy();
        self::$wrote = true;

        // کوکی نشست را هم منقضی کن تا مرورگر دیگر آن را نفرستد.
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $params['secure'] ?? false,
        ]);
    }

    /**
     * آیا این پاسخ به نشست وابسته است؟ (کوکی داشت، یا چیزی نوشته شد)
     *
     * گام کشِ HTTP از این استفاده می‌کند: فقط پاسخِ بازدیدکنندهٔ کاملاً ناشناس
     * را می‌توان عمومی کش کرد.
     */
    public static function touched(): bool
    {
        return self::$hadCookie || self::$wrote;
    }

    private static function applyCookieParams(): void
    {
        /*
         * httponly — JS نتواند کوکی نشست را بخواند (جلوگیری از تصاحب با XSS).
         * samesite — Lax، نه Strict: لینک «نوبت من» از پیامک باز می‌شود و با
         *            Strict کاربرِ واردشده بیرون می‌افتد.
         * secure   — فقط HTTPS؛ از روی درخواست تشخیص داده می‌شود تا روی هاست
         *            بدون گواهی، ورود از کار نیفتد.
         */
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Request::basePath() . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $https,
        ]);
    }

    /**
     * یک تغییر را روی نشست می‌نویسد: نشست را کوتاه باز می‌کند (قفل می‌گیرد و
     * وضعیت ذخیره‌شده را تازه می‌خواند، پس نوشتهٔ درخواست موازی هم دیده می‌شود)،
     * تغییر را اعمال و فوراً می‌بندد. اگر هنوز نشستی نبوده، همین‌جا ساخته می‌شود
     * و کوکی فرستاده می‌شود.
     */
    private static function mutate(callable $fn): void
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        if (PHP_SAPI === 'cli') {
            $fn();

            return;
        }
        // نشستِ صاحب‌دار باز است؛ فقط بنویس، بستنش با صاحبش است.
        if (self::$external) {
            $fn();
            self::$wrote = true;

            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $fn();
        self::$wrote = true;
        session_write_close();
    }
}
