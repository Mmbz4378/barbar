<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Support\ImageUpload;

/**
 * لوگوی سامانه و آیکون‌های ساخته‌شده از آن.
 *
 * لوگو از همان مسیر امنِ آپلود تصویر می‌گذرد (خواندن واقعی با GD، نام
 * تصادفی، WebP). از روی آن آیکون‌های مربعیِ PNG برای مرورگر، صفحهٔ خانهٔ
 * گوشی و نصب اپ ساخته می‌شود؛ لوگوی پهن وسط مربعِ شفاف می‌نشیند و بریده
 * نمی‌شود. SVG پذیرفته نمی‌شود: از همین دامنه سرو می‌شود و می‌تواند اسکریپت
 * داشته باشد.
 */
final class BrandAssets
{
    public const DIR = 'uploads/brand';

    /** اندازه‌های آیکون: ۱۸۰ (iOS)، ۱۹۲ و ۵۱۲ (اندروید و مانیفست) */
    public const ICON_SIZES = [180, 192, 512];

    /** @return array{ok:bool,error:?string} */
    public static function storeLogo(?array $file): array
    {
        $dir = BASE_PATH . '/public/' . self::DIR;
        $saved = ImageUpload::saveImage($file, $dir, 'logo', 512);
        if (!$saved['ok']) {
            return ['ok' => false, 'error' => $saved['error'] ?? 'تصویری انتخاب نشد.'];
        }

        $logoPath = self::DIR . '/' . $saved['path'];
        $iconBase = self::DIR . '/icon-' . bin2hex(random_bytes(6));
        $made = self::makeIcons(BASE_PATH . '/public/' . $logoPath, BASE_PATH . '/public/' . $iconBase);

        $old = [SiteSettings::str('brand.logo'), SiteSettings::str('brand.icon_base')];
        SiteSettings::setMany([
            'brand.logo' => $logoPath,
            'brand.icon_base' => $made ? $iconBase : null,
        ]);
        self::deleteFiles($old[0], $old[1]);

        return ['ok' => true, 'error' => $made ? null : 'لوگو ذخیره شد، ولی آیکون‌ها ساخته نشدند (GD بدون PNG).'];
    }

    public static function removeLogo(): void
    {
        $old = [SiteSettings::str('brand.logo'), SiteSettings::str('brand.icon_base')];
        SiteSettings::setMany(['brand.logo' => null, 'brand.icon_base' => null]);
        self::deleteFiles($old[0], $old[1]);
    }

    private static function makeIcons(string $logoFile, string $iconBase): bool
    {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagepng')) {
            return false;
        }
        $source = @imagecreatefromwebp($logoFile);
        if ($source === false) {
            return false;
        }
        $w = imagesx($source);
        $h = imagesy($source);
        foreach (self::ICON_SIZES as $size) {
            $canvas = imagecreatetruecolor($size, $size);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagealphablending($canvas, true);
            // کمی حاشیه تا روی آیکون گردِ اندروید بریده نشود
            $inner = (int) round($size * 0.84);
            $scale = min($inner / $w, $inner / $h);
            $dw = max(1, (int) round($w * $scale));
            $dh = max(1, (int) round($h * $scale));
            imagecopyresampled($canvas, $source, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
            imagealphablending($canvas, false);
            $ok = imagepng($canvas, $iconBase . '-' . $size . '.png', 6);
            imagedestroy($canvas);
            if (!$ok) {
                imagedestroy($source);

                return false;
            }
            @chmod($iconBase . '-' . $size . '.png', 0644);
        }
        imagedestroy($source);

        return true;
    }

    private static function deleteFiles(string $logo, string $iconBase): void
    {
        $dir = BASE_PATH . '/public/' . self::DIR;
        if ($logo !== '' && str_starts_with($logo, self::DIR . '/')) {
            ImageUpload::delete($dir, basename($logo));
        }
        if ($iconBase !== '' && str_starts_with($iconBase, self::DIR . '/')) {
            foreach (self::ICON_SIZES as $size) {
                ImageUpload::delete($dir, basename($iconBase) . '-' . $size . '.png');
            }
        }
    }
}
