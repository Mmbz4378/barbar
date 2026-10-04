<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\Cache;
use App\Core\DB;
use App\Domain\Salon\SalonRepository;
use App\Support\Jalali;
use App\Support\Now;
use DateTimeImmutable;

/**
 * تقویم کاری هر نفر: کِی سر کار است و کِی گرفتار.
 *
 * سه نوع بازهٔ اشغال داریم و تفاوتشان مهم است:
 *
 *   سخت  — استراحت روزانه و مرخصی. خدمت نباید با آن‌ها هم‌پوشانی داشته
 *          باشد، ولی «زمان آماده‌سازی» پس از خدمت می‌تواند به استراحت
 *          بخورد (کسی جارو را وسط ناهار نمی‌گذارد).
 *   نوبت — نوبت‌های زنده به‌اضافهٔ زمان آماده‌سازی‌شان. نوبت تازه، هم
 *          خودش و هم آماده‌سازی‌اش، نباید با آن‌ها برخورد کند.
 *   صف   — کسانی که همین الان در سالن منتظرند. از اکنون تا پایانِ
 *          تخمینیِ صف، آن نفر برای رزرو آنلاینِ امروز آزاد نیست.
 *
 * همه‌چیز برای هر سالن یک بار در درخواست خوانده و کش می‌شود؛ تقویم
 * ماهانه برای هر روز سراغ همین داده می‌آید.
 */
final class SlotFinder
{
    private const DEFAULT_STEP_MINUTES = 15;

    /** نوبتی که مدت خدمتش ثبت نشده — نباید صفر فرض شود. */
    private const FALLBACK_BOOKING_MINUTES = 30;

    /** @var array<int,array> */
    private static array $settingsCache = [];

    /** @var array<int,array{salon:array,staff:array}> */
    private static array $hoursCache = [];

    /** @var array<string,int>|null */
    private static ?array $holidays = null;

    /** @var array<int,array> */
    private static array $busyCache = [];

    /**
     * تنظیمات زمان‌بندی سالن، با حفاظ روی مقادیر معیوب.
     *
     * @return array{step:int,min_notice:int,horizon:int,cancel_notice:int,observe_holidays:bool,flow:string}
     */
    public function settings(int $salonId): array
    {
        if (!isset(self::$settingsCache[$salonId])) {
            // همان ردیف کش‌شدهٔ سالن — بدون کوئری جدا. با هر ذخیرهٔ تنظیمات،
            // SalonRepository::forget نسخه را بالا می‌برد و این تازه می‌شود.
            $row = (new SalonRepository())->find($salonId) ?? [];

            $step = (int) ($row['slot_step_minutes'] ?? self::DEFAULT_STEP_MINUTES);

            self::$settingsCache[$salonId] = [
                // صفر حلقهٔ سانس را بی‌نهایت می‌کند؛ بیش از ۱۲۰ عملاً رزرو را می‌بندد
                'step' => max(5, min(120, $step ?: self::DEFAULT_STEP_MINUTES)),
                'min_notice' => max(0, min(10080, (int) ($row['min_notice_minutes'] ?? 0))),
                'horizon' => max(1, min(365, (int) ($row['booking_horizon_days'] ?? 30) ?: 30)),
                'cancel_notice' => max(0, min(10080, (int) ($row['cancel_notice_minutes'] ?? 0))),
                'observe_holidays' => (int) ($row['observe_official_holidays'] ?? 1) === 1,
                'flow' => ($row['booking_flow'] ?? 'time_first') === 'service_first' ? 'service_first' : 'time_first',
            ];
        }

        return self::$settingsCache[$salonId];
    }

    public function stepMinutes(int $salonId): int
    {
        return $this->settings($salonId)['step'];
    }

