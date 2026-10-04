<?php

declare(strict_types=1);

/**
 * اجرای مهاجرت‌های دیتابیس از خط فرمان.
 *
 *   php tools/migrate.php            اجرای مهاجرت‌های جدید
 *   php tools/migrate.php --fresh    ریست کامل (فقط توسعه!)
 *
 * منطق واقعی در App\Core\Migrator است تا نصاب وب و این ابزار دقیقاً
 * یک کار را بکنند — اگر دو جا نوشته شود، نصبِ مشتری با نصبِ
 * توسعه‌دهنده فرق می‌کند و این بدترین نوع باگ است.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Migrator;

$migrator = new Migrator(BASE_PATH . '/database/migrations');

if (in_array('--fresh', $argv, true)) {
    if (Config::get('app.env') === 'production') {
        fwrite(STDERR, "روی محیط production اجازه ندارد. APP_ENV را بررسی کنید.\n");
        exit(1);
    }
    echo "حذف همهٔ جدول‌ها...\n";
    $migrator->dropAllTables();
}

$report = $migrator->run();

if ($report === []) {
    echo "چیزی برای اجرا نیست.\n";
    exit(0);
}

$failed = false;
foreach ($report as $row) {
    if ($row['ok']) {
        echo "  ✓ {$row['file']}\n";
    } else {
        echo "  ✗ {$row['file']}\n    {$row['error']}\n";
        $failed = true;
    }
}

exit($failed ? 1 : 0);
