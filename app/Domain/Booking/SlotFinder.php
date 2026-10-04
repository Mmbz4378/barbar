<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\DB;
use App\Support\Jalali;
use DateTimeImmutable;

/**
 * جستجوی سانس‌های آزاد برای رزرو آنلاین.
 *
 * ساعت کاری آرایشگر را در آن روز قدم‌به‌قدم می‌پیماید و هر زمان شروعی را
 * نگه می‌دارد که بازهٔ [شروع، شروع + مدت خدمت] با هیچ نوبت رزروشده،
 * مرخصی، یا **استراحت روزانه** تداخل نداشته باشد.
 *
 * طول قدم (سانس‌بندی) از تنظیمات سالن می‌آید، نه از یک عدد ثابت در کد:
 * سالنی که کوتاه‌ترین خدمتش ۲۰ دقیقه است، نباید سانس ۱۵ دقیقه‌ای ببیند.
 */
final class SlotFinder
{
    /** وقتی سالن چیزی تنظیم نکرده باشد. */
    private const DEFAULT_STEP_MINUTES = 15;

    /** نوبتی که خدمتش ثبت نشده — نباید صفر فرض شود. */
    private const FALLBACK_BOOKING_MINUTES = 30;

    /*
     * کشِ درون‌درخواستی.
     *
     * این داده‌ها در طول یک درخواست عوض نمی‌شوند ولی ده‌ها بار خوانده
     * می‌شوند — تقویم مشتری برای هر روزِ ماه یک بار سراغشان می‌رود.
     * ایستا هستند نه نمونه‌ای، چون BookingService برای هر آرایشگر یک
     * SlotFinder تازه نمی‌سازد ولی چند نمونه در یک درخواست ممکن است.
     */
    /** @var array<int,array{salon:array,staff:array}> */
    private static array $hoursCache = [];

    /** @var array<string,int>|null */
    private static ?array $holidays = null;

    /** @var array<int,int> */
    private static array $stepCache = [];

    /** @var array<int,array{appointments:array,offs:array}> */
    private static array $busyCache = [];

    /** @return string[] ساعت شروع سانس‌ها، به شکل «ساعت:دقیقه» */
    public function freeSlotsForStaff(int $salonId, int $staffId, DateTimeImmutable $date, int $durationMinutes): array
    {
        $weekday = Jalali::weekday($date);

        $table = $this->workingHours($salonId);
        $hours = $this->mergeHours(
            $table['salon'][$weekday] ?? null,
            $table['staff'][$staffId][$weekday] ?? null
        );

        if ($hours === null || (int) $hours['is_closed'] === 1) {
            return [];
        }

        $dateStr = $date->format('Y-m-d');
        if ($this->isHoliday($dateStr)) {
            return [];
        }

        $dayStart = new DateTimeImmutable($dateStr . ' ' . $hours['opens_at']);
        $dayEnd = new DateTimeImmutable($dateStr . ' ' . $hours['closes_at']);
        $now = new DateTimeImmutable();

        $busy = $this->busyIntervals($salonId, $staffId, $dateStr);

        // استراحت روزانه مثل یک نوبتِ اشغال رفتار می‌کند. اگر اینجا
        // نیاید، مشتری برای ساعتی نوبت می‌گیرد که کسی سر کار نیست.
        if (!empty($hours['break_start']) && !empty($hours['break_end'])) {
            $breakFrom = new DateTimeImmutable($dateStr . ' ' . $hours['break_start']);
            $breakTo = new DateTimeImmutable($dateStr . ' ' . $hours['break_end']);
            if ($breakTo > $breakFrom) {
                $busy[] = [$breakFrom, $breakTo];
            }
        }

        $step = $this->stepMinutes($salonId);

        $slots = [];
        $cursor = $dayStart;
        while ($cursor->modify("+{$durationMinutes} minutes") <= $dayEnd) {
            $slotEnd = $cursor->modify("+{$durationMinutes} minutes");
            if ($cursor > $now && !$this->overlaps($cursor, $slotEnd, $busy)) {
                $slots[] = $cursor->format('H:i');
            }
            $cursor = $cursor->modify('+' . $step . ' minutes');
        }

        return $slots;
    }

