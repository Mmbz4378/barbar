<?php

declare(strict_types=1);

namespace App\Support;

/**
 * رنگ نشانهٔ هر نفر در پنل (آواتار و نوار کنار نوبت).
 *
 * حرف اول نام با متن سفید روی همین رنگ می‌نشیند، پس فقط رنگ‌هایی
 * مجازند که با سفید ≥ ۴٫۵:۱ دارند. ورودی فرم هم فقط یکی از همین‌ها را
 * می‌پذیرد؛ نسخهٔ قبلی هر رشته‌ای را در style چاپ می‌کرد.
 */
final class StaffColor
{
    public const DEFAULT = '#1D4ED8';

    /** @var array<string,string> */
    public const PALETTE = [
        '#1D4ED8' => 'آبی',
        '#0F766E' => 'سبزآبی',
        '#15803D' => 'سبز',
        '#C2410C' => 'نارنجی',
        '#B91C1C' => 'آجری',
        '#BE185D' => 'صورتی',
        '#9F1239' => 'زرشکی',
        '#86198F' => 'آلویی',
        '#6D28D9' => 'بنفش',
        '#0369A1' => 'آبی آسمانی',
        '#44403C' => 'خاکستری',
    ];

    public static function resolve(?string $hex): string
    {
        $hex = strtoupper(trim((string) $hex));

        return isset(self::PALETTE[$hex]) ? $hex : self::DEFAULT;
    }

    public static function isValid(?string $hex): bool
    {
        return isset(self::PALETTE[strtoupper(trim((string) $hex))]);
    }

    /** رنگ بعدی که هنوز در سالن استفاده نشده — برای نفر تازه. */
    public static function next(array $used): string
    {
        $used = array_map(static fn ($c) => strtoupper((string) $c), $used);
        foreach (array_keys(self::PALETTE) as $color) {
            if (!in_array($color, $used, true)) {
                return $color;
            }
        }

        return self::DEFAULT;
    }
}
