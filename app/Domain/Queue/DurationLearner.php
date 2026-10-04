<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;

/**
 * Feeds the estimation engine's memory every time a service is marked
 * "تمام شد" (doc 8.6 "یادگیری"):
 *   - outliers (<5 or >180 min) are dropped before they poison the stats
 *   - only single-service appointments update per-(staff,service) percentiles
 *     — with a bundle we don't know which service took how long
 *   - the customer's personal pace factor updates from the WHOLE
 *     appointment (bundle or not), against the *unadjusted* baseline, so
 *     the factor never compounds against its own previous value
 *   - percentiles are recomputed over a rolling window of the most recent
 *     samples, so a barber who's gotten faster isn't dragged down by data
 *     from a year ago
 */
final class DurationLearner
{
    public function recordCompletion(int $salonId, array $appointment, array $items, ?array $customer): void
    {
        $estimator = new DurationEstimator();
        $outlierMin = (float) Config::get('reshen.estimation.outlier_min_minutes', 5);
        $outlierMax = (float) Config::get('reshen.estimation.outlier_max_minutes', 180);

        $actualMinutes = (strtotime($appointment['actual_end_at']) - strtotime($appointment['actual_start_at'])) / 60;

        if ($actualMinutes >= $outlierMin && $actualMinutes <= $outlierMax) {
            if (count($items) === 1) {
                $this->addSample($salonId, (int) $appointment['staff_id'], (int) $items[0]['service_id'], (int) $appointment['id'], $actualMinutes);
                $this->recomputePercentiles((int) $appointment['staff_id'], (int) $items[0]['service_id']);
            }

            $this->updateCustomerFactor($salonId, $customer, (int) $appointment['staff_id'], array_column($items, 'service_id'), $actualMinutes, $estimator);
        }

        DB::update(
            'customers',
            ['visit_count' => (int) ($customer['visit_count'] ?? 0) + 1, 'last_visit_at' => date('Y-m-d H:i:s')],
            'id = :id AND salon_id = :salon_id',
            ['id' => $customer['id'], 'salon_id' => $salonId]
        );
    }

    private function addSample(int $salonId, int $staffId, int $serviceId, int $appointmentId, float $minutes): void
    {
        DB::insert('duration_samples', [
            'salon_id' => $salonId,
            'staff_id' => $staffId,
            'service_id' => $serviceId,
            'appointment_id' => $appointmentId,
            'minutes' => round($minutes, 2),
        ]);
    }

    private function recomputePercentiles(int $staffId, int $serviceId): void
    {
        $window = (int) Config::get('reshen.estimation.rolling_window_samples', 200);

        $rows = DB::select(
            'SELECT minutes FROM duration_samples WHERE staff_id = ? AND service_id = ? ORDER BY id DESC LIMIT ?',
            [$staffId, $serviceId, $window]
        );

        $values = array_map(static fn ($r) => (float) $r['minutes'], $rows);
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return;
        }

        $p50 = $this->percentile($values, 50);
        $p80 = $this->percentile($values, 80);
        $mean = array_sum($values) / $n;
        $variance = $n > 1 ? array_sum(array_map(static fn ($v) => ($v - $mean) ** 2, $values)) / ($n - 1) : 0;

        $existing = DB::selectOne('SELECT id FROM duration_stats WHERE staff_id = ? AND service_id = ?', [$staffId, $serviceId]);
        $data = [
            'sample_count' => $n,
            'p50_minutes' => round($p50, 2),
            'p80_minutes' => round($p80, 2),
            'mean_minutes' => round($mean, 2),
            'stddev_minutes' => round(sqrt($variance), 2),
        ];

        if ($existing) {
            DB::update('duration_stats', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            DB::insert('duration_stats', array_merge($data, ['staff_id' => $staffId, 'service_id' => $serviceId, 'salon_id' => $this->salonIdForStaff($staffId)]));
        }
    }

    /** @param float[] $sortedValues مقادیر، از قبل مرتب‌شده */
    private function percentile(array $sortedValues, int $p): float
    {
        $n = count($sortedValues);
        if ($n === 1) {
            return $sortedValues[0];
        }
        $rank = ($p / 100) * ($n - 1);
        $low = (int) floor($rank);
        $high = (int) ceil($rank);
        if ($low === $high) {
            return $sortedValues[$low];
        }

        $fraction = $rank - $low;

        return $sortedValues[$low] + ($sortedValues[$high] - $sortedValues[$low]) * $fraction;
    }

    private function updateCustomerFactor(int $salonId, ?array $customer, int $staffId, array $serviceIds, float $actualMinutes, DurationEstimator $estimator): void
    {
        if ($customer === null) {
            return;
        }

        $baseline = $estimator->forAppointment($staffId, array_map('intval', $serviceIds), null);
        if ($baseline['p50'] <= 0) {
            return;
        }

        $ratio = $actualMinutes / $baseline['p50'];
        $previous = $customer['duration_factor'] !== null ? (float) $customer['duration_factor'] : null;
        $newFactor = $previous === null ? $ratio : ($previous * 0.7 + $ratio * 0.3);

        DB::update('customers', ['duration_factor' => round($newFactor, 3)], 'id = :id AND salon_id = :salon_id', [
            'id' => $customer['id'],
            'salon_id' => $salonId,
        ]);
    }

    private function salonIdForStaff(int $staffId): int
    {
        $row = DB::selectOne('SELECT salon_id FROM staff WHERE id = ?', [$staffId]);

        return (int) ($row['salon_id'] ?? 0);
    }
}
