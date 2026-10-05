<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string,array> */
    private static array $items = [];

    private static string $path = '';

    public static function load(string $configPath): void
    {
        self::$path = rtrim($configPath, '/\\');
        foreach (glob(self::$path . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            self::$items[$key] = require $file;
        }
    }

    /**
     * لایهٔ تنبلِ تنظیمات پنل روی فایل‌های config (و .env).
     *
     * تنظیماتی که مدیر کل از پنل ذخیره می‌کند (پیامک، پرداخت) در دیتابیس‌اند.
     * به‌جای عوض‌کردن همهٔ جاهایی که Config::get را صدا می‌زنند، اولین باری
     * که کلیدی با یکی از این پیشوندها خوانده شود، مقادیر پنل یک بار بار
     * می‌شوند و روی مقادیر فایل می‌نشینند. درخواستی که به این کلیدها کاری
     * ندارد (بیشتر صفحه‌ها) هیچ کوئری اضافه‌ای نمی‌زند.
     *
     * @var (callable(): array<string,mixed>)|null
     */
    private static $overlay = null;

    private const OVERLAY_PREFIXES = ['reshen.sms', 'reshen.payment'];

    /** @param callable(): array<string,mixed> $loader  نگاشت «کلید نقطه‌دار» ← مقدار */
    public static function setOverlay(callable $loader): void
    {
        self::$overlay = $loader;
    }

    public static function set(string $dotKey, mixed $value): void
    {
        $segments = explode('.', $dotKey);
        $ref = &self::$items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public static function get(string $dotKey, mixed $default = null): mixed
    {
        if (self::$overlay !== null) {
            foreach (self::OVERLAY_PREFIXES as $prefix) {
                if (str_starts_with($dotKey, $prefix)) {
                    $loader = self::$overlay;
                    self::$overlay = null; // یک بار در هر درخواست؛ پیش از صدا زدن، تا بازگشت بی‌پایان نشود
                    foreach ($loader() as $key => $value) {
                        self::set((string) $key, $value);
                    }
                    break;
                }
            }
        }

        $segments = explode('.', $dotKey);
        $file = array_shift($segments);
        $value = self::$items[$file] ?? null;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value ?? $default;
    }
}