    /**
     * ساعت کاری یک نفر در یک روز، یا null اگر آن روز کار نمی‌کند.
     *
     * @return array{open:DateTimeImmutable,close:DateTimeImmutable,breaks:array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}>}|null
     */
    public function window(int $salonId, int $staffId, DateTimeImmutable $date): ?array
    {
        $dateStr = $date->format('Y-m-d');
        if ($this->settings($salonId)['observe_holidays'] && $this->isHoliday($dateStr)) {
            return null;
        }

        $weekday = Jalali::weekday($date);
        $table = $this->workingHours($salonId);
        $salonDay = $table['salon'][$weekday] ?? null;

        // روزی که سالن بسته است، ساعت اختصاصی آرایشگر آن را باز نمی‌کند
        if ($salonDay !== null && (int) $salonDay['is_closed'] === 1) {
            return null;
        }

        $hours = $this->mergeHours($salonDay, $table['staff'][$staffId][$weekday] ?? null);
        if ($hours === null || (int) $hours['is_closed'] === 1) {
            return null;
        }

        $open = new DateTimeImmutable($dateStr . ' ' . $hours['opens_at']);
        $close = new DateTimeImmutable($dateStr . ' ' . $hours['closes_at']);
        if ($close <= $open) {
            return null;
        }

        $breaks = [];
        if (!empty($hours['break_start']) && !empty($hours['break_end'])) {
            $from = new DateTimeImmutable($dateStr . ' ' . $hours['break_start']);
            $to = new DateTimeImmutable($dateStr . ' ' . $hours['break_end']);
            if ($to > $from) {
                $breaks[] = [$from, $to];
            }
        }

        return ['open' => $open, 'close' => $close, 'breaks' => $breaks];
    }

    /**
     * آیا این نفر برای این بازه آزاد است؟
     *
     * $buffer زمان آماده‌سازی پس از خدمت است: لازم نیست داخل ساعت کاری
     * جا شود، ولی نباید روی نوبت بعدی بیفتد.
     */
    public function isFree(int $salonId, int $staffId, DateTimeImmutable $start, int $minutes, int $buffer = 0): bool
    {
        $window = $this->window($salonId, $staffId, $start->setTime(0, 0));
        if ($window === null) {
            return false;
        }

        $end = $start->modify('+' . max(1, $minutes) . ' minutes');
        if ($start < $window['open'] || $end > $window['close']) {
            return false;
        }

        $busy = $this->busy($salonId, $staffId, $start->format('Y-m-d'));

        if ($this->overlaps($start, $end, array_merge($window['breaks'], $busy['hard']))) {
            return false;
        }

        $endWithBuffer = $end->modify('+' . max(0, $buffer) . ' minutes');

        return !$this->overlaps($start, $endWithBuffer, $busy['booked']);
    }

    /**
     * ساعت‌های شروع ممکن برای یک نفر.
     *
     * @return string[] به شکل «ساعت:دقیقه»
     */
    public function freeSlotsForStaff(
        int $salonId,
        int $staffId,
        DateTimeImmutable $date,
        int $minutes,
        int $buffer = 0,
        ?DateTimeImmutable $notBefore = null,
        bool $firstOnly = false,
    ): array {
        $window = $this->window($salonId, $staffId, $date);
        if ($window === null) {
            return [];
        }

        $notBefore ??= Now::get();
        $step = $this->stepMinutes($salonId);
        $slots = [];

        for ($cursor = $window['open']; $cursor < $window['close']; $cursor = $cursor->modify('+' . $step . ' minutes')) {
            if ($cursor <= $notBefore) {
                continue;
            }
            if ($cursor->modify('+' . $minutes . ' minutes') > $window['close']) {
                break;
            }
            if ($this->isFree($salonId, $staffId, $cursor, $minutes, $buffer)) {
                $slots[] = $cursor->format('H:i');
                if ($firstOnly) {
                    break;
                }
            }
        }

        return $slots;
    }

    /**
     * شبکهٔ ساعت‌های شروع برای چند نفر با هم.
     *
     * از زودترین ساعت باز تا دیرترین ساعت بسته، با گام سانس سالن. مبدأ
     * شبکه ساعت باز سالن است تا سانس‌ها برای همه روی یک خط بیفتند.
     *
     * @param int[] $staffIds
     * @return DateTimeImmutable[]
     */
    public function candidateStarts(int $salonId, DateTimeImmutable $date, array $staffIds, ?DateTimeImmutable $notBefore = null): array
    {
        $open = null;
        $close = null;
        foreach ($staffIds as $staffId) {
            $window = $this->window($salonId, (int) $staffId, $date);
            if ($window === null) {
                continue;
            }
            $open = $open === null || $window['open'] < $open ? $window['open'] : $open;
            $close = $close === null || $window['close'] > $close ? $window['close'] : $close;
        }

        if ($open === null) {
            return [];
        }

        $weekday = Jalali::weekday($date);
        $salonDay = $this->workingHours($salonId)['salon'][$weekday] ?? null;
        $origin = $salonDay !== null
            ? new DateTimeImmutable($date->format('Y-m-d') . ' ' . $salonDay['opens_at'])
            : $open;

        $step = $this->stepMinutes($salonId);
        if ($origin > $open) {
            // ساعت اختصاصیِ زودتر از سالن: شبکه را به عقب گسترش بده
            $diff = (int) (($origin->getTimestamp() - $open->getTimestamp()) / 60);
            $origin = $origin->modify('-' . ((int) ceil($diff / $step) * $step) . ' minutes');
        }

        $notBefore ??= Now::get();
        $starts = [];
        for ($cursor = $origin; $cursor < $close; $cursor = $cursor->modify('+' . $step . ' minutes')) {
            if ($cursor > $notBefore) {
                $starts[] = $cursor;
            }
        }

        return $starts;
    }

