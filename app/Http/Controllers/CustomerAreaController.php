<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Booking\BookingService;
use App\Domain\Customer\CustomerAuth;
use App\Domain\Identity\OtpService;
use App\Support\IranMobile;

/**
 * «نوبت‌های من» — جایی که مشتری همهٔ نوبت‌هایش را یک‌جا می‌بیند.
 *
 * کارت نوبتِ تک‌لینکی (/q/{token}) سر جایش می‌ماند، چون کسی که تازه
 * رزرو کرده نباید برای دیدن همان نوبت وارد شود. این صفحه برای دفعه‌های
 * بعد است: «نوبت هفتهٔ پیشم ساعت چند بود؟»
 */
final class CustomerAreaController extends Controller
{
    private const OTP_PURPOSE = 'customer';

    public function index(Request $request): Response
    {
        $phone = CustomerAuth::phone();
        if ($phone === null) {
            return $this->redirect('/me/login');
        }

        return $this->page('layouts.customer', 'customer.index', [
            'title' => 'نوبت‌های من',
            'phone' => $phone,
            'upcoming' => CustomerAuth::appointments($phone, true),
            'past' => CustomerAuth::appointments($phone, false),
        ]);
    }

    public function login(Request $request): Response
    {
        if (CustomerAuth::check()) {
            return $this->redirect('/me');
        }

        if ($request->method === 'POST') {
            $phone = IranMobile::tryParse((string) $request->input('phone', ''));
            if ($phone === null) {
                return $this->withError('شمارهٔ موبایل نامعتبر است.', '/me/login');
            }

            $result = (new OtpService())->request($phone, self::OTP_PURPOSE);
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'خطا در ارسال کد', '/me/login');
            }

            Session::put('me_otp_phone', $phone->e164);

            return $this->redirect('/me/verify');
        }

        return $this->page('layouts.customer', 'customer.login', [
            'title' => 'ورود',
            'error' => Session::flash('error'),
        ]);
    }

    public function verify(Request $request): Response
    {
        $phone = Session::get('me_otp_phone');
        if (!is_string($phone) || $phone === '') {
            return $this->redirect('/me/login');
        }

        if ($request->method === 'POST') {
            $result = (new OtpService())->verify(
                IranMobile::parse($phone),
                (string) $request->input('code', ''),
                self::OTP_PURPOSE
            );

            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/me/verify');
            }

            CustomerAuth::login($phone);
            Session::forget('me_otp_phone');

            return $this->redirect('/me');
        }

        return $this->page('layouts.customer', 'customer.verify', [
            'title' => 'تأیید کد',
            'phone' => $phone,
            'error' => Session::flash('error'),
            'debugLine' => OtpService::devHint($phone),
        ]);
    }

    public function logout(Request $request): Response
    {
        CustomerAuth::logout();

        return $this->redirect('/me/login');
    }

    /**
     * لغو نوبت از داخل «نوبت‌های من».
     *
     * مالکیت با شمارهٔ نشست سنجیده می‌شود، نه با شناسه‌ای که در فرم
     * آمده — وگرنه هر کسی با عوض کردن یک عدد، نوبت دیگری را لغو
     * می‌کرد.
     */
    public function cancel(Request $request): Response
    {
        $phone = CustomerAuth::phone();
        if ($phone === null) {
            return $this->redirect('/me/login');
        }

        $id = (int) $request->param('id');
        $mine = null;
        foreach (CustomerAuth::appointments($phone, true) as $row) {
            if ((int) $row['id'] === $id) {
                $mine = $row;
                break;
            }
        }

        if ($mine === null) {
            return $this->withError('این نوبت در فهرست شما نیست.', '/me');
        }

        (new BookingService())->cancelByToken((string) $mine['public_token'], 'لغو توسط مشتری');

        return $this->withSuccess('نوبت لغو شد.', '/me');
    }
}
