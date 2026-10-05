<?php

declare(strict_types=1);

namespace App\Domain\System;

/**
 * تنظیمات سایت که مدیر کل از پنل عوض می‌کند (برند، ورود، ثبت‌نام، …).
 *
 * روی جدول system_settings با پیشوند «site.» می‌نشیند. هر مقدار پیش‌فرضی
 * دارد، پس نصبِ تازه یا نصبی که هنوز چیزی ذخیره نکرده همان رفتار
 * پیش‌فرض را دارد.
 */
final class SiteSettings
{
    public const REG_CLOSED = 'closed';
    public const REG_OPEN = 'open';
    public const REG_APPROVAL = 'approval';

    public const DEFAULT_BRAND = 'رشن';

    public static function str(string $key, string $default = ''): string
    {
        $value = SystemSettings::get('site.' . $key);

        return $value === null ? $default : $value;
    }

    public static function bool(string $key, bool $default): bool
    {
        $value = SystemSettings::get('site.' . $key);

        return $value === null ? $default : $value === '1';
    }

    public static function int(string $key, int $default, int $min, int $max): int
    {
        $value = SystemSettings::get('site.' . $key);
        if ($value === null || !is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    public static function set(string $key, ?string $value): void
    {
        SystemSettings::set('site.' . $key, $value);
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

    // ─── برند ─────────────────────────────────────────────────────────

    public static function brandName(): string
    {
        $name = trim(self::str('brand.name'));

        return $name !== '' ? $name : self::DEFAULT_BRAND;
    }
}
