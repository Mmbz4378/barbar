<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * پشتیبان کامل دیتابیس با PHP خالص — هاست اشتراکی معمولاً mysqldump
 * یا exec ندارد.
 *
 * خروجی یک فایل SQL استاندارد فشرده (.sql.gz) است: اگر بازگردانی از
 * پنل ممکن نشد، همین فایل را می‌شود در phpMyAdmin ← Import داد.
 * جدول‌ها تکه‌تکه خوانده می‌شوند تا جدول بزرگ حافظه را پر نکند.
 */
final class DatabaseBackup
{
    private const ROWS_PER_INSERT = 200;
    private const ROWS_PER_READ = 2000;

    public function dump(string $file): void
    {
        $pdo = DB::connection();
        $gz = @gzopen($file, 'wb6');
        if ($gz === false) {
            throw new RuntimeException('فایل پشتیبان دیتابیس ساخته نشد.');
        }

        try {
            gzwrite($gz, "-- Reshen database backup\n-- created: " . date('c') . "\n");
            gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n\n");

            foreach ($this->tables() as $table) {
                $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n");

                $order = $this->primaryKey($table);
                $orderSql = $order !== [] ? ' ORDER BY ' . implode(', ', array_map(static fn ($c) => '`' . $c . '`', $order)) : '';
                for ($offset = 0; ; $offset += self::ROWS_PER_READ) {
                    $rows = $pdo->query('SELECT * FROM `' . $table . '`' . $orderSql . ' LIMIT ' . self::ROWS_PER_READ . ' OFFSET ' . $offset)->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows === []) {
                        break;
                    }
                    $columns = '(`' . implode('`, `', array_keys($rows[0])) . '`)';
                    foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
                        $values = [];
                        foreach ($chunk as $row) {
                            $values[] = '(' . implode(', ', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                        }
                        // هر INSERT در یک خط؛ quote() خط‌شکن‌ها را escape می‌کند
                        gzwrite($gz, "INSERT INTO `{$table}` {$columns} VALUES " . implode(', ', $values) . ";\n");
                    }
                    if (count($rows) < self::ROWS_PER_READ) {
                        break;
                    }
                }
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n-- end of backup\n");
        } finally {
            gzclose($gz);
        }

        if (!$this->looksComplete($file)) {
            @unlink($file);
            throw new RuntimeException('پشتیبان دیتابیس ناقص ماند (فضای دیسک؟).');
        }
    }

    /**
     * بازگردانی کامل: جدول‌هایی که در پشتیبان نیستند (مثلاً ساختهٔ یک
     * مهاجرت نیمه‌کاره) هم حذف می‌شوند تا وضعیت دقیقاً همان لحظه شود.
     */
    /**
     * @param string[] $keepTables جدول‌هایی که دست نمی‌خورند — مثلاً تاریخچهٔ
     *                             به‌روزرسانی، که نباید با بازگردانی عقب برود
     */
    public function restore(string $file, array $keepTables = []): void
    {
        if (!$this->looksComplete($file)) {
            throw new RuntimeException('فایل پشتیبان کامل نیست؛ بازگردانی انجام نشد.');
        }
        $pdo = DB::connection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $keepPattern = $keepTables === [] ? null
            : '/^\s*(?:DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO)\s+`(?:' . implode('|', array_map(static fn ($t) => preg_quote($t, '/'), $keepTables)) . ')`/i';
        try {
            foreach ($this->tables() as $table) {
                if (!in_array($table, $keepTables, true)) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
                }
            }
            $gz = gzopen($file, 'rb');
            $buffer = '';
            while (!gzeof($gz)) {
                $line = (string) gzgets($gz, 16 * 1024 * 1024);
                $trim = trim($line);
                if ($buffer === '' && ($trim === '' || str_starts_with($trim, '--'))) {
                    continue;
                }
                $buffer .= $line;
                if (str_ends_with($trim, ';')) {
                    if ($keepPattern === null || !preg_match($keepPattern, $buffer)) {
                        $pdo->exec($buffer);
                    }
                    $buffer = '';
                }
            }
            gzclose($gz);
            if (trim($buffer) !== '') {
                $pdo->exec($buffer);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /** پشتیبان سالم با خط پایانی بسته می‌شود. */
    public function looksComplete(string $file): bool
    {
        if (!is_file($file) || filesize($file) < 40) {
            return false;
        }
        $gz = @gzopen($file, 'rb');
        if ($gz === false) {
            return false;
        }
        $last = '';
        while (!gzeof($gz)) {
            $line = gzgets($gz, 16 * 1024 * 1024);
            if ($line !== false && trim($line) !== '') {
                $last = trim($line);
            }
        }
        gzclose($gz);

        return $last === '-- end of backup';
    }

    /** @return string[] */
    private function tables(): array
    {
        $rows = DB::connection()->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

        return array_map(static fn ($r) => (string) $r[0], $rows);
    }

    /** @return string[] */
    private function primaryKey(string $table): array
    {
        $keys = DB::connection()->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
        usort($keys, static fn ($a, $b) => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index']);

        return array_map(static fn ($k) => (string) $k['Column_name'], $keys);
    }
}
