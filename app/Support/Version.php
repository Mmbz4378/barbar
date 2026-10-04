<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نسخهٔ نصب‌شدهٔ برنامه.
 *
 * منبع حقیقت فایل VERSION در ریشه است که بستهٔ انتشار با خودش می‌آورد؛
 * به‌روزرسان بعد از جایگزینی فایل‌ها همین را می‌خواند. نسخه‌گذاری معنایی
 * (major.minor.patch) است.
 */
final class Version
{
    public static function current(): string
    {
        $file = BASE_PATH . '/VERSION';
        $raw = is_file($file) ? trim((string) @file_get_contents($file)) : '';

        return self::isValid($raw) ? self::normalize($raw) : '0.0.0';
    }

    public static function isValid(string $version): bool
    {
        return preg_match('/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', trim($version)) === 1;
    }

    public static function normalize(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }

    /** -1، 0 یا 1 — مثل version_compare، با «v» اختیاری. */
    public static function compare(string $a, string $b): int
    {
        return version_compare(self::normalize($a), self::normalize($b));
    }

    public static function isNewer(string $candidate, ?string $than = null): bool
    {
        return self::isValid($candidate) && self::compare($candidate, $than ?? self::current()) > 0;
    }

    public static function isPrerelease(string $version): bool
    {
        return str_contains(self::normalize($version), '-');
    }
}
