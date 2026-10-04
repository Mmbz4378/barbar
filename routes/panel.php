<?php

declare(strict_types=1);

use App\Domain\Access\Access;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalonCustomersController;
use App\Http\Controllers\SalonPublicationController;
use App\Http\Controllers\SalonSettingsController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SmsPatternController;
use App\Http\Controllers\StaffController;
use App\Http\Middleware\AbilityRequired;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\OwnerManagerRequired;
use App\Http\Middleware\TenantRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->group(['middleware' => [AuthRequired::class, TenantRequired::class]], function ($router) {
    // ─── همهٔ اعضای سالن ─────────────────────────────────────────────
    $router->get('/panel', [QueueController::class, 'index']);
    $router->get('/panel/queue/poll', [QueueController::class, 'poll']);
    $router->get('/panel/bookings', [BookingsController::class, 'index']);
    $router->get('/panel/qr', [QrController::class, 'show']);
    $router->get('/panel/qr.svg', [QrController::class, 'svg']);
    $router->get('/panel/qr.png', [QrController::class, 'png']);

    /*
     * اکشن‌های صف برای همه باز است تا آرایشگر نوبت خودش را شروع و تمام
     * کند؛ «فقط نوبت خودت» در کنترلر با Access::canActOnAppointment
     * اعمال می‌شود چون به خودِ نوبت نگاه می‌خواهد.
     */
    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/panel/queue/{id}/start', [QueueController::class, 'start']);
        $router->post('/panel/queue/{id}/complete', [QueueController::class, 'complete']);
        $router->post('/panel/queue/{id}/no-show', [QueueController::class, 'noShow']);
        $router->post('/panel/queue/{id}/cancel', [QueueController::class, 'cancel']);
    });

    // ─── پرونده‌های مشتری (نه برای آرایشگر) ─────────────────────────
    $router->group(['middleware' => [AbilityRequired::class . ':' . Access::VIEW_CUSTOMERS]], function ($router) {
        $router->get('/panel/customers', [SalonCustomersController::class, 'index']);
        $router->get('/panel/customers/{id}', [SalonCustomersController::class, 'show']);
        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/customers/{id}', [SalonCustomersController::class, 'update']);
        });
    });

    // ─── تسویه ──────────────────────────────────────────────────────
    $router->group(['middleware' => [AbilityRequired::class . ':' . Access::TAKE_PAYMENT]], function ($router) {
        $router->get('/panel/pay/{id}', [PaymentController::class, 'show']);
        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/pay/{id}', [PaymentController::class, 'store']);
        });
    });

    // ─── پیشخوان: رزرو برای دیگران، پذیرش حضوری، بیعانه ─────────────
    $router->group(['middleware' => [AbilityRequired::class . ':' . Access::BOOK_FOR_OTHERS]], function ($router) {
        $router->get('/panel/bookings/new', [BookingsController::class, 'create']);
        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/bookings', [BookingsController::class, 'store']);
            $router->post('/panel/bookings/{id}/confirm', [BookingsController::class, 'confirmDeposit']);
            $router->post('/panel/queue/walkin', [QueueController::class, 'addWalkin']);
        });
    });

    // ─── مدیریت سالن (صاحب و مدیر) ──────────────────────────────────
    $router->group(['middleware' => [OwnerManagerRequired::class]], function ($router) {
        $router->get('/panel/setup', static fn () => App\Core\Response::redirect('/panel/staff'));
        $router->get('/panel/staff', [StaffController::class, 'index']);
        $router->get('/panel/staff/create', [StaffController::class, 'create']);
        $router->get('/panel/staff/{id}/edit', [StaffController::class, 'edit']);
        $router->get('/panel/services', [ServiceController::class, 'index']);
        $router->get('/panel/services/create', [ServiceController::class, 'create']);
        $router->get('/panel/services/{id}/edit', [ServiceController::class, 'edit']);
        $router->get('/panel/reports', [ReportController::class, 'daily']);
        $router->get('/panel/reports/monthly', [ReportController::class, 'monthly']);
        $router->get('/panel/sms', [SmsPatternController::class, 'index']);
        $router->get('/panel/settings', [SalonSettingsController::class, 'show']);
        $router->get('/panel/publication', [SalonPublicationController::class, 'edit']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/staff', [StaffController::class, 'store']);
            $router->post('/panel/staff/{id}', [StaffController::class, 'update']);
            $router->post('/panel/staff/{id}/toggle', [StaffController::class, 'toggle']);
            $router->post('/panel/team/members', [StaffController::class, 'addMember']);
            $router->post('/panel/team/members/{user}', [StaffController::class, 'updateMember']);

            $router->post('/panel/services', [ServiceController::class, 'store']);
            $router->post('/panel/services/{id}', [ServiceController::class, 'update']);
            $router->post('/panel/services/{id}/toggle', [ServiceController::class, 'toggle']);
            $router->post('/panel/services/{id}/image', [ServiceController::class, 'image']);
            $router->post('/panel/categories', [ServiceController::class, 'storeCategory']);
            $router->post('/panel/categories/{id}', [ServiceController::class, 'updateCategory']);

            // روی حساب پیامکی اثر می‌گذارد، نه فقط این سالن
            $router->post('/panel/sms/register', [SmsPatternController::class, 'register']);
            $router->post('/panel/settings/profile', [SalonSettingsController::class, 'updateProfile']);
            $router->post('/panel/settings/hours', [SalonSettingsController::class, 'updateHours']);
            $router->post('/panel/settings/rules', [SalonSettingsController::class, 'updateRules']);
            $router->post('/panel/settings/timeoff', [SalonSettingsController::class, 'addTimeOff']);
            $router->post('/panel/settings/timeoff/{id}/remove', [SalonSettingsController::class, 'removeTimeOff']);
            $router->post('/panel/publication', [SalonPublicationController::class, 'save']);
        });
    });
});
