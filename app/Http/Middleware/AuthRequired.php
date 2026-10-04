<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class AuthRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
