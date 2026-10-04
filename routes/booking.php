<?php

declare(strict_types=1);

use App\Http\Controllers\BookingWizardController;
use App\Http\Controllers\MyAppointmentController;
use App\Http\Controllers\OnlinePaymentController;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

/*
 * مسیر رزرو عمومی. ترتیب گام‌ها را خود سالن تعیین می‌کند (اول زمان یا
 * اول خدمت)؛ /s/{slug} همیشه گام اول همان سالن است و روی QR چاپ می‌شود.
 *
 * /menu منوی خواندنیِ خدمات است برای بیوی اینستاگرام.
 */
$router->get('/s/{slug}', [BookingWizardController::class, 'landing']);
$router->get('/s/{slug}/menu', [BookingWizardController::class, 'menu']);
$router->get('/s/{slug}/time', [BookingWizardController::class, 'timeStep']);
$router->get('/s/{slug}/services', [BookingWizardController::class, 'servicesStep']);
$router->get('/s/{slug}/staff', [BookingWizardController::class, 'staffStep']);
$router->get('/s/{slug}/phone', [BookingWizardController::class, 'phoneStep']);
$router->get('/s/{slug}/verify', [BookingWizardController::class, 'verifyStep']);

$router->get('/q/{token}', [MyAppointmentController::class, 'show']);
$router->get('/q/{token}/calendar.ics', [MyAppointmentController::class, 'calendar']);
$router->get('/payments/callback', [OnlinePaymentController::class, 'callback']);

$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    $router->post('/s/{slug}', [BookingWizardController::class, 'chooseSlot']);
    $router->post('/s/{slug}/time', [BookingWizardController::class, 'timeStep']);
    $router->post('/s/{slug}/services', [BookingWizardController::class, 'servicesStep']);
    $router->post('/s/{slug}/staff', [BookingWizardController::class, 'staffStep']);
    $router->post('/s/{slug}/phone', [BookingWizardController::class, 'phoneStep']);
    $router->post('/s/{slug}/verify', [BookingWizardController::class, 'verifyStep']);
    $router->post('/q/{token}/cancel', [MyAppointmentController::class, 'cancel']);
    $router->post('/q/{token}/pay', [OnlinePaymentController::class, 'start']);
});
