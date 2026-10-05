<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\System\SystemAccess;

final class SystemAdminRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }
        if (!SystemAccess::allowed()) {
            return Response::redirect('/panel');
        }

        return Auth::passwordChangeGate($request) ?? $next($request);
    }
}
