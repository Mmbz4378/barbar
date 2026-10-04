<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;
use DateTimeImmutable;

/**
 * صف مرتب‌شدهٔ یک آرایشگر را به ساعت‌های وعده‌داده‌شده تبدیل می‌کند.
 *
 *   باقی‌ماندهٔ نفر روی صندلی = بیشینهٔ (کف، انتظاری − گذشته)
 *   ساعت نفر i = اکنون + باقی‌مانده + جمعِ (انتظاری + فاصله) برای نفرات جلوتر
 *   کران بالا = همان فرمول، با p80 به‌جای p50
 *
 * دو محدودیت عمدی:
 *
 * هرگز «همین حالا» نمی‌گوید (کف دو دقیقه) — چون مشتری‌ای که «همین
 * حالا» می‌شنود و می‌بیند هنوز کسی روی صندلی است، دیگر به هیچ عددی
 * اعتماد نمی‌کند.
 *
 * و جلوتر از سه ساعت تخمین نمی‌زند. آن‌طرف‌تر خطا آن‌قدر بزرگ می‌شود
 * که عدد دادن بدتر از عدد ندادن است.
 */
final class EtaEngine
{
    private DurationEstimator $estimator;

    public function __construct(?DurationEstimator $estimator = null)
    {
        $this->estimator = $estimator ?? new DurationEstimator();
    }

    /**
     * @param array<int,array> $orderedAppointments نوبت‌های *یک* آرایشگر، از قبل مرتب‌شده
     * @return array<int,array{expected_p50:float,expected_p80:float,start_p50:DateTimeImmutable,start_p80:DateTimeImmutable,position:int}>
     *         کلید: شناسهٔ نوبت
     */
    public function computeForStaffQueue(array $orderedAppointments, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable();
        $bufferMinutes = (float) Config::get('reshen.queue.buffer_minutes', 5);
        $minRemaining = (float) Config::get('reshen.queue.min_remaining_minutes', 2);

        $results = [];
        $cursorP50 = $now;
        $cursorP80 = $now;
        $position = 0;

        /*
         * خدمت‌ها و مشتری‌ها را **یک‌جا** می‌گیریم، نه به‌ازای هر نوبت.
         *
         * صفحهٔ صف هر ۱۵ ثانیه تازه می‌شود. با کوئری به‌ازای نوبت، یک
         * سالن شلوغ با ۳۰ نفر در صف، هر ۱۵ ثانیه بیش از صد کوئری
         * می‌زد — روی هاست اشتراکی همین کافی است که صفحه کند شود.
         */
        [$itemsByAppointment, $customersById] = $this->preload($orderedAppointments);

        foreach ($orderedAppointments as $appt) {
            $serviceIds = $itemsByAppointment[(int) $appt['id']] ?? [];
            $customer = $appt['staff_id'] !== null
                ? ($customersById[(int) $appt['customer_id']] ?? null)
                : null;

            $expected = $serviceIds !== [] && $appt['staff_id'] !== null
                ? $this->estimator->forAppointment((int) $appt['staff_id'], $serviceIds, $customer)
                : ['p50' => (float) Config::get('reshen.estimation.fallback_minutes', 30), 'p80' => (float) Config::get('reshen.estimation.fallback_minutes', 30) * 1.3];

            if ($appt['status'] === 'in_chair' && $appt['actual_start_at'] !== null) {
                /*
                 * از $now خوانده می‌شود نه time().
                 *
                 * متد $now می‌گیرد تا قابل آزمودن باشد، ولی اینجا ساعت
                 * واقعی سیستم را می‌خواند — یعنی در شبیه‌سازی یک روز
                 * کاری، «گذشته از شروع» عددی نجومی می‌شد و تخمین کل صف
                 * به هم می‌ریخت. در تولید هم اگر $now کمی عقب‌تر از
                 * الان پاس داده شود، دو مبنای زمانی با هم می‌جنگند.
                 */
                $elapsedMinutes = ($now->getTimestamp() - strtotime($appt['actual_start_at'])) / 60;
                $remainingP50 = max($minRemaining, $expected['p50'] - $elapsedMinutes);
                $remainingP80 = max($minRemaining, $expected['p80'] - $elapsedMinutes);

                $startP50 = $now;
                $startP80 = $now;
                $cursorP50 = $now->modify('+' . (int) round($remainingP50) . ' minutes');
                $cursorP80 = $now->modify('+' . (int) round($remainingP80) . ' minutes');
            } else {
                $startP50 = $cursorP50;
                $startP80 = $cursorP80;
                $cursorP50 = $cursorP50->modify('+' . (int) round($expected['p50'] + $bufferMinutes) . ' minutes');
                $cursorP80 = $cursorP80->modify('+' . (int) round($expected['p80'] + $bufferMinutes) . ' minutes');
            }

            $results[(int) $appt['id']] = [
                'expected_p50' => $expected['p50'],
                'expected_p80' => $expected['p80'],
                'start_p50' => $startP50,
                'start_p80' => $startP80,
                'position' => $position,
            ];
            $position++;
        }

        return $results;
    }

