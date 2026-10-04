<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * اجرای مهاجرت‌های دیتابیس.
 *
 * چرا کلاس جدا و نه فقط یک اسکریپت: نصاب وب و ابزار خط فرمان هر دو باید
 * همین کار را بکنند. اگر منطقش دو جا نوشته شود، یکی‌شان عقب می‌ماند و
 * نصبِ مشتری با نصبِ توسعه‌دهنده فرق می‌کند — که بدترین نوع باگ است.
 */
final class Migrator
{
    public function __construct(
        private readonly string $migrationsPath,
    ) {
    }

    /**
     * مهاجرت‌های اجرانشده را اجرا می‌کند.
     *
     * @return array<int,array{file:string,ok:bool,error:?string}> گزارش هر فایل
     */
    public function run(): array
    {
        $this->ensureLedger();

        $applied = $this->appliedFiles();
        $report = [];

        foreach ($this->files() as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                $report[] = ['file' => $name, 'ok' => false, 'error' => 'فایل خوانده نشد'];

                return $report;   // ادامه نده — ترتیب مهاجرت‌ها مهم است
            }

            try {
                DB::connection()->exec($sql);
                DB::insert('schema_migrations', ['filename' => $name]);
                $report[] = ['file' => $name, 'ok' => true, 'error' => null];
            } catch (Throwable $e) {
                $report[] = ['file' => $name, 'ok' => false, 'error' => $e->getMessage()];

                // یک مهاجرتِ شکست‌خورده یعنی بقیه هم روی اسکیمای ناقص
                // اجرا می‌شوند. همین‌جا بایست.
                return $report;
            }
        }

        return $report;
    }

    /** آیا چیزی برای اجرا مانده است؟ */
    public function pendingCount(): int
    {
        $this->ensureLedger();
        $applied = $this->appliedFiles();

        $pending = 0;
        foreach ($this->files() as $file) {
            if (!in_array(basename($file), $applied, true)) {
                $pending++;
            }
        }

        return $pending;
    }

    /**
     * همهٔ جدول‌ها را می‌اندازد. فقط برای توسعه.
     *
     * عمداً متد جدا و با نام صریح است تا کسی اشتباهی صدایش نزند.
     */
    public function dropAllTables(): void
    {
        $pdo = DB::connection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function ensureLedger(): void
    {
        DB::connection()->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return array<int,string> */
    private function appliedFiles(): array
    {
        return array_column(
            DB::select('SELECT filename FROM schema_migrations'),
            'filename'
        );
    }

    /** @return array<int,string> */
    private function files(): array
    {
        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        sort($files);

        return $files;
    }
}
