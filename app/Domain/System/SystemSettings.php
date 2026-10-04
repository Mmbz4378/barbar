<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Core\DB;
use Throwable;

/**
 * تنظیمات سطح سامانه (نه سالن) — کلید/مقدار.
 *
 * اگر جدول هنوز ساخته نشده باشد (پیش از مهاجرت ۰۰۲۳)، مقدار پیش‌فرض
 * برمی‌گردد و نوشتن بی‌صدا نادیده گرفته می‌شود؛ به‌روزرسان باید حتی روی
 * نصبی که مهاجرتش عقب است هم کار کند.
 */
final class SystemSettings
{
    /** @var array<string,?string>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::load();

        return array_key_exists($key, self::$cache) ? self::$cache[$key] : $default;
    }

    public static function getJson(string $key): ?array
    {
        $raw = self::get($key);
        $data = $raw !== null ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    public static function set(string $key, ?string $value): void
    {
        try {
            DB::statement(
                'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$key, $value]
            );
            self::load();
            self::$cache[$key] = $value;
        } catch (Throwable $e) {
            error_log('system_settings write skipped: ' . $e->getMessage());
        }
    }

    public static function setJson(string $key, ?array $value): void
    {
        self::set($key, $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            foreach (DB::select('SELECT setting_key, setting_value FROM system_settings') as $row) {
                self::$cache[(string) $row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            // جدول هنوز نیست
        }
    }
}