    /**
     * خدمت‌های هر نوبت و مشتری‌های صف، در دو کوئری.
     *
     * @param array<int,array> $appointments
     * @return array{0:array<int,int[]>,1:array<int,array>}
     */
    private function preload(array $appointments): array
    {
        if ($appointments === []) {
            return [[], []];
        }

        $appointmentIds = array_map(static fn ($a) => (int) $a['id'], $appointments);

        /*
         * سالن از خودِ نوبت‌ها گرفته می‌شود تا کوئریِ مشتری‌ها هم
         * محدود به همان سالن باشد.
         *
         * تا الان امن بود ولی **به‌طور ضمنی**: شناسه‌ها از فهرستی
         * می‌آمدند که خودش با salon_id فیلتر شده بود. اگر روزی کسی
         * این متد را از جای دیگری صدا بزند، آن فرض بی‌صدا می‌شکند —
         * و انزوای مستأجر چیزی نیست که به فرض بسپاریم.
         */
        $salonId = (int) ($appointments[array_key_first($appointments)]['salon_id'] ?? 0);
        $customerIds = array_values(array_unique(array_filter(
            array_map(static fn ($a) => (int) $a['customer_id'], $appointments)
        )));

        $items = [];
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        foreach (DB::select(
            "SELECT appointment_id, service_id FROM appointment_items WHERE appointment_id IN ({$placeholders})",
            $appointmentIds
        ) as $row) {
            $items[(int) $row['appointment_id']][] = (int) $row['service_id'];
        }

        $customers = [];
        if ($customerIds !== [] && $salonId > 0) {
            $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
            foreach (DB::select(
                "SELECT id, duration_factor FROM customers WHERE salon_id = ? AND id IN ({$placeholders})",
                array_merge([$salonId], $customerIds)
            ) as $row) {
                $customers[(int) $row['id']] = $row;
            }
        }

        return [$items, $customers];
    }

    /** Human display text per doc 8.6 "قواعد نمایش". */
    public function displayText(int $position, DateTimeImmutable $startP50, DateTimeImmutable $startP80, DateTimeImmutable $now): array
    {
        if ($position === 0) {
            return ['text' => 'نوبت بعدی توست', 'rough' => false];
        }

        $minutesP50 = max(0, (int) round(($startP50->getTimestamp() - $now->getTimestamp()) / 60));
        $minutesP80 = max($minutesP50, (int) round(($startP80->getTimestamp() - $now->getTimestamp()) / 60));

        $imminent = (int) Config::get('reshen.display.imminent_threshold_minutes', 15);
        $far = (int) Config::get('reshen.display.far_threshold_minutes', 60);
        $maxWindow = (int) Config::get('reshen.display.max_window_minutes', 25);

        if ($minutesP50 < $imminent) {
            return ['text' => "حدود {$this->fa($minutesP50)} دقیقهٔ دیگر", 'rough' => false];
        }

        $window = $minutesP80 - $minutesP50;
        $rough = false;
        if ($window > $maxWindow) {
            $minutesP80 = $minutesP50 + $maxWindow;
            $rough = true;
        }

        if ($minutesP50 < $far) {
            return ['text' => "{$this->fa($minutesP50)} تا {$this->fa($minutesP80)} دقیقهٔ دیگر", 'rough' => $rough];
        }

        $timeFrom = $startP50->format('H:i');
        $timeToStr = $rough
            ? (clone $startP50)->modify('+' . $maxWindow . ' minutes')->format('H:i')
            : $startP80->format('H:i');

        return ['text' => "حدود ساعت {$this->fa($timeFrom)} تا {$this->fa($timeToStr)}", 'rough' => $rough];
    }

    private function fa(int|string $v): string
    {
        return \App\Support\Jalali::toPersianDigits((string) $v);
    }
}
