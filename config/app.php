<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'reshen'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => Env::bool('APP_DEBUG', true),
    'url' => Env::get('APP_URL', 'http://localhost'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Tehran'),
    'session_name' => Env::get('SESSION_NAME', 'reshen_session'),

    /**
     * کلید امضای برنامه. نصاب آن را می‌سازد.
     *
     * برای چه لازم است: لینک‌هایی که باید جعل‌ناپذیر باشند — تأیید نوبت با
     * پیامک، مسیر کرون، و توکن‌های یک‌بارمصرف. بدون کلید ثابت، این لینک‌ها
     * بعد از هر ری‌استارت باطل می‌شوند.
     */
    'key' => Env::get('APP_KEY', ''),
];