    /**
     * ساعت کاری آرایشگر روی ساعت کاری سالن.
     *
     * ردیف اختصاصی آرایشگر، ساعت باز و بستهٔ خودش را تعیین می‌کند. ولی
     * **استراحت را پاک نمی‌کند**: تعطیلی ظهر یک واقعیتِ سطحِ سالن است
     * (مغازه بسته است)، نه سلیقهٔ یک آرایشگر. اگر ردیف آرایشگر استراحت
     * نداشته باشد، استراحت سالن سر جایش می‌ماند.
     *
     * بدون این ادغام، لحظه‌ای که برای یک آرایشگر ساعت اختصاصی ثبت شود،
     * استراحتی که صاحب سالن در تنظیمات گذاشته بی‌صدا از کار می‌افتد و
     * مشتری برای وسط تعطیلی نوبت می‌گیرد.
     *
     * @param array<string,mixed>|null $salon
     * @param array<string,mixed>|null $staff
     * @return array<string,mixed>|null
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
     * طول سانس سالن، با حفاظ.
     *
     * صفر یا منفی، حلقهٔ بالا را بی‌نهایت می‌کند؛ عدد خیلی بزرگ هم عملاً
     * رزرو را غیرممکن. پس بین ۵ تا ۱۲۰ دقیقه بریده می‌شود.
     *
     * عمومی است چون ویزارد رزرو، سانس‌ها را پیش از انتخاب خدمت
     * می‌سازد و باید طول سانس را بداند.
     */
    public function stepMinutes(int $salonId): int
    {
        if (!isset(self::$stepCache[$salonId])) {
            $row = DB::selectOne('SELECT slot_step_minutes FROM salons WHERE id = ?', [$salonId]);
            $value = (int) ($row['slot_step_minutes'] ?? self::DEFAULT_STEP_MINUTES);
            self::$stepCache[$salonId] = max(5, min(120, $value ?: self::DEFAULT_STEP_MINUTES));
        }

        return self::$stepCache[$salonId];
    }

    /**
     * بازه‌های اشغال یک آرایشگر در یک روز.
     *
     * از کشِ سالن خوانده می‌شود، نه با کوئریِ تازه. پیش از این برای هر
     * آرایشگر و هر روز دو کوئری می‌رفت: تقویم یک‌ماهه با سه آرایشگر
     * یعنی ۱۸۰ کوئری، در حالی که کل دادهٔ لازم چند ده ردیف است.
     *
     * @return array<int,array{0:DateTimeImmutable,1:DateTimeImmutable}>
     */
    private function busyIntervals(int $salonId, int $staffId, string $dateStr): array
    {
        $cache = $this->salonBusy($salonId);

        return array_merge(
            $cache['appointments'][$staffId][$dateStr] ?? [],
            $cache['offs'][$staffId] ?? [],
            $cache['offs'][0] ?? [],  // مرخصی کل سالن
        );
    }

