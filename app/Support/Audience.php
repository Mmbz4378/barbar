<?php

declare(strict_types=1);

namespace App\Support;

/**
 * مخاطب سالن و واژگانی که با آن عوض می‌شود.
 *
 * آرایشگاه مردانه و سالن زیبایی بانوان یک گردش کار دارند ولی یک زبان
 * ندارند: در سالن بانوان «آرایشگر» برای ناخن‌کار و مژه‌کار نادرست است و
 * «روی صندلی» برای پدیکور معنا ندارد. واژه‌ها اینجا یک‌جا هستند تا هیچ
 * ویویی برچسب مردانه را سخت‌کد نکند.
 */
final class Audience
{
    public const MEN = 'men';
    public const WOMEN = 'women';
    public const UNISEX = 'unisex';

    public const DEFAULT = self::MEN;

    /** @var array<string,array<string,string>> */
    private const TERMS = [
        self::MEN => [
            'salon_type' => 'آرایشگاه مردانه',
            'salon_short' => 'آرایشگاه',
            'audience_label' => 'مردانه',
            'staff' => 'آرایشگر',
            'staff_plural' => 'آرایشگرها',
            'staff_any' => 'فرقی نمی‌کند',
            'staff_any_hint' => 'اولین آرایشگرِ آزاد انتخاب می‌شود',
            'staff_question' => 'کدام آرایشگر؟',
            'station' => 'صندلی',
            'in_service' => 'روی صندلی',
            'in_service_customer' => 'خدمت شما در حال انجام است',
            'staff_title_placeholder' => 'مثلاً استادکار، آرایشگر ارشد',
        ],
        self::WOMEN => [
            'salon_type' => 'سالن زیبایی بانوان',
            'salon_short' => 'سالن',
            'audience_label' => 'بانوان',
            'staff' => 'متخصص',
            'staff_plural' => 'متخصص‌ها',
            'staff_any' => 'فرقی نمی‌کند',
            'staff_any_hint' => 'اولین متخصصِ آزاد و توانا انتخاب می‌شود',
            'staff_question' => 'کدام متخصص؟',
            'station' => 'ایستگاه',
            'in_service' => 'در حال انجام خدمت',
            'in_service_customer' => 'خدمت شما در حال انجام است',
            'staff_title_placeholder' => 'مثلاً رنگ‌کار، ناخن‌کار، میکاپ‌آرتیست',
        ],
        self::UNISEX => [
            'salon_type' => 'سالن زیبایی',
            'salon_short' => 'سالن',
            'audience_label' => 'آقایان و بانوان',
            'staff' => 'متخصص',
            'staff_plural' => 'متخصص‌ها',
            'staff_any' => 'فرقی نمی‌کند',
            'staff_any_hint' => 'اولین متخصصِ آزاد و توانا انتخاب می‌شود',
            'staff_question' => 'کدام متخصص؟',
            'station' => 'ایستگاه',
            'in_service' => 'در حال انجام خدمت',
            'in_service_customer' => 'خدمت شما در حال انجام است',
            'staff_title_placeholder' => 'مثلاً آرایشگر، رنگ‌کار، ناخن‌کار',
        ],
    ];

    /** @return array<string,string> کلید ← برچسب، برای فرم‌ها */
    public static function options(): array
    {
        return [
            self::MEN => 'آرایشگاه مردانه',
            self::WOMEN => 'سالن زیبایی بانوان',
            self::UNISEX => 'هر دو (آقایان و بانوان)',
        ];
    }

    public static function resolve(?string $key): string
    {
        return isset(self::TERMS[$key ?? '']) ? (string) $key : self::DEFAULT;
    }

    public static function term(string $key, ?string $audience = null): string
    {
        $audience = self::resolve($audience ?? SalonContext::audience());

        return self::TERMS[$audience][$key] ?? self::TERMS[self::DEFAULT][$key] ?? $key;
    }

    /**
     * ترتیب پیش‌فرض رزرو برای سالن تازه.
     *
     * آرایشگاه مردانه معمولاً یک خدمت دارد و سؤال مشتری «کِی؟» است؛
     * سالن بانوان خدمت‌هایی از ۲۰ دقیقه تا چهار ساعت دارد و تا خدمت
     * معلوم نشود، هیچ ساعتی را نمی‌شود قول داد.
     */
    public static function defaultBookingFlow(string $audience): string
    {
        return $audience === self::MEN ? 'time_first' : 'service_first';
    }

    /** تم پیش‌فرض متناسب با مخاطب. */
    public static function defaultTheme(string $audience): string
    {
        return match ($audience) {
            self::WOMEN => 'rose',
            self::UNISEX => 'teal',
            default => 'forest',
        };
    }
}
