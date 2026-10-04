<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Access\Access;
use App\Domain\Booking\BookingService;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\Clock;
use App\Support\JalaliCalendar;
use DateTimeImmutable;
use RuntimeException;

/**
 * رزروهای زمان‌دار (سانس‌های گرفته‌شده).
 *
 * چرا جدا از «صف زنده»: صف، حالِ سالن است — چه کسی روی صندلی است و چه
 * کسی بعدی. رزرو، آیندهٔ سالن است. آرایشگر صبح که می‌آید باید بتواند
 * بپرسد «امروز چند تا وقت دارم؟» و جواب بگیرد؛ این سؤال را صف جواب
 * نمی‌دهد چون رزروِ ساعت ۶ عصر هنوز وارد صف نشده.
 *
 * بدون این صفحه، رزرو آنلاین یک‌طرفه بود: مشتری سانس می‌گرفت و سالن
 * تا لحظهٔ آمدنش خبردار نمی‌شد. (منوی کناری هم به مسیری لینک می‌داد
 * که وجود نداشت و ۴۰۴ می‌گرفت.)
 */
final class BookingsController extends Controller
{
    /** چند روز جلوتر را یک‌جا نشان بدهیم. */
    private const HORIZON_DAYS = 13;

    public function index(Request $request): Response
    {
        $salonId = Auth::salonId();

        $from = $this->parseDate((string) $request->query('from', ''), new DateTimeImmutable('today'));
        $to = $from->modify('+' . self::HORIZON_DAYS . ' days');

        $repo = new AppointmentRepository();

        // آرایشگر فقط رزروهای خودش را می‌بیند؛ صاحب و مدیر، همه را.
        // همان قاعدهٔ صفحهٔ صف (سند امنیت، بخش ۴).
        $onlyMine = !Access::allows(Access::BOOK_FOR_OTHERS);
        $staffFilter = $onlyMine ? Auth::staffId() : null;

        $rows = $repo->scheduledBetween(
            $salonId,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $staffFilter
        );

        return $this->page('layouts.panel', 'panel.bookings.index', [
            'title' => 'رزروها',
            'days' => $this->groupByDay($rows, $from, $to),
            'counts' => $repo->scheduledCounts($salonId, $from->format('Y-m-d'), $to->format('Y-m-d')),
            'from' => $from,
            'to' => $to,
            'prev' => $from->modify('-' . (self::HORIZON_DAYS + 1) . ' days')->format('Y-m-d'),
            'next' => $to->modify('+1 day')->format('Y-m-d'),
            'isToday' => $from->format('Y-m-d') === date('Y-m-d'),
            'onlyMine' => $onlyMine,
            'staffList' => (new StaffRepository())->all($salonId, true),
        ]);
    }

    /**
     * فرم رزرو دستی — اول تاریخ، بعد سانس.
     *
     * چرا در پنل لازم است: مشتری‌ای که زنگ می‌زند یا سر پیشخوان
     * می‌ایستد، نمی‌تواند از جریان رزرو آنلاین استفاده کند. تا الان
     * پذیرش فقط می‌توانست «مراجعهٔ حضوری» به صف اضافه کند — یعنی همین
     * الان — و هیچ راهی برای «چهارشنبه ساعت ۵» نداشت.
     *
     * یک فرم است نه ویزارد: پذیرش مشتری را پشت تلفن دارد و نباید چهار
     * صفحه جلو و عقب برود. تاریخ و آرایشگر و خدمت که عوض شود، سانس‌ها
     * دوباره حساب می‌شوند (با GET، پس بدون جاوااسکریپت هم کار می‌کند).
     */
    public function create(Request $request): Response
    {
        $salonId = Auth::salonId();

        $date = $this->parseDate(
            (string) ($this->jalaliQueryDate($request) ?? ''),
            new DateTimeImmutable('today')
        );

        $staffRaw = $request->query('staff_id', '');
        $staffId = $staffRaw !== '' && $staffRaw !== null ? (int) $staffRaw : null;

        $serviceIds = array_values(array_filter(array_map(
            'intval',
            (array) $request->query('service_ids', [])
        )));

        $services = (new ServiceRepository())->all($salonId, true);

        // تا خدمتی انتخاب نشده، مدت را از کوتاه‌ترین خدمت می‌گیریم تا
        // فهرست سانس‌ها خالی نماند و پذیرش بفهمد آن روز اصلاً باز است.
        $duration = $this->durationFor($salonId, $serviceIds, $services);

        $free = $serviceIds === [] && $services === []
            ? []
            : (new BookingService())->freeSlots($salonId, $staffId, $date, $duration);

        return $this->page('layouts.panel', 'panel.bookings.create', [
            'title' => 'رزرو جدید',
            'date' => $date,
            'staffId' => $staffId,
            'serviceIds' => $serviceIds,
            'services' => $services,
            'staffList' => (new StaffRepository())->all($salonId, true),
            'slots' => $free,
            'duration' => $duration,
            'isClosed' => $free === [] && $services !== [],
        ]);
    }

