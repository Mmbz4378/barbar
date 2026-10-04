<?php

declare(strict_types=1);

use App\Core\Response;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\SalonCustomersController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SmsPatternController;
use App\Http\Controllers\SalonSettingsController;
use App\Http\Controllers\StaffController;
use App\Domain\Access\Access;
use App\Http\Middleware\AbilityRequired;
use App\Http\Middleware\AuthRequired;
use App\Http\Middleware\OwnerManagerRequired;
use App\Http\Middleware\TenantRequired;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

$router->group(['middleware' => [AuthRequired::class, TenantRequired::class]], function ($router) {
    $router->get('/panel', [QueueController::class, 'index']);
    $router->get('/panel/queue/poll', [QueueController::class, 'poll']);
    $router->get('/panel/bookings', [BookingsController::class, 'index']);

    // برگهٔ QR — هر کسی که به پنل سالن دسترسی دارد می‌تواند چاپش کند.
    $router->get('/panel/qr', [QrController::class, 'show']);
    $router->get('/panel/qr.svg', [QrController::class, 'svg']);
    $router->get('/panel/qr.png', [QrController::class, 'png']);
    $router->get('/panel/setup', function () {
        return Response::redirect('/panel/staff');
    });

    /*
     * اکشن‌های صف برای همهٔ اعضای سالن باز است، چون آرایشگر باید
     * بتواند نوبت خودش را شروع و تمام کند. محدودیتِ «فقط نوبتِ خودت»
     * در خود کنترلر و با Access::canActOnAppointment اعمال می‌شود —
     * اینجا قابل اعمال نیست چون به خودِ نوبت نگاه می‌خواهد.
     */
    $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
        $router->post('/panel/queue/{id}/start', [QueueController::class, 'start']);
        $router->post('/panel/queue/{id}/complete', [QueueController::class, 'complete']);
        $router->post('/panel/queue/{id}/no-show', [QueueController::class, 'noShow']);
        $router->post('/panel/queue/{id}/cancel', [QueueController::class, 'cancel']);
    });

    /*
     * پروندهٔ مشتریان و شمارهٔ تماسشان — پیش از این هر آرایشگری
     * می‌دیدش. برای پذیرش لازم است، برای آرایشگر نه.
     */
    $router->group(['middleware' => [AbilityRequired::class . ':' . Access::VIEW_CUSTOMERS]], function ($router) {
        $router->get('/panel/customers', [SalonCustomersController::class, 'index']);
        $router->get('/panel/customers/{id}', [SalonCustomersController::class, 'show']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/customers/{id}', [SalonCustomersController::class, 'update']);
        });
    });

    // تسویه — کار پیشخوان است، نه کار هر آرایشگری.
    $router->group(['middleware' => [AbilityRequired::class . ':' . Access::TAKE_PAYMENT]], function ($router) {
        $router->get('/panel/pay/{id}', [PaymentController::class, 'show']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/pay/{id}', [PaymentController::class, 'store']);
        });
    });

    // ثبت نوبت به نام دیگران و افزودن مراجع حضوری.
    $router->group(['middleware' => [AbilityRequired::class . ':' . Access::BOOK_FOR_OTHERS]], function ($router) {
        $router->get('/panel/bookings/new', [BookingsController::class, 'create']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/bookings', [BookingsController::class, 'store']);
            $router->post('/panel/queue/walkin', [QueueController::class, 'addWalkin']);
        });
    });

    // ─── آرایشگرها (فقط صاحب و مدیر) ──────────────────────────────────────
    $router->group(['middleware' => [OwnerManagerRequired::class]], function ($router) {
        $router->get('/panel/staff', [StaffController::class, 'index']);
        $router->get('/panel/staff/create', [StaffController::class, 'create']);
        $router->get('/panel/staff/{id}/edit', [StaffController::class, 'edit']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/staff', [StaffController::class, 'store']);
            $router->post('/panel/staff/{id}', [StaffController::class, 'update']);
            $router->post('/panel/staff/{id}/toggle', [StaffController::class, 'toggle']);
        });

        // ─── خدمات ───────────────────────────────────────────────────────────
        $router->get('/panel/services', [ServiceController::class, 'index']);
        $router->get('/panel/services/create', [ServiceController::class, 'create']);
        $router->get('/panel/services/{id}/edit', [ServiceController::class, 'edit']);

        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            $router->post('/panel/services', [ServiceController::class, 'store']);
            $router->post('/panel/services/{id}', [ServiceController::class, 'update']);
            $router->post('/panel/services/{id}/override', [ServiceController::class, 'setOverride']);
            $router->post('/panel/services/{id}/toggle', [ServiceController::class, 'toggle']);
        });

        // ─── گزارش‌ها ─────────────────────────────────────────────────────────
        $router->get('/panel/reports', [ReportController::class, 'daily']);
        $router->get('/panel/reports/monthly', [ReportController::class, 'monthly']);

        // ─── تنظیمات سالن ────────────────────────────────────────────────────
        $router->get('/panel/sms', [SmsPatternController::class, 'index']);
        $router->get('/panel/settings', [SalonSettingsController::class, 'show']);
        $router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
            // ثبت الگو در سامانهٔ اپراتور. داخل گروه «صاحب و مدیر» است
            // چون روی حساب پیامکی اثر می‌گذارد، نه فقط روی این سالن.
            $router->post('/panel/sms/register', [SmsPatternController::class, 'register']);
            $router->post('/panel/settings/profile', [SalonSettingsController::class, 'updateProfile']);
            $router->post('/panel/settings/hours', [SalonSettingsController::class, 'updateHours']);
            $router->post('/panel/settings/holidays/seed', [SalonSettingsController::class, 'seedHolidays']);
            $router->post('/panel/settings/holidays', [SalonSettingsController::class, 'addHoliday']);
            $router->post('/panel/settings/holidays/{id}/remove', [SalonSettingsController::class, 'removeHoliday']);
        $router->post('/panel/settings/timeoff', [SalonSettingsController::class, 'addTimeOff']);
        $router->post('/panel/settings/timeoff/{id}/remove', [SalonSettingsController::class, 'removeTimeOff']);
        });
    });
});

$router->group(['middleware'=>[AuthRequired::class,TenantRequired::class,OwnerManagerRequired::class]], function($router){
 $router->get('/panel/publication',[App\Http\Controllers\SalonPublicationController::class,'edit']);
 $router->group(['middleware'=>[VerifyCsrf::class]],function($router){
  $router->post('/panel/publication',[App\Http\Controllers\SalonPublicationController::class,'save']);
  $router->post('/panel/services/{id}/image',[App\Http\Controllers\SalonPublicationController::class,'serviceImage']);
 });
});
