<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Catalog\CatalogTemplates;
use App\Domain\Salon\SalonSetupService;
use App\Support\Audience;
use App\Support\IranMobile;
use RuntimeException;

/**
 * ثبت سالن در دو گام: نوع و مشخصات، بعد خدمات پیشنهادی همان نوع.
 *
 * فقط چیزهایی پرسیده می‌شود که بدونشان سالن کار نمی‌کند؛ بقیه در
 * تنظیمات. پیش‌نویس در نشست می‌ماند تا برگشت از گام دوم چیزی را پاک نکند.
 */
final class OnboardingController extends Controller
{
    private const DRAFT = 'onboarding_draft';

    public function show(Request $request): Response
    {
        if (!empty(Auth::memberships()) && $request->path !== '/onboarding/new') {
            return $this->redirect('/salons');
        }

        return $this->page('layouts.auth', 'auth.onboarding', [
            'title' => 'ساخت سالن',
            'wideCard' => true,
            'draft' => Session::get(self::DRAFT, []),
        ]);
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $audience = (string) $request->input('audience', '');
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'نام سالن را وارد کنید.';
        }
        if (!array_key_exists($audience, Audience::options())) {
            $errors['audience'] = 'نوع سالن را انتخاب کنید؛ خدمات پیشنهادی و واژه‌ها بر همین اساس تنظیم می‌شوند.';
        }
        $phone = trim((string) $request->input('phone', ''));
        if ($errors !== []) {
            return $this->invalid($request, $errors, '/onboarding');
        }

        Session::put(self::DRAFT, [
            'name' => mb_substr($name, 0, 150),
            'audience' => $audience,
            'city' => mb_substr(trim((string) $request->input('city', '')), 0, 80),
            'address' => mb_substr(trim((string) $request->input('address', '')), 0, 255),
            'phone' => preg_replace('/[^\d+]/', '', \App\Support\Jalali::fromPersianDigits($phone)) ?: '',
            'owner_works' => $request->input('owner_works') === '1' ? '1' : '',
            'owner_name' => mb_substr(trim((string) $request->input('owner_name', '')), 0, 120),
        ]);

        return $this->redirect('/onboarding/services');
    }

    public function services(Request $request): Response
    {
        $draft = Session::get(self::DRAFT);
        if (!is_array($draft) || empty($draft['name'])) {
            return $this->redirect('/onboarding');
        }

        if ($request->method !== 'POST') {
            return $this->page('layouts.auth', 'auth.onboarding-services', [
                'title' => 'خدمات سالن',
                'wideCard' => true,
                'draft' => $draft,
                'templates' => CatalogTemplates::for($draft['audience']),
            ]);
        }

        $selected = [];
        if ($request->input('skip') !== '1') {
            $prices = (array) $request->input('price', []);
            foreach ((array) $request->input('pick', []) as $key) {
                if (!is_string($key) || !preg_match('/^\d+:\d+$/', $key)) {
                    continue;
                }
                $toman = int_input($prices[$key] ?? null);
                $selected[] = ['template' => $key, 'price' => $toman !== null && $toman > 0 ? $toman * 10 : null];
            }
        }

        $ownerName = $draft['owner_works'] === '1'
            ? ($draft['owner_name'] !== '' ? $draft['owner_name'] : (Auth::user()['name'] ?? 'صاحب سالن'))
            : null;

        try {
            $salonId = (new SalonSetupService())->create([
                'name' => $draft['name'],
                'audience' => $draft['audience'],
                'city' => $draft['city'],
                'address' => $draft['address'],
                'phone' => $draft['phone'],
            ], (int) Auth::id(), $selected, $ownerName);
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/onboarding');
        }

        Session::forget(self::DRAFT);
        Auth::setSalon($salonId);
        $inactive = count(array_filter($selected, static fn ($s) => $s['price'] === null));

        return $this->withSuccess(
            'سالن ساخته شد. ' . ($ownerName === null ? 'حالا افراد تیم را اضافه کنید.' : 'افراد دیگر تیم را هم اضافه کنید.')
            . ($inactive > 0 ? ' ' . fa_num($inactive) . ' خدمت بدون قیمت غیرفعال مانده است.' : ''),
            '/panel/staff'
        );
    }
}