    /** ثبت نهایی. */
    public function store(Request $request): Response
    {
        $salonId = Auth::salonId();

        $date = $this->parseDate((string) (jalali_date_from_request($request, 'date') ?? ''), new DateTimeImmutable('today'));
        $time = (string) $request->input('time', '');
        $phone = trim((string) $request->input('phone', ''));
        $name = trim((string) $request->input('name', ''));
        $staffRaw = $request->input('staff_id', '');
        $staffId = $staffRaw !== '' && $staffRaw !== null ? (int) $staffRaw : null;
        $serviceIds = array_values(array_filter(array_map('intval', (array) $request->input('service_ids', []))));

        if ($time === '') {
            return $this->withError('یک سانس انتخاب کنید.', '/panel/bookings/new');
        }
        if ($phone === '') {
            return $this->withError('شمارهٔ موبایل مشتری لازم است.', '/panel/bookings/new');
        }

        try {
            $appointment = (new BookingService())->createBooking(
                $salonId,
                $staffId,
                $serviceIds,
                $date,
                $time,
                $phone,
                $name !== '' ? $name : null,
                $request->ip(),
            );
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel/bookings/new');
        }

        $label = JalaliCalendar::humanDate(new DateTimeImmutable($appointment['scheduled_at']));

        return $this->withSuccess(
            'نوبت ثبت شد: ' . $label . ' ساعت ' . Clock::hm($time),
            '/panel/bookings?from=' . $date->format('Y-m-d')
        );
    }

    /**
     * مدت کل خدمت‌های انتخاب‌شده.
     *
     * اگر چیزی انتخاب نشده، کوتاه‌ترین خدمت ملاک است — نه صفر. با صفر،
     * حلقهٔ تولید سانس هر بازه‌ای را آزاد می‌بیند و فهرستی نشان می‌دهد
     * که با انتخاب خدمت کوچک‌تر می‌شود؛ گمراه‌کننده است.
     *
     * @param int[] $serviceIds
     * @param array<int,array> $services
     */
    private function durationFor(int $salonId, array $serviceIds, array $services): int
    {
        if ($services === []) {
            return 30;
        }

        if ($serviceIds === []) {
            return max(5, min(array_map(static fn ($s) => (int) $s['duration_minutes'], $services)));
        }

        $byId = [];
        foreach ($services as $s) {
            $byId[(int) $s['id']] = (int) $s['duration_minutes'];
        }

        $total = 0;
        foreach ($serviceIds as $id) {
            $total += $byId[$id] ?? 0;
        }

        return max(5, $total);
    }

    /** تاریخ از کوئری، اگر انتخابگر شمسی فرستاده باشد. */
    private function jalaliQueryDate(Request $request): ?string
    {
        return jalali_date_from_request($request, 'date');
    }

    /**
     * هر روزِ بازه یک خانه می‌گیرد، حتی روزهای خالی.
     *
     * روزِ خالی هم خبر است: «فردا هیچ رزروی نداری» چیزی است که صاحب
     * سالن باید ببیند، نه اینکه روز از فهرست غیب شود و فکر کند بارگذاری
     * نشده.
     *
     * @param array<int,array> $rows
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
            $key = $d->format('Y-m-d');
            $days[] = [
                'date' => $d,
                'label' => JalaliCalendar::relativeDate($d),
                'rows' => $byDate[$key] ?? [],
            ];
        }

        return $days;
    }

    /** تاریخ نامعتبر در نوار آدرس نباید صفحه را بترکاند. */
    private function parseDate(string $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $fallback;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed === false ? $fallback : $parsed;
    }
}
