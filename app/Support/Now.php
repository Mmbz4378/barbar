<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * «اکنون» برنامه — قابل ثابت‌کردن در آزمون.
 *
 * محاسبهٔ سانس آزاد به ساعت فعلی وابسته است (سانسِ گذشته، حداقل فاصله
 * تا نوبت). بدون یک منبع واحد، آزمون‌ها فقط در ساعت‌های خاصی از روز
 * سبز می‌شدند.
 */
final class Now
{
    private static ?DateTimeImmutable $fixed = null;

    public static function get(): DateTimeImmutable
    {
        return self::$fixed ?? new DateTimeImmutable();
    }

    public static function today(): DateTimeImmutable
    {
        return self::get()->setTime(0, 0);
    }

    public static function freeze(?DateTimeImmutable $at): void
    {
        self::$fixed = $at;
    }
}
