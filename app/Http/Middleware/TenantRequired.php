<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\SalonRepository;
use App\Support\SalonContext;

/**
 * سالن فعال در نشست هست، و کاربر واقعاً عضو همان سالن است؟
 *
 * لایهٔ اول جداسازی داده بین سالن‌ها: حتی اگر یک کوئری یادش برود با
 * salon_id محدود شود، هیچ اکشنی پیش از رد شدن از این بررسی اجرا نمی‌شود.
 *
 * سالن همین‌جا یک بار خوانده و در SalonContext گذاشته می‌شود تا قالب
 * و واژه‌ها (آرایشگر/متخصص) بدون کوئری دوباره در دسترس باشند.
 */
final class TenantRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::isPlatformAdmin() && Auth::isImpersonating()) {
            return $this->withSalon($request, $next);
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

        return $this->withSalon($request, $next);
    }

    private function withSalon(Request $request, callable $next): Response
    {
        $salon = (new SalonRepository())->find((int) Auth::salonId());
        if ($salon === null) {
            Auth::logout();

            return Response::redirect('/login');
        }
        SalonContext::set($salon);

        return $next($request);
    }
}
