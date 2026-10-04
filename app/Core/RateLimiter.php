<?php

declare(strict_types=1);

namespace App\Core;

/**
 * محدودیت نرخ در حافظه (APCu) — برای پس زدنِ ارزانِ سیل درخواست.
 *
 * این جای سقف‌های کسب‌وکار (BookingGuard، سقف ساعتیِ کد ورود) را نمی‌گیرد؛
 * آن‌ها در دیتابیس شمرده می‌شوند و درست‌اند. این لایه جلوتر می‌نشیند تا وقتی
 * یک ربات صدها درخواست در دقیقه می‌فرستد، هر کدام پیش از رسیدن به دیتابیس
 * رد شود.
 *
 * بدون APCu هیچ کاری نمی‌کند: شمارش در یک درخواست بی‌معناست، و شمردن در
 * دیتابیس یعنی یک نوشتن اضافه برای هر درخواست — درست همان چیزی که زیر هجوم
 * نمی‌خواهیم.
 *
 * سقف‌ها عمداً بالا هستند: اینترنت موبایل ایران (CGNAT) هزاران کاربر واقعی
 * را پشت یک IP می‌گذارد.
 */
final class RateLimiter
{
    /** آیا این درخواست در سقف است؟ (پنجرهٔ ثابت؛ با APCu، اتمی) */
    public static function allow(string $bucket, int $max, int $windowSeconds): bool
    {
        if ($max <= 0 || !Cache::shared()) {
            return true;
        }
        $key = 'rl:' . $bucket . ':' . intdiv(time(), max(1, $windowSeconds));

        return Cache::increment($key, $windowSeconds + 5) <= $max;
    }

    /** پاسخ ۴۲۹ سبک و خودبسنده. */
    public static function tooMany(int $retryAfter = 60): Response
    {
        $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="' . max(5, $retryAfter) . '">'
            . '<title>کمی آهسته‌تر · رشن</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;background:#f6f6f4;color:#1c1c1a;font:16px/1.9 Tahoma,system-ui,sans-serif;text-align:center}'
            . '@media (prefers-color-scheme:dark){body{background:#111110;color:#ededeb}}main{max-width:420px}h1{font-size:22px}</style></head>'
            . '<body><main><h1>درخواست‌ها زیاد شد</h1><p>از این شبکه در یک دقیقه درخواست زیادی رسید. چند لحظهٔ دیگر این صفحه خودش دوباره تلاش می‌کند.</p></main></body></html>';

        return new Response($html, 429, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Retry-After' => (string) $retryAfter,
            'Cache-Control' => 'no-store',
        ]);
    }
}
