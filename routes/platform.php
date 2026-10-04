<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformController;
use App\Http\Controllers\SalonPublicationController;
use App\Http\Middleware\PlatformAdminRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->group(['middleware' => [PlatformAdminRequired::class]], function ($router) {
    $router->get('/platform', [PlatformController::class, 'index']);
    $router->get('/platform/moderation', [SalonPublicationController::class, 'moderation']);
    $router->get('/platform/holidays', [PlatformController::class, 'holidays']);
    $router->get('/platform/impersonate/stop', [PlatformController::class, 'stopImpersonating']);
    $router->get('/platform/{id}', [PlatformController::class, 'show']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/platform/moderation', [SalonPublicationController::class, 'moderate']);
        $router->post('/platform/holidays', [PlatformController::class, 'addHoliday']);
        $router->post('/platform/holidays/seed', [PlatformController::class, 'seedHolidays']);
        $router->post('/platform/holidays/{id}/remove', [PlatformController::class, 'removeHoliday']);
        $router->post('/platform/{id}/impersonate', [PlatformController::class, 'impersonate']);
        $router->post('/platform/{id}/active', [PlatformController::class, 'setActive']);
    });
});
