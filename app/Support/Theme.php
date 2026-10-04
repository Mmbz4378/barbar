<?php

declare(strict_types=1);

namespace App\Support;

/**
 * پالت رنگی سالن.
 *
 * هر سالن یک پالت انتخاب می‌کند و صفحهٔ اختصاصی‌اش همان رنگ را می‌گیرد.
 * این برای محصولی که قرار است «صفحهٔ اختصاصی هر آرایشگاه» بدهد مهم است:
 * سالن باید حس کند صفحه مالِ خودش است، نه یک قالب یکسان برای همه.
 *
 * پیاده‌سازی عمداً ساده است: نام پالت روی <html data-theme="..."> می‌نشیند
 * و CSS بقیه را انجام می‌دهد. نه کلاس اضافه‌ای در ویوها لازم است، نه
 * استایل درون‌خطی.
 *
 * همهٔ این رنگ‌ها با tools/assets/check-contrast.py سنجیده شده‌اند و در
 * هر دو حالت روشن و تیره از آستانهٔ WCAG AA (۴٫۵:۱) عبور می‌کنند.
 */
final class Theme
{
    public const DEFAULT = 'forest';

    /** @var array<string,array{name:string,swatch:string}> */
    private const PALETTES = [
        'forest' => ['name' => 'سبز رشن', 'swatch' => '#285D49'],
        'gold'    => ['name' => 'طلایی',     'swatch' => '#A16207'],
        'emerald' => ['name' => 'زمردی',     'swatch' => '#047857'],
        'indigo'  => ['name' => 'نیلی',      'swatch' => '#4338CA'],
        'copper'  => ['name' => 'مسی',       'swatch' => '#9A3412'],
        'teal'    => ['name' => 'فیروزه‌ای', 'swatch' => '#0F766E'],
        'ruby'    => ['name' => 'یاقوتی',    'swatch' => '#BE123C'],
    ];

    /**
     * کلیدهایی که دیگر پالت نیستند، به نزدیک‌ترین رنگ.
     *
     * یک نسخه با رنگ‌های سیستمی iOS منتشر شد و سالن‌هایی که در آن
     * فاصله رنگ انتخاب کردند، کلیدهای آن نسخه را در دیتابیس دارند.
     * بدون این نگاشت، resolve آن‌ها را «ناشناخته» می‌دید و همه‌شان
     * یک‌شبه طلایی می‌شدند.
     *
     * @var array<string,string>
     */
    private const LEGACY = [
        'blue'   => 'indigo',
        'green'  => 'emerald',
        'orange' => 'copper',
        'pink'   => 'ruby',
        'purple' => 'indigo',
    ];

    /** @return array<string,array{name:string,swatch:string}> */
    public static function all(): array
    {
        return self::PALETTES;
    }

    public static function exists(string $key): bool
    {
        return isset(self::PALETTES[$key]);
    }

    /**
     * پالت معتبر، یا پیش‌فرض.
     *
     * هرگز مقدار خام را به ویو نده — اگر کسی در دیتابیس چیز دیگری
     * بنویسد، مستقیم در HTML می‌نشیند.
     */
    public static function resolve(?string $key): string
    {
        if ($key === null) {
            return self::DEFAULT;
        }
        if (self::exists($key)) {
            return $key;
        }

        return self::LEGACY[$key] ?? self::DEFAULT;
    }

    public static function name(string $key): string
    {
        return self::PALETTES[self::resolve($key)]['name'];
    }

    public static function swatch(string $key): string
    {
        return self::PALETTES[self::resolve($key)]['swatch'];
    }
}
