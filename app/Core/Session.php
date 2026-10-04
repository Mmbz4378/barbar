<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // در CLI (تست، کرون، مهاجرت) نشست معنی ندارد و session_start
        // هشدار «headers already sent» می‌دهد.
        if (PHP_SAPI === 'cli') {
            return;
        }

        session_name((string) Config::get('app.session_name', 'reshen_session'));

        /*
         * پرچم‌های کوکی. پیش‌فرض PHP هیچ‌کدام را نمی‌گذارد:
         *
         *  httponly — جاوااسکریپت نتواند کوکی نشست را بخواند. بدون این،
         *             هر XSS به تصاحب حساب تبدیل می‌شود.
         *  samesite — کوکی با درخواست‌های بین‌سایتی فرستاده نشود (CSRF).
         *             Lax و نه Strict، چون لینک «نوبت من» از پیامک باز
         *             می‌شود و با Strict، کاربرِ واردشده بیرون می‌افتد.
         *  secure   — فقط روی HTTPS. روی هاست بدون گواهی نباید روشن باشد
         *             وگرنه ورود اصلاً کار نمی‌کند، پس از روی درخواست
         *             تشخیص می‌دهیم.
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

        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if (func_num_args() === 1) {
            $value = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);

            return $value;
        }
        $_SESSION['_flash'][$key] = $value;

        return null;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals(self::csrfToken(), $token);
    }

    /*
     * هر دو تابع زیر روی نشستِ فعال کار می‌کنند و بیرون از آن هشدار
     * می‌دهند. در CLI — تست، کرون، ابزارهای خط فرمان — نشستی باز
     * نیست، ولی ورود و خروج همان‌جا هم صدا زده می‌شود. حالت حافظه‌ای
     * ($_SESSION) کار خودش را می‌کند؛ فقط توابع نشستِ PHP رد می‌شوند.
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
