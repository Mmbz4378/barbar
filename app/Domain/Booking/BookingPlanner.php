<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Domain\Catalog\ServiceRepository;
use App\Support\Now;
use DateTimeImmutable;

/**
 * از «این خدمات، این ساعت» به یک برنامهٔ اجراشدنی می‌رسد.
 *
 * دو حالت دارد:
 *
 *   یک‌نفره — یک نفر همهٔ خدمات را پشت سر هم انجام می‌دهد. حالت معمولِ
 *   آرایشگاه مردانه (کوتاهی + ریش) و هر جایی که یک نفر از پس همه
 *   برمی‌آید.
 *
 *   چندنفره — هیچ‌کس همهٔ خدمات را انجام نمی‌دهد (رنگ مو با رنگ‌کار،
 *   مانیکور با ناخن‌کار). خدمات به ترتیب دسته‌ها پشت سر هم چیده
 *   می‌شوند و هر بخش به کسی می‌رسد که آن را انجام می‌دهد. هر بخش یک
 *   نوبت عادی است که با group_token به بقیه وصل می‌شود، پس صف، تسویه و
 *   گزارش‌ها بدون تغییر کار می‌کنند.
 *
 * عمداً فقط وقتی چندنفره می‌شود که «توانایی» ایجاب کند، نه وقتی کسی
 * «سرش شلوغ است»: در آرایشگاه، کوتاهی با یک نفر و ریش با نفر دیگر،
 * تجربهٔ عجیبی است که مشتری نخواسته.
 */
final class BookingPlanner
{
    private SlotFinder $slots;

    private ServiceRepository $services;

    public function __construct(?SlotFinder $slots = null, ?ServiceRepository $services = null)
    {
        $this->slots = $slots ?? new SlotFinder();
        $this->services = $services ?? new ServiceRepository();
    }

    /**
     * خدمات انتخاب‌شده قابل رزروند؟
     *
     * @param int[] $serviceIds
     * @return string|null پیام خطا برای کاربر، یا null
     */
    public function validate(int $salonId, array $serviceIds, bool $online): ?string
    {
        $serviceIds = self::normalizeIds($serviceIds);
        if ($serviceIds === []) {
            return 'حداقل یک خدمت انتخاب کنید.';
        }

        $matrix = $this->services->matrix($salonId);
        foreach ($serviceIds as $id) {
            $service = $matrix['services'][$id] ?? null;
            if ($service === null || (int) $service['is_active'] !== 1) {
                return 'یکی از خدمات انتخاب‌شده دیگر ارائه نمی‌شود. لطفاً دوباره انتخاب کنید.';
            }
            if ($online && (int) $service['online_booking'] !== 1) {
                return '«' . $service['name'] . '» فقط با هماهنگی تلفنی رزرو می‌شود.';
            }
            if ($this->services->staffFor($salonId, $id, $online) === []) {
                return 'در حال حاضر کسی «' . $service['name'] . '» را انجام نمی‌دهد.';
            }
        }

        return null;
    }

    /**
     * کسانی که به‌تنهایی همهٔ این خدمات را انجام می‌دهند.
     *
     * @param int[] $serviceIds
     * @return int[]
     */
    public function capableStaff(int $salonId, array $serviceIds, bool $online): array
    {
        $serviceIds = self::normalizeIds($serviceIds);
        $capable = null;
        foreach ($serviceIds as $id) {
            $staff = $this->services->staffFor($salonId, $id, $online);
            $capable = $capable === null ? $staff : array_values(array_intersect($capable, $staff));
        }

        return $capable ?? [];
    }

    /** آیا این ترکیب فقط با چند نفر ممکن است؟ */
    public function needsMultipleStaff(int $salonId, array $serviceIds, bool $online): bool
    {
        return $this->validate($salonId, $serviceIds, $online) === null
            && $this->capableStaff($salonId, $serviceIds, $online) === [];
    }

    /**
     * برنامهٔ اجرا برای یک ساعت مشخص، یا null اگر ممکن نیست.
     *
     * @param int[] $serviceIds
     * @return array{segments:array<int,array>,start:DateTimeImmutable,end:DateTimeImmutable,price:int,price_from:bool,deposit:int,minutes:int,multi:bool}|null
     */
    public function plan(int $salonId, array $serviceIds, DateTimeImmutable $start, ?int $staffId, bool $online): ?array
    {
        $serviceIds = $this->ordered($salonId, self::normalizeIds($serviceIds));
        if ($serviceIds === [] || !$this->withinPolicy($salonId, $start, $online)) {
            return null;
        }

        $capable = $this->capableStaff($salonId, $serviceIds, $online);

        if ($staffId !== null) {
            if (!in_array($staffId, $capable, true)) {
                return null;
            }

            return $this->single($salonId, $serviceIds, $start, $staffId);
        }

        if ($capable !== []) {
            foreach ($this->byLoad($salonId, $capable, $start) as $candidate) {
                $plan = $this->single($salonId, $serviceIds, $start, $candidate);
                if ($plan !== null) {
                    return $plan;
                }
            }

            return null;
        }

        return $this->sequential($salonId, $serviceIds, $start, $online);
    }

