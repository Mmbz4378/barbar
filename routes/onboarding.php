<?php

declare(strict_types=1);

use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->group(['middleware' => [AuthRequired::class]], function ($router) {
    $router->get('/onboarding', [OnboardingController::class, 'show']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/onboarding', [OnboardingController::class, 'store']);
    });
});