    /**
     * دقیقه‌های اشغالِ یک نفر در یک روز — برای پخش عادلانهٔ نوبت‌ها.
     */
    public function bookedMinutes(int $salonId, int $staffId, string $dateStr): int
    {
        $total = 0;
        foreach ($this->busy($salonId, $staffId, $dateStr)['booked'] as [$from, $to]) {
            $total += (int) (($to->getTimestamp() - $from->getTimestamp()) / 60);
        }

        return $total;
    }

    /**
     * ساعت کاری آرایشگر روی ساعت کاری سالن.
     *
     * ردیف اختصاصی آرایشگر ساعت باز و بستهٔ خودش را تعیین می‌کند، ولی
     * استراحت سالن را پاک نمی‌کند: تعطیلی ظهر واقعیتِ سطح سالن است.
     */
    private function mergeHours(?array $salon, ?array $staff): ?array
    {
        if ($staff === null) {
            return $salon;
        }

        if ($salon !== null && empty($staff['break_start'])) {
            $staff['break_start'] = $salon['break_start'] ?? null;
            $staff['break_end'] = $salon['break_end'] ?? null;
        }

        return $staff;
    }

    /**
     * @return array{hard:array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}>,booked:array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}>}
     */
    private function busy(int $salonId, int $staffId, string $dateStr): array
    {
        $cache = $this->salonBusy($salonId);

        return [
            'hard' => array_merge($cache['offs'][$staffId] ?? [], $cache['offs'][0] ?? []),
            'booked' => array_merge(
                $cache['appointments'][$staffId][$dateStr] ?? [],
                $dateStr === Now::get()->format('Y-m-d') ? ($cache['queue'][$staffId] ?? []) : [],
            ),
        ];
    }