    /**
     * ساعت‌های شروعی که برایشان برنامه وجود دارد.
     *
     * @param int[] $serviceIds
     * @return string[] «ساعت:دقیقه»
     */
    public function availableTimes(int $salonId, array $serviceIds, DateTimeImmutable $date, ?int $staffId, bool $online, bool $firstOnly = false): array
    {
        $serviceIds = self::normalizeIds($serviceIds);
        if ($serviceIds === [] || !$this->dateWithinHorizon($salonId, $date, $online)) {
            return [];
        }

        $staffIds = $staffId !== null ? [$staffId] : $this->relevantStaff($salonId, $serviceIds, $online);
        if ($staffIds === []) {
            return [];
        }

        $times = [];
        foreach ($this->slots->candidateStarts($salonId, $date, $staffIds, $this->notBefore($salonId, $online)) as $start) {
            if ($this->plan($salonId, $serviceIds, $start, $staffId, $online) !== null) {
                $times[] = $start->format('H:i');
                if ($firstOnly) {
                    break;
                }
            }
        }

        return $times;
    }

    /**
     * ساعت‌های آزادِ «هر کسی» برای مسیرِ اول-زمان، پیش از انتخاب خدمت.
     *
     * مبنا طول سانس سالن است چون خدمت هنوز معلوم نیست؛ جا شدن واقعی
     * خدمت در گام‌های بعد سنجیده می‌شود.
     *
     * @return string[]
     */
    public function openTimes(int $salonId, DateTimeImmutable $date, bool $online, bool $firstOnly = false): array
    {
        if (!$this->dateWithinHorizon($salonId, $date, $online)) {
            return [];
        }

        $minutes = $this->slots->stepMinutes($salonId);
        $notBefore = $this->notBefore($salonId, $online);
        $times = [];
        foreach ($this->activeStaff($salonId, $online) as $staffId) {
            foreach ($this->slots->freeSlotsForStaff($salonId, $staffId, $date, $minutes, 0, $notBefore, $firstOnly) as $time) {
                $times[$time] = true;
                if ($firstOnly) {
                    return [$time];
                }
            }
        }
        ksort($times);

        return array_keys($times);
    }

    /**
     * کمینه و بیشینهٔ قیمت بین کسانی که می‌توانند — پیش از انتخاب فرد.
     *
     * @param int[] $serviceIds
     * @return array{min:int,max:int,from:bool,minutes_min:int,minutes_max:int,deposit:int}
     */
    public function estimate(int $salonId, array $serviceIds, ?int $staffId, bool $online): array
    {
        $serviceIds = self::normalizeIds($serviceIds);
        $matrix = $this->services->matrix($salonId);
        $from = false;
        $deposit = 0;
        foreach ($serviceIds as $id) {
            $from = $from || (($matrix['services'][$id]['price_type'] ?? 'fixed') === 'from');
            $deposit += (int) ($matrix['services'][$id]['deposit_amount'] ?? 0);
        }

        $candidates = $staffId !== null ? [$staffId] : $this->capableStaff($salonId, $serviceIds, $online);
        $prices = [];
        $minutes = [];

        if ($candidates !== []) {
            foreach ($candidates as $candidate) {
                $p = 0;
                $m = 0;
                foreach ($serviceIds as $id) {
                    $e = $this->services->effective($salonId, $candidate, $id);
                    $p += $e['price'];
                    $m += $e['duration_minutes'];
                }
                $prices[] = $p;
                $minutes[] = $m;
            }
        } else {
            // چندنفره: برای هر خدمت، بازهٔ قیمت بین توانایان
            $pMin = $pMax = $mMin = $mMax = 0;
            foreach ($serviceIds as $id) {
                $ps = [];
                $ms = [];
                foreach ($this->services->staffFor($salonId, $id, $online) as $candidate) {
                    $e = $this->services->effective($salonId, $candidate, $id);
                    $ps[] = $e['price'];
                    $ms[] = $e['duration_minutes'];
                }
                if ($ps === []) {
                    $ps = [(int) ($matrix['services'][$id]['price'] ?? 0)];
                    $ms = [(int) ($matrix['services'][$id]['duration_minutes'] ?? 30)];
                }
                $pMin += min($ps);
                $pMax += max($ps);
                $mMin += min($ms);
                $mMax += max($ms);
            }
            $prices = [$pMin, $pMax];
            $minutes = [$mMin, $mMax];
        }

        return [
            'min' => $prices === [] ? 0 : min($prices),
            'max' => $prices === [] ? 0 : max($prices),
            'from' => $from,
            'minutes_min' => $minutes === [] ? 0 : min($minutes),
            'minutes_max' => $minutes === [] ? 0 : max($minutes),
            'deposit' => $deposit,
        ];
    }