    /**
     * همهٔ نوبت‌های زنده و مرخصی‌های سالن — دو کوئری، یک بار.
     *
     * محدودهٔ زمانی لازم نیست: فیلترِ وضعیت خودش بازه را می‌بندد.
     * «تأییدشده، در صف، روی صندلی» یعنی نوبت‌های امروز و آینده؛
     * تمام‌شده‌ها و لغوشده‌ها — که انبوه‌اند — اصلاً نمی‌آیند.
     *
     * @return array{appointments:array<int,array<string,array>>,offs:array<int,array>}
     */
    private function salonBusy(int $salonId): array
    {
        if (isset(self::$busyCache[$salonId])) {
            return self::$busyCache[$salonId];
        }

        /*
         * مدت واقعی از روی خدمت‌های همان نوبت جمع می‌شود.
         *
         * پیش از این عدد ثابت ۳۰ دقیقه بود — یعنی یک نوبتِ رنگِ
         * ۹۰ دقیقه‌ای فقط نیم‌ساعت از تقویم را می‌بست و مشتری بعدی
         * می‌توانست وسطش نوبت بگیرد. آرایشگر دو نفر را هم‌زمان روی
         * یک صندلی داشت و تا لحظهٔ آمدنشان نمی‌فهمید.
         */
        $rows = DB::select(
            "SELECT a.staff_id,
                    COALESCE(a.scheduled_at, a.queued_at) AS starts_at,
                    COALESCE(SUM(ai.duration_minutes), ?) AS minutes
             FROM appointments a
             LEFT JOIN appointment_items ai ON ai.appointment_id = a.id
             WHERE a.salon_id = ? AND a.staff_id IS NOT NULL
               AND a.status IN ('confirmed','queued','in_chair')
             GROUP BY a.id, a.staff_id, starts_at",
            [self::FALLBACK_BOOKING_MINUTES, $salonId]
        );

        $appointments = [];
        foreach ($rows as $row) {
            if ($row['starts_at'] === null) {
                continue;
            }
            $start = new DateTimeImmutable($row['starts_at']);
            $minutes = max(5, (int) $row['minutes']);
            $appointments[(int) $row['staff_id']][$start->format('Y-m-d')][] = [
                $start,
                $start->modify('+' . $minutes . ' minutes'),
            ];
        }

        $offs = [];
        foreach (DB::select('SELECT staff_id, starts_at, ends_at FROM time_offs WHERE salon_id = ?', [$salonId]) as $off) {
            // مرخصی سطح سالن (staff_id تهی) زیر کلید صفر می‌نشیند
            $offs[(int) ($off['staff_id'] ?? 0)][] = [
                new DateTimeImmutable($off['starts_at']),
                new DateTimeImmutable($off['ends_at']),
            ];
        }

        return self::$busyCache[$salonId] = ['appointments' => $appointments, 'offs' => $offs];
    }

    private function overlaps(DateTimeImmutable $start, DateTimeImmutable $end, array $busy): bool
    {
        foreach ($busy as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * تعطیلات، یک بار برای همیشه.
     *
     * تقویم مشتری برای هر روزِ ماه یک بار سانس‌ها را می‌پرسد. با کوئری
     * به‌ازای روز، همین یک صفحه ۷۸ کوئریِ تعطیلات می‌زد.
     */
    private function isHoliday(string $dateStr): bool
    {
        if (self::$holidays === null) {
            self::$holidays = array_flip(array_column(
                DB::select('SELECT gregorian_date FROM holidays'),
                'gregorian_date'
            ));
        }

        return isset(self::$holidays[$dateStr]);
    }

    /**
     * ساعت کاری سالن و آرایشگرها — یک کوئری برای کل سالن.
     *
     * پیش از این، هر روزِ تقویم دو کوئری می‌زد (یکی سطح سالن، یکی سطح
     * آرایشگر). برای یک ماه با سه آرایشگر می‌شد ۱۸۰ کوئری، در حالی که
     * کل جدول برای یک سالن حداکثر چند ده ردیف است.
     *
     * @return array{salon:array<int,array>,staff:array<int,array<int,array>>}
     */
    private function workingHours(int $salonId): array
    {
        if (isset(self::$hoursCache[$salonId])) {
            return self::$hoursCache[$salonId];
        }

        $rows = DB::select('SELECT * FROM working_hours WHERE salon_id = ?', [$salonId]);

        $table = ['salon' => [], 'staff' => []];
        foreach ($rows as $row) {
            $weekday = (int) $row['weekday'];
            if ($row['staff_id'] === null) {
                $table['salon'][$weekday] = $row;
            } else {
                $table['staff'][(int) $row['staff_id']][$weekday] = $row;
            }
        }

        return self::$hoursCache[$salonId] = $table;
    }

    /**
     * کشِ درون‌درخواستی را خالی می‌کند.
     *
     * تنها جایی که لازم است، تست است: در یک درخواست واقعی، ساعت کاری
     * وسط کار عوض نمی‌شود، ولی در تست چند سناریو پشت سر هم می‌آیند.
     */
    public static function flushCache(): void
    {
        self::$hoursCache = [];
        self::$holidays = null;
        self::$stepCache = [];
        self::$busyCache = [];
    }
}
