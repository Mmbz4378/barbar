<?php

declare(strict_types=1);

namespace App\Core;

/**
 * احراز هویت و اینکه کاربر همین حالا داخل کدام سالن است.
 *
 * کاربرِ واردشده یک هویت سراسری دارد (users.id). ولی اینکه الان در چه
 * سالنی کار می‌کند، حالتی *جدا* در نشست است (salon_id) که از عضویت‌های
 * او در salon_user درمی‌آید.
 *
 * چرا جدا: یک نفر می‌تواند در سالن الف کارمند باشد و در سالن ب صاحب.
 * اگر این دو یکی بودند، نقشش در یک سالن به سالن دیگر نشت می‌کرد.
 */
final class Auth
{
    private static ?array $userCache = null;

    private static ?array $membershipCache = null;

    /** نتیجهٔ اعتبارسنجی نشست در همین درخواست (یک کوئری، یک بار). */
    private static ?bool $valid = null;

    /**
     * کاربری وارد شده و نشستش هنوز معتبر است؟
     *
     * نشست فقط شناسهٔ کاربر را دارد؛ پس هر درخواست، ردیف کاربر را می‌بیند:
     * اگر حذف یا مسدود شده، یا auth_version عوض شده (رمز تازه، «خروج از همهٔ
     * دستگاه‌ها»، مسدودی)، نشست همان‌جا بسته می‌شود. بدون این، کسی که
     * مسدودش کرده‌ایم تا پایان عمر نشستش داخل می‌ماند.
     */
    public static function check(): bool
    {
        if (Session::get('user_id') === null) {
            return false;
        }
        if (self::$valid === null) {
            $row = DB::selectOne('SELECT * FROM users WHERE id = ?', [(int) Session::get('user_id')]);
            // نشستِ پیش از نسخهٔ ۱۵ نسخه ندارد؛ همان ۱ حساب می‌شود
            $version = (int) (Session::get('auth_v') ?? 1);
            self::$valid = $row !== null
                && (int) ($row['is_active'] ?? 1) === 1
                && (int) ($row['auth_version'] ?? 1) === $version;
            if (self::$valid) {
                self::$userCache = $row;
            } else {
                Session::destroy();
                self::$userCache = null;
                self::$membershipCache = null;
            }
        }

        return self::$valid;
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');

        return $id === null ? null : (int) $id;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$userCache === null) {
            self::$userCache = DB::selectOne('SELECT * FROM users WHERE id = ?', [self::id()]);
        }

        return self::$userCache;
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        $row = DB::selectOne('SELECT * FROM users WHERE id = ?', [$userId]);
        Session::put('user_id', $userId);
        Session::put('auth_v', (int) ($row['auth_version'] ?? 1));
        self::$userCache = null;
        self::$membershipCache = null;
        self::$valid = null;
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $userId]);
    }

    /**
     * پس از اینکه خودِ کاربر رمزش را عوض کرد (و auth_version بالا رفت):
     * نشست‌های دیگرش بسته می‌شوند، این یکی با نسخهٔ تازه می‌ماند.
     */
    public static function refreshVersion(): void
    {
        $userId = self::id();
        if ($userId === null) {
            return;
        }
        $row = DB::selectOne('SELECT auth_version FROM users WHERE id = ?', [$userId]);
        Session::regenerate();
        Session::put('auth_v', (int) ($row['auth_version'] ?? 1));
        self::$userCache = null;
        self::$valid = null;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$userCache = null;
        self::$membershipCache = null;
        self::$valid = null;
    }

    /** رمزی که مدیر داده و باید در اولین ورود عوض شود؟ */
    public static function mustChangePassword(): bool
    {
        return (int) (self::user()['must_change_password'] ?? 0) === 1;
    }

    /**
     * کاربرِ «باید رمز را عوض کند» تا آن را عوض نکرده جای دیگری نمی‌رود.
     * میان‌افزارهای ورود این را صدا می‌زنند.
     */
    public static function passwordChangeGate(\App\Core\Request $request): ?Response
    {
        if (!self::mustChangePassword()) {
            return null;
        }
        $path = $request->path;
        if (str_starts_with($path, '/account') || $path === '/logout') {
            return null;
        }

        return Response::redirect('/account/password');
    }

    public static function isPlatformAdmin(): bool
    {
        return (bool) (self::user()['is_platform_admin'] ?? false);
    }

    public static function salonId(): ?int
    {
        $impersonating = Session::get('impersonate_salon_id');
        if ($impersonating !== null) {
            return (int) $impersonating;
        }

        $id = Session::get('salon_id');

        return $id === null ? null : (int) $id;
    }

    public static function isImpersonating(): bool
    {
        return Session::get('impersonate_salon_id') !== null;
    }

    public static function startImpersonating(int $salonId): void
    {
        Session::put('impersonate_salon_id', $salonId);
    }

    public static function stopImpersonating(): void
    {
        Session::forget('impersonate_salon_id');
    }

    public static function setSalon(int $salonId): void
    {
        Session::put('salon_id', $salonId);
        self::$membershipCache = null;
        $salon = DB::selectOne('SELECT name, theme FROM salons WHERE id = ?', [$salonId]);
        Session::put('_salon_name', $salon['name'] ?? null);

        // پالت رنگی در نشست می‌ماند تا قالب پنل برای رندر هر صفحه یک
        // کوئری اضافه نزند.
        Session::put('_salon_theme', $salon['theme'] ?? null);
    }

    /** @return array<int,array> عضویت‌های کاربر واردشده (salon_id، نقش، نام سالن) */
    public static function memberships(): array
    {
        if (!self::check()) {
            return [];
        }
        if (self::$membershipCache === null) {
            self::$membershipCache = DB::select(
                'SELECT su.salon_id, su.role, s.name AS salon_name, s.slug
                 FROM salon_user su JOIN salons s ON s.id = su.salon_id
                 WHERE su.user_id = ? AND su.is_active = 1
                 ORDER BY s.name',
                [self::id()]
            );
        }

        return self::$membershipCache;
    }

    public static function role(): ?string
    {
        if (self::isImpersonating()) {
            return 'owner';
        }

        $salonId = self::salonId();
        if ($salonId === null) {
            return null;
        }
        foreach (self::memberships() as $m) {
            if ((int) $m['salon_id'] === $salonId) {
                return $m['role'];
            }
        }

        return null;
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function staffId(): ?int
    {
        $salonId = self::salonId();
        $userId = self::id();
        if ($salonId === null || $userId === null) {
            return null;
        }
        $row = DB::selectOne('SELECT id FROM staff WHERE salon_id = ? AND user_id = ?', [$salonId, $userId]);

        return $row ? (int) $row['id'] : null;
    }
}