    /** آیا این روز در بازهٔ رزروپذیر سالن است؟ */
    public function dateWithinHorizon(int $salonId, DateTimeImmutable $date, bool $online): bool
    {
        $today = Now::today();
        $day = $date->setTime(0, 0);
        if ($day < $today) {
            return false;
        }

        // پذیرش می‌تواند دورتر از افق آنلاین هم نوبت بدهد
        $horizon = $online ? $this->slots->settings($salonId)['horizon'] : 365;

        return $day <= $today->modify('+' . $horizon . ' days');
    }

    /**
     * زودترین لحظه‌ای که نوبت می‌تواند شروع شود.
     *
     * آنلاین: اکنون + حداقل فاصلهٔ سالن (مثلاً «تا دو ساعت مانده
     * رزرو آنلاین نداریم»). پذیرش این محدودیت را ندارد.
     */
    public function notBefore(int $salonId, bool $online): DateTimeImmutable
    {
        $notice = $online ? $this->slots->settings($salonId)['min_notice'] : 0;

        return Now::get()->modify('+' . $notice . ' minutes');
    }

    private function withinPolicy(int $salonId, DateTimeImmutable $start, bool $online): bool
    {
        return $this->dateWithinHorizon($salonId, $start, $online) && $start > $this->notBefore($salonId, $online);
    }

    /** @param int[] $serviceIds */
    private function single(int $salonId, array $serviceIds, DateTimeImmutable $start, int $staffId): ?array
    {
        $segment = $this->segment($salonId, $staffId, $serviceIds, $start);
        if (!$this->slots->isFree($salonId, $staffId, $start, $segment['minutes'], $segment['buffer'])) {
            return null;
        }

        return $this->finish($salonId, [$segment]);
    }

    /**
     * چیدن پشت‌سرهم بین چند نفر.
     *
     * در هر قدم، کسی انتخاب می‌شود که بیشترین تعداد خدمتِ پشت‌سرهمِ
     * باقی‌مانده را می‌تواند انجام دهد و برایش آزاد است — تا مشتری کمتر
     * دست‌به‌دست شود.
     *
     * @param int[] $serviceIds
     */
    private function sequential(int $salonId, array $serviceIds, DateTimeImmutable $start, bool $online): ?array
    {
        $segments = [];
        $cursor = $start;
        $remaining = $serviceIds;

        while ($remaining !== []) {
            $best = null;
            foreach ($this->byLoad($salonId, $this->services->staffFor($salonId, $remaining[0], $online), $cursor) as $candidate) {
                $run = [];
                foreach ($remaining as $id) {
                    if (!in_array($candidate, $this->services->staffFor($salonId, $id, $online), true)) {
                        break;
                    }
                    $trial = array_merge($run, [$id]);
                    $segment = $this->segment($salonId, $candidate, $trial, $cursor);
                    if (!$this->slots->isFree($salonId, $candidate, $cursor, $segment['minutes'], $segment['buffer'])) {
                        break;
                    }
                    $run = $trial;
                }
                if ($run !== [] && ($best === null || count($run) > count($best['run']))) {
                    $best = ['staff' => $candidate, 'run' => $run];
                }
            }

            if ($best === null) {
                return null;
            }

            $segment = $this->segment($salonId, $best['staff'], $best['run'], $cursor);
            $segments[] = $segment;
            $cursor = $segment['end'];
            $remaining = array_values(array_slice($remaining, count($best['run'])));
        }

        return $this->finish($salonId, $segments);
    }

