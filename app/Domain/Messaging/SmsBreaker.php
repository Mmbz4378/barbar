<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Cache;

/**
 * قطع‌کنندهٔ مدارِ پیامک.
 *
 * وقتی اپراتور از دسترس خارج است، هر تلاش تا سقف مهلت (و یک بار دیگر برای
 * اپراتور پشتیبان) معطل می‌ماند و یک پردازش PHP را اشغال می‌کند. زیر هجوم،
 * همین معطلی‌ها همهٔ پردازش‌ها را می‌گیرد و سایت برای همه از کار می‌افتد.
 *
 * پس اگر در یک دقیقه چند تلاشِ «کُندِ ناموفق» دیده شود، تا یک دقیقه تلاش تازه
 * انجام نمی‌شود: پیامک‌های عادی در صندوق خروجی می‌مانند تا cron بفرستد، و
 * OTP بی‌درنگ پیام روشن می‌دهد به‌جای معطلی.
 *
 * چرا فقط «کند»: خطای سریع (مثلاً الگوی تنظیم‌نشده یا شمارهٔ نامعتبر) پردازشی
 * را اشغال نمی‌کند و نباید مدار را باز کند — وگرنه یک پیکربندی ناقص، ورود همه
 * را می‌بست. چیزی که می‌شمریم دقیقاً همان آسیب است: انتظار.
 *
 * بدون APCu، شمارش فقط در همان پردازش است — برای cron که چند پیامک پشت هم
 * می‌فرستد همچنان کار می‌کند.
 */
final class SmsBreaker
{
    /** تلاشی که این‌قدر طول بکشد و ناموفق باشد، «کند» است (ثانیه). */
    private const SLOW_SECONDS = 4.0;

    /** این تعداد تلاش کند در یک دقیقه، مدار را باز می‌کند. */
    private const THRESHOLD = 3;

    /** مدت باز ماندن مدار (ثانیه). */
    private const OPEN_SECONDS = 60;

    public static function isOpen(): bool
    {
        return (int) Cache::get('sms:breaker:until', 0) > time();
    }

    /**
     * یک ارسال را با زمان‌سنجی اجرا و در شمارش ثبت می‌کند.
     *
     * @param callable():array{ok:bool} $send
     */
    public static function send(callable $send): array
    {
        $started = microtime(true);
        $result = $send();
        self::record((bool) ($result['ok'] ?? false), microtime(true) - $started);

        return $result;
    }

    public static function record(bool $ok, float $seconds): void
    {
        if ($ok || $seconds < self::SLOW_SECONDS) {
            return;
        }
        $bucket = 'sms:breaker:slow:' . intdiv(time(), 60);
        $count = (int) Cache::get($bucket, 0) + 1;
        Cache::set($bucket, $count, 120);
        if ($count >= self::THRESHOLD) {
            Cache::set('sms:breaker:until', time() + self::OPEN_SECONDS, self::OPEN_SECONDS + 10);
        }
    }

    /** فقط برای آزمون. */
    public static function reset(): void
    {
        Cache::forget('sms:breaker:until');
        Cache::forget('sms:breaker:slow:' . intdiv(time(), 60));
    }
}
