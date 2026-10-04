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
 * «نوبت‌های من» — همهٔ نوبت‌های یک شماره در همهٔ سالن‌ها.
 *
 * کارت نوبتِ تک‌لینکی (/q/{token}) سر جایش می‌ماند؛ کسی که تازه رزرو
 * کرده برای دیدن همان نوبت نباید وارد شود.
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

        $booking = new BookingService();
        $upcoming = CustomerAuth::appointments($phone, true);
        foreach ($upcoming as $i => $a) {
            $upcoming[$i]['cancel'] = $booking->customerCancellation($a);
        }

        return $this->page('layouts.customer', 'customer.index', [
            'title' => 'نوبت‌های من',
            'phone' => $phone,
            'upcoming' => $upcoming,
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
                return $this->invalid($request, ['phone' => 'شمارهٔ موبایل را کامل وارد کنید؛ مثل ۰۹۱۲۳۴۵۶۷۸۹.'], '/me/login', 'شمارهٔ موبایل نامعتبر است.');
            }

            $result = (new OtpService())->request($phone, self::OTP_PURPOSE);
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'ارسال کد ممکن نشد. کمی بعد دوباره تلاش کنید.', '/me/login');
            }

            Session::put('me_otp_phone', $phone->e164);

            return $this->redirect('/me/verify');
        }

        return $this->page('layouts.customer', 'customer.login', ['title' => 'ورود به نوبت‌های من']);
    }

    public function verify(Request $request): Response
    {
        $phone = Session::get('me_otp_phone');
        if (!is_string($phone) || $phone === '') {
            return $this->redirect('/me/login');
        }

        if ($request->method === 'POST') {
            $result = (new OtpService())->verify(IranMobile::parse($phone), (string) $request->input('code', ''), self::OTP_PURPOSE);
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/me/verify');
            }

            CustomerAuth::login($phone);
            Session::forget('me_otp_phone');

            return $this->redirect('/me');
        }

        return $this->page('layouts.customer', 'customer.verify', [
            'title' => 'تأیید شماره',
            'phone' => $phone,
            'debugLine' => OtpService::devHint($phone),
        ]);
    }

    public function logout(Request $request): Response
    {
        CustomerAuth::logout();

        return $this->withSuccess('از حساب خارج شدی.', '/me/login');
    }

    /**
     * لغو از «نوبت‌های من». مالکیت با شمارهٔ نشست سنجیده می‌شود، نه با
     * شناسه‌ای که در فرم آمده.
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

        return match ((new BookingService())->cancelByToken((string) $mine['public_token'], 'لغو توسط مشتری')) {
            'cancelled' => $this->withSuccess('نوبت لغو شد.', '/me'),
            'too_late' => $this->withError('مهلت لغو آنلاین این نوبت گذشته است. برای تغییر با سالن تماس بگیرید.', '/me'),
            default => $this->withError('این نوبت دیگر قابل لغو نیست.', '/me'),
        };
    }
}
