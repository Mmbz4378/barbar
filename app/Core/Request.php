<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public readonly string $method;
    public readonly string $path;
    /** @var array<string,string> */
    public array $routeParams = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $this->path = '/' . trim($uri, '/');
    }

    /**
     * پیشوند آدرس پروژه — خالی اگر روی ریشهٔ دامنه نصب شده باشد،
     * وگرنه چیزی مثل «/reshen».
     *
     * دو حالت نصب را پوشش می‌دهد (راهنمای استقرار، بخش cPanel):
     *
     *   الف) document root روی public/ است
     *        SCRIPT_NAME = /index.php            → ''
     *        SCRIPT_NAME = /reshen/index.php     → '/reshen'
     *
     *   ب) کل پروژه در public_html است و .htaccess ریشه درخواست‌ها را
     *      به public/ می‌فرستد. آپاچی «/public» را در SCRIPT_NAME
     *      نگه می‌دارد، ولی کاربر آن را در آدرس نمی‌بیند — پس باید حذف شود،
     *      وگرنه همهٔ لینک‌ها یک «/public» اضافه می‌گیرند و ۴۰۴ می‌شوند.
     *        SCRIPT_NAME = /public/index.php        → ''
     *        SCRIPT_NAME = /reshen/public/index.php → '/reshen'
     */
    public static function basePath(): string
    {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = rtrim($scriptDir, '/');

        // حالت (ب): «/public» انتهایی را بردار.
        // فقط وقتی که درخواستِ واقعی کاربر شامل «/public» نبوده باشد —
        // اگر کسی عمداً example.com/public/... را باز کند، دست نمی‌زنیم.
        if (str_ends_with($scriptDir, '/public') || $scriptDir === '/public') {
            $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
            if (!str_starts_with(ltrim($requestUri, '/'), 'public/')
                && ltrim($requestUri, '/') !== 'public') {
                $scriptDir = substr($scriptDir, 0, -strlen('/public'));
            }
        }

        return rtrim($scriptDir, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function isJson(): bool
    {
        return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * IP مشتری.
     *
     * روی cPanel، سایت معمولاً پشت یک پراکسی یا CDN (آروان، ابرآروان)
     * است و REMOTE_ADDR آدرسِ خودِ پراکسی را می‌دهد — یعنی همهٔ
     * بازدیدکننده‌ها یک IP می‌شوند و محدودیت نرخ، همه را با هم می‌بندد.
     *
     * پس سرآیندهای پراکسی هم خوانده می‌شوند. اولین مقدارِ
     * X-Forwarded-For، IP واقعی کاربر است.
     *
     * نکته: این سرآیندها جعل‌شدنی‌اند. برای محدودیت نرخ کافی است (بدترین
     * حالت، مهاجم سقف خودش را دور می‌زند که با شمارش بر اساس شماره هم
     * گرفته می‌شود)، ولی هرگز نباید مبنای **مجوز دسترسی** شود.
     */
    public function ip(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            $value = $_SERVER[$header] ?? '';
            if ($value === '') {
                continue;
            }

            $first = trim(explode(',', (string) $value)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : null;
    }

    public function header(string $key): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));

        return $_SERVER[$normalized] ?? null;
    }
}
