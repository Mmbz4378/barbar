<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class VerifyCsrf implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method, ['POST', 'PUT', 'DELETE'], true)) {
            $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
            if (!Session::verifyCsrf(is_string($token) ? $token : null)) {
                return Response::html('توکن امنیتی نامعتبر است. صفحه را رفرش کنید.', 419);
            }
        }

        return $next($request);
    }
}
