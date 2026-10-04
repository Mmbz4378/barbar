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
                /*
                 * دستورها یکی‌یکی اجرا می‌شوند، نه با یک exec چنددستوری.
                 *
                 * در اجرای چنددستوری، PDO فقط خطای دستور اول را گزارش
                 * می‌کند؛ اگر ALTER سوم شکست بخورد، مهاجرت «موفق» ثبت
                 * می‌شد و اسکیمای نیمه‌کاره بی‌صدا در تولید می‌ماند.
                 */
                foreach (self::splitStatements($sql) as $statement) {
                    DB::connection()->exec($statement);
                }
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

    /**
     * متن یک فایل SQL را به دستورهای جدا می‌شکند.
     *
     * «;» داخل رشته‌ها و توضیح‌ها جداکننده حساب نمی‌شود. توضیح‌های
     * «--» و «/* … *\/» حذف می‌شوند چون بعضی نسخه‌های MariaDB دستورِ
     * فقط-توضیح را خطا می‌دانند.
     *
     * @return string[]
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`') {
                    $buffer .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '-' && $next === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $buffer .= "\n";
                continue;
            }
            if ($char === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $buffer .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
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
