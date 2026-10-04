<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'reshen'),
    'username' => Env::get('DB_USERNAME', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    // سقف انتظار برای قفل ردیف (ثانیه) — app/Core/DB.php
    'lock_wait_timeout' => (int) Env::get('DB_LOCK_WAIT_TIMEOUT', '5'),
];
