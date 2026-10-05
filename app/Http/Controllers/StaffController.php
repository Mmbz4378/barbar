<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Identity\PasswordAuth;
use App\Domain\Identity\UserRepository;
use App\Domain\System\AuditLog;
use App\Domain\Salon\WorkingHoursRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\Clock;
use App\Support\IranMobile;
use App\Support\StaffColor;

/**
 * تیم سالن: کسانی که خدمت می‌دهند (staff) و کسانی که به پنل دسترسی دارند
 * (salon_user). یک نفر می‌تواند هر دو باشد — صاحب آرایشگاهی که خودش هم
 * کار می‌کند — یا فقط یکی: پذیرشی که خدمت نمی‌دهد، یا آرایشگری که
 * گوشی ندارد و فقط در فهرست نوبت است.
 *
 * قواعد نقش: نقش «صاحب» از اینجا عوض نمی‌شود؛ فقط صاحب سالن «مدیر»
 * تعیین یا عوض می‌کند؛ هیچ‌کس دسترسی خودش را برنمی‌دارد.
 */
final class StaffController extends Controller
{
    private const ROLES = ['staff', 'reception', 'manager'];

    public function index(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $staff = (new StaffRepository())->withAccounts($salonId);
        $linked = array_filter(array_map(static fn ($s) => $s['user_id'] !== null ? (int) $s['user_id'] : null, $staff));

        $members = DB::select(
            'SELECT su.user_id, su.role, su.is_active, u.name, u.phone, u.last_login_at
               FROM salon_user su JOIN users u ON u.id = su.user_id
              WHERE su.salon_id = ? ORDER BY su.is_active DESC, FIELD(su.role, \'owner\', \'manager\', \'reception\', \'staff\'), u.name',
            [$salonId]
        );
        $accessOnly = array_values(array_filter($members, static fn ($m) => !in_array((int) $m['user_id'], $linked, true)));

        return $this->page('layouts.panel', 'panel.staff.index', [
            'title' => 'تیم و دسترسی‌ها',
            'staff' => $staff,
            'accessOnly' => $accessOnly,
            'isOwner' => Auth::hasRole('owner') || Auth::isImpersonating(),
            'serviceCount' => count((new ServiceRepository())->all($salonId, true)),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request): Response
    {
        $staff = (new StaffRepository())->find((int) Auth::salonId(), (int) $request->param('id'));
        if ($staff === null) {
            return $this->withError('یافت نشد.', '/panel/staff');
        }

        return $this->form($staff);
    }

    private function form(?array $staff): Response
    {
        $salonId = (int) Auth::salonId();
        $services = new ServiceRepository();
        $offered = [];
        if ($staff !== null) {
            foreach ($services->all($salonId) as $service) {
                $offered[(int) $service['id']] = $services->effective($salonId, (int) $staff['id'], (int) $service['id'])['offered'];
            }
        }
        $account = $staff !== null && $staff['user_id'] !== null
            ? DB::selectOne('SELECT su.role, su.is_active, u.phone FROM users u LEFT JOIN salon_user su ON su.user_id = u.id AND su.salon_id = ? WHERE u.id = ?', [$salonId, $staff['user_id']])
            : null;

        return $this->page('layouts.panel', 'panel.staff.form', [
            'title' => $staff ? 'ویرایش ' . $staff['name'] : 'افزودن به تیم',
            'staff' => $staff,
            'account' => $account,
            'groups' => $services->grouped($salonId),
            'offered' => $offered,
            'hours' => $staff !== null ? (new StaffRepository())->hours($salonId, (int) $staff['id']) : [],
            'salonHours' => (new WorkingHoursRepository())->salonDefaults($salonId),
            'isOwner' => Auth::hasRole('owner') || Auth::isImpersonating(),
            'usedColors' => array_column((new StaffRepository())->all($salonId), 'color'),
        ]);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, null);
    }

    public function update(Request $request): Response
    {
        $staff = (new StaffRepository())->find((int) Auth::salonId(), (int) $request->param('id'));
        if ($staff === null) {
            return $this->notFound();
        }

        return $this->save($request, $staff);
    }

    private function save(Request $request, ?array $staff): Response
    {
        $salonId = (int) Auth::salonId();
        $repo = new StaffRepository();
        $back = $staff === null ? '/panel/staff/create' : '/panel/staff/' . $staff['id'] . '/edit';
        $errors = [];

        $name = mb_substr(trim((string) $request->input('name', '')), 0, 120);
        if ($name === '') {
            $errors['name'] = 'نام را وارد کنید.';
        }
        $phoneRaw = trim((string) $request->input('phone', ''));
        $phone = $phoneRaw !== '' ? IranMobile::tryParse($phoneRaw) : null;
        if ($phoneRaw !== '' && $phone === null) {
            $errors['phone'] = 'شمارهٔ موبایل معتبر نیست.';
        }
        $role = (string) $request->input('role', 'staff');
        if (!in_array($role, self::ROLES, true)) {
            $role = 'staff';
        }
        $commission = trim((string) $request->input('commission_percent', ''));
        $commissionValue = $commission === '' ? null : (float) \App\Support\Jalali::fromPersianDigits($commission);
        if ($commissionValue !== null && ($commissionValue < 0 || $commissionValue > 100)) {
            $errors['commission_percent'] = 'درصد باید بین ۰ تا ۱۰۰ باشد.';
        }
        if ($role === 'manager' && !Auth::hasRole('owner') && !Auth::isImpersonating()) {
            $errors['role'] = 'فقط صاحب سالن می‌تواند نقش «مدیر» بدهد.';
        }
        if ($errors !== []) {
            return $this->invalid($request, $errors, $back);
        }

        $userId = $staff['user_id'] ?? null;
        if ($phone !== null) {
            $user = (new UserRepository())->findOrCreate($phone, $name);
            $userId = (int) $user['id'];
            $conflict = DB::selectOne('SELECT id FROM staff WHERE salon_id = ? AND user_id = ? AND id <> ?', [$salonId, $userId, (int) ($staff['id'] ?? 0)]);
            if ($conflict !== null) {
                return $this->invalid($request, ['phone' => 'این شماره به پروفایل دیگری در همین سالن وصل است.'], $back);
            }
            $roleError = $this->grantAccess($salonId, $userId, $role);
            if ($roleError !== null) {
                return $this->invalid($request, ['role' => $roleError], $back);
            }
        } elseif ($phoneRaw === '' && $staff !== null && $staff['user_id'] !== null && $request->input('unlink') === '1') {
            $this->revokeIfOnlyStaff($salonId, (int) $staff['user_id']);
            $userId = null;
        }

        $data = [
            'name' => $name,
            'title' => mb_substr(trim((string) $request->input('title', '')), 0, 80) ?: null,
            'bio' => mb_substr(trim((string) $request->input('bio', '')), 0, 300) ?: null,
            'phone' => $phone?->e164 ?? ($staff['phone'] ?? null),
            'user_id' => $userId,
            'commission_percent' => $commissionValue,
            'color' => StaffColor::resolve((string) $request->input('color', '')),
            'accepts_online' => $request->input('accepts_online') === '1' ? 1 : 0,
        ];

        $id = $staff === null ? $repo->create($salonId, $data) : (int) $staff['id'];
        if ($staff !== null) {
            $repo->update($salonId, $id, $data);
        }

        // مهارت‌ها: هر خدمتی که تیک نخورده «انجام نمی‌دهد»
        if ($request->input('skills_present') === '1') {
            $picked = array_map('intval', (array) $request->input('skills', []));
            $services = new ServiceRepository();
            foreach ($services->all($salonId) as $service) {
                $should = in_array((int) $service['id'], $picked, true);
                if ($services->effective($salonId, $id, (int) $service['id'])['offered'] !== $should) {
                    $services->setOffered($salonId, $id, (int) $service['id'], $should);
                }
            }
        }

        // ساعت کاری اختصاصی
        $days = [];
        foreach ((array) $request->input('hours', []) as $weekday => $day) {
            if (!is_array($day) || (int) $weekday < 0 || (int) $weekday > 6) {
                continue;
            }
            $mode = in_array($day['mode'] ?? 'salon', ['salon', 'custom', 'off'], true) ? $day['mode'] : 'salon';
            $days[(int) $weekday] = [
                'mode' => $mode,
                'opens' => Clock::fromParts($day['opens_h'] ?? null, $day['opens_m'] ?? null),
                'closes' => Clock::fromParts($day['closes_h'] ?? null, $day['closes_m'] ?? null),
            ];
        }
        if ($days !== []) {
            $repo->setHours($salonId, $id, $days);
        }

        return $this->withSuccess($staff === null ? $name . ' به تیم اضافه شد.' : 'تغییرات ذخیره شد.', '/panel/staff/' . $id . '/edit');
    }

    public function toggle(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $repo = new StaffRepository();
        $staff = $repo->find($salonId, $id);
        if ($staff === null) {
            return $this->redirect('/panel/staff');
        }

        $activate = !(bool) $staff['is_active'];
        if (!$activate) {
            $future = $repo->futureBookings($salonId, $id);
            if ($future > 0 && $request->input('force') !== '1') {
                return $this->withError(
                    $staff['name'] . ' ' . fa_num($future) . ' نوبت پیش رو دارد. اول آن‌ها را لغو یا جابه‌جا کنید، یا دوباره «غیرفعال کن» را بزنید و تأیید کنید.',
                    '/panel/bookings?staff=' . $id
                );
            }
        }

        $repo->setActive($salonId, $id, $activate);
        // کسی که فقط «آرایشگر» بوده و رفته، دیگر به پنل دسترسی ندارد
        if ($staff['user_id'] !== null) {
            DB::update('salon_user', ['is_active' => $activate ? 1 : 0], "salon_id = :s AND user_id = :u AND role = 'staff'", ['s' => $salonId, 'u' => $staff['user_id']]);
        }

        return $this->withSuccess($activate ? $staff['name'] . ' دوباره فعال شد.' : $staff['name'] . ' غیرفعال شد و در رزرو نمایش داده نمی‌شود.', '/panel/staff');
    }

    /** افزودن عضوِ فقط-دسترسی (پذیرش، مدیر). */
    public function addMember(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $phone = IranMobile::tryParse((string) $request->input('phone', ''));
        $role = (string) $request->input('role', 'reception');
        if ($phone === null) {
            return $this->invalid($request, ['member_phone' => 'شمارهٔ موبایل معتبر نیست.'], '/panel/staff#members');
        }
        if (!in_array($role, ['reception', 'manager'], true)) {
            $role = 'reception';
        }
        // نام کاربری و رمز اولیه (اختیاری) — فقط برای کسی که هنوز حساب پنل ندارد
        $username = PasswordAuth::normalizeUsername((string) $request->input('username', ''));
        $initial = (string) $request->input('initial_password', '');
        $existing = (new UserRepository())->findByPhone($phone);
        $credentials = $username !== '' || $initial !== '';
        if ($credentials) {
            $fieldErrors = [];
            if ($existing !== null && (!empty($existing['password_hash']) || UserRepository::hasPanelAccess((int) $existing['id']))) {
                $fieldErrors['member_username'] = 'این شماره از قبل حساب پنل دارد و با نام کاربری و رمز خودش وارد می‌شود؛ این دو را خالی بگذارید.';
            } else {
                if ($username !== '') {
                    $fieldErrors['member_username'] = PasswordAuth::usernameError($username)
                        ?? (PasswordAuth::usernameTaken($username, $existing !== null ? (int) $existing['id'] : null) ? 'این نام کاربری مال کس دیگری است.' : null);
                }
                $fieldErrors['member_password'] = $initial === '' ? 'رمز اولیه را هم بنویسید.' : PasswordAuth::policyError($initial, $phone->e164, $username ?: null);
            }
            $fieldErrors = array_filter($fieldErrors);
            if ($fieldErrors !== []) {
                return $this->invalid($request, $fieldErrors, '/panel/staff#members');
            }
        }

        $user = (new UserRepository())->findOrCreate($phone, mb_substr(trim((string) $request->input('name', '')), 0, 120) ?: null);
        $error = $this->grantAccess($salonId, (int) $user['id'], $role);
        if ($error !== null) {
            return $this->withError($error, '/panel/staff#members');
        }
        if ($credentials) {
            DB::update('users', ['username' => $username !== '' ? $username : null], 'id = :id', ['id' => $user['id']]);
            PasswordAuth::setPassword((int) $user['id'], $initial, true);
            AuditLog::record($salonId, 'member.credentials_set', 'user', (int) $user['id']);

            return $this->withSuccess('دسترسی داده شد. با ' . ($username !== '' ? 'نام کاربری «' . $username . '»' : 'شمارهٔ موبایل') . ' و رمز اولیه وارد شود؛ در اولین ورود رمز خودش را می‌گذارد.', '/panel/staff#members');
        }

        return $this->withSuccess('دسترسی داده شد. با همین شماره و کد پیامکی وارد شود.', '/panel/staff#members');
    }

    /** تغییر نقش یا برداشتن دسترسی. */
    public function updateMember(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $userId = (int) $request->param('user');
        $member = DB::selectOne('SELECT * FROM salon_user WHERE salon_id = ? AND user_id = ?', [$salonId, $userId]);
        if ($member === null) {
            return $this->notFound();
        }
        if ($member['role'] === 'owner') {
            return $this->withError('نقش صاحب سالن از اینجا تغییر نمی‌کند.', '/panel/staff#members');
        }
        if ($userId === Auth::id()) {
            return $this->withError('دسترسی خودتان را نمی‌توانید تغییر دهید.', '/panel/staff#members');
        }
        if ($member['role'] === 'manager' && !Auth::hasRole('owner') && !Auth::isImpersonating()) {
            return $this->withError('فقط صاحب سالن می‌تواند دسترسی مدیر را تغییر دهد.', '/panel/staff#members');
        }

        if ($request->input('action') === 'remove') {
            DB::update('salon_user', ['is_active' => 0], 'salon_id = :s AND user_id = :u', ['s' => $salonId, 'u' => $userId]);

            return $this->withSuccess('دسترسی برداشته شد.', '/panel/staff#members');
        }

        $role = (string) $request->input('role', $member['role']);
        $error = $this->grantAccess($salonId, $userId, in_array($role, self::ROLES, true) ? $role : 'staff');

        return $error !== null ? $this->withError($error, '/panel/staff#members') : $this->withSuccess('نقش به‌روز شد.', '/panel/staff#members');
    }

    /** @return string|null پیام خطا */
    private function grantAccess(int $salonId, int $userId, string $role): ?string
    {
        if ($role === 'manager' && !Auth::hasRole('owner') && !Auth::isImpersonating()) {
            return 'فقط صاحب سالن می‌تواند نقش «مدیر» بدهد.';
        }
        $existing = DB::selectOne('SELECT * FROM salon_user WHERE salon_id = ? AND user_id = ?', [$salonId, $userId]);
        if ($existing === null) {
            DB::insert('salon_user', ['salon_id' => $salonId, 'user_id' => $userId, 'role' => $role]);

            return null;
        }
        if ($existing['role'] === 'owner') {
            // صاحب سالن که خودش هم کار می‌کند صاحب می‌ماند
            DB::update('salon_user', ['is_active' => 1], 'id = :id', ['id' => $existing['id']]);

            return null;
        }
        if ($userId === Auth::id() && $existing['role'] !== $role) {
            return 'نقش خودتان را نمی‌توانید تغییر دهید.';
        }
        DB::update('salon_user', ['role' => $role, 'is_active' => 1], 'id = :id', ['id' => $existing['id']]);

        return null;
    }

    private function revokeIfOnlyStaff(int $salonId, int $userId): void
    {
        DB::update('salon_user', ['is_active' => 0], "salon_id = :s AND user_id = :u AND role = 'staff'", ['s' => $salonId, 'u' => $userId]);
    }
}
