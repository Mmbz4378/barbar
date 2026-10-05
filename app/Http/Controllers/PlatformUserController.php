<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Domain\Identity\AccountService;
use App\Domain\Identity\LoginEvents;
use App\Domain\Identity\PasswordAuth;
use App\Domain\System\AuditLog;
use App\Support\IranMobile;

/**
 * مدیریت کاربران در پنل مدیر کل.
 *
 * همهٔ حساب‌ها اینجا هستند: مدیران کل، صاحبان سالن، کارکنان، و مشتری‌هایی
 * که رزرو کرده‌اند (آن‌ها هم ردیف users دارند ولی پنلی ندارند).
 *
 * قواعدی که نمی‌شکنند:
 *   - مدیر نمی‌تواند خودش را مسدود کند یا مدیریت کل را از خودش بگیرد.
 *   - آخرین مدیر کلِ فعال برداشته یا مسدود نمی‌شود (قفل شدن سامانه).
 *   - مسدودی و رمز تازه نشست‌های باز را همان لحظه می‌بندند (auth_version).
 *   - رمزی که مدیر می‌سازد فقط یک بار، در همان پاسخ نشان داده می‌شود.
 */
final class PlatformUserController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'panel');
        $page = max(1, (int) $request->query('page', '1'));

        $member = 'EXISTS(SELECT 1 FROM salon_user su WHERE su.user_id = u.id AND su.is_active = 1)';
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes(\App\Support\Jalali::fromPersianDigits($q), '%_\\') . '%';
            $phone = IranMobile::tryParse($q);
            $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.phone LIKE ?' . ($phone !== null ? ' OR u.phone = ?' : '') . ')';
            array_push($params, $like, $like, $like);
            if ($phone !== null) {
                $params[] = $phone->e164;
            }
        }
        $where[] = match ($type) {
            'admins' => 'u.is_platform_admin = 1',
            'blocked' => 'u.is_active = 0',
            'locked' => 'u.locked_until IS NOT NULL AND u.locked_until > ' . DB::connection()->quote(date('Y-m-d H:i:s')),
            'no_password' => "u.password_hash IS NULL AND (u.is_platform_admin = 1 OR {$member})",
            'customers' => "u.is_platform_admin = 0 AND NOT {$member}",
            'all' => '1 = 1',
            default => "(u.is_platform_admin = 1 OR {$member})",
        };
        $whereSql = implode(' AND ', $where);

        $total = (int) (DB::selectOne("SELECT COUNT(*) AS c FROM users u WHERE {$whereSql}", $params)['c'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $users = DB::select(
            "SELECT u.id, u.name, u.phone, u.username, u.is_platform_admin, u.is_active, u.locked_until,
                    u.password_hash IS NOT NULL AS has_password, u.must_change_password, u.last_login_at, u.created_at,
                    (SELECT GROUP_CONCAT(CONCAT(s.name, '|', su.role) ORDER BY s.name SEPARATOR ';;')
                       FROM salon_user su JOIN salons s ON s.id = su.salon_id
                      WHERE su.user_id = u.id AND su.is_active = 1) AS memberships
               FROM users u WHERE {$whereSql}
              ORDER BY u.is_platform_admin DESC, u.created_at DESC, u.id DESC
              LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params
        );

        $counts = DB::selectOne(
            "SELECT SUM(u.is_platform_admin = 1 OR {$member}) AS panel, SUM(u.is_platform_admin = 1) AS admins,
                    SUM(u.is_active = 0) AS blocked, SUM(u.is_platform_admin = 0 AND NOT {$member}) AS customers
               FROM users u"
        ) ?? [];

        return $this->page('layouts.platform', 'platform.users.index', [
            'title' => 'کاربران',
            'users' => $users,
            'counts' => array_map('intval', $counts),
            'filters' => ['q' => $q, 'type' => $type],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->page('layouts.platform', 'platform.users.create', [
            'title' => 'کاربر تازه',
            'salons' => DB::select('SELECT id, name FROM salons ORDER BY name'),
        ]);
    }

    public function store(Request $request): Response
    {
        $input = $request->all();
        [$errors, $data] = AccountService::validateNew($input);
        $salonId = (int) ($input['salon_id'] ?? 0);
        $role = (string) ($input['role'] ?? '');
        if ($salonId > 0) {
            if (DB::selectOne('SELECT id FROM salons WHERE id = ?', [$salonId]) === null) {
                $errors['salon_id'] = 'سالن یافت نشد.';
            }
            if (!array_key_exists($role, PlatformSalonController::ROLES)) {
                $errors['role'] = 'نقش را انتخاب کنید.';
            }
        }
        if ($errors !== []) {
            return $this->invalid($request, $errors, '/platform/users/new');
        }

        $isAdmin = ($input['is_platform_admin'] ?? '') === '1';
        $mustChange = ($input['must_change'] ?? '') === '1';
        $userId = (int) DB::transaction(static function () use ($data, $mustChange, $isAdmin, $salonId, $role) {
            $userId = AccountService::create($data, $mustChange, $isAdmin);
            if ($salonId > 0) {
                DB::insert('salon_user', ['salon_id' => $salonId, 'user_id' => $userId, 'role' => $role]);
            }

            return $userId;
        });
        AuditLog::record($salonId > 0 ? $salonId : null, 'user.created', 'user', $userId, array_filter(['admin' => $isAdmin, 'role' => $salonId > 0 ? $role : null]));

        return Response::html(View::renderWithLayout('layouts.platform', 'platform.users.created', [
            'title' => 'حساب ساخته شد',
            'user' => DB::selectOne('SELECT id, name, phone, username FROM users WHERE id = ?', [$userId]),
            'password' => $data['password'],
            'mustChange' => $mustChange,
            'context' => $isAdmin ? 'مدیر کل' : ($salonId > 0 ? PlatformSalonController::ROLES[$role] : 'کاربر'),
            'backUrl' => '/platform/users/' . $userId,
        ]));
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $user = DB::selectOne('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            return $this->notFound('کاربر یافت نشد.');
        }

        $memberships = DB::select(
            'SELECT su.salon_id, su.role, su.is_active, s.name AS salon_name, s.is_active AS salon_active
               FROM salon_user su JOIN salons s ON s.id = su.salon_id
              WHERE su.user_id = ? ORDER BY su.is_active DESC, s.name',
            [$id]
        );
        $bookings = (int) (DB::selectOne(
            'SELECT COUNT(*) AS c FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE c.user_id = ?',
            [$id]
        )['c'] ?? 0);
        $events = DB::select(
            "SELECT al.*, s.name AS salon_name, actor.name AS actor_name FROM audit_logs al
               LEFT JOIN salons s ON s.id = al.salon_id
               LEFT JOIN users actor ON actor.id = al.actor_user_id
              WHERE (al.subject_type = 'user' AND al.subject_id = ?) OR al.actor_user_id = ?
              ORDER BY al.id DESC LIMIT 20",
            [$id, $id]
        );

        return $this->page('layouts.platform', 'platform.users.show', [
            'title' => $user['name'] ?: phone_local((string) $user['phone']),
            'user' => $user,
            'memberships' => $memberships,
            'bookings' => $bookings,
            'logins' => LoginEvents::forUser($id, 20),
            'events' => $events,
            'salons' => DB::select('SELECT id, name FROM salons ORDER BY name'),
            'isSelf' => $id === Auth::id(),
        ]);
    }

    /** نام، موبایل و نام کاربری. */
    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        $user = DB::selectOne('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            return $this->notFound('کاربر یافت نشد.');
        }
        $back = '/platform/users/' . $id;
        $errors = [];

        $name = mb_substr(trim((string) $request->input('name', '')), 0, 120);
        $phone = IranMobile::tryParse((string) $request->input('phone', ''));
        if ($phone === null) {
            $errors['phone'] = 'شمارهٔ موبایل معتبر نیست.';
        } elseif (DB::selectOne('SELECT id FROM users WHERE phone = ? AND id <> ?', [$phone->e164, $id]) !== null) {
            $errors['phone'] = 'این شماره مال کاربر دیگری است.';
        }
        $username = PasswordAuth::normalizeUsername((string) $request->input('username', ''));
        if ($username !== '') {
            $error = PasswordAuth::usernameError($username) ?? (PasswordAuth::usernameTaken($username, $id) ? 'این نام کاربری مال کس دیگری است.' : null);
            if ($error !== null) {
                $errors['username'] = $error;
            }
        }
        if ($errors !== []) {
            return $this->invalid($request, $errors, $back);
        }

        $data = ['name' => $name !== '' ? $name : null, 'phone' => $phone->e164, 'username' => $username !== '' ? $username : null];
        $changes = [];
        foreach ($data as $key => $value) {
            if ((string) ($user[$key] ?? '') !== (string) ($value ?? '')) {
                $changes[$key] = ['from' => $user[$key], 'to' => $value];
            }
        }
        DB::update('users', $data, 'id = :id', ['id' => $id]);
        if ($changes !== []) {
            AuditLog::record(null, 'user.updated', 'user', $id, ['changes' => $changes]);
        }

        return $this->withSuccess('مشخصات کاربر ذخیره شد.', $back);
    }

    /** تعیین یا ساخت رمز تازه. نشست‌های باز کاربر بسته می‌شوند. */
    public function password(Request $request): Response
    {
        $id = (int) $request->param('id');
        $user = DB::selectOne('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            return $this->notFound('کاربر یافت نشد.');
        }
        $back = '/platform/users/' . $id . '#password';
        $generated = $request->input('generate') === '1';
        $password = $generated ? PasswordAuth::generate() : (string) $request->input('password', '');
        if (!$generated) {
            $error = PasswordAuth::policyError($password, (string) $user['phone'], $user['username'] ?? null);
            if ($error !== null) {
                return $this->invalid($request, ['password' => $error], $back);
            }
        }
        $mustChange = $request->input('must_change') === '1' && $id !== Auth::id();
        PasswordAuth::setPassword($id, $password, $mustChange);
        if ($id === Auth::id()) {
            Auth::refreshVersion();
        }
        AuditLog::record(null, 'user.password_reset', 'user', $id, ['generated' => $generated, 'must_change' => $mustChange]);

        return Response::html(View::renderWithLayout('layouts.platform', 'platform.users.created', [
            'title' => 'رمز تازه',
            'user' => $user,
            'password' => $password,
            'mustChange' => $mustChange,
            'context' => null,
            'reset' => true,
            'backUrl' => '/platform/users/' . $id,
        ]));
    }

    /** مسدود / رفع مسدودی / بازکردن قفل رمز / مدیر کل. */
    public function status(Request $request): Response
    {
        $id = (int) $request->param('id');
        $user = DB::selectOne('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            return $this->notFound('کاربر یافت نشد.');
        }
        $back = '/platform/users/' . $id;
        $isSelf = $id === Auth::id();
        $isActiveAdmin = (int) $user['is_platform_admin'] === 1 && (int) $user['is_active'] === 1;

        switch ((string) $request->input('action', '')) {
            case 'block':
                if ($isSelf) {
                    return $this->withError('خودتان را نمی‌توانید مسدود کنید.', $back);
                }
                if ($isActiveAdmin && AccountService::activeAdminCount() <= 1) {
                    return $this->withError('این آخرین مدیر کلِ فعال است.', $back);
                }
                DB::statement('UPDATE users SET is_active = 0, auth_version = auth_version + 1 WHERE id = ?', [$id]);
                AuditLog::record(null, 'user.blocked', 'user', $id);

                return $this->withSuccess('حساب مسدود شد و نشست‌های باز آن بسته شدند.', $back);

            case 'unblock':
                DB::update('users', ['is_active' => 1, 'failed_logins' => 0, 'locked_until' => null], 'id = :id', ['id' => $id]);
                AuditLog::record(null, 'user.unblocked', 'user', $id);

                return $this->withSuccess('مسدودی برداشته شد.', $back);

            case 'unlock':
                DB::update('users', ['failed_logins' => 0, 'locked_until' => null], 'id = :id', ['id' => $id]);
                AuditLog::record(null, 'user.unlocked', 'user', $id);

                return $this->withSuccess('قفل ورود با رمز باز شد.', $back);

            case 'logout':
                DB::statement('UPDATE users SET auth_version = auth_version + 1 WHERE id = ?', [$id]);
                if ($isSelf) {
                    Auth::refreshVersion();
                }
                AuditLog::record(null, 'user.sessions_revoked', 'user', $id);

                return $this->withSuccess('همهٔ نشست‌های این کاربر بسته شد.', $back);

            case 'grant_admin':
                DB::update('users', ['is_platform_admin' => 1], 'id = :id', ['id' => $id]);
                AuditLog::record(null, 'user.admin_granted', 'user', $id);

                return $this->withSuccess('این کاربر حالا مدیر کل است.', $back);

            case 'revoke_admin':
                if ($isSelf) {
                    return $this->withError('مدیریت کل را از خودتان نمی‌توانید بگیرید.', $back);
                }
                if ($isActiveAdmin && AccountService::activeAdminCount() <= 1) {
                    return $this->withError('این آخرین مدیر کلِ فعال است.', $back);
                }
                DB::update('users', ['is_platform_admin' => 0], 'id = :id', ['id' => $id]);
                AuditLog::record(null, 'user.admin_revoked', 'user', $id);

                return $this->withSuccess('مدیریت کل از این کاربر گرفته شد.', $back);
        }

        return $this->withError('اقدام نامعتبر است.', $back);
    }

    /** افزودن عضویت در یک سالن از صفحهٔ کاربر. */
    public function addMembership(Request $request): Response
    {
        $id = (int) $request->param('id');
        if (DB::selectOne('SELECT id FROM users WHERE id = ?', [$id]) === null) {
            return $this->notFound('کاربر یافت نشد.');
        }
        $salonId = (int) $request->input('salon_id', '0');
        $role = (string) $request->input('role', '');
        $back = '/platform/users/' . $id . '#memberships';
        if (DB::selectOne('SELECT id FROM salons WHERE id = ?', [$salonId]) === null || !array_key_exists($role, PlatformSalonController::ROLES)) {
            return $this->withError('سالن و نقش را انتخاب کنید.', $back);
        }
        $existing = DB::selectOne('SELECT id FROM salon_user WHERE salon_id = ? AND user_id = ?', [$salonId, $id]);
        if ($existing === null) {
            DB::insert('salon_user', ['salon_id' => $salonId, 'user_id' => $id, 'role' => $role]);
        } else {
            DB::update('salon_user', ['role' => $role, 'is_active' => 1], 'id = :id', ['id' => $existing['id']]);
        }
        AuditLog::record($salonId, 'member.added', 'user', $id, ['role' => $role]);

        return $this->withSuccess('عضویت ثبت شد.', $back);
    }
}
