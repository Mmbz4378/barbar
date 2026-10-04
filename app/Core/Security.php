<?php

declare(strict_types=1);

namespace App\Core;

/**
 * سربرگ‌های امنیتی و nonce سیاست امنیت محتوا (CSP).
 *
 * چرا اینجا و نه فقط در .htaccess: فایل .htaccess فقط روی آپاچی خوانده
 * می‌شود. روی Nginx، سرور داخلی PHP، یا هر میزبانی که mod_headers ندارد،
 * هیچ‌کدام از این سربرگ‌ها فرستاده نمی‌شد. چون بسته با به‌روزرسان خودکار
 * مستقیم روی هاست می‌نشیند و کسی پیکربندی سرور را دستی وارسی نمی‌کند،
 * دفاع باید از خود PHP بیاید تا همه‌جا باشد.
 *
 * CSP یک لایهٔ دوم روی فرار-escape است: حتی اگر روزی یک جای خروجی بدون
 * e() رد شود، مرورگر اجرای اسکریپت تزریق‌شده را رد می‌کند، چون فقط
 * اسکریپتِ هم‌ریشه یا دارای nonce این درخواست اجازهٔ اجرا دارد.
 *
 * nonce یک‌بار در هر درخواست ساخته می‌شود؛ هم در سربرگ CSP می‌نشیند و هم
 * در تگ‌های <script> درون‌خطی (از راه csp_nonce()). چون هر دو از همین
 * مقدار ثابتِ ایستا می‌خوانند، همیشه هم‌خوان‌اند.
 */
final class Security
{
    private static ?string $nonce = null;

    /** nonce یکتا و ثابتِ همین درخواست. */
    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = bin2hex(random_bytes(16));
        }

        return self::$nonce;
    }

    /**
     * سیاست امنیت محتوا.
     *
     * - script-src: فقط هم‌ریشه و nonce همین درخواست؛ unsafe-inline نیست،
     *   پس هیچ onclick=/javascript: یا اسکریپت تزریقی اجرا نمی‌شود.
     * - style-src: unsafe-inline لازم است، چون سیستم طراحی از ویژگی‌های
     *   سفارشی درون‌خطی (style="--gap:…") استفاده می‌کند و nonce روی
     *   صفت style اثر ندارد.
     * - img-src data:: فلش آیکون select و تصویر QR به صورت data: می‌آیند.
     * - default-src 'self' بقیه (فونت، fetch هم‌ریشه، manifest، worker) را
     *   پوشش می‌دهد. object/base/frame-ancestors/form-action سفت بسته‌اند.
     */
    public static function csp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self' 'nonce-" . self::nonce() . "'",
        ]);
    }

    /** @return array<string,string> */
    public static function headers(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Content-Security-Policy' => self::csp(),
        ];
    }

    /**
     * سربرگ‌ها را همین حالا می‌فرستد.
     *
     * پیش از هر خروجی و فقط وقتی سربرگ‌ها هنوز نرفته‌اند. اگر میزبان روی
     * آپاچی سه سربرگ اول را از .htaccess هم بگذارد، mod_headers با «set»
     * جایگزین می‌کند (نه افزودن)، پس تکراری ساخته نمی‌شود؛ CSP فقط از
     * همین‌جا می‌آید چون nonce در .htaccess ایستا نمی‌شود.
     */
    public static function send(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        foreach (self::headers() as $key => $value) {
            header("$key: $value");
        }
    }
}
