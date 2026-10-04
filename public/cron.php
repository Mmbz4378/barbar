<?php

declare(strict_types=1);

/**
 * اجرای کارهای زمان‌بندی‌شده از طریق وب.
 *
 * چرا از وب و نه فقط CLI: در cPanel بخش «Cron Jobs» هست، ولی روی بعضی
 * هاست‌ها دستور `php` در دسترس نیست یا مسیرش فرق می‌کند. فراخوانی با
 * curl همیشه کار می‌کند:
 *
 *   curl -s "https://example.com/cron.php?token=..." > /dev/null
 *
 * اگر هاست شما CLI دارد، این بهتر است چون محدودیت زمان اجرا ندارد:
 *
 *   php /home/user/public_html/tools/cron.php
 *
 * امنیت: این مسیر عمومی است، پس با توکنی که از APP_KEY مشتق می‌شود
 * محافظت می‌شود. بدون توکن درست، ۴۰۴ می‌دهد — نه ۴۰۳ — تا وجود این
 * فایل را به کسی که تصادفی پیدایش کرده لو ندهد.
 *
 * توکن را از صفحهٔ «تنظیمات سالن» یا با این دستور بگیرید:
 *   php tools/cron.php --token
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Cron;

if (!Cron::tokenMatches((string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit('Not Found');
}

// کرون وب ممکن است طول بکشد؛ اگر هاست اجازه بدهد، سقف را بالا ببر.
@set_time_limit(120);
ignore_user_abort(true);

header('Content-Type: text/plain; charset=utf-8');

$report = Cron::run();

foreach ($report as $task => $result) {
    printf("%-28s %s\n", $task, $result);
}
