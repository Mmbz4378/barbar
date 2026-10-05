<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PlatformSalonController;
use App\Http\Controllers\PlatformUserController;
use App\Http\Controllers\SalonPublicationController;
use App\Http\Controllers\SystemUpdateController;
use App\Http\Middleware\PlatformAdminRequired;
use App\Http\Middleware\SystemAdminRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

// پنل مدیر کل. مسیرهای مشخص پیش از «/platform/{id}» ثبت می‌شوند (اولین تطابق برنده است).
$router->group(['middleware' => [PlatformAdminRequired::class]], function ($router) {
    $router->get('/platform', [PlatformController::class, 'index']);

    $router->get('/platform/salons', [PlatformSalonController::class, 'index']);
    $router->get('/platform/salons/new', [PlatformSalonController::class, 'create']);
    $router->get('/platform/salons/{id}', [PlatformSalonController::class, 'show']);
    $router->get('/platform/salons/{id}/edit', [PlatformSalonController::class, 'edit']);

    $router->get('/platform/users', [PlatformUserController::class, 'index']);
    $router->get('/platform/users/new', [PlatformUserController::class, 'create']);
    $router->get('/platform/users/{id}', [PlatformUserController::class, 'show']);

    $router->get('/platform/moderation', [SalonPublicationController::class, 'moderation']);
    $router->get('/platform/holidays', [PlatformController::class, 'holidays']);
    $router->get('/platform/impersonate/stop', [PlatformController::class, 'stopImpersonating']);
    $router->get('/platform/{id}', [PlatformSalonController::class, 'legacy']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/platform/salons', [PlatformSalonController::class, 'store']);
        $router->post('/platform/salons/{id}', [PlatformSalonController::class, 'update']);
        $router->post('/platform/salons/{id}/credit', [PlatformSalonController::class, 'credit']);
        $router->post('/platform/salons/{id}/active', [PlatformSalonController::class, 'setActive']);
        $router->post('/platform/salons/{id}/impersonate', [PlatformSalonController::class, 'impersonate']);
        $router->post('/platform/salons/{id}/members', [PlatformSalonController::class, 'addMember']);
        $router->post('/platform/salons/{id}/members/{user}', [PlatformSalonController::class, 'updateMember']);

        $router->post('/platform/users', [PlatformUserController::class, 'store']);
        $router->post('/platform/users/{id}', [PlatformUserController::class, 'update']);
        $router->post('/platform/users/{id}/password', [PlatformUserController::class, 'password']);
        $router->post('/platform/users/{id}/status', [PlatformUserController::class, 'status']);
        $router->post('/platform/users/{id}/memberships', [PlatformUserController::class, 'addMembership']);

        $router->post('/platform/moderation', [SalonPublicationController::class, 'moderate']);
        $router->post('/platform/holidays', [PlatformController::class, 'addHoliday']);
        $router->post('/platform/holidays/seed', [PlatformController::class, 'seedHolidays']);
        $router->post('/platform/holidays/{id}/remove', [PlatformController::class, 'removeHoliday']);
        // نشانی‌های قدیمی فرم‌ها (نسخه‌های پیش از ۱۵)
        $router->post('/platform/{id}/impersonate', [PlatformSalonController::class, 'impersonate']);
        $router->post('/platform/{id}/active', [PlatformSalonController::class, 'setActive']);
    });
});

// به‌روزرسانی سامانه — مدیر پلتفرم، یا صاحب سالن در نصب تک‌سالنی
$router->group(['middleware' => [SystemAdminRequired::class]], function ($router) {
    $router->get('/system/updates', [SystemUpdateController::class, 'index']);
    $router->get('/system/design', [App\Http\Controllers\DesignSystemController::class, 'index']);

    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/system/updates/check', [SystemUpdateController::class, 'check']);
        $router->post('/system/updates/apply', [SystemUpdateController::class, 'apply']);
        $router->post('/system/updates/settings', [SystemUpdateController::class, 'settings']);
        $router->post('/system/updates/{id}/restore-db', [SystemUpdateController::class, 'restoreDatabase']);
    });
});
