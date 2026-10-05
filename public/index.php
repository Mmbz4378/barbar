<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

/*
 * سرور داخلی PHP (php -S) فایل‌های ایستا را هم به همین کنترلر می‌دهد،
 * پس بدون این چند خط، CSS و فونت‌ها ۴۰۴ می‌شوند و صفحه لخت بالا می‌آید.
 * روی آپاچی این کار را .htaccess انجام می‌دهد؛ اینجا فقط برای اجرای محلی:
 *
 *     php -S 127.0.0.1:8080 -t public public/index.php
 */
if (PHP_SAPI === 'cli-server') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($path) && $path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
    // Dotted application routes (for example qr.svg) must use the
    // front controller's base path, just like extensionless routes.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require dirname(__DIR__) . '/app/bootstrap.php';

// در لحظهٔ جایگزینی فایل‌ها (به‌روزرسانی خودکار) سایت ۵۰۳ می‌دهد؛
// /health باز می‌ماند چون به‌روزرسان با همان سلامت کد تازه را می‌سنجد.
if (App\Core\Maintenance::active() && parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) !== rtrim(url('health'), '/')) {
    App\Core\Maintenance::respond();
    exit;
}

// حالت تعمیرِ دستیِ مدیر کل: سایت برای همه جز مدیران کل بسته است. ورود و
// پنل مدیریت باز می‌مانند تا مدیر بتواند وارد شود و حالت را خاموش کند.
if (App\Domain\System\SiteSettings::maintenanceOn()) {
    $path = '/' . ltrim(substr((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), strlen(App\Core\Request::basePath())), '/');
    $open = (bool) preg_match('#^/(login|logout|platform|system|account|health|robots\.txt|manifest\.webmanifest)(/|$)#', $path);
    if (!$open && !(App\Core\Auth::check() && App\Core\Auth::isPlatformAdmin())) {
        App\Core\Security::send();
        http_response_code(503);
        header('Retry-After: 600');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        echo App\Core\View::renderWithLayout('layouts.minimal', 'site.maintenance', [
            'title' => 'در دست تعمیر',
            'message' => App\Domain\System\SiteSettings::maintenanceMessage(),
        ]);
        exit;
    }
}

// کارهای «پس از پاسخ» (مثل پیامک) فقط در درخواستِ وب عقب می‌افتند.
App\Core\Deferred::enable();

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';

$request = new Request();
$response = $router->dispatch($request);
$response->send();

// پاسخ رفت؛ حالا پیامک‌ها و دیگر کارهای عقب‌افتاده، بی‌آنکه کاربر منتظر بماند.
App\Core\Deferred::run();
