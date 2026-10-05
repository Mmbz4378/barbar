<?php

declare(strict_types=1);

namespace App\Core;

/**
 * حالت نگه‌داری — فقط در لحظهٔ جایگزینی فایل‌ها.
 *
 * یک فایل پرچم با زمان انقضا. انقضا مهم است: اگر فرایند به‌روزرسانی
 * وسط کار بمیرد (قطع برق، کشته‌شدن پردازه)، سایت برای همیشه بسته
 * نمی‌ماند و پس از چند دقیقه خودش باز می‌شود.
 */
final class Maintenance
{
    private const MAX_MINUTES = 15;

    public static function file(): string
    {
        return BASE_PATH . '/storage/maintenance.json';
    }

    public static function enable(string $reason, int $minutes = 10): void
    {
        $minutes = max(1, min(self::MAX_MINUTES, $minutes));
        @file_put_contents(self::file(), json_encode([
            'reason' => $reason,
            'until' => time() + $minutes * 60,
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function disable(): void
    {
        if (is_file(self::file())) {
            @unlink(self::file());
        }
    }

    public static function active(): bool
    {
        $file = self::file();
        if (!is_file($file)) {
            return false;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || (int) ($data['until'] ?? 0) < time()) {
            @unlink($file);

            return false;
        }

        return true;
    }

    /** پاسخ ۵۰۳ سبک و خودبسنده — بدون قالب‌ها، چون فایل‌ها در حال جایگزینی‌اند. */
    public static function respond(): void
    {
        http_response_code(503);
        header('Retry-After: 120');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="60">'
            . '<title>در حال به‌روزرسانی</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;background:#f6f6f4;color:#1c1c1a;font:16px/1.9 Tahoma,system-ui,sans-serif;text-align:center}'
            . '@media (prefers-color-scheme:dark){body{background:#111110;color:#ededeb}}main{max-width:420px}h1{font-size:22px}</style></head>'
            . '<body><main><h1>سامانه در حال به‌روزرسانی است</h1><p>چند دقیقهٔ دیگر دوباره سر بزنید؛ این صفحه خودش تازه می‌شود. نوبت‌های ثبت‌شده محفوظ‌اند.</p></main></body></html>';
    }
}
