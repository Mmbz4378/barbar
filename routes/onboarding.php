<?php

declare(strict_types=1);

use App\Http\Controllers\OnboardingController;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->group(['middleware' => [AuthRequired::class]], function ($router) {
    $router->get('/onboarding', [OnboardingController::class, 'show']);
    $router->get('/onboarding/new', [OnboardingController::class, 'show']);
    $router->get('/onboarding/services', [OnboardingController::class, 'services']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/onboarding', [OnboardingController::class, 'store']);
        $router->post('/onboarding/services', [OnboardingController::class, 'services']);
    });
});
