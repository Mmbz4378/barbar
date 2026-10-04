<?php

declare(strict_types=1);

namespace App\Core;

use App\Domain\Messaging\QueueNotificationService;
use Throwable;

/**
 * کارهای زمان‌بندی‌شده.
 *
 * یک جا جمع شده‌اند تا هم `public/cron.php` (فراخوانی با curl) و هم
 * `tools/cron.php` (خط فرمان) دقیقاً یک کار را بکنند.
 *
 * چرا اصلاً کرون لازم است: پیامک‌های یادآور و «نوبتت نزدیکه» با هیچ
 * درخواست کاربری تحریک نمی‌شوند — کسی که نوبت فردا دارد، امروز سایت را
 * باز نمی‌کند. بدون کرون، این پیامک‌ها هرگز ارسال نمی‌شوند و صف زنده
 * بی‌صدا می‌ماند، که یعنی فیچر اصلی محصول کار نمی‌کند.
 */
final class Cron
{
    /**
     * توکن محافظ مسیر وب.
     *
     * از APP_KEY مشتق می‌شود، نه اینکه جدا ذخیره شود — پس با عوض شدن
     * کلید، خودکار باطل می‌شود و جایی برای فراموش کردنش نیست.
     */
    public static function token(): string
    {
        $key = (string) Config::get('app.key', '');

        if ($key === '') {
            // بدون کلید، توکن قابل پیش‌بینی می‌شود. بهتر است کرون کار نکند
            // تا اینکه با توکن حدس‌زدنی باز باشد.
            return '';
        }

        return substr(hash_hmac('sha256', 'cron', $key), 0, 32);
    }

    public static function tokenMatches(string $candidate): bool
    {
        $expected = self::token();

        if ($expected === '' || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /**
     * همهٔ کارهای دوره‌ای.
     *
     * هر کار داخل try خودش است: اگر یکی بشکند، بقیه باید اجرا شوند.
     * یک ارائه‌دهندهٔ پیامکِ از کار افتاده نباید جلوی بستن نوبت‌های
     * رهاشده را بگیرد.
     *
     * @return array<string,string> نام کار => نتیجه
     */
    public static function run(): array
    {
        $report = [];

        foreach (self::tasks() as $name => $task) {
            $started = microtime(true);
            try {
                $result = $task();
                $elapsed = round((microtime(true) - $started) * 1000);
                $report[$name] = $result . " ({$elapsed}ms)";
            } catch (Throwable $e) {
                $report[$name] = 'خطا: ' . $e->getMessage();
                error_log("[cron] {$name}: " . $e->getMessage());
            }
        }

        self::recordRun();

        return $report;
    }

    /** @return array<string,callable():string> */
    private static function tasks(): array
    {
        return [
            'یادآورهای نوبت' => static function (): string {
                $sent = (new QueueNotificationService())->sendUpcomingReminders();

                return "{$sent} پیامک";
            },

            'بستن نوبت‌های رهاشده' => static function (): string {
                // نوبتی که سالن بسته شده و هنوز در صف مانده، باید غیبت
                // ثبت شود — وگرنه فردا در صف ظاهر می‌شود و ترتیب را
                // به هم می‌ریزد.
                $affected = DB::statement(
                    "UPDATE appointments
                        SET status = 'no_show', updated_at = NOW()
                      WHERE status IN ('queued', 'confirmed')
                        AND COALESCE(scheduled_at, queued_at) < (NOW() - INTERVAL 1 DAY)"
                )->rowCount();

                return "{$affected} نوبت";
            },

            'پاک‌سازی کدهای منقضی' => static function (): string {
                $deleted = DB::statement(
                    'DELETE FROM otp_codes WHERE expires_at < (NOW() - INTERVAL 1 DAY)'
                )->rowCount();

                return "{$deleted} کد";
            },
        ];
    }

    /**
     * آخرین اجرا را ثبت می‌کند تا صفحهٔ سلامت بتواند هشدار بدهد
     * «کرون تنظیم نشده» — یکی از رایج‌ترین اشتباهات نصب.
     */
    private static function recordRun(): void
    {
        $path = BASE_PATH . '/storage/cron-last-run';
        @file_put_contents($path, (string) time());
    }

    /** آخرین اجرای موفق، یا null اگر هرگز اجرا نشده. */
    public static function lastRunAt(): ?int
    {
        $path = BASE_PATH . '/storage/cron-last-run';

        if (!is_file($path)) {
            return null;
        }

        $value = (int) file_get_contents($path);

        return $value > 0 ? $value : null;
    }
}
