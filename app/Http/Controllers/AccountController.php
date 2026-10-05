<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Identity\LoginEvents;
use App\Domain\Identity\PasswordAuth;
use App\Domain\System\AuditLog;
use App\Domain\System\SiteSettings;

/**
 * «حساب من» — برای هر کاربر پنل (مدیر کل، صاحب سالن، کارکنان).
 *
 * نام، نام کاربری، رمز، تاریخچهٔ ورود و بستن نشست‌های دیگر. موبایل اینجا
 * عوض نمی‌شود: هویت کاربر و راه بازیابی رمز است؛ تغییرش با مدیر کل است.
 */
final class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        return $this->render($request->path === '/account/password');
    }

    public function updateProfile(Request $request): Response
    {
        $user = Auth::user();
        $name = mb_substr(trim((string) $request->input('name', '')), 0, 120);
        $usernameRaw = (string) $request->input('username', '');
        $username = PasswordAuth::normalizeUsername($usernameRaw);
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'نامتان را بنویسید.';
        }
        if ($username !== '') {
            $errors['username'] = PasswordAuth::usernameError($username)
                ?? (PasswordAuth::usernameTaken($username, (int) $user['id']) ? 'این نام کاربری مال کس دیگری است.' : null);
        }
        $errors = array_filter($errors);
        if ($errors !== []) {
            return $this->invalid($request, $errors, '/account');
        }

        $before = (string) ($user['username'] ?? '');
        DB::update('users', ['name' => $name, 'username' => $username !== '' ? $username : null], 'id = :id', ['id' => $user['id']]);
        if ($before !== $username) {
            AuditLog::record(null, 'account.username_changed', 'user', (int) $user['id'], ['from' => $before, 'to' => $username]);
        }

        return $this->withSuccess('مشخصات ذخیره شد.', '/account');
    }

    public function updatePassword(Request $request): Response
    {
        $user = Auth::user();
        $forced = Auth::mustChangePassword();
        $back = $forced ? '/account/password' : '/account#password';
        $hasPassword = !empty($user['password_hash']);
        $password = (string) $request->input('password', '');

        // رمز فعلی را می‌خواهیم تا نشستِ رهاشده روی دستگاه دیگر نتواند رمز
        // را عوض کند؛ جز وقتی هنوز رمزی نیست یا مدیر همین الان رمز موقت داده
        if ($hasPassword && !$forced && !password_verify((string) $request->input('current_password', ''), (string) $user['password_hash'])) {
            return $this->invalid($request, ['current_password' => 'رمز فعلی درست نیست.'], $back);
        }
        $error = PasswordAuth::policyError($password, (string) $user['phone'], $user['username'] ?? null);
        if ($error === null && $password !== (string) $request->input('password_confirm', '')) {
            $error = 'تکرار رمز با خودِ رمز یکی نیست.';
        }
        if ($error === null && $hasPassword && password_verify($password, (string) $user['password_hash'])) {
            $error = 'رمز تازه با رمز فعلی یکی است.';
        }
        if ($error !== null) {
            return $this->invalid($request, ['password' => $error], $back);
        }

        PasswordAuth::setPassword((int) $user['id'], $password);
        Auth::refreshVersion();
        AuditLog::record(null, $hasPassword ? 'account.password_changed' : 'account.password_set', 'user', (int) $user['id']);

        if ($forced) {
            \App\Core\Session::flash('success', 'رمز تازه ذخیره شد.');

            return $this->redirect(Auth::isPlatformAdmin() ? '/platform' : '/panel');
        }

        return $this->withSuccess('رمز ذخیره شد. اگر روی دستگاه دیگری وارد بودید، آنجا خارج شدید.', '/account');
    }

    /** خروج از همهٔ دستگاه‌ها جز همین یکی. */
    public function logoutOthers(Request $request): Response
    {
        DB::statement('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?', [Auth::id()]);
        Auth::refreshVersion();
        AuditLog::record(null, 'account.sessions_revoked', 'user', Auth::id());

        return $this->withSuccess('از همهٔ دستگاه‌های دیگر خارج شدید.', '/account');
    }

    private function render(bool $forced): Response
    {
        $user = Auth::user();
        $data = [
            'title' => 'حساب من',
            'user' => $user,
            'forced' => $forced && Auth::mustChangePassword(),
            'hasPassword' => !empty($user['password_hash']),
            'events' => LoginEvents::forUser((int) $user['id'], 10),
            'minLength' => SiteSettings::minPasswordLength(),
            'backUrl' => Auth::memberships() !== [] ? '/panel' : (Auth::isPlatformAdmin() ? '/platform' : null),
        ];

        if (Auth::isPlatformAdmin()) {
            return $this->page('layouts.platform', 'account.show', $data);
        }

        return $this->page('layouts.auth', 'account.show', $data + ['wideCard' => true]);
    }
}
