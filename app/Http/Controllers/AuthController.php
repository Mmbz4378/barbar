<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Identity\LoginEvents;
use App\Domain\Identity\LoginLinkService;
use App\Domain\Identity\OtpService;
use App\Domain\Identity\PasswordAuth;
use App\Domain\Identity\UserRepository;
use App\Domain\System\SiteSettings;
use App\Support\IranMobile;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (Auth::check()) {
            return $this->afterLogin();
        }

        $password = SiteSettings::passwordLoginEnabled();
        $otp = SiteSettings::otpLoginEnabled();
        $method = (string) $request->query('method', '');
        if (!in_array($method, ['password', 'otp'], true)) {
            $method = $password ? 'password' : 'otp';
        }
        if (($method === 'password' && !$password) || ($method === 'otp' && !$otp)) {
            $method = $password ? 'password' : 'otp';
        }

        return $this->page('layouts.auth', 'auth.login', [
            'title' => 'ورود',
            'method' => $method,
            'passwordEnabled' => $password,
            'otpEnabled' => $otp,
            'registrationOpen' => SiteSettings::registrationMode() !== SiteSettings::REG_CLOSED,
        ]);
    }

    /** ورود با نام کاربری (یا موبایل) و رمز. */
    public function passwordLogin(Request $request): Response
    {
        if (!SiteSettings::passwordLoginEnabled()) {
            return $this->redirect('/login?method=otp');
        }
        $identifier = (string) $request->input('identifier', '');
        $password = (string) $request->input('password', '');
        if (trim($identifier) === '' || $password === '') {
            return $this->invalid($request, array_filter([
                'identifier' => trim($identifier) === '' ? 'نام کاربری یا شمارهٔ موبایل را وارد کنید.' : null,
                'password' => $password === '' ? 'رمز را وارد کنید.' : null,
            ]), '/login?method=password');
        }

        $result = (new PasswordAuth())->attempt($identifier, $password);
        if (!$result['ok']) {
            Session::flash('_old', ['identifier' => mb_substr(trim($identifier), 0, 80)]);

            return $this->withError((string) $result['error'], '/login?method=password');
        }

        Auth::login((int) $result['user']['id']);

        return $this->afterLogin();
    }

    public function sendOtp(Request $request): Response
    {
        if (!SiteSettings::otpLoginEnabled()) {
            return $this->redirect('/login?method=password');
        }
        $raw = (string) $request->input('phone', '');
        $phone = IranMobile::tryParse($raw);

        if ($phone === null) {
            return $this->invalid($request, ['phone' => 'شمارهٔ موبایل را کامل وارد کنید؛ مثل ۰۹۱۲۳۴۵۶۷۸۹.'], '/login?method=otp', 'شمارهٔ موبایل نامعتبر است.');
        }

        // ثبت‌نام بسته: به شماره‌ای که به هیچ پنلی دسترسی ندارد کد نمی‌فرستیم؛
        // نه حسابی ساخته می‌شود و نه اعتبار پیامک برای ورودِ بی‌حاصل خرج می‌شود.
        $access = $this->otpAccessError($phone);
        if ($access !== null) {
            LoginEvents::record(null, $phone->e164, LoginEvents::OTP, false, 'no_access');
            Session::flash('_old', ['phone' => $raw]);

            return $this->withError($access, '/login?method=otp');
        }

        $otp = new OtpService();
        $result = $otp->request($phone);

        if (!$result['ok']) {
            return $this->withError($result['error'] ?? 'خطا در ارسال کد', '/login?method=otp');
        }

        Session::put('otp_phone', $phone->e164);

        return $this->redirect('/login/verify');
    }

    public function showVerify(Request $request): Response
    {
        $phone = Session::get('otp_phone');
        if ($phone === null) {
            return $this->redirect('/login');
        }

        return $this->page('layouts.auth', 'auth.verify', [
            'phone' => $phone,
            'debugLine' => OtpService::devHint($phone),
            'title' => 'تأیید کد',
        ]);
    }

    public function verify(Request $request): Response
    {
        $phoneRaw = Session::get('otp_phone');
        if ($phoneRaw === null) {
            return $this->redirect('/login');
        }
        $phone = IranMobile::parse($phoneRaw);
        $code = (string) $request->input('code', '');

        $otp = new OtpService();
        $result = $otp->verify($phone, $code);

        if (!$result['ok']) {
            LoginEvents::record(null, $phone->e164, LoginEvents::OTP, false, 'bad_code');

            return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/login/verify');
        }

        // شرط دسترسی دوباره، چون بین ارسال و تأیید ممکن است عوض شده باشد
        $access = $this->otpAccessError($phone);
        if ($access !== null) {
            Session::forget('otp_phone');
            LoginEvents::record(null, $phone->e164, LoginEvents::OTP, false, 'no_access');

            return $this->withError($access, '/login?method=otp');
        }

        $user = (new UserRepository())->findOrCreate($phone);
        Auth::login((int) $user['id']);
        Session::forget('otp_phone');
        LoginEvents::record((int) $user['id'], $phone->e164, LoginEvents::OTP, true);

        return $this->afterLogin();
    }

    /**
     * چرا این شماره نمی‌تواند با کد وارد شود؟ (null یعنی می‌تواند)
     *
     * مسدودشده هرگز. در ثبت‌نام بسته، فقط کسی که مدیر کل است یا در سالنی
     * عضویت فعال دارد؛ مشتری‌ها هم در users ردیف دارند ولی پنلی ندارند.
     */
    private function otpAccessError(IranMobile $phone): ?string
    {
        $user = (new UserRepository())->findByPhone($phone);
        if ($user !== null && (int) ($user['is_active'] ?? 1) !== 1) {
            return 'این حساب غیرفعال شده است. با مدیر سامانه تماس بگیرید.';
        }
        if (SiteSettings::registrationMode() !== SiteSettings::REG_CLOSED) {
            return null;
        }
        if ($user === null || !UserRepository::hasPanelAccess((int) $user['id'])) {
            return 'این شماره به پنل هیچ سالنی دسترسی ندارد. اگر صاحب یا کارمند سالن هستید، از مدیر سامانه یا صاحب سالن بخواهید شما را اضافه کند.';
        }

        return null;
    }

    // ─── فراموشی رمز: کد پیامکی، بعد رمز تازه ───────────────────────────

    public function showForgot(Request $request): Response
    {
        if (!SiteSettings::otpLoginEnabled()) {
            return $this->withError('بازیابی رمز با پیامک خاموش است. از مدیر سامانه بخواهید رمزتان را بازنشانی کند.', '/login');
        }

        return $this->page('layouts.auth', 'auth.forgot', ['title' => 'فراموشی رمز']);
    }

    public function sendReset(Request $request): Response
    {
        if (!SiteSettings::otpLoginEnabled()) {
            return $this->redirect('/login');
        }
        $raw = (string) $request->input('phone', '');
        $phone = IranMobile::tryParse($raw);
        if ($phone === null) {
            return $this->invalid($request, ['phone' => 'شمارهٔ موبایل را کامل وارد کنید؛ مثل ۰۹۱۲۳۴۵۶۷۸۹.'], '/login/forgot');
        }
        $user = (new UserRepository())->findByPhone($phone);
        if ($user === null || (int) ($user['is_active'] ?? 1) !== 1 || !UserRepository::hasPanelAccess((int) $user['id'])) {
            Session::flash('_old', ['phone' => $raw]);

            return $this->withError('این شماره حساب فعالی در پنل ندارد.', '/login/forgot');
        }

        $result = (new OtpService())->request($phone, 'reset');
        if (!$result['ok']) {
            return $this->withError($result['error'] ?? 'خطا در ارسال کد', '/login/forgot');
        }
        Session::put('reset_phone', $phone->e164);

        return $this->redirect('/login/reset');
    }

    public function showReset(Request $request): Response
    {
        $phone = Session::get('reset_phone');
        if ($phone === null) {
            return $this->redirect('/login/forgot');
        }

        return $this->page('layouts.auth', 'auth.reset', [
            'title' => 'رمز تازه',
            'phone' => $phone,
            'debugLine' => OtpService::devHint($phone),
            'minLength' => SiteSettings::minPasswordLength(),
        ]);
    }

    public function reset(Request $request): Response
    {
        $phoneRaw = Session::get('reset_phone');
        if ($phoneRaw === null) {
            return $this->redirect('/login/forgot');
        }
        $phone = IranMobile::parse($phoneRaw);
        $user = (new UserRepository())->findByPhone($phone);
        if ($user === null) {
            Session::forget('reset_phone');

            return $this->redirect('/login/forgot');
        }

        $password = (string) $request->input('password', '');
        $error = PasswordAuth::policyError($password, $phone->e164, $user['username'] ?? null);
        if ($error === null && $password !== (string) $request->input('password_confirm', '')) {
            $error = 'تکرار رمز با خودِ رمز یکی نیست.';
        }
        if ($error !== null) {
            return $this->invalid($request, ['password' => $error], '/login/reset');
        }

        // کد را فقط وقتی مصرف می‌کنیم که رمز پذیرفتنی است؛ وگرنه کاربر برای
        // یک اشتباه تایپی باید کد تازه بگیرد
        $result = (new OtpService())->verify($phone, (string) $request->input('code', ''), 'reset');
        if (!$result['ok']) {
            LoginEvents::record((int) $user['id'], $phone->e164, LoginEvents::RESET, false, 'bad_code');

            return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/login/reset');
        }

        PasswordAuth::setPassword((int) $user['id'], $password);
        Session::forget('reset_phone');
        LoginEvents::record((int) $user['id'], $phone->e164, LoginEvents::RESET, true);
        \App\Domain\System\AuditLog::record(null, 'account.password_reset', 'user', (int) $user['id']);
        Auth::login((int) $user['id']);
        Session::flash('success', 'رمز تازه ذخیره شد و وارد شدید. نشست‌های قبلی روی دستگاه‌های دیگر بسته شدند.');

        return $this->afterLogin();
    }

    /**
     * ورود با لینک یک‌بارمصرف (ت-۳۶).
     *
     * توکن فقط از روی سرور ساخته می‌شود (`php tools/login-link.php`).
     * هیچ مسیری برای **درخواست** لینک از وب وجود ندارد — وگرنه همان
     * چیزی می‌شد که کد پیامکی هست، منهای پیامک.
     */
    public function loginWithLink(Request $request): Response
    {
        $userId = (new LoginLinkService())->consume((string) $request->param('token'));

        if ($userId === null) {
            return $this->withError(
                'این لینک معتبر نیست یا قبلاً استفاده شده. یکی تازه بساز.',
                '/login'
            );
        }
        $user = (new UserRepository())->find($userId);
        if ($user === null || (int) ($user['is_active'] ?? 1) !== 1) {
            LoginEvents::record($userId, null, LoginEvents::LINK, false, 'blocked');

            return $this->withError('این حساب غیرفعال شده است.', '/login');
        }

        Auth::login($userId);
        LoginEvents::record($userId, null, LoginEvents::LINK, true);

        return $this->afterLogin();
    }

    /**
     * مقصد پس از ورود موفق.
     *
     * یک عضویت → مستقیم به پنل. چند تا → صفحهٔ انتخاب سالن. هیچ‌کدام
     * → راه‌اندازی سالن تازه.
     */
    private function afterLogin(): Response
    {
        if (Auth::mustChangePassword()) {
            return $this->redirect('/account/password');
        }

        $memberships = Auth::memberships();

        // مدیر کل بی‌سالن به پنل پلتفرم؛ اگر عضو سالنی هم هست، صفحهٔ انتخاب
        if (Auth::isPlatformAdmin() && $memberships === []) {
            return $this->redirect('/platform');
        }

        if (count($memberships) === 1 && !Auth::isPlatformAdmin()) {
            Auth::setSalon((int) $memberships[0]['salon_id']);

            return $this->redirect('/panel');
        }

        if (count($memberships) >= 1) {
            return $this->redirect('/salons');
        }

        // بی‌سالن: در ثبت‌نام باز سالن می‌سازد؛ در ثبت‌نام بسته صفحهٔ توضیح
        return $this->redirect('/panel');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return $this->redirect('/login');
    }

    public function switchSalon(Request $request): Response
    {
        $salonId = (int) $request->param('id');
        $allowed = array_filter(Auth::memberships(), static fn ($m) => (int) $m['salon_id'] === $salonId);
        if (empty($allowed)) {
            return $this->withError('دسترسی به این سالن ندارید.', '/salons');
        }
        Auth::setSalon($salonId);

        return $this->redirect('/panel');
    }

    public function pickSalon(Request $request): Response
    {
        return $this->page('layouts.auth', 'auth.salons', ['memberships' => Auth::memberships(), 'title' => 'انتخاب سالن']);
    }

}
