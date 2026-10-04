<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * شمارهٔ موبایل ایرانی، به یک شکل واحد.
 *
 * مردم شماره را به ده شکل می‌نویسند: با صفر و بی‌صفر، با ‎+98‎ و ‎0098‎،
 * با ارقام فارسی، با خط تیره و فاصله. همه اینجا به یک رشتهٔ استاندارد
 * E.164 تبدیل می‌شوند.
 *
 * عمداً فقط همین‌جا: اگر هر بخشی از کد خودش شماره را تفسیر کند، همان
 * مشتری دو بار در دیتابیس ثبت می‌شود و نوبت‌هایش دو تکه می‌شود.
 */
final class IranMobile
{
    private function __construct(public readonly string $e164)
    {
    }

    public static function parse(string $raw): self
    {
        $normalized = self::normalizeDigits($raw);
        $normalized = preg_replace('/[\s\-()]/', '', $normalized) ?? '';

        // ‎0098912…‎، ‎+98912…‎، ‎98912…‎، ‎0912…‎، ‎912…‎
        $normalized = preg_replace('/^0098/', '', $normalized);
        $normalized = preg_replace('/^\+98/', '', $normalized);
        $normalized = preg_replace('/^98(?=9\d{9}$)/', '', $normalized);
        $normalized = preg_replace('/^0(?=9\d{9}$)/', '', $normalized);

        if (!preg_match('/^9\d{9}$/', $normalized)) {
            throw new InvalidArgumentException("شمارهٔ موبایل نامعتبر است: $raw");
        }

        return new self('+98' . $normalized);
    }

    public static function tryParse(string $raw): ?self
    {
        try {
            return self::parse($raw);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isValid(string $raw): bool
    {
        return self::tryParse($raw) !== null;
    }

    /** شکل نمایشی داخلی: ‎0912 345 6789‎ */
    public function local(): string
    {
        $digits = '0' . substr($this->e164, 3);

        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
    }

    public function localCompact(): string
    {
        return '0' . substr($this->e164, 3);
    }

    public function __toString(): string
    {
        return $this->e164;
    }

    private static function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return strtr($value, array_combine(array_merge($persian, $arabic), array_merge($latin, $latin)));
    }
}
