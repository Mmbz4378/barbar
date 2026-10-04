<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformController;
use App\Http\Middleware\PlatformAdminRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->group(['middleware' => [PlatformAdminRequired::class]], function ($router) {
    $router->get('/platform', [PlatformController::class, 'index']);
    $router->get('/platform/moderation', [App\Http\Controllers\SalonPublicationController::class,'moderation']);
    $router->get('/platform/{id}', [PlatformController::class, 'show']);
    $router->get('/platform/impersonate/stop', [PlatformController::class, 'stopImpersonating']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/platform/moderation', [App\Http\Controllers\SalonPublicationController::class,'moderate']);
        $router->post('/platform/{id}/impersonate', [PlatformController::class, 'impersonate']);
    });
});
