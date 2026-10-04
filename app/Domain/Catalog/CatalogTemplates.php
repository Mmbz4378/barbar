<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Support\Audience;

/**
 * فهرست پیشنهادی خدمات برای راه‌اندازی سالن تازه.
 *
 * فقط نام، دسته و مدتِ معمول پیشنهاد می‌شود؛ قیمت عمداً خالی است. قیمت
 * آرایشگاه به شهر، محله و ماه بستگی دارد و هر عدد پیش‌فرضی یا غلط است
 * یا با تورم کهنه می‌شود. خدمتی که قیمتش وارد نشود، غیرفعال ساخته
 * می‌شود تا با «۰ تومان» در صفحهٔ عمومی دیده نشود.
 *
 * price_type = from یعنی «از …»: رنگ و کراتین به طول و حجم مو بستگی
 * دارند و قیمت قطعی پیش از دیدن مو، وعدهٔ نادرست است.
 */
final class CatalogTemplates
{
    /**
     * @return array<int,array{name:string,visual:string,services:array<int,array{name:string,minutes:int,buffer:int,price_type:string,description?:string}>}>
     */
    public static function for(string $audience): array
    {
        return match (Audience::resolve($audience)) {
            Audience::WOMEN => self::women(),
            Audience::UNISEX => self::unisex(),
            default => self::men(),
        };
    }

    private static function s(string $name, int $minutes, string $priceType = 'fixed', int $buffer = 0): array
    {
        return ['name' => $name, 'minutes' => $minutes, 'buffer' => $buffer, 'price_type' => $priceType];
    }

    private static function men(): array
    {
        return [
            ['name' => 'اصلاح مو', 'visual' => 'haircut', 'services' => [
                self::s('کوتاهی مو', 30),
                self::s('کوتاهی فید و ماشینی', 40),
                self::s('اصلاح موی کودک', 25),
            ]],
            ['name' => 'ریش و صورت', 'visual' => 'beard', 'services' => [
                self::s('فرم و اصلاح ریش', 20),
                self::s('اصلاح صورت با تیغ', 20),
                self::s('اصلاح ابرو', 10),
            ]],
            ['name' => 'رنگ و مراقبت', 'visual' => 'color', 'services' => [
                self::s('رنگ مو', 60, 'from', 10),
                self::s('رنگ ریش', 30),
                self::s('کراتین و صافی مو', 120, 'from', 10),
                self::s('ماسک و شست‌وشوی مو', 20),
            ]],
            ['name' => 'پوست', 'visual' => 'facial', 'services' => [
                self::s('پاکسازی پوست', 45, 'fixed', 5),
                self::s('ماسک صورت', 20),
            ]],
            ['name' => 'داماد', 'visual' => 'bridal', 'services' => [
                self::s('پکیج داماد', 180, 'from', 15),
            ]],
        ];
    }

    private static function women(): array
    {
        return [
            ['name' => 'کوتاهی و حالت‌دهی', 'visual' => 'styling', 'services' => [
                self::s('کوتاهی مو', 45),
                self::s('براشینگ', 45, 'from'),
                self::s('سشوار و حالت', 40),
                self::s('شینیون', 90, 'from'),
            ]],
            ['name' => 'رنگ و لایت', 'visual' => 'color', 'services' => [
                self::s('رنگ ریشه', 90, 'fixed', 10),
                self::s('رنگ کامل مو', 150, 'from', 10),
                self::s('هایلایت و لایت', 180, 'from', 15),
                self::s('بالیاژ و آمبره', 240, 'from', 15),
            ]],
            ['name' => 'مراقبت مو', 'visual' => 'care', 'services' => [
                self::s('کراتین', 180, 'from', 15),
                self::s('احیا و پروتئین‌تراپی', 90, 'from', 10),
                self::s('ماسک مو', 30),
            ]],
            ['name' => 'ناخن', 'visual' => 'nails', 'services' => [
                self::s('مانیکور', 45, 'fixed', 5),
                self::s('پدیکور', 60, 'fixed', 10),
                self::s('لاک ژل', 60, 'fixed', 5),
                self::s('کاشت ناخن', 120, 'from', 10),
                self::s('ترمیم کاشت', 90, 'fixed', 10),
            ]],
            ['name' => 'ابرو و مژه', 'visual' => 'lashes', 'services' => [
                self::s('اصلاح ابرو', 20),
                self::s('لیفت ابرو', 45),
                self::s('لیفت مژه', 60),
                self::s('اکستنشن مژه', 120, 'from', 10),
            ]],
            ['name' => 'پوست', 'visual' => 'facial', 'services' => [
                self::s('پاکسازی پوست', 60, 'fixed', 10),
                self::s('فیشیال', 75, 'from', 10),
            ]],
            ['name' => 'اپیلاسیون', 'visual' => 'waxing', 'services' => [
                self::s('اپیلاسیون صورت', 20),
                self::s('وکس دست و پا', 60, 'from', 10),
            ]],
            ['name' => 'میکاپ و عروس', 'visual' => 'bridal', 'services' => [
                self::s('میکاپ', 90),
                self::s('پکیج عروس', 300, 'from', 30),
            ]],
        ];
    }

    private static function unisex(): array
    {
        return [
            ['name' => 'مو', 'visual' => 'haircut', 'services' => [
                self::s('کوتاهی موی آقایان', 30),
                self::s('کوتاهی موی بانوان', 45),
                self::s('براشینگ و حالت', 45, 'from'),
            ]],
            ['name' => 'رنگ و مراقبت', 'visual' => 'color', 'services' => [
                self::s('رنگ مو', 90, 'from', 10),
                self::s('کراتین', 150, 'from', 15),
            ]],
            ['name' => 'ریش', 'visual' => 'beard', 'services' => [
                self::s('اصلاح و فرم ریش', 20),
            ]],
            ['name' => 'ناخن', 'visual' => 'nails', 'services' => [
                self::s('مانیکور', 45, 'fixed', 5),
                self::s('پدیکور', 60, 'fixed', 10),
            ]],
            ['name' => 'پوست', 'visual' => 'facial', 'services' => [
                self::s('پاکسازی پوست', 60, 'fixed', 10),
            ]],
        ];
    }
}
