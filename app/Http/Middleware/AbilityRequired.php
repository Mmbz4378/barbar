<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Access\Access;

/**
 * گیت یک اجازهٔ مشخص روی یک گروه مسیر.
 *
 * در تعریف مسیر به شکل «AbilityRequired::class . ':view_customers'»
 * نوشته می‌شود؛ روتر پارامتر را به سازنده می‌دهد.
 */
final class AbilityRequired implements Middleware
{
    private string $ability;

    public function __construct(string $ability)
    {
        $this->ability = $ability;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!Access::allows($this->ability)) {
            return Response::html(View::render('errors.403'), 403);
        }

        return $next($request);
    }
}
