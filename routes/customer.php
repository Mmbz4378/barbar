<?php

declare(strict_types=1);

use App\Http\Controllers\CustomerAreaController;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

/*
 * ناحیهٔ مشتری — سطح سوم دسترسی.
 *
 * عمداً زیر /panel نیست و هیچ میدل‌ور احراز هویتِ کارکنان ندارد؛
 * هویتش کلید نشستِ جداگانه‌ای است (CustomerAuth) تا هیچ مسیری از
 * پنل آرایشگاه با آن باز نشود.
 */
$router->get('/me', [CustomerAreaController::class, 'index']);
$router->get('/me/login', [CustomerAreaController::class, 'login']);
$router->get('/me/verify', [CustomerAreaController::class, 'verify']);

$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    $router->post('/me/login', [CustomerAreaController::class, 'login']);
    $router->post('/me/verify', [CustomerAreaController::class, 'verify']);
    $router->post('/me/logout', [CustomerAreaController::class, 'logout']);
    $router->post('/me/{id}/cancel', [CustomerAreaController::class, 'cancel']);
});
