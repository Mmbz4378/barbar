<?php

declare(strict_types=1);

use App\Http\Controllers\BookingWizardController;
use App\Http\Controllers\MyAppointmentController;
use App\Http\Middleware\VerifyCsrf;

/** @var \App\Core\Router $router */

/*
 * ترتیب گام‌ها: روز و سانس -> خدمت -> آرایشگر -> نام و شماره.
 *
 * /menu منوی خواندنیِ خدمات است برای بیوی اینستاگرام — بدون ورود به
 * مسیر رزرو، چون خیلی‌ها فقط می‌خواهند بدانند «چی دارید و چند؟».
 */
$router->get('/s/{slug}', [BookingWizardController::class, 'landing']);
$router->get('/s/{slug}/menu', [BookingWizardController::class, 'menu']);
$router->get('/s/{slug}/services', [BookingWizardController::class, 'servicesStep']);
$router->get('/s/{slug}/staff', [BookingWizardController::class, 'staffStep']);
$router->get('/s/{slug}/phone', [BookingWizardController::class, 'phoneStep']);
$router->get('/s/{slug}/verify', [BookingWizardController::class, 'verifyStep']);

$router->get('/q/{token}', [MyAppointmentController::class, 'show']);

$router->group(['middleware' => [VerifyCsrf::class]], function ($router) {
    $router->post('/s/{slug}', [BookingWizardController::class, 'chooseSlot']);
    $router->post('/s/{slug}/services', [BookingWizardController::class, 'servicesStep']);
    $router->post('/s/{slug}/staff', [BookingWizardController::class, 'staffStep']);
    $router->post('/s/{slug}/phone', [BookingWizardController::class, 'phoneStep']);
    $router->post('/s/{slug}/verify', [BookingWizardController::class, 'verifyStep']);
    $router->post('/q/{token}/cancel', [MyAppointmentController::class, 'cancel']);
});

$router->get('/payments/callback',[App\Http\Controllers\OnlinePaymentController::class,'callback']);
$router->group(['middleware'=>[VerifyCsrf::class]],function($router){
 $router->post('/q/{token}/pay',[App\Http\Controllers\OnlinePaymentController::class,'start']);
});
