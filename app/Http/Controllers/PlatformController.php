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

    public function stopImpersonating(Request $request): Response
    {
        $salonId = Auth::isImpersonating() ? Auth::salonId() : null;
        Auth::stopImpersonating();

        return $this->redirect($salonId !== null ? '/platform/salons/' . $salonId : '/platform');
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
        \App\Domain\System\AuditLog::record($salonId, $action, $subjectType, $subjectId, $meta);
    }
}
