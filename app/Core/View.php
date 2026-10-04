<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    private static string $basePath = '';

    private static array $shared = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/\\');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = []): string
    {
        $file = self::$basePath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $view");
        }

        $data = array_merge(self::$shared, $data);
        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    public static function renderWithLayout(string $layout, string $view, array $data = []): string
    {
        $content = self::render($view, $data);

        return self::render($layout, array_merge($data, ['content' => $content]));
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
