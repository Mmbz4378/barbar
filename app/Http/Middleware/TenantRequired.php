<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * سالن فعال در نشست هست، و کاربر واقعاً عضو همان سالن است؟
 *
 * لایهٔ اول از سه لایهٔ جداسازی داده بین سالن‌ها. اهمیتش اینجاست: حتی
 * اگر یک کوئری یادش برود با salon_id محدود شود، هیچ اکشنی پیش از رد
 * شدن از این بررسی اجرا نمی‌شود. یعنی یک اشتباه در یک کوئری، به‌تنهایی
 * دادهٔ سالن دیگری را لو نمی‌دهد.
 */
final class TenantRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::isPlatformAdmin() && Auth::isImpersonating()) {
            return $next($request);
        }

        $memberships = Auth::memberships();

        if (empty($memberships)) {
            return Response::redirect('/onboarding');
        }

        $salonId = Auth::salonId();
        $valid = $salonId !== null && array_filter($memberships, static fn ($m) => (int) $m['salon_id'] === $salonId);

        if (!$valid) {
            if (count($memberships) === 1) {
                Auth::setSalon((int) $memberships[0]['salon_id']);
            } else {
                return Response::redirect('/salons');
            }
        }

        return $next($request);
    }
}
