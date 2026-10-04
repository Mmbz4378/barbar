<?php

declare(strict_types=1);

/** نسخهٔ خط‌فرمانِ صفحهٔ سلامت. همان HealthCheck، خروجی ترمینال. */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Domain\Diagnostics\HealthCheck;

$mark = ['ok' => "\033[32m✓\033[0m", 'warn' => "\033[33m!\033[0m", 'fail' => "\033[31m✗\033[0m"];
$failures = 0;

foreach ((new HealthCheck())->run() as $group => $rows) {
    echo "\n\033[1m{$group}\033[0m\n";
    foreach ($rows as $row) {
        printf("  %s %-34s %s\n", $mark[$row['status']], $row['label'], $row['value']);
        if ($row['hint'] !== '') {
            echo "      \033[90m{$row['hint']}\033[0m\n";
        }
        if ($row['status'] === 'fail') {
            $failures++;
        }
    }
}

echo "\n";
echo $failures === 0
    ? "\033[32mهمه‌چیز سالم است.\033[0m\n"
    : "\033[31m{$failures} مورد نیاز به رسیدگی دارد.\033[0m\n";

exit($failures === 0 ? 0 : 1);
