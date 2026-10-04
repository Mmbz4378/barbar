<?php

declare(strict_types=1);

namespace App\Support;

/**
 * سالنی که درخواستِ فعلی در بسترِ آن رندر می‌شود.
 *
 * پنل (از TenantRequired)، صفحه‌های رزرو و کارت نوبت آن را تنظیم
 * می‌کنند تا اجزای مشترک — واژه‌ها، رنگ برند، نام سالن — بدون اینکه هر
 * ویو سالن را جداگانه دریافت کند، درست نمایش داده شوند.
 *
 * عمداً فقط در طول یک درخواست زنده است؛ نسخهٔ قبلی نام و رنگ را در
 * نشست نگه می‌داشت و پس از ویرایش تنظیمات تا ورود بعدی کهنه می‌ماند.
 */
final class SalonContext
{
    private static ?array $salon = null;

    public static function set(?array $salon): void
    {
        self::$salon = $salon;
    }

    public static function get(): ?array
    {
        return self::$salon;
    }

    public static function id(): ?int
    {
        return self::$salon !== null ? (int) self::$salon['id'] : null;
    }

    public static function audience(): string
    {
        return Audience::resolve(self::$salon['audience'] ?? null);
    }

    public static function theme(): string
    {
        return Theme::resolve(self::$salon['theme'] ?? null);
    }

    public static function name(): string
    {
        return (string) (self::$salon['name'] ?? 'رشن');
    }
}
