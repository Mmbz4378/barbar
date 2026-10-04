<?php

declare(strict_types=1);

namespace App\Support;

/**
 * رنگ برند هر سالن.
 *
 * نام پالت روی <html data-theme="…"> می‌نشیند و reshen.css فقط نقش‌های
 * «تأکید» را عوض می‌کند؛ سطح‌ها، متن و رنگ‌های وضعیت (موفق، هشدار، خطا)
 * مستقل از برند می‌مانند تا «لغو» در هیچ سالنی سبز و «تأیید» قرمز
 * دیده نشود.
 *
 * همهٔ پالت‌ها با tools/check-contrast.mjs سنجیده می‌شوند: متن سفید روی
 * رنگ تأکید و رنگ تأکید روی سطح، در هر دو حالت روشن و تیره ≥ ۴٫۵:۱.
 */
final class Theme
{
    public const DEFAULT = 'forest';

    /**
     * swatch همان رنگ تأکیدِ حالت روشن در reshen.css است.
     *
     * @var array<string,array{name:string,swatch:string,audience:string}>
     */
    private const PALETTES = [
        'forest' => ['name' => 'سبز رشن', 'swatch' => '#176B52', 'audience' => 'all'],
        'ink' => ['name' => 'زغالی', 'swatch' => '#27272A', 'audience' => 'men'],
        'indigo' => ['name' => 'نیلی', 'swatch' => '#4338CA', 'audience' => 'men'],
        'teal' => ['name' => 'فیروزه‌ای', 'swatch' => '#0F766E', 'audience' => 'all'],
        'copper' => ['name' => 'مسی', 'swatch' => '#9A3412', 'audience' => 'men'],
        'gold' => ['name' => 'طلایی', 'swatch' => '#8A5A00', 'audience' => 'all'],
        'emerald' => ['name' => 'زمردی', 'swatch' => '#047857', 'audience' => 'all'],
        'rose' => ['name' => 'رز', 'swatch' => '#BE185D', 'audience' => 'women'],
        'ruby' => ['name' => 'یاقوتی', 'swatch' => '#BE123C', 'audience' => 'women'],
        'plum' => ['name' => 'آلویی', 'swatch' => '#86198F', 'audience' => 'women'],
        'lavender' => ['name' => 'یاسی', 'swatch' => '#6D28D9', 'audience' => 'women'],
        'mauve' => ['name' => 'گلبهی', 'swatch' => '#9F3A6A', 'audience' => 'women'],
        'nude' => ['name' => 'کرم‌قهوه‌ای', 'swatch' => '#8C4A32', 'audience' => 'women'],
    ];

    /**
     * کلیدهایی که دیگر پالت مستقل نیستند.
     *
     * نسخه‌ای با رنگ‌های سیستمی iOS منتشر شد و سالن‌هایی که آن زمان رنگ
     * انتخاب کردند این کلیدها را در دیتابیس دارند.
     *
     * @var array<string,string>
     */
    private const LEGACY = [
        'blue' => 'indigo',
        'green' => 'emerald',
        'orange' => 'copper',
        'pink' => 'rose',
        'purple' => 'lavender',
    ];

    /** @return array<string,array{name:string,swatch:string,audience:string}> */
    public static function all(): array
    {
        return self::PALETTES;
    }

    /**
     * پالت‌ها به ترتیبِ مناسب‌بودن برای مخاطب سالن.
     *
     * همه قابل انتخاب‌اند؛ فقط ترتیب عوض می‌شود تا صاحب سالن بانوان
     * اول رنگ‌های خودش را ببیند.
     *
     * @return array<string,array{name:string,swatch:string,audience:string}>
     */
    public static function forAudience(string $audience): array
    {
        $rank = static fn (array $p): int => match (true) {
            $p['audience'] === $audience => 0,
            $p['audience'] === 'all' => 1,
            default => 2,
        };

        $palettes = self::PALETTES;
        uasort($palettes, static fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        return $palettes;
    }

    public static function exists(string $key): bool
    {
        return isset(self::PALETTES[$key]);
    }

    /**
     * پالت معتبر، یا پیش‌فرض. مقدار خام دیتابیس هرگز مستقیم در HTML
     * نمی‌نشیند.
     */
    public static function resolve(?string $key): string
    {
        if ($key === null || $key === '') {
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