    /** @param int[] $serviceIds */
    private function segment(int $salonId, int $staffId, array $serviceIds, DateTimeImmutable $start): array
    {
        $items = [];
        $minutes = 0;
        $buffer = 0;
        $price = 0;
        $matrix = $this->services->matrix($salonId);
        foreach ($serviceIds as $id) {
            $e = $this->services->effective($salonId, $staffId, $id);
            $items[] = [
                'service_id' => $id,
                'name' => (string) ($matrix['services'][$id]['name'] ?? ''),
                'price_type' => (string) ($matrix['services'][$id]['price_type'] ?? 'fixed'),
                'price' => $e['price'],
                'minutes' => $e['duration_minutes'],
                'buffer' => $e['buffer_minutes'],
            ];
            $minutes += $e['duration_minutes'];
            $price += $e['price'];
            // آماده‌سازی فقط پس از آخرین خدمتِ هر نفر معنا دارد؛ بیشینه را نگه می‌داریم
            $buffer = max($buffer, $e['buffer_minutes']);
        }
        $minutes = max(5, $minutes);

        return [
            'staff_id' => $staffId,
            'service_ids' => $serviceIds,
            'items' => $items,
            'start' => $start,
            'end' => $start->modify('+' . $minutes . ' minutes'),
            'minutes' => $minutes,
            'buffer' => $buffer,
            'price' => $price,
        ];
    }

    private function finish(int $salonId, array $segments): array
    {
        $matrix = $this->services->matrix($salonId);
        $price = 0;
        $from = false;
        $deposit = 0;
        foreach ($segments as $i => $segment) {
            $price += $segment['price'];
            $segments[$i]['staff_name'] = (string) ($matrix['staff'][$segment['staff_id']]['name'] ?? '');
            foreach ($segment['service_ids'] as $id) {
                $from = $from || (($matrix['services'][$id]['price_type'] ?? 'fixed') === 'from');
                $deposit += (int) ($matrix['services'][$id]['deposit_amount'] ?? 0);
            }
        }

        $first = $segments[0];
        $last = $segments[count($segments) - 1];

        return [
            'segments' => $segments,
            'start' => $first['start'],
            'end' => $last['end'],
            'price' => $price,
            'price_from' => $from,
            'deposit' => $deposit,
            'minutes' => (int) (($last['end']->getTimestamp() - $first['start']->getTimestamp()) / 60),
            'multi' => count($segments) > 1,
        ];
    }

    /**
     * کم‌کارترین اول — نوبت‌های «فرقی نمی‌کند» عادلانه پخش شوند، نه
     * اینکه همه به نفر اول فهرست برسند.
     *
     * @param int[] $staffIds
     * @return int[]
     */
    private function byLoad(int $salonId, array $staffIds, DateTimeImmutable $day): array
    {
        $dateStr = $day->format('Y-m-d');
        $matrix = $this->services->matrix($salonId);
        $load = [];
        foreach ($staffIds as $id) {
            $load[$id] = $this->slots->bookedMinutes($salonId, $id, $dateStr);
        }
        usort($staffIds, static function (int $a, int $b) use ($load, $matrix): int {
            return [$load[$a], (int) ($matrix['staff'][$a]['sort_order'] ?? 0), $a]
                <=> [$load[$b], (int) ($matrix['staff'][$b]['sort_order'] ?? 0), $b];
        });

        return $staffIds;
    }

    /**
     * خدمات به ترتیب منوی سالن (ترتیب دسته، بعد ترتیب خدمت) — در
     * برنامهٔ چندنفره همین ترتیب اجرا می‌شود.
     *
     * @param int[] $serviceIds
     * @return int[]
     */
    private function ordered(int $salonId, array $serviceIds): array
    {
        $matrix = $this->services->matrix($salonId);
        $position = array_flip(array_keys($matrix['services']));
        $serviceIds = array_values(array_filter($serviceIds, static fn (int $id): bool => isset($position[$id])));
        usort($serviceIds, static fn (int $a, int $b): int => $position[$a] <=> $position[$b]);

        return $serviceIds;
    }

    /** @return int[] */
    private function relevantStaff(int $salonId, array $serviceIds, bool $online): array
    {
        $ids = [];
        foreach ($serviceIds as $id) {
            foreach ($this->services->staffFor($salonId, $id, $online) as $staffId) {
                $ids[$staffId] = true;
            }
        }

        return array_keys($ids);
    }

    /** @return int[] */
    private function activeStaff(int $salonId, bool $online): array
    {
        $ids = [];
        foreach ($this->services->matrix($salonId)['staff'] as $id => $staff) {
            if ((int) $staff['is_active'] === 1 && (!$online || (int) $staff['accepts_online'] === 1)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }

    /** @return int[] */
    public static function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
