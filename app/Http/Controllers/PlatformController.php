<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\HolidayRepository;
use App\Domain\Salon\SalonRepository;
use App\Support\Audience;
use App\Support\Jalali;
use App\Support\Now;

/**
 * پنل خودِ پلتفرم — بالاترین سطح دسترسی.
 *
 * فهرست سالن‌ها، آمار کلی، تعطیلات رسمی، و «ورود به‌جای» صاحب سالن
 * برای پشتیبانی. هر اقدام حساس در audit_logs ثبت می‌شود: دسترسی‌ای که
 * رد نگذارد، دسترسی‌ای است که کسی جوابگویش نیست.
 */
final class PlatformController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $audience = (string) $request->query('audience', '');
        $page = max(1, (int) $request->query('page', '1'));

        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(s.name LIKE ? OR s.slug LIKE ? OR s.city LIKE ? OR s.phone LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status === 'inactive') {
            $where[] = 's.is_active = 0';
        } elseif (in_array($status, ['draft', 'pending', 'published', 'rejected'], true)) {
            $where[] = 's.publication_status = ?';
            $params[] = $status;
        }
        if (array_key_exists($audience, Audience::options())) {
            $where[] = 's.audience = ?';
            $params[] = $audience;
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) (DB::selectOne("SELECT COUNT(*) AS c FROM salons s WHERE {$whereSql}", $params)['c'] ?? 0);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);
        $since = Now::today()->modify('-30 days')->format('Y-m-d 00:00:00');

        $salons = DB::select(
            "SELECT s.id, s.name, s.slug, s.city, s.audience, s.theme, s.plan_code, s.is_active, s.publication_status,
                    s.sms_credit, s.trial_ends_at, s.created_at,
                    (SELECT COUNT(*) FROM staff st WHERE st.salon_id = s.id AND st.is_active = 1) AS staff_count,
                    (SELECT COUNT(*) FROM appointments a WHERE a.salon_id = s.id AND a.status = 'completed' AND a.actual_end_at >= ?) AS completed_30d
               FROM salons s WHERE {$whereSql}
              ORDER BY s.created_at DESC, s.id DESC
              LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            array_merge([$since], $params)
        );

        return $this->page('layouts.platform', 'platform.index', [
            'title' => 'پنل پلتفرم',
            'salons' => $salons,
            'metrics' => $this->platformMetrics(),
            'filters' => ['q' => $q, 'status' => $status, 'audience' => $audience],
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
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
            "SELECT u.id, u.name, u.phone, su.role FROM salon_user su JOIN users u ON u.id = su.user_id
              WHERE su.salon_id = ? ORDER BY FIELD(su.role, 'owner', 'manager', 'reception', 'staff'), u.name",
            [$id]
        );
        $stats = DB::selectOne(
            "SELECT
                (SELECT COUNT(*) FROM staff WHERE salon_id = ? AND is_active = 1) AS staff,
                (SELECT COUNT(*) FROM services WHERE salon_id = ? AND is_active = 1) AS services,
                (SELECT COUNT(*) FROM customers WHERE salon_id = ?) AS customers,
                (SELECT COUNT(*) FROM appointments WHERE salon_id = ? AND status = 'completed' AND actual_end_at >= ?) AS completed,
                (SELECT COUNT(*) FROM appointments WHERE salon_id = ? AND status = 'no_show' AND scheduled_at >= ?) AS no_show,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE salon_id = ? AND paid_at >= ?) AS revenue",
            [$id, $id, $id, $id, $since, $id, $since, $id, $since]
        ) ?? [];
        $auditLogs = DB::select(
            "SELECT al.*, u.name AS actor_name, u.phone AS actor_phone FROM audit_logs al
               LEFT JOIN users u ON u.id = al.actor_user_id
              WHERE al.salon_id = ? ORDER BY al.id DESC LIMIT 25",
            [$id]
        );

        return $this->page('layouts.platform', 'platform.show', [
            'title' => $salon['name'],
            'salon' => $salon,
            'members' => $members,
            'stats' => $stats,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function setActive(Request $request): Response
    {
        $id = (int) $request->param('id');
        $salon = DB::selectOne('SELECT id, is_active FROM salons WHERE id = ?', [$id]);
        if ($salon === null) {
            return $this->notFound('سالن یافت نشد.');
        }
        $active = $request->input('active') === '1';

        DB::transaction(function () use ($id, $active) {
            DB::update('salons', ['is_active' => $active ? 1 : 0], 'id = :id', ['id' => $id]);
            $this->audit($id, $active ? 'salon_activate' : 'salon_deactivate', 'salon', $id);
        });
        SalonRepository::forget($id);

        return $this->withSuccess($active ? 'سالن فعال شد.' : 'سالن غیرفعال شد؛ صفحهٔ رزرو و پنل آن از دسترس خارج است.', '/platform/' . $id);
    }

    public function impersonate(Request $request): Response
    {
        $id = (int) $request->param('id');
        if (DB::selectOne('SELECT id FROM salons WHERE id = ?', [$id]) === null) {
            return $this->notFound('سالن یافت نشد.');
        }

        $this->audit($id, 'support_login_as', 'salon', $id);
        Auth::startImpersonating($id);

        return $this->redirect('/panel');
    }

    public function stopImpersonating(Request $request): Response
    {
        Auth::stopImpersonating();

        return $this->redirect('/platform');
    }

    // ─── تعطیلات رسمی ─────────────────────────────────────────────────

    public function holidays(Request $request): Response
    {
        [$thisYear] = Jalali::fromDateTime(Now::today());
        $jy = (int) $request->query('jy', (string) $thisYear);
        if ($jy < $thisYear - 2 || $jy > $thisYear + 3) {
            $jy = $thisYear;
        }
        $from = Jalali::toDateTime($jy, 1, 1)->format('Y-m-d');
        $to = Jalali::toDateTime($jy, 12, Jalali::daysInJalaliMonth($jy, 12))->format('Y-m-d');

        return $this->page('layouts.platform', 'platform.holidays', [
            'title' => 'تعطیلات رسمی',
            'jy' => $jy,
            'thisYear' => $thisYear,
            'holidays' => (new HolidayRepository())->between($from, $to),
            'observing' => (int) (DB::selectOne('SELECT COUNT(*) AS c FROM salons WHERE observe_official_holidays = 1 AND is_active = 1')['c'] ?? 0),
        ]);
    }

    public function addHoliday(Request $request): Response
    {
        $date = jalali_date_from_request($request, 'date');
        $label = mb_substr(trim((string) $request->input('label', '')), 0, 100);
        if ($date === null || $label === '') {
            return $this->invalid($request, array_filter([
                'date' => $date === null ? 'تاریخ معتبر نیست.' : null,
                'label' => $label === '' ? 'عنوان تعطیلی را بنویسید.' : null,
            ]), '/platform/holidays');
        }
        [$jy] = Jalali::fromDateTime(new \DateTimeImmutable($date));
        $back = '/platform/holidays?jy=' . $jy;

        if (DB::selectOne('SELECT id FROM holidays WHERE gregorian_date = ?', [$date]) !== null) {
            return $this->withError('برای این روز از قبل تعطیلی ثبت شده است.', $back);
        }
        (new HolidayRepository())->add($date, $label);
        $this->audit(null, 'holiday_add', 'holiday', null, ['date' => $date, 'label' => $label]);

        return $this->withSuccess('تعطیلی «' . $label . '» ثبت شد. نوبت‌دهی آنلاین سالن‌هایی که تعطیلات رسمی را رعایت می‌کنند در این روز بسته است.', $back);
    }

    public function seedHolidays(Request $request): Response
    {
        $jy = (int) $request->input('jy');
        [$thisYear] = Jalali::fromDateTime(Now::today());
        if ($jy < $thisYear - 1 || $jy > $thisYear + 3) {
            return $this->withError('سال نامعتبر است.', '/platform/holidays');
        }
        (new HolidayRepository())->seedFixedHolidaysForYear($jy);
        $this->audit(null, 'holiday_seed', 'holiday', null, ['year' => $jy]);

        return $this->withSuccess('تعطیلات ثابت شمسی سال ' . fa_num($jy) . ' اضافه شد. تعطیلات قمری را جدا وارد کنید.', '/platform/holidays?jy=' . $jy);
    }

    public function removeHoliday(Request $request): Response
    {
        $id = (int) $request->param('id');
        $row = DB::selectOne('SELECT * FROM holidays WHERE id = ?', [$id]);
        if ($row === null) {
            return $this->withError('یافت نشد.', '/platform/holidays');
        }
        (new HolidayRepository())->remove($id);
        $this->audit(null, 'holiday_remove', 'holiday', $id, ['date' => $row['gregorian_date'], 'label' => $row['jalali_label']]);
        [$jy] = Jalali::fromDateTime(new \DateTimeImmutable((string) $row['gregorian_date']));

        return $this->withSuccess('حذف شد.', '/platform/holidays?jy=' . $jy);
    }

    private function audit(?int $salonId, string $action, string $subjectType, ?int $subjectId, array $meta = []): void
    {
        DB::insert('audit_logs', [
            'salon_id' => $salonId,
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    private function platformMetrics(): array
    {
        $since7 = Now::today()->modify('-7 days')->format('Y-m-d 00:00:00');
        $since30 = Now::today()->modify('-30 days')->format('Y-m-d 00:00:00');

        $salons = DB::selectOne(
            "SELECT SUM(is_active = 1) AS active, SUM(is_active = 1 AND audience = 'men') AS men,
                    SUM(is_active = 1 AND audience = 'women') AS women, SUM(is_active = 1 AND audience = 'unisex') AS unisex,
                    SUM(publication_status = 'pending') AS pending, SUM(is_active = 1 AND sms_credit < 20) AS low_sms
               FROM salons"
        ) ?? [];
        $completedThisWeek = (int) (DB::selectOne(
            "SELECT COUNT(*) AS c FROM appointments WHERE status = 'completed' AND actual_end_at >= ?",
            [$since7]
        )['c'] ?? 0);
        $avgMae = DB::selectOne(
            "SELECT AVG(ABS(TIMESTAMPDIFF(MINUTE, estimated_start_at, actual_start_at))) AS mae
               FROM appointments WHERE estimated_start_at IS NOT NULL AND actual_start_at IS NOT NULL AND actual_end_at >= ?",
            [$since30]
        )['mae'] ?? null;
        $rate = DB::selectOne(
            "SELECT SUM(status = 'completed') AS completed, SUM(status IN ('completed','no_show')) AS total
               FROM appointments WHERE created_at >= ?",
            [$since30]
        );
        $pendingReviews = (int) (DB::selectOne("SELECT COUNT(*) AS c FROM reviews WHERE moderation_status = 'pending'")['c'] ?? 0);

        return [
            'active_salons' => (int) ($salons['active'] ?? 0),
            'by_audience' => ['men' => (int) ($salons['men'] ?? 0), 'women' => (int) ($salons['women'] ?? 0), 'unisex' => (int) ($salons['unisex'] ?? 0)],
            'pending' => (int) ($salons['pending'] ?? 0) + $pendingReviews,
            'low_sms' => (int) ($salons['low_sms'] ?? 0),
            'completed_this_week' => $completedThisWeek,
            'mae_minutes' => $avgMae !== null ? round((float) $avgMae, 1) : null,
            'end_registration_rate' => $rate && (int) $rate['total'] > 0 ? round(((int) $rate['completed'] / (int) $rate['total']) * 100, 1) : null,
        ];
    }
}
