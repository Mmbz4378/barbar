<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Catalog\CatalogTemplates;
use App\Domain\Identity\AccountService;
use App\Domain\Identity\PasswordAuth;
use App\Domain\Salon\SalonRepository;
use App\Domain\Salon\SalonSetupService;
use App\Domain\System\AuditLog;
use App\Support\Audience;
use App\Support\Now;
use RuntimeException;

/**
 * مدیریت سالن‌ها در پنل مدیر کل.
 *
 * ساخت سالن همراه با صاحبش (کاربر موجود یا تازه با رمز)، ویرایش مشخصات،
 * طرح و مهلت آزمایشی، شارژ پیامک، اعضای پنل و انتقال مالکیت. حذف دائمی
 * عمداً نیست: نوبت‌ها و پرداخت‌ها با cascade پاک می‌شدند؛ غیرفعال‌سازی
 * همان اثر را بی‌بازگشت‌ناپذیری دارد.
 */
final class PlatformSalonController extends Controller
{
    private const PER_PAGE = 30;

    public const PLANS = ['trial' => 'آزمایشی', 'free' => 'رایگان', 'basic' => 'پایه', 'pro' => 'حرفه‌ای'];

    public const ROLES = ['owner' => 'صاحب سالن', 'manager' => 'مدیر', 'reception' => 'پذیرش', 'staff' => 'کارمند'];

