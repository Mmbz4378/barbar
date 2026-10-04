<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * کارهایی که باید «بعد از جواب دادن به کاربر» انجام شوند.
 *
 * مثال اصلی: پیامک. فرستادنش به اپراتور ممکن است چند ثانیه طول بکشد و زیر
 * هجوم که اپراتور کند است، بیشتر. کاربری که رزرو کرده نباید منتظر آن بماند.
 *
 * چطور: کنترلر جلویی (index.php) پس از فرستادن پاسخ، run() را صدا می‌زند.
 * اگر میزبان PHP-FPM یا LiteSpeed باشد (تقریباً همهٔ cPanelها)،
 * fastcgi_finish_request / litespeed_finish_request اتصال را می‌بندد و کاربر
 * صفحه را کامل می‌گیرد؛ کار در پس‌زمینهٔ همان پردازش ادامه می‌یابد. روی
 * میزبانی بدون این دو، کار پس از چاپ بدنه اجرا می‌شود — یعنی بدتر از قبل
 * نیست. و چون پیامک پیش‌تر در صندوق خروجی ثبت شده، اگر این پردازش بمیرد،
 * cron آن را می‌فرستد.
 *
 * فقط وقتی کنترلر جلویی enable() کرده باشد کار عقب می‌افتد. در CLI، cron و
 * ورودی‌های مستقل، کار همان لحظه اجرا می‌شود — آنجا کاربری منتظر نیست.
 */
final class Deferred
{
    /** سقف زمانِ کل کارهای پس از پاسخ (ثانیه). */
    private const TIME_LIMIT = 60;

    private static bool $enabled = false;

    /** @var array<int,callable> */
    private static array $tasks = [];

    public static function enable(bool $on = true): void
    {
        self::$enabled = $on;
    }

    public static function push(callable $task): void
    {
        if (!self::$enabled) {
            self::safely($task);

            return;
        }
        self::$tasks[] = $task;
    }

    /** @return int تعداد کارهای در انتظار — برای آزمون */
    public static function pending(): int
    {
        return count(self::$tasks);
    }

    public static function run(): void
    {
        if (self::$tasks === []) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
        ignore_user_abort(true);
        @set_time_limit(self::TIME_LIMIT);

        // کاری که حین اجرا کار تازه بسازد، همان‌جا اجرا می‌شود
        while (self::$tasks !== []) {
            self::safely(array_shift(self::$tasks));
        }
    }

    private static function safely(callable $task): void
    {
        try {
            $task();
        } catch (Throwable $e) {
            error_log('[deferred] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
