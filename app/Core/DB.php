<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * پوسته‌ای نازک روی PDO.
 *
 * ‏SQL را خودِ مخزن‌های دامنه می‌نویسند؛ اینجا فقط اتصال، اجرای امنِ
 * کوئری آماده، و تراکنش‌ها یک‌جا جمع شده‌اند. عمداً ORM نیست: روی هاست
 * اشتراکی، هر لایهٔ اضافه یعنی کندی و یک چیز بیشتر که می‌تواند خراب شود.
 */
final class DB
{
    private static int $savepoints = 0;

    private static ?PDO $pdo = null;

    private static bool $profiling = false;

    /** @var array<int,array{sql:string,ms:float}> */
    private static array $queryLog = [];

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $host = Config::get('database.host');
            $port = Config::get('database.port');
            $name = Config::get('database.database');
            $user = Config::get('database.username');
            $pass = Config::get('database.password');
            $charset = Config::get('database.charset', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    // اتصالی که در چند ثانیه برقرار نشود، برقرار نمی‌شود؛ زودتر
                    // صفحهٔ «شلوغ است» بهتر از معطلیِ پردازش است.
                    PDO::ATTR_TIMEOUT => 5,
                    // اتصال ماندگار فقط با تصمیم مدیر: روی cPanel با سقف
                    // max_user_connections، هر پردازشِ بیکار یک اتصال نگه می‌دارد
                    // و می‌تواند سقف را زودتر پر کند (docs/performance.md).
                    PDO::ATTR_PERSISTENT => (bool) Config::get('database.persistent', false),
                ]);
            } catch (\PDOException $e) {
                if (Overloaded::isOverload($e)) {
                    throw new Overloaded('دیتابیس اتصال تازه نمی‌پذیرد.', 0, $e);
                }
                throw $e;
            }

            /*
             * سقف انتظار برای قفل ردیف. پیش‌فرض MySQL پنجاه ثانیه است: زیر هجوم
             * روی یک سالن، درخواست‌ها تا پنجاه ثانیه پشت قفل می‌ماندند و هرکدام
             * یک پردازش PHP را نگه می‌داشت — سالن‌های دیگر هم از دسترس خارج
             * می‌شدند. بخش قفل‌شده چند میلی‌ثانیه است، پس چند ثانیه انتظار یعنی
             * صدها درخواستِ جلوتر؛ بیش از آن، پیام «شلوغ است» بهتر از معطلی است.
             */
            $wait = max(1, min(50, (int) Config::get('database.lock_wait_timeout', 5)));
            self::$pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . $wait);
        }

        return self::$pdo;
    }

    public static function statement(string $sql, array $bindings = []): PDOStatement
    {
        try {
            return self::run($sql, $bindings);
        } catch (\PDOException $e) {
            // اتصالِ قطع‌شده وسط کار هم یعنی سرور زیر فشار است
            if (Overloaded::isOverload($e)) {
                throw new Overloaded('اتصال دیتابیس وسط کار قطع شد.', 0, $e);
            }
            throw $e;
        }
    }

    private static function run(string $sql, array $bindings): PDOStatement
    {
        if (!self::$profiling) {
            $stmt = self::connection()->prepare($sql);
            $stmt->execute($bindings);

            return $stmt;
        }

        $started = microtime(true);
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($bindings);
        self::$queryLog[] = [
            'sql' => preg_replace('/\s+/', ' ', trim($sql)),
            'ms' => (microtime(true) - $started) * 1000,
        ];

        return $stmt;
    }

    /**
     * ثبت کوئری‌ها برای پیدا کردن کوئریِ تکراری (N+1).
     *
     * پیش‌فرض خاموش است و در مسیر داغ حتی یک شرط بیشتر هزینه ندارد.
     * فقط با ابزار سنجش روشن می‌شود، نه در تولید.
     */
    public static function startProfiling(): void
    {
        self::$profiling = true;
        self::$queryLog = [];
    }

    /** @return array<int,array{sql:string,ms:float}> کوئری‌های اجراشده و زمانشان */
    public static function queryLog(): array
    {
        return self::$queryLog;
    }

    public static function stopProfiling(): void
    {
        self::$profiling = false;
    }

    public static function select(string $sql, array $bindings = []): array
    {
        return self::statement($sql, $bindings)->fetchAll();
    }

    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::statement($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    public static function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        self::statement($sql, self::bindKeys($data));

        return self::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereBindings = []): int
    {
        $set = implode(', ', array_map(static fn (string $c) => "$c = :set_$c", array_keys($data)));
        $setBindings = [];
        foreach ($data as $key => $value) {
            $setBindings["set_$key"] = $value;
        }

        $sql = "UPDATE $table SET $set WHERE $where";
        $stmt = self::statement($sql, array_merge($setBindings, $whereBindings));

        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $bindings = []): int
    {
        $stmt = self::statement("DELETE FROM $table WHERE $where", $bindings);

        return $stmt->rowCount();
    }

    /**
     * تراکنش؛ تودرتو هم امن است.
     *
     * اگر تراکنشی باز باشد (مثلاً سرویسی که خودش تراکنش دارد از درون
     * تراکنش دیگری صدا زده شود)، به‌جای beginTransaction دوم — که PDO با
     * خطا ردش می‌کند — یک SAVEPOINT ساخته می‌شود تا شکستِ بخش درونی فقط
     * همان بخش را برگرداند.
     */
    /**
     * کار را اجرا می‌کند و اگر به بن‌بست (deadlock) خورد، یک بار دیگر.
     *
     *  - 1213 بن‌بست: InnoDB یکی از دو تراکنش را برگردانده و طرف مقابل رفته؛
     *    تلاش دوباره معمولاً موفق است.
     *  - 1205 پایان مهلت قفل: نگه‌دارنده هنوز نگه داشته؛ تلاش دوباره فقط انتظار
     *    را دو برابر می‌کرد. بی‌درنگ پیام «شلوغ است».
     *
     * فقط در بیرونی‌ترین سطح دوباره تلاش می‌کند: بن‌بست کل تراکنش را برمی‌گرداند
     * و تکرارِ یک savepoint درونی بی‌معناست؛ آنجا خطا بالا می‌رود تا سطح بیرونی
     * تصمیم بگیرد.
     */
    public static function retryOnLockConflict(callable $work, int $retries = 1): mixed
    {
        $attempt = 0;
        while (true) {
            try {
                return $work();
            } catch (\PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? 0);
                if (($code !== 1213 && $code !== 1205) || self::connection()->inTransaction()) {
                    throw $e;
                }
                if ($code === 1205 || $attempt++ >= $retries) {
                    throw new LockConflict();
                }
                usleep(random_int(20, 100) * 1000);
            }
        }
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        if ($pdo->inTransaction()) {
            $savepoint = 'sp_' . (++self::$savepoints);
            $pdo->exec('SAVEPOINT ' . $savepoint);
            try {
                $result = $callback();
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);

                return $result;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                }
                throw $e;
            } finally {
                self::$savepoints--;
            }
        }

        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    private static function bindKeys(array $data): array
    {
        $bindings = [];
        foreach ($data as $key => $value) {
            $bindings[$key] = $value;
        }

        return $bindings;
    }
}
