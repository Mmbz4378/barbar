<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\Str;

/**
 * ثبت سالن — از ورود تا آمادهٔ رزرو، زیر ده دقیقه.
 *
 * هرچه اینجا پرسیده شود یک مانع است، پس فقط چیزهایی پرسیده می‌شوند که
 * بدونشان سالن کار نمی‌کند. بقیه بعداً در تنظیمات.
 */
final class OnboardingController extends Controller
{
    public function show(Request $request): Response
    {
        if (!empty(Auth::memberships())) {
            return $this->redirect('/salons');
        }

        return $this->page('layouts.auth', 'auth.onboarding', [
            'error' => Session::flash('error'),
            'title' => 'ساخت سالن',
        ]);
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $city = trim((string) $request->input('city', ''));
        $address = trim((string) $request->input('address', ''));
        $seats = max(1, (int) $request->input('seats', 1));

        if ($name === '') {
            return $this->withError('نام سالن را وارد کنید.', '/onboarding');
        }

        $slug = Str::slug($name) ?: 'salon';
        $baseSlug = $slug;
        $i = 1;
        while (DB::selectOne('SELECT id FROM salons WHERE slug = ?', [$slug]) !== null) {
            $slug = $baseSlug . '-' . (++$i);
        }

        $salonId = DB::transaction(function () use ($name, $slug, $city, $address, $seats) {
            $id = DB::insert('salons', [
                'slug' => $slug,
                'name' => $name,
                'city' => $city ?: null,
                'address' => $address ?: null,
                'seats' => $seats,
                'plan_code' => 'trial',
                'sms_credit' => 200,
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ]);

            DB::insert('salon_user', [
                'salon_id' => $id,
                'user_id' => Auth::id(),
                'role' => 'owner',
            ]);

            // ساعت کاری پیش‌فرض: هر روز ۹ تا ۲۱، بعداً قابل تغییر. بدون این،
        // سالن تازه هیچ سانس آزادی ندارد و صفحهٔ عمومی‌اش خالی است.
            for ($weekday = 0; $weekday <= 6; $weekday++) {
                DB::insert('working_hours', [
                    'salon_id' => $id,
                    'staff_id' => null,
                    'weekday' => $weekday,
                    'opens_at' => '09:00:00',
                    'closes_at' => '21:00:00',
                    'is_closed' => $weekday === 6 ? 1 : 0, // جمعه پیش‌فرض تعطیل
                ]);
            }

            return $id;
        });

        Auth::setSalon((int) $salonId);

        return $this->withSuccess('سالن با موفقیت ساخته شد.', '/panel/setup');
    }
}
