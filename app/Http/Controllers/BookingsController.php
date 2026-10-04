<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Access\Access;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Booking\BookingPlanner;
use App\Domain\Booking\BookingService;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\Clock;
use App\Support\JalaliCalendar;
use App\Support\Now;
use DateTimeImmutable;
use RuntimeException;

/**
 * رزروهای زمان‌دار: برنامهٔ روزهای پیش رو، تأیید بیعانه و رزرو دستی.
 */
final class BookingsController extends Controller
{
    private const HORIZON_DAYS = 13;

    public function index(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $repo = new AppointmentRepository();
        $desk = Access::allows(Access::BOOK_FOR_OTHERS);

        // آرایشگر فقط رزروهای خودش را می‌بیند
        $staffFilter = $desk ? (($s = (string) $request->query('staff', '')) !== '' ? (int) $s : null) : Auth::staffId();
        if (!$desk && $staffFilter === null) {
            $staffFilter = -1;
        }

        $from = $this->parseDate((string) $request->query('from', ''), Now::today());
        $to = $from->modify('+' . self::HORIZON_DAYS . ' days');
        $rows = $repo->scheduledBetween($salonId, $from->format('Y-m-d'), $to->format('Y-m-d'), $staffFilter);

        return $this->page('layouts.panel', 'panel.bookings.index', [
            'title' => $desk ? 'رزروها' : 'نوبت‌های من',
            'days' => $this->groupByDay($rows, $from, $to),
            'counts' => $repo->scheduledCounts($salonId, $from->format('Y-m-d'), $to->format('Y-m-d'), $staffFilter),
            'deposits' => $desk ? $repo->pendingDeposits($salonId) : [],
            'from' => $from,
            'to' => $to,
            'prev' => $from->modify('-' . (self::HORIZON_DAYS + 1) . ' days')->format('Y-m-d'),
            'next' => $to->modify('+1 day')->format('Y-m-d'),
            'isToday' => $from->format('Y-m-d') === Now::today()->format('Y-m-d'),
            'desk' => $desk,
            'staffFilter' => $desk ? $staffFilter : null,
            'staffList' => $desk ? (new StaffRepository())->all($salonId) : [],
        ]);
    }

    /**
     * رزرو دستی — یک فرم، با تازه‌سازی GET تا بدون جاوااسکریپت هم کار کند.
     */
    public function create(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $date = $this->parseDate((string) (jalali_date_from_request($request, 'date') ?? $request->query('date', '')), Now::today());
        $staffRaw = (string) $request->query('staff_id', '');
        $staffId = $staffRaw !== '' ? (int) $staffRaw : null;
        $serviceIds = BookingPlanner::normalizeIds((array) $request->query('service_ids', []));

        $planner = new BookingPlanner();
        $error = $serviceIds !== [] ? $planner->validate($salonId, $serviceIds, false) : null;
        $capable = $serviceIds !== [] && $error === null ? $planner->capableStaff($salonId, $serviceIds, false) : [];
        if ($staffId !== null && $serviceIds !== [] && !in_array($staffId, $capable, true)) {
            $error ??= 'فرد انتخاب‌شده همهٔ این خدمات را انجام نمی‌دهد.';
        }

        $slots = $serviceIds !== [] && $error === null
            ? $planner->availableTimes($salonId, $serviceIds, $date, $staffId, false)
            : [];

        return $this->page('layouts.panel', 'panel.bookings.create', [
            'title' => 'رزرو جدید',
            'date' => $date,
            'staffId' => $staffId,
            'serviceIds' => $serviceIds,
            'groups' => (new ServiceRepository())->grouped($salonId, true),
            'staffList' => (new StaffRepository())->all($salonId, true),
            'capable' => $capable,
            'slots' => $slots,
            'pickError' => $error,
            'estimate' => $serviceIds !== [] && $error === null ? $planner->estimate($salonId, $serviceIds, $staffId, false) : null,
            'multi' => $serviceIds !== [] && $error === null && $capable === [],
        ]);
    }

    public function store(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $date = $this->parseDate((string) $request->input('date', ''), Now::today());
        $time = (string) $request->input('time', '');
        $staffRaw = (string) $request->input('staff_id', '');
        $staffId = $staffRaw !== '' ? (int) $staffRaw : null;
        $serviceIds = BookingPlanner::normalizeIds((array) $request->input('service_ids', []));
        $back = '/panel/bookings/new?' . http_build_query(['date' => $date->format('Y-m-d'), 'staff_id' => $staffRaw, 'service_ids' => $serviceIds]);

        $errors = [];
        if ($time === '') {
            $errors['time'] = 'یک ساعت انتخاب کنید.';
        }
        if (\App\Support\IranMobile::tryParse((string) $request->input('phone', '')) === null) {
            $errors['phone'] = 'شمارهٔ موبایل مشتری لازم است تا پیامک تأیید و یادآوری برایش برود.';
        }
        if ($errors !== []) {
            return $this->invalid($request, $errors, $back);
        }

        try {
            $appointment = (new BookingService())->createBooking(
                $salonId,
                $staffId,
                $serviceIds,
                $date,
                $time,
                (string) $request->input('phone'),
                trim((string) $request->input('name', '')) ?: null,
                $request->ip(),
                ['online' => false, 'note' => (string) $request->input('note', ''), 'created_by' => Auth::id()],
            );
        } catch (RuntimeException $e) {
            return $this->invalid($request, [], $back, $e->getMessage());
        }

        return $this->withSuccess(
            'نوبت ثبت شد: ' . JalaliCalendar::humanDate(new DateTimeImmutable((string) $appointment['scheduled_at'])) . '، ساعت ' . Clock::hm($time),
            '/panel/bookings?from=' . $date->format('Y-m-d')
        );
    }

    /** تأیید دریافت بیعانه. */
    public function confirmDeposit(Request $request): Response
    {
        $method = (string) $request->input('method', 'card_to_card');
        try {
            (new BookingService())->confirmDeposit((int) Auth::salonId(), (int) $request->param('id'), $method, Auth::id());
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel/bookings');
        }

        return $this->withSuccess('بیعانه تأیید شد و نوبت قطعی شد.', '/panel/bookings');
    }

    /**
     * @return array<int,array{date:DateTimeImmutable,label:string,rows:array}>
     */
    private function groupByDay(array $rows, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[substr((string) $r['scheduled_at'], 0, 10)][] = $r;
        }

        $days = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $days[] = ['date' => $d, 'label' => JalaliCalendar::relativeDate($d), 'rows' => $byDate[$d->format('Y-m-d')] ?? []];
        }

        return $days;
    }

    private function parseDate(string $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $fallback;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed === false ? $fallback : $parsed;
    }
}
