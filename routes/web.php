<?php

declare(strict_types=1);

use App\Core\Response;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManifestController;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->get('/', function () {
    return Response::redirect('/discover');
});

// مانیفست PWA — در لحظه ساخته می‌شود تا برای هر سالن، اسم و صفحهٔ
// شروعِ خودش را بدهد (ت-۳۲).
$router->get('/manifest.webmanifest', [ManifestController::class, 'show']);

// سلامت — برای بررسی پس از به‌روزرسانی خودکار و پایش بیرونی
$router->get('/health', [App\Http\Controllers\HealthController::class, 'show']);

// ─── ورود و خروج ─────────────────────────────────────────────────────
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/login/verify', [AuthController::class, 'showVerify']);
$router->get('/logout', [AuthController::class, 'logout']);

/*
 * ورود با لینک یک‌بارمصرف (ت-۳۶). فقط توکنی که از روی سرور ساخته شده
 * کار می‌کند؛ هیچ راهی برای درخواستِ لینک از وب نیست.
 */
$router->get('/login/link/{token}', [AuthController::class, 'loginWithLink']);

$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    $router->post('/login', [AuthController::class, 'sendOtp']);
    $router->post('/login/verify', [AuthController::class, 'verify']);
});

$router->group(['middleware' => [AuthRequired::class]], function ($router) {
    $router->get('/salons', [AuthController::class, 'pickSalon']);
    $router->get('/salons/{id}/switch', [AuthController::class, 'switchSalon']);
});

require __DIR__ . '/onboarding.php';
require __DIR__ . '/panel.php';
require __DIR__ . '/booking.php';
require __DIR__ . '/customer.php';
require __DIR__ . '/platform.php';

$router->get('/discover', [App\Http\Controllers\DiscoveryController::class, 'index']);
$router->get('/salons/view/{slug}', [App\Http\Controllers\DiscoveryController::class, 'show']);
$router->get('/me/favorites', [App\Http\Controllers\DiscoveryController::class, 'index']);
$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    $router->post('/salons/view/{slug}/favorite', [App\Http\Controllers\DiscoveryController::class, 'favorite']);
    $router->post('/me/{id}/review', [App\Http\Controllers\DiscoveryController::class, 'review']);
    $router->post('/reviews/{id}/report', [App\Http\Controllers\DiscoveryController::class, 'report']);
});
