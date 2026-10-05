<?php

declare(strict_types=1);

namespace App\Core;

use Exception;
use PDOException;

/**
 * دیتابیس اتصال تازه نمی‌پذیرد — سرور زیر فشار است.
 *
 * رایج‌ترین علت روی cPanel سقف max_user_connections است: هر درخواست یک
 * اتصال دارد و در هجوم، اتصال‌ها تمام می‌شوند. پیش از این کاربر صفحهٔ
 * «خطایی رخ داد» می‌دید؛ حالا صفحهٔ سبکِ «الان شلوغ است» با تلاش دوبارهٔ
 * خودکار و Retry-After (تا مرورگر و خزنده‌ها هم بدانند موقتی است).
 *
 * عمداً RuntimeException نیست: کنترلرها RuntimeException را می‌گیرند و
 * پیامش را flash می‌کنند و redirect می‌زنند — که خودش دوباره به دیتابیس
 * نیاز دارد. این استثنا مستقیم به گیرندهٔ سراسری می‌رسد.
 */
final class Overloaded extends Exception
{
    /**
     *  1040 Too many connections        — سقف کل سرور
     *  1203 max_user_connections        — سقف حساب cPanel (رایج‌ترین)
     *  1226 user limit reached          — سقف منابعِ خودِ کاربر (WITH MAX_USER_CONNECTIONS)
     *  2002 Can't connect               — سرویس در دسترس نیست / صف پر
     *  2006 MySQL server has gone away  — اتصال وسط کار قطع شد
     *  2013 Lost connection             — همان، هنگام پرس‌وجو
     */
    private const CODES = [1040, 1203, 1226, 2002, 2006, 2013];

    /** آیا این خطای PDO یعنی «سرور زیر فشار است»؟ */
    public static function isOverload(PDOException $e): bool
    {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 0 && preg_match('/\[(\d{4})\]/', $e->getMessage(), $m)) {
            $code = (int) $m[1];   // خطای اتصال: «SQLSTATE[HY000] [1040] …»
        }
        if ($code === 0) {
            $code = (int) $e->getCode();
        }

        return in_array($code, self::CODES, true);
    }

    /** پاسخ ۵۰۳ سبک و خودبسنده — بدون دیتابیس، نشست و قالب. */
    public static function respond(): void
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 10');
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }
        echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="10">'
            . '<title>کمی شلوغ است</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;background:#f6f6f4;color:#1c1c1a;font:16px/1.9 Tahoma,system-ui,sans-serif;text-align:center}'
            . '@media (prefers-color-scheme:dark){body{background:#111110;color:#ededeb}}main{max-width:420px}h1{font-size:22px}</style></head>'
            . '<body><main><h1>الان کمی شلوغ است</h1><p>تعداد زیادی هم‌زمان در حال رزروند. این صفحه چند ثانیهٔ دیگر خودش دوباره تلاش می‌کند؛ لازم نیست کاری بکنید.</p></main></body></html>';
    }
}
