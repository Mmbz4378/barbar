<?php

declare(strict_types=1);

namespace App\Support;

/**
 * آپلود تصویر، با بازرمزگذاری.
 *
 * چرا فایل را همان‌طور که آمده ذخیره نمی‌کنیم: یک فایل می‌تواند هم
 * تصویر معتبر باشد و هم کد PHP داخلش داشته باشد (polyglot). بررسی
 * پسوند و حتی MIME، جلوی این را نمی‌گیرد.
 *
 * راه‌حل: تصویر با GD **باز و دوباره ساخته** می‌شود. خروجی، تصویری
 * تازه از پیکسل‌هاست؛ هر چیزی که تصویر نبوده — متادیتا، کامنت، کد —
 * در این تبدیل از بین می‌رود.
 *
 * لایهٔ دوم، `.htaccess` پوشهٔ آپلود است که اجرای PHP را خاموش می‌کند.
 * یک لایه هم کافی بود، ولی اینجا جایی است که یک اشتباه، سرور را از
 * دست می‌دهد.
 */
final class ImageUpload
{
    /** حداکثر اندازهٔ فایل ورودی. بزرگ‌تر از این، لوگو نیست. */
    private const MAX_BYTES = 3 * 1024 * 1024;

    /** لوگو بزرگ‌تر از این لازم نیست و روی موبایل فقط کندی می‌آورد. */
    private const MAX_DIMENSION = 512;

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $file
     * @return array{ok:bool,path:?string,error:?string} path نسبی به public
     */
    public static function saveImage(?array $file, string $targetDir, string $prefix = 'img'): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'path' => null, 'error' => null];
        }

        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => null, 'error' => self::uploadErrorText((int) $file['error'])];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'path' => null, 'error' => 'فایل درست آپلود نشد.'];
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'path' => null, 'error' => 'حجم فایل بیشتر از ۳ مگابایت است.'];
        }

        // getimagesize واقعاً فایل را می‌خواند؛ به پسوند اعتماد نمی‌کند.
        $info = @getimagesize($tmp);
        if ($info === false) {
            return ['ok' => false, 'path' => null, 'error' => 'این فایل تصویر نیست.'];
        }

        if (!extension_loaded('gd') || !function_exists('imagewebp')) return ['ok'=>false,'path'=>null,'error'=>'پردازش تصویر روی سرور فعال نیست. افزونهٔ GD را فعال کنید.'];
        if ($info[0] * $info[1] > 20000000) return ['ok'=>false,'path'=>null,'error'=>'ابعاد تصویر بیش از حد مجاز است.'];
        $source = self::open($tmp, (int) $info[2]);
        if ($source === null) {
            return ['ok' => false, 'path' => null, 'error' => 'قالب تصویر پشتیبانی نمی‌شود. PNG یا JPG بفرست.'];
        }

        $resized = self::fitWithin($source, self::MAX_DIMENSION);
        imagedestroy($source);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            imagedestroy($resized);

            return ['ok' => false, 'path' => null, 'error' => 'پوشهٔ آپلود ساخته نشد.'];
        }

        // نام تصادفی: نام اصلی فایل هرگز استفاده نمی‌شود تا نه حدس‌زدنی
        // باشد و نه بتواند فایل دیگری را بازنویسی کند.
        $name = $prefix . '-' . bin2hex(random_bytes(8)) . '.webp';
        $fullPath = rtrim($targetDir, '/') . '/' . $name;

        $written = imagewebp($resized, $fullPath, 82);
        imagedestroy($resized);

        if (!$written) {
            return ['ok' => false, 'path' => null, 'error' => 'ذخیرهٔ تصویر ناموفق بود.'];
        }

        @chmod($fullPath, 0644);

        return ['ok' => true, 'path' => $name, 'error' => null];
    }

    /** پاک کردن فایل قبلی. نبودنش خطا نیست. */
    public static function delete(string $targetDir, ?string $name): void
    {
        if ($name === null || $name === '') {
            return;
        }

        // فقط نام فایل، نه مسیر — جلوی ../ را می‌گیرد.
        $safe = basename($name);
        $path = rtrim($targetDir, '/') . '/' . $safe;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function open(string $path, int $type): ?\GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        return $image === false ? null : $image;
    }

    /** کوچک کردن با حفظ نسبت. تصویر کوچک‌تر از حد، دست‌نخورده می‌ماند. */
    private static function fitWithin(\GdImage $source, int $max): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $longest = max($width, $height);

        $scale = $longest > $max ? $max / $longest : 1.0;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // شفافیت باید بماند: لوگوی PNG با پس‌زمینهٔ شفاف، روی سرصفحهٔ
        // تیره می‌نشیند و اگر شفافیتش برود، یک مستطیل سفید می‌شود.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private static function uploadErrorText(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'فایل بزرگ‌تر از حد مجاز سرور است.',
            UPLOAD_ERR_PARTIAL => 'آپلود نیمه‌کاره ماند. دوباره تلاش کن.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'سرور نتوانست فایل را ذخیره کند.',
            UPLOAD_ERR_EXTENSION => 'یک افزونهٔ سرور جلوی آپلود را گرفت.',
            default => 'آپلود ناموفق بود.',
        };
    }
}
