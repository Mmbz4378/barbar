<?php

declare(strict_types=1);

namespace App\Support;

/**
 * خانواده‌های تصویری خدمات.
 *
 * هر دسته‌بندی خدمت یک «تصویر» دارد: عکس نمونه (اگر موجود باشد) یا
 * کاشیِ رنگی با آیکون. عکس‌های نمونه فقط برای شش خانوادهٔ اول ساخته
 * شده‌اند؛ بقیه با آیکون نمایش داده می‌شوند تا هیچ عکس ساختگی‌ای به‌جای
 * کار واقعی سالن نشسته باشد.
 */
final class ServiceVisual
{
    /** @var array<string,array{label:string,icon:string,photo:bool,pattern:string}> */
    private const VISUALS = [
        'haircut' => ['label' => 'کوتاهی و اصلاح مو', 'icon' => 'scissors', 'photo' => true, 'pattern' => '/کوتاهی|اصلاح مو|فید|ماشینی|haircut|cut/iu'],
        'beard' => ['label' => 'ریش و صورت', 'icon' => 'beard', 'photo' => true, 'pattern' => '/ریش|سبیل|تیغ|شیو|beard|shave/iu'],
        'color' => ['label' => 'رنگ و لایت', 'icon' => 'palette', 'photo' => true, 'pattern' => '/رنگ|هایلایت|لایت|دکلره|مش|بالیاژ|آمبره|color|colour|highlight|balayage/iu'],
        'care' => ['label' => 'مراقبت مو', 'icon' => 'droplet', 'photo' => true, 'pattern' => '/مراقبت|شست|شامپو|کراتین|احیا|تقویت|پروتئین|اسکالپ|ماسک مو|صافی|wash|keratin|treatment/iu'],
        'facial' => ['label' => 'پوست', 'icon' => 'sparkles', 'photo' => true, 'pattern' => '/فشیال|فیشیال|پوست|پاکسازی|پاک‌سازی|ماسک صورت|میکرودرم|هیدرا|facial|skin/iu'],
        'styling' => ['label' => 'حالت‌دهی', 'icon' => 'comb', 'photo' => true, 'pattern' => '/شانه|براش|حالت|سشوار|استایل|شینیون|بافت|فر|styling|blow/iu'],
        'nails' => ['label' => 'ناخن', 'icon' => 'polish', 'photo' => false, 'pattern' => '/ناخن|مانیکور|پدیکور|ژل|لاک|کاشت|nail|manicure|pedicure/iu'],
        'lashes' => ['label' => 'ابرو و مژه', 'icon' => 'eye', 'photo' => false, 'pattern' => '/مژه|ابرو|لیفت|اکستنشن|میکروبلیدینگ|فیبروز|lash|brow/iu'],
        'waxing' => ['label' => 'اپیلاسیون', 'icon' => 'flower', 'photo' => false, 'pattern' => '/اپیلاسیون|وکس|موم|بند|لیزر|wax|threading/iu'],
        'makeup' => ['label' => 'میکاپ', 'icon' => 'brush', 'photo' => false, 'pattern' => '/میکاپ|آرایش صورت|گریم|makeup/iu'],
        'bridal' => ['label' => 'عروس و داماد', 'icon' => 'crown', 'photo' => false, 'pattern' => '/عروس|داماد|bridal|groom/iu'],
    ];

    /** @return array<string,string> کلید ← برچسب */
    public static function options(): array
    {
        return array_map(static fn (array $v): string => $v['label'], self::VISUALS);
    }

    public static function exists(string $key): bool
    {
        return isset(self::VISUALS[$key]);
    }

    public static function label(string $key): string
    {
        return self::VISUALS[$key]['label'] ?? self::VISUALS['haircut']['label'];
    }

    public static function icon(string $key): string
    {
        return self::VISUALS[$key]['icon'] ?? 'scissors';
    }

    public static function hasPhoto(string $key): bool
    {
        return self::VISUALS[$key]['photo'] ?? false;
    }

    /**
     * کلید تصویری یک خدمت.
     *
     * دسته‌بندیِ صریح اولویت دارد؛ وگرنه از روی نام حدس زده می‌شود —
     * سالن‌هایی که پیش از دسته‌بندی ساخته شده‌اند هم تصویر درست بگیرند.
     * ترتیب الگوها مهم است: «عروس» پیش از «میکاپ» و «ناخن» پیش از «رنگ»
     * (لاک رنگ دارد ولی خدمت رنگ مو نیست).
     */
    public static function resolve(string $serviceName, ?string $categoryVisual = null): string
    {
        if ($categoryVisual !== null && isset(self::VISUALS[$categoryVisual])) {
            return $categoryVisual;
        }

        foreach (['bridal', 'nails', 'lashes', 'waxing', 'makeup', 'facial', 'color', 'beard', 'care', 'styling', 'haircut'] as $key) {
            if (preg_match(self::VISUALS[$key]['pattern'], $serviceName)) {
                return $key;
            }
        }

        return 'haircut';
    }
}
