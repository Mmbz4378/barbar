<?php

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    /** @var array<string,string> */
    private static array $prefixes = [];

    public static function register(string $prefix, string $baseDir): void
    {
        self::$prefixes[rtrim($prefix, '\\') . '\\'] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require $file;

                return;
            }
        }
    }
}
