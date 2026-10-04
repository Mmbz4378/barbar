<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ساعت، به فارسی.
 *
 * مرورگر برای <input type="time"> قالب را بر اساس زبانِ **خودش** انتخاب
 * می‌کند، نه زبان صفحه. نتیجه‌اش روی یک دستگاه انگلیسی «09:00 AM» بود
 * وسط برنامه‌ای که همه‌جایش فارسی است. راهی برای اجبار مرورگر نیست، پس
 * ورودی بومی کنار گذاشته شد و ساعت اینجا ساخته می‌شود.
 *
 * دو نمایش داریم و هر کدام جای خودش را دارد:
 *
 *   hm()      «۱۹:۳۰» — ۲۴ساعته. هرجا ساعت‌ها زیر هم می‌نشینند همین
 *             می‌آید، چون همه هم‌عرض‌اند و ستون تراز می‌ماند.
 *   label()   «۷:۳۰ شب» — ۱۲ساعته با واژهٔ فارسی، برای جایی که ساعت
 *             تنهاست و باید در یک نگاه خوانده شود.
 *
 * واژه‌ها: روز به‌جای AM و شب به‌جای PM.
 */
final class Clock
{
    /** برش‌های شبانه‌روز برای دسته‌بندی سانس‌ها. */
    private const PARTS = [
        [0, 4, 'شب'],
        [5, 11, 'صبح'],
        [12, 13, 'ظهر'],
        [14, 17, 'عصر'],
        [18, 23, 'شب'],
    ];

    /**
     * «۱۹:۳۰» — ۲۴ساعته با رقم فارسی.
     *
     * ورودی هر چیزی با شکل H:i یا H:i:s است؛ ثانیه دور ریخته می‌شود.
     */
    public static function hm(string $time): string
    {
        [$h, $m] = self::parse($time);

        return Jalali::toPersianDigits(sprintf('%02d:%02d', $h, $m));
    }

    /** «روز» برای پیش از ظهر، «شب» برای بعد از آن. */
    public static function period(string $time): string
    {
        [$h] = self::parse($time);

        return $h < 12 ? 'روز' : 'شب';
    }

    /** «صبح» / «ظهر» / «عصر» / «شب» — دقیق‌تر از روز و شب. */
    public static function partOfDay(string $time): string
    {
        [$h] = self::parse($time);

        foreach (self::PARTS as [$from, $to, $name]) {
            if ($h >= $from && $h <= $to) {
                return $name;
            }
        }

        return 'شب';
    }

    /**
     * «۷:۳۰ شب» — ۱۲ساعته با واژهٔ فارسی.
     *
     * ساعت ۰ و ۱۲ هر دو «۱۲» می‌شوند، مثل عرفِ ۱۲ساعته.
     */
    public static function label(string $time): string
    {
        [$h, $m] = self::parse($time);
        $twelve = $h % 12 === 0 ? 12 : $h % 12;

        return Jalali::toPersianDigits(sprintf('%d:%02d', $twelve, $m)) . ' ' . self::period($time);
    }

    /**
     * فهرست ساعت‌های شبانه‌روز برای <select>.
     *
     * @return array<int,string> کلید ۰..۲۳، مقدار برچسب فارسی
     */
    public static function hourOptions(): array
    {
        $out = [];
        for ($h = 0; $h <= 23; $h++) {
            $out[$h] = Jalali::toPersianDigits(sprintf('%02d', $h));
        }

        return $out;
    }

    /**
     * فهرست دقیقه‌ها با گام دلخواه.
     *
     * @return array<int,string>
     */
    public static function minuteOptions(int $step = 5): array
    {
        $step = max(1, min(30, $step));
        $out = [];
        for ($m = 0; $m < 60; $m += $step) {
            $out[$m] = Jalali::toPersianDigits(sprintf('%02d', $m));
        }

        return $out;
    }

    /**
     * ساعت و دقیقهٔ جداجدا از فرم، به «HH:MM».
     *
     * چرا دو فیلد و نه یکی: ورودی بومی حذف شده و جایش دو <select> آمده.
     * بدون جاوااسکریپت هم باید کار کند، پس ترکیب سمت سرور انجام می‌شود.
     *
     * خالی بودن یکی از دو فیلد یعنی «تنظیم نشده» — نه ساعت صفر.
     */
    public static function fromParts(mixed $hour, mixed $minute): ?string
    {
        if ($hour === null || $hour === '' || $minute === null || $minute === '') {
            return null;
        }

        $h = (int) $hour;
        $m = (int) $minute;

        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    /** @return array{0:int,1:int} */
    private static function parse(string $time): array
    {
        $parts = explode(':', trim($time));
        $h = isset($parts[0]) ? (int) $parts[0] : 0;
        $m = isset($parts[1]) ? (int) $parts[1] : 0;

        return [max(0, min(23, $h)), max(0, min(59, $m))];
    }
}
