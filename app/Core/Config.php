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

    public static function get(string $dotKey, mixed $default = null): mixed
    {
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