    public const PUBLICATION = ['draft' => 'پیش‌نویس', 'pending' => 'در انتظار بررسی', 'published' => 'منتشرشده', 'rejected' => 'رد شده'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $audience = (string) $request->query('audience', '');
        $plan = (string) $request->query('plan', '');
        $page = max(1, (int) $request->query('page', '1'));

        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(s.name LIKE ? OR s.slug LIKE ? OR s.city LIKE ? OR s.phone LIKE ?
                        OR EXISTS (SELECT 1 FROM salon_user su2 JOIN users u2 ON u2.id = su2.user_id
                                    WHERE su2.salon_id = s.id AND su2.role = \'owner\' AND (u2.name LIKE ? OR u2.phone LIKE ? OR u2.username LIKE ?)))';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        if ($status === 'inactive') {
            $where[] = 's.is_active = 0';
        } elseif ($status === 'active') {
            $where[] = 's.is_active = 1';
        } elseif (array_key_exists($status, self::PUBLICATION)) {
            $where[] = 's.publication_status = ?';
            $params[] = $status;
        } elseif ($status === 'trial_ending') {
            $where[] = "s.plan_code = 'trial' AND s.trial_ends_at IS NOT NULL AND s.trial_ends_at <= ?";
            $params[] = Now::get()->modify('+7 days')->format('Y-m-d H:i:s');
        } elseif ($status === 'low_sms') {
            $where[] = 's.sms_credit < 20';
        }
        if (array_key_exists($audience, Audience::options())) {
            $where[] = 's.audience = ?';
            $params[] = $audience;
        }
        if (array_key_exists($plan, self::PLANS)) {
            $where[] = 's.plan_code = ?';
            $params[] = $plan;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (DB::selectOne("SELECT COUNT(*) AS c FROM salons s WHERE {$whereSql}", $params)['c'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $since = Now::today()->modify('-30 days')->format('Y-m-d 00:00:00');

        $salons = DB::select(
            "SELECT s.id, s.name, s.slug, s.city, s.audience, s.theme, s.plan_code, s.is_active, s.publication_status,
                    s.sms_credit, s.trial_ends_at, s.created_at,
                    (SELECT u.name FROM salon_user su JOIN users u ON u.id = su.user_id
                      WHERE su.salon_id = s.id AND su.role = 'owner' AND su.is_active = 1 ORDER BY su.id LIMIT 1) AS owner_name,
                    (SELECT u.phone FROM salon_user su JOIN users u ON u.id = su.user_id
                      WHERE su.salon_id = s.id AND su.role = 'owner' AND su.is_active = 1 ORDER BY su.id LIMIT 1) AS owner_phone,
                    (SELECT COUNT(*) FROM staff st WHERE st.salon_id = s.id AND st.is_active = 1) AS staff_count,
                    (SELECT COUNT(*) FROM appointments a WHERE a.salon_id = s.id AND a.status = 'completed' AND a.actual_end_at >= ?) AS completed_30d
               FROM salons s WHERE {$whereSql}
              ORDER BY s.created_at DESC, s.id DESC
              LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            array_merge([$since], $params)
        );

        $counts = DB::selectOne(
            "SELECT COUNT(*) AS total, SUM(is_active = 1) AS active, SUM(is_active = 0) AS inactive,
                    SUM(publication_status = 'pending') AS pending, SUM(sms_credit < 20) AS low_sms
               FROM salons"
        ) ?? [];

        return $this->page('layouts.platform', 'platform.salons.index', [
            'title' => 'سالن‌ها',
            'salons' => $salons,
            'counts' => array_map('intval', $counts),
            'filters' => ['q' => $q, 'status' => $status, 'audience' => $audience, 'plan' => $plan],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->page('layouts.platform', 'platform.salons.create', ['title' => 'سالن تازه']);
    }

    public function store(Request $request): Response
    {
        $input = $request->all();
        $errors = [];

        $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 150);
        if ($name === '') {
            $errors['name'] = 'نام سالن را وارد کنید.';
        }
        $audience = (string) ($input['audience'] ?? '');
        if (!array_key_exists($audience, Audience::options())) {
            $errors['audience'] = 'نوع سالن را انتخاب کنید.';
        }
        $plan = array_key_exists((string) ($input['plan_code'] ?? ''), self::PLANS) ? (string) $input['plan_code'] : 'trial';
        $trialDays = max(0, min(3650, (int) int_input($input['trial_days'] ?? '30')));
        $credit = max(0, min(1_000_000, (int) int_input($input['sms_credit'] ?? '200')));

        // صاحب سالن: کاربر موجود (با موبایل یا نام کاربری) یا حساب تازه
        $ownerMode = ($input['owner_mode'] ?? 'new') === 'existing' ? 'existing' : 'new';
        $owner = null;
        $newOwner = null;
        if ($ownerMode === 'existing') {
            $owner = AccountService::findExisting((string) ($input['owner_identifier'] ?? ''));
            if ($owner === null) {
                $errors['owner_identifier'] = 'کاربری با این موبایل یا نام کاربری پیدا نشد.';
            } elseif ((int) ($owner['is_active'] ?? 1) !== 1) {
                $errors['owner_identifier'] = 'این حساب مسدود است.';
            }
        } else {
            [$ownerErrors, $newOwner] = AccountService::validateNew($input, 'owner_');
            $errors += $ownerErrors;
        }

        if ($errors !== []) {
            return $this->invalid($request, $errors, '/platform/salons/new');
        }

        $mustChange = ($input['owner_must_change'] ?? '') === '1';
        $services = [];
        if (($input['seed_services'] ?? '') === '1') {
            // همهٔ خدمات پیشنهادی، بی‌قیمت و غیرفعال تا صاحب سالن قیمت بگذارد
            foreach (CatalogTemplates::for($audience) as $ci => $category) {
                foreach ($category['services'] as $si => $service) {
                    $services[] = ['template' => $ci . ':' . $si, 'price' => null];
                }
            }
        }

        try {
            [$salonId, $ownerId] = DB::transaction(function () use ($input, $name, $audience, $plan, $trialDays, $credit, $owner, $newOwner, $mustChange, $services) {
                $ownerId = $owner !== null ? (int) $owner['id'] : AccountService::create($newOwner, $mustChange);
                $ownerName = $owner !== null ? (string) ($owner['name'] ?? '') : $newOwner['name'];
                $salonId = (new SalonSetupService())->create([
                    'name' => $name,
                    'audience' => $audience,
                    'city' => trim((string) ($input['city'] ?? '')),
                    'address' => trim((string) ($input['address'] ?? '')),
                    'phone' => preg_replace('/[^\d+]/', '', \App\Support\Jalali::fromPersianDigits((string) ($input['phone'] ?? ''))) ?: '',
                ], $ownerId, $services, ($input['owner_works'] ?? '') === '1' ? ($ownerName ?: 'صاحب سالن') : null);

                DB::update('salons', [
                    'plan_code' => $plan,
                    'sms_credit' => $credit,
                    'trial_ends_at' => $plan === 'trial' ? date('Y-m-d H:i:s', strtotime('+' . $trialDays . ' days')) : null,
                ], 'id = :id', ['id' => $salonId]);

                return [$salonId, $ownerId];
            });
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/platform/salons/new');
        }
        SalonRepository::forget($salonId);

        AuditLog::record($salonId, 'salon.created', 'salon', $salonId, ['owner' => $ownerId, 'new_owner' => $newOwner !== null]);
        if ($newOwner !== null) {
            AuditLog::record($salonId, 'user.created', 'user', $ownerId, ['role' => 'owner']);
        }

        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$salonId]);

        // رمز تازه فقط همین یک بار، در همین پاسخ نشان داده می‌شود؛ در نشست
        // یا دیتابیس به‌صورت خوانا نمی‌ماند
        return Response::html(View::renderWithLayout('layouts.platform', 'platform.salons.created', [
            'title' => 'سالن ساخته شد',
            'salon' => $salon,
            'owner' => DB::selectOne('SELECT id, name, phone, username FROM users WHERE id = ?', [$ownerId]),
            'password' => $newOwner !== null ? $newOwner['password'] : null,
            'mustChange' => $mustChange,
            'seeded' => count($services),
        ]));
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$id]);
        if ($salon === null) {
            return $this->notFound('سالن یافت نشد.');
        }

        $since = Now::today()->modify('-30 days')->format('Y-m-d 00:00:00');
        $members = DB::select(
            "SELECT u.id, u.name, u.phone, u.username, u.is_active AS user_active, u.password_hash IS NOT NULL AS has_password,
                    u.last_login_at, su.role, su.is_active
               FROM salon_user su JOIN users u ON u.id = su.user_id
              WHERE su.salon_id = ? ORDER BY su.is_active DESC, FIELD(su.role, 'owner', 'manager', 'reception', 'staff'), u.name",
            [$id]
        );
        $stats = DB::selectOne(
            "SELECT
                (SELECT COUNT(*) FROM staff WHERE salon_id = ? AND is_active = 1) AS staff,
                (SELECT COUNT(*) FROM services WHERE salon_id = ? AND is_active = 1) AS services,
                (SELECT COUNT(*) FROM customers WHERE salon_id = ?) AS customers,
                (SELECT COUNT(*) FROM appointments WHERE salon_id = ? AND status = 'completed' AND actual_end_at >= ?) AS completed,
                (SELECT COUNT(*) FROM appointments WHERE salon_id = ? AND status = 'no_show' AND scheduled_at >= ?) AS no_show,
                (SELECT COUNT(*) FROM appointments WHERE salon_id = ? AND created_at >= ?) AS bookings,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE salon_id = ? AND paid_at >= ?) AS revenue,
                (SELECT COUNT(*) FROM sms_messages WHERE salon_id = ? AND status = 'sent' AND created_at >= ?) AS sms_sent",
            [$id, $id, $id, $id, $since, $id, $since, $id, $since, $id, $since, $id, $since]
        ) ?? [];
        $auditLogs = DB::select(
            "SELECT al.*, u.name AS actor_name, u.phone AS actor_phone FROM audit_logs al
               LEFT JOIN users u ON u.id = al.actor_user_id
              WHERE al.salon_id = ? ORDER BY al.id DESC LIMIT 25",
            [$id]
        );

        return $this->page('layouts.platform', 'platform.salons.show', [
            'title' => $salon['name'],
            'salon' => $salon,
            'members' => $members,
            'stats' => $stats,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function edit(Request $request): Response
    {
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [(int) $request->param('id')]);
        if ($salon === null) {
            return $this->notFound('سالن یافت نشد.');
        }

        return $this->page('layouts.platform', 'platform.salons.edit', ['title' => 'ویرایش ' . $salon['name'], 'salon' => $salon]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$id]);
        if ($salon === null) {
            return $this->notFound('سالن یافت نشد.');
        }
        $back = '/platform/salons/' . $id . '/edit';
        $errors = [];

        $name = mb_substr(trim((string) $request->input('name', '')), 0, 150);
        if ($name === '') {
            $errors['name'] = 'نام سالن را وارد کنید.';
        }
        $slug = strtolower(trim((string) $request->input('slug', '')));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/', $slug)) {
            $errors['slug'] = 'نشانی صفحه ۳ تا ۶۰ نویسهٔ لاتین کوچک، عدد یا خط تیره باشد.';
        } elseif (DB::selectOne('SELECT id FROM salons WHERE slug = ? AND id <> ?', [$slug, $id]) !== null) {
            $errors['slug'] = 'این نشانی مال سالن دیگری است.';
        }
        $audience = (string) $request->input('audience', '');
        if (!array_key_exists($audience, Audience::options())) {
            $errors['audience'] = 'نوع سالن را انتخاب کنید.';
        }
        $plan = (string) $request->input('plan_code', '');
        if (!array_key_exists($plan, self::PLANS)) {
            $errors['plan_code'] = 'طرح نامعتبر است.';
        }
        $publication = (string) $request->input('publication_status', '');
        if (!array_key_exists($publication, self::PUBLICATION)) {
            $errors['publication_status'] = 'وضعیت انتشار نامعتبر است.';
        }
        $trialEnds = jalali_date_from_request($request, 'trial_ends');
        if ($errors !== []) {
            return $this->invalid($request, $errors, $back);
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'audience' => $audience,
            'city' => mb_substr(trim((string) $request->input('city', '')), 0, 80) ?: null,
            'address' => mb_substr(trim((string) $request->input('address', '')), 0, 255) ?: null,
            'phone' => preg_replace('/[^\d+]/', '', \App\Support\Jalali::fromPersianDigits((string) $request->input('phone', ''))) ?: null,
            'seats' => max(1, min(50, (int) int_input($request->input('seats', (string) $salon['seats'])))),
            'plan_code' => $plan,
            // همان روزِ قبلی، همان مقدار قبلی — وگرنه هر ذخیره ساعتش را عوض و «تغییر» ثبت می‌کرد
            'trial_ends_at' => $plan !== 'trial' ? null
                : ($trialEnds === null || $trialEnds === substr((string) $salon['trial_ends_at'], 0, 10) ? $salon['trial_ends_at'] : $trialEnds . ' 23:59:59'),
            'publication_status' => $publication,
            'is_active' => $request->input('is_active') === '1' ? 1 : 0,
        ];

        $changed = [];
        foreach ($data as $key => $value) {
            if ((string) ($salon[$key] ?? '') !== (string) ($value ?? '')) {
                $changed[$key] = ['from' => $salon[$key], 'to' => $value];
            }
        }
        DB::update('salons', $data, 'id = :id', ['id' => $id]);
        SalonRepository::forget($id);
        if ($changed !== []) {
            AuditLog::record($id, 'salon.updated', 'salon', $id, ['changes' => $changed]);
        }

        return $this->withSuccess('مشخصات سالن ذخیره شد.', '/platform/salons/' . $id);
    }

    /** شارژ یا کسر اعتبار پیامک، با دلیل. */
    public function credit(Request $request): Response
    {
        $id = (int) $request->param('id');
        if (DB::selectOne('SELECT id FROM salons WHERE id = ?', [$id]) === null) {
            return $this->notFound('سالن یافت نشد.');
        }
        $amount = (int) int_input($request->input('amount', '0'));
        $direction = $request->input('direction') === 'debit' ? -1 : 1;
        $reason = mb_substr(trim((string) $request->input('reason', '')), 0, 200);
        if ($amount <= 0 || $amount > 1_000_000) {
            return $this->invalid($request, ['amount' => 'تعداد پیامک را درست وارد کنید.'], '/platform/salons/' . $id . '#credit');
        }
        $delta = $amount * $direction;
        DB::statement('UPDATE salons SET sms_credit = sms_credit + ? WHERE id = ?', [$delta, $id]);
        $after = (int) (DB::selectOne('SELECT sms_credit FROM salons WHERE id = ?', [$id])['sms_credit'] ?? 0);
        SalonRepository::forget($id);
        AuditLog::record($id, 'salon.sms_credit', 'salon', $id, ['delta' => $delta, 'after' => $after, 'reason' => $reason]);

        return $this->withSuccess(sprintf('%s پیامک %s. اعتبار فعلی: %s', fa_num($amount), $direction > 0 ? 'افزوده شد' : 'کسر شد', fa_num($after)), '/platform/salons/' . $id . '#credit');
    }

    public function setActive(Request $request): Response
    {
        $id = (int) $request->param('id');
        if (DB::selectOne('SELECT id FROM salons WHERE id = ?', [$id]) === null) {
            return $this->notFound('سالن یافت نشد.');
        }
        $active = $request->input('active') === '1';
        DB::update('salons', ['is_active' => $active ? 1 : 0], 'id = :id', ['id' => $id]);
        SalonRepository::forget($id);
        AuditLog::record($id, $active ? 'salon_activate' : 'salon_deactivate', 'salon', $id);

        return $this->withSuccess($active ? 'سالن فعال شد.' : 'سالن غیرفعال شد؛ صفحهٔ رزرو و پنل آن بسته است.', '/platform/salons/' . $id);
    }

    public function impersonate(Request $request): Response
    {
        $id = (int) $request->param('id');
        if (DB::selectOne('SELECT id FROM salons WHERE id = ?', [$id]) === null) {
            return $this->notFound('سالن یافت نشد.');
        }
        AuditLog::record($id, 'support_login_as', 'salon', $id);
        Auth::startImpersonating($id);

        return $this->redirect('/panel');
    }

    /** افزودن عضو پنل: کاربر موجود یا حساب تازه، با نقش. */
    public function addMember(Request $request): Response
    {
        $id = (int) $request->param('id');
        if (DB::selectOne('SELECT id FROM salons WHERE id = ?', [$id]) === null) {
            return $this->notFound('سالن یافت نشد.');
        }
        $back = '/platform/salons/' . $id . '#members';
        $input = $request->all();
        $role = array_key_exists((string) ($input['role'] ?? ''), self::ROLES) ? (string) $input['role'] : 'reception';

        if (($input['member_mode'] ?? 'existing') === 'new') {
            [$errors, $data] = AccountService::validateNew($input, 'member_');
            if ($errors !== []) {
                return $this->invalid($request, $errors, $back);
            }
            $mustChange = ($input['member_must_change'] ?? '') === '1';
            $userId = DB::transaction(static function () use ($data, $mustChange, $id, $role) {
                $userId = AccountService::create($data, $mustChange);
                DB::insert('salon_user', ['salon_id' => $id, 'user_id' => $userId, 'role' => $role]);

                return $userId;
            });
            AuditLog::record($id, 'user.created', 'user', (int) $userId, ['role' => $role]);
            AuditLog::record($id, 'member.added', 'user', (int) $userId, ['role' => $role]);

            return Response::html(View::renderWithLayout('layouts.platform', 'platform.users.created', [
                'title' => 'حساب ساخته شد',
                'user' => DB::selectOne('SELECT id, name, phone, username FROM users WHERE id = ?', [$userId]),
                'password' => $data['password'],
                'mustChange' => $mustChange,
                'context' => 'عضو «' . self::ROLES[$role] . '» سالن',
                'backUrl' => '/platform/salons/' . $id . '#members',
            ]));
        }

        $user = AccountService::findExisting((string) ($input['member_identifier'] ?? ''));
        if ($user === null) {
            return $this->invalid($request, ['member_identifier' => 'کاربری با این موبایل یا نام کاربری پیدا نشد.'], $back);
        }
        $existing = DB::selectOne('SELECT * FROM salon_user WHERE salon_id = ? AND user_id = ?', [$id, $user['id']]);
        if ($existing === null) {
            DB::insert('salon_user', ['salon_id' => $id, 'user_id' => $user['id'], 'role' => $role]);
        } else {
            DB::update('salon_user', ['role' => $role, 'is_active' => 1], 'id = :id', ['id' => $existing['id']]);
        }
        AuditLog::record($id, 'member.added', 'user', (int) $user['id'], ['role' => $role]);

        return $this->withSuccess('«' . ($user['name'] ?: phone_local((string) $user['phone'])) . '» با نقش ' . self::ROLES[$role] . ' به پنل این سالن اضافه شد.', $back);
    }

    /** تغییر نقش، برداشتن یا بازگرداندن دسترسی یک عضو. */
    public function updateMember(Request $request): Response
    {
        $id = (int) $request->param('id');
        $userId = (int) $request->param('user');
        $back = '/platform/salons/' . $id . '#members';
        $member = DB::selectOne('SELECT * FROM salon_user WHERE salon_id = ? AND user_id = ?', [$id, $userId]);
        if ($member === null) {
            return $this->notFound('عضو یافت نشد.');
        }
        $action = (string) $request->input('action', 'role');
        $isActiveOwner = $member['role'] === 'owner' && (int) $member['is_active'] === 1;

        if ($action === 'remove') {
            if ($isActiveOwner && AccountService::activeOwnerCount($id) <= 1) {
                return $this->withError('این تنها صاحب سالن است. اول صاحب دیگری تعیین کنید.', $back);
            }
            DB::update('salon_user', ['is_active' => 0], 'id = :id', ['id' => $member['id']]);
            AuditLog::record($id, 'member.removed', 'user', $userId, ['role' => $member['role']]);

            return $this->withSuccess('دسترسی برداشته شد.', $back);
        }
        if ($action === 'restore') {
            DB::update('salon_user', ['is_active' => 1], 'id = :id', ['id' => $member['id']]);
            AuditLog::record($id, 'member.restored', 'user', $userId, ['role' => $member['role']]);

            return $this->withSuccess('دسترسی برگردانده شد.', $back);
        }

        $role = (string) $request->input('role', '');
        if (!array_key_exists($role, self::ROLES)) {
            return $this->withError('نقش نامعتبر است.', $back);
        }
        if ($isActiveOwner && $role !== 'owner' && AccountService::activeOwnerCount($id) <= 1) {
            return $this->withError('این تنها صاحب سالن است. اول نقش «صاحب سالن» را به کس دیگری بدهید.', $back);
        }
        DB::update('salon_user', ['role' => $role, 'is_active' => 1], 'id = :id', ['id' => $member['id']]);
        AuditLog::record($id, 'member.role_changed', 'user', $userId, ['from' => $member['role'], 'to' => $role]);

        return $this->withSuccess('نقش به «' . self::ROLES[$role] . '» تغییر کرد.', $back);
    }

    /** نشانی قدیمی /platform/{id} — پیوندهای ذخیره‌شده نشکنند. */
    public function legacy(Request $request): Response
    {
        $id = (int) $request->param('id');

        return $id > 0 ? $this->redirect('/platform/salons/' . $id) : $this->notFound();
    }
}