    /**
     * همهٔ نوبت‌های زنده، صف امروز و مرخصی‌های پیشِ رو — سه کوئری، یک بار.
     */
    private function salonBusy(int $salonId): array
    {
        if (isset(self::$busyCache[$salonId])) {
            return self::$busyCache[$salonId];
        }

        $now = Now::get();
        $from = $now->modify('-1 day')->format('Y-m-d 00:00:00');

        /*
         * نوبت در انتظار بیعانه هم جا را نگه می‌دارد — تا وقتی مهلتش
         * نگذشته. وگرنه دو نفر هم‌زمان یک سانس را با بیعانه می‌گیرند.
         */
        $rows = DB::select(
            "SELECT a.staff_id, a.scheduled_at,
                    COALESCE(SUM(ai.duration_minutes), ?) AS minutes,
                    COALESCE(SUM(ai.buffer_minutes), 0) AS buffer
               FROM appointments a
               LEFT JOIN appointment_items ai ON ai.appointment_id = a.id
              WHERE a.salon_id = ? AND a.staff_id IS NOT NULL
                AND a.scheduled_at IS NOT NULL AND a.scheduled_at >= ?
                AND (a.status = 'confirmed'
                     OR (a.status = 'pending' AND (a.hold_expires_at IS NULL OR a.hold_expires_at > ?)))
              GROUP BY a.id, a.staff_id, a.scheduled_at",
            [self::FALLBACK_BOOKING_MINUTES, $salonId, $from, $now->format('Y-m-d H:i:s')]
        );

        $appointments = [];
        foreach ($rows as $row) {
            $start = new DateTimeImmutable((string) $row['scheduled_at']);
            $minutes = max(5, (int) $row['minutes']) + max(0, (int) $row['buffer']);
            $appointments[(int) $row['staff_id']][$start->format('Y-m-d')][] = [
                $start,
                $start->modify('+' . $minutes . ' minutes'),
            ];
        }

        /*
         * صفِ همین حالا: کسی روی صندلی و مراجعه‌های منتظر.
         *
         * زمان دقیق‌شان را نمی‌دانیم، پس پشت سر هم چیده می‌شوند و کل
         * بازه از اکنون تا پایان صف اشغال حساب می‌شود. نسخهٔ قبلی هر
         * مراجعه را از لحظهٔ ورودش حساب می‌کرد و نوبت آنلاین درست وسط
         * صفِ واقعی جا می‌گرفت.
         */
        $queueRows = DB::select(
            "SELECT a.staff_id, a.status, a.actual_start_at, a.queued_at,
                    COALESCE(SUM(ai.duration_minutes), ?) AS minutes,
                    COALESCE(SUM(ai.buffer_minutes), 0) AS buffer
               FROM appointments a
               LEFT JOIN appointment_items ai ON ai.appointment_id = a.id
              WHERE a.salon_id = ? AND a.staff_id IS NOT NULL AND a.status IN ('queued','in_chair')
              GROUP BY a.id, a.staff_id, a.status, a.actual_start_at, a.queued_at
              ORDER BY (a.status = 'in_chair') DESC, a.queued_at, a.id",
            [self::FALLBACK_BOOKING_MINUTES, $salonId]
        );

        $chains = [];
        foreach ($queueRows as $row) {
            $staff = (int) $row['staff_id'];
            $minutes = max(5, (int) $row['minutes']) + max(0, (int) $row['buffer']);
            $cursor = $chains[$staff] ?? $now;
            if ($row['status'] === 'in_chair' && $row['actual_start_at'] !== null) {
                $end = (new DateTimeImmutable((string) $row['actual_start_at']))->modify('+' . $minutes . ' minutes');
                $chains[$staff] = $end > $cursor ? $end : $cursor;
            } else {
                $chains[$staff] = $cursor->modify('+' . $minutes . ' minutes');
            }
        }

        $queue = [];
        foreach ($chains as $staff => $end) {
            if ($end > $now) {
                $queue[$staff][] = [$now, $end];
            }
        }

        $offs = [];
        foreach (DB::select(
            'SELECT staff_id, starts_at, ends_at FROM time_offs WHERE salon_id = ? AND ends_at >= ?',
            [$salonId, $from]
        ) as $off) {
            // مرخصی سطح سالن (staff_id تهی) زیر کلید صفر می‌نشیند
            $offs[(int) ($off['staff_id'] ?? 0)][] = [
                new DateTimeImmutable((string) $off['starts_at']),
                new DateTimeImmutable((string) $off['ends_at']),
            ];
        }

        return self::$busyCache[$salonId] = ['appointments' => $appointments, 'queue' => $queue, 'offs' => $offs];
    }

    /** @param array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}> $busy */
    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $busy): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }

    /** تعطیلات رسمی سراسری؛ تعطیلی اختصاصی هر سالن در time_offs است. */
    private function isHoliday(string $dateStr): bool
    {
        if (self::$holidays === null) {
            $from = Now::today()->modify('-1 day')->format('Y-m-d');
            // جدول تعطیلات برای همهٔ سالن‌ها یکی است و کم عوض می‌شود.
            self::$holidays = Cache::remember('holidays:v' . Cache::version('holidays') . ':' . $from, 3600, static fn () => array_flip(array_column(
                DB::select('SELECT gregorian_date FROM holidays WHERE gregorian_date >= ?', [$from]),
                'gregorian_date'
            )));
        }

        return isset(self::$holidays[$dateStr]);
    }

    /** @return array{salon:array<int,array>,staff:array<int,array<int,array>>} */
    private function workingHours(int $salonId): array
    {
        if (isset(self::$hoursCache[$salonId])) {
            return self::$hoursCache[$salonId];
        }

        $ver = Cache::version("salon:$salonId");

        return self::$hoursCache[$salonId] = Cache::remember("salon:$salonId:v$ver:hours", 300, static function () use ($salonId): array {
            $table = ['salon' => [], 'staff' => []];
            foreach (DB::select('SELECT * FROM working_hours WHERE salon_id = ? ORDER BY id', [$salonId]) as $row) {
                $weekday = (int) $row['weekday'];
                if ($row['staff_id'] === null) {
                    $table['salon'][$weekday] = $row;
                } else {
                    $table['staff'][(int) $row['staff_id']][$weekday] = $row;
                }
            }

            return $table;
        });
    }

    /**
     * کشِ درون‌درخواستی را خالی می‌کند — پیش از ثبت نهایی داخل قفل، و
     * بین سناریوهای آزمون.
     */
    public static function flushCache(): void
    {
        self::$settingsCache = [];
        self::$hoursCache = [];
        self::$holidays = null;
        self::$busyCache = [];
        Cache::flushLocal();
    }
}
