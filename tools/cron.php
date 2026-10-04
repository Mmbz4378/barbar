<?php

declare(strict_types=1);

/**
 * اجرای کارهای دوره‌ای از خط فرمان.
 *
 *   php tools/cron.php            اجرا
 *   php tools/cron.php --token    نمایش توکن مسیر وب
 *
 * در cPanel → Cron Jobs، هر ۵ دقیقه:
 *   *\/5 * * * * php /home/USER/public_html/tools/cron.php >/dev/null 2>&1
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Cron;

if (in_array('--token', $argv, true)) {
    $token = Cron::token();

    if ($token === '') {
        fwrite(STDERR, "APP_KEY تنظیم نشده — بدون آن مسیر وبِ کرون کار نمی‌کند.\n");
        exit(1);
    }

    $base = rtrim((string) App\Core\Config::get('app.url', ''), '/');
    echo "توکن: {$token}\n";
    echo "آدرس: {$base}/cron.php?token={$token}\n";
    exit(0);
}

foreach (Cron::run() as $task => $result) {
    printf("%-28s %s\n", $task, $result);
}
