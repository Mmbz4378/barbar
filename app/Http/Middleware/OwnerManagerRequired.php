<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class OwnerManagerRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::hasRole('owner', 'manager')) {
            return Response::html(View::render('errors.403'), 403);
        }

        return $next($request);
    }
}
