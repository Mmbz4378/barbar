<?php

declare(strict_types=1);

namespace App\Domain\Appointment;

use App\Core\DB;
use App\Domain\Booking\AvailabilityCache;
use App\Support\Now;
use App\Support\Str;

/**
 * نوبت‌ها.
 *
 * همهٔ فیلترهای تاریخ به‌صورت بازه نوشته شده‌اند (>= شروع روز و < شروع
 * روز بعد)، نه DATE(ستون) = …؛ تابع روی ستون جلوی استفاده از ایندکس را
 * می‌گیرد و با بزرگ شدن جدول، صفحهٔ امروز هر ۱۵ ثانیه کل نوبت‌های
 * سالن را پیمایش می‌کرد.
 */
final class AppointmentRepository
{
    public const LIVE = ['confirmed', 'queued', 'in_chair'];

    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne('SELECT * FROM appointments WHERE salon_id = ? AND id = ?', [$salonId, $id]);
    }

    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-z0-9]{6,24}$/', $token)) {
            return null;
        }

        return DB::selectOne('SELECT * FROM appointments WHERE public_token = ?', [$token]);
    }

    /**
     * همهٔ بخش‌های یک رزرو چندنفره، به ترتیب زمان؛ برای نوبت تکی خودش.
     *
     * @return array<int,array>
     */
    public function group(array $appointment): array
    {
        if (empty($appointment['group_token'])) {
            return [$appointment];
        }

        return DB::select(
            'SELECT a.*, st.name AS staff_name FROM appointments a
               LEFT JOIN staff st ON st.id = a.staff_id
              WHERE a.salon_id = ? AND a.group_token = ?
              ORDER BY a.scheduled_at, a.id',
            [$appointment['salon_id'], $appointment['group_token']]
        );
    }

    /** نوبت‌های زندهٔ سالن — صف، روی صندلی، و رزروهای امروز. */
    public function activeForSalon(int $salonId): array
    {
        return DB::select(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, s.name AS staff_name, s.color AS staff_color
             FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             LEFT JOIN staff s ON s.id = a.staff_id
             WHERE a.salon_id = ? AND a.status IN ('confirmed','queued','in_chair')
             ORDER BY a.queued_at IS NULL, a.queued_at, a.scheduled_at, a.id",
            [$salonId]
        );
    }

    /**
     * رزروهای زمان‌دار در یک بازهٔ تاریخی (شامل لغوشده‌ها و منتظر بیعانه).
     *
     * @return array<int,array>
     */
    public function scheduledBetween(int $salonId, string $fromDate, string $toDate, ?int $staffId = null): array
    {
        [$from, $to] = self::range($fromDate, $toDate);
        $sql = "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone,
                       s.name AS staff_name, s.color AS staff_color,
                       (SELECT GROUP_CONCAT(sv.name ORDER BY ai.id SEPARATOR '، ')
                          FROM appointment_items ai JOIN services sv ON sv.id = ai.service_id
                         WHERE ai.appointment_id = a.id) AS service_names
                FROM appointments a
                JOIN customers c ON c.id = a.customer_id
                LEFT JOIN staff s ON s.id = a.staff_id
                WHERE a.salon_id = ? AND a.scheduled_at >= ? AND a.scheduled_at < ?";
        $params = [$salonId, $from, $to];

        if ($staffId !== null) {
            $sql .= ' AND a.staff_id = ?';
            $params[] = $staffId;
        }

        return DB::select($sql . ' ORDER BY a.scheduled_at, a.id', $params);
    }

    /** شمارش رزروهای هر وضعیت — برای نشان‌های بالای صفحهٔ رزروها. */
    public function scheduledCounts(int $salonId, string $fromDate, string $toDate, ?int $staffId = null): array
    {
        [$from, $to] = self::range($fromDate, $toDate);
        $sql = "SELECT status, cancelled_by, COUNT(*) AS n FROM appointments
                 WHERE salon_id = ? AND scheduled_at >= ? AND scheduled_at < ?";
        $params = [$salonId, $from, $to];
        if ($staffId !== null) {
            $sql .= ' AND staff_id = ?';
            $params[] = $staffId;
        }
        $rows = DB::select($sql . ' GROUP BY status, cancelled_by', $params);

        $out = [];
        foreach ($rows as $r) {
            $status = (string) $r['status'];
            $out[$status] = ($out[$status] ?? 0) + (int) $r['n'];

            // «۴ تا را خودمان لغو کردیم» با «۴ تا را مشتری لغو کرد» فرق دارد
            if ($status === 'cancelled') {
                $by = $r['cancelled_by'] ?? 'unknown';
                $out['cancelled_by'][$by] = ($out['cancelled_by'][$by] ?? 0) + (int) $r['n'];
            }
        }

        return $out;
    }

    public function activeForStaff(int $salonId, int $staffId): array
    {
        [$from, $to] = self::range(Now::today()->format('Y-m-d'));

        return DB::select(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, c.no_show_count AS customer_no_shows
             FROM appointments a JOIN customers c ON c.id = a.customer_id
             WHERE a.salon_id = ? AND a.staff_id = ?
               AND (a.status IN ('queued','in_chair')
                    OR (a.status = 'confirmed' AND a.scheduled_at >= ? AND a.scheduled_at < ?))
             ORDER BY a.id",
            [$salonId, $staffId, $from, $to]
        );
    }

    /**
     * نوبت‌های زندهٔ امروزِ همهٔ کارکنان در یک کوئری — برای صفحهٔ امروز.
     *
     * @return array<int,array<int,array>> کلید: شناسهٔ نفر
     */
    public function activeTodayByStaff(int $salonId): array
    {
        [$from, $to] = self::range(Now::today()->format('Y-m-d'));
        $rows = DB::select(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, c.no_show_count AS customer_no_shows
             FROM appointments a JOIN customers c ON c.id = a.customer_id
             WHERE a.salon_id = ? AND a.staff_id IS NOT NULL
               AND (a.status IN ('queued','in_chair')
                    OR (a.status = 'confirmed' AND a.scheduled_at >= ? AND a.scheduled_at < ?))
             ORDER BY a.id",
            [$salonId, $from, $to]
        );

        $byStaff = [];
        foreach ($rows as $row) {
            $byStaff[(int) $row['staff_id']][] = $row;
        }

        return $byStaff;
    }

    public function inChairFor(int $salonId, int $staffId): ?array
    {
        return DB::selectOne(
            "SELECT * FROM appointments WHERE salon_id = ? AND staff_id = ? AND status = 'in_chair' LIMIT 1",
            [$salonId, $staffId]
        );
    }

    public function itemsFor(int $salonId, int $appointmentId): array
    {
        return DB::select(
            'SELECT ai.*, sv.name AS service_name, sv.price_type FROM appointment_items ai
             JOIN services sv ON sv.id = ai.service_id
             WHERE ai.salon_id = ? AND ai.appointment_id = ?
             ORDER BY ai.id',
            [$salonId, $appointmentId]
        );
    }

    /**
     * خدمت‌های چند نوبت، در یک کوئری.
     *
     * @param int[] $appointmentIds
     * @return array<int,array<int,array>> کلید: شناسهٔ نوبت
     */
    public function itemsForMany(int $salonId, array $appointmentIds): array
    {
        if ($appointmentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $rows = DB::select(
            "SELECT ai.*, sv.name AS service_name, sv.price_type FROM appointment_items ai
             JOIN services sv ON sv.id = ai.service_id
             WHERE ai.salon_id = ? AND ai.appointment_id IN ({$placeholders})
             ORDER BY ai.id",
            array_merge([$salonId], array_values($appointmentIds))
        );

        $byAppointment = [];
        foreach ($rows as $row) {
            $byAppointment[(int) $row['appointment_id']][] = $row;
        }

        return $byAppointment;
    }

    public function create(int $salonId, array $data): int
    {
        $data['salon_id'] = $salonId;
        $data['public_token'] = $data['public_token'] ?? Str::token(12);

        $id = (int) DB::insert('appointments', $data);
        AvailabilityCache::bump($salonId);

        return $id;
    }

    public function addItem(int $salonId, int $appointmentId, int $serviceId, int $price, ?int $durationMinutes, int $bufferMinutes = 0): void
    {
        DB::insert('appointment_items', [
            'salon_id' => $salonId,
            'appointment_id' => $appointmentId,
            'service_id' => $serviceId,
            'price' => $price,
            'duration_minutes' => $durationMinutes,
            'buffer_minutes' => max(0, $bufferMinutes),
        ]);
        AvailabilityCache::bump($salonId);
    }

    public function update(int $salonId, int $id, array $data): void
    {
        DB::update('appointments', $data, 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);
        AvailabilityCache::bump($salonId);
    }

    public function todayCompletedCount(int $salonId, ?int $staffId = null): int
    {
        [$from, $to] = self::range(Now::today()->format('Y-m-d'));
        $sql = "SELECT COUNT(*) AS c FROM appointments
                 WHERE salon_id = ? AND status = 'completed' AND actual_end_at >= ? AND actual_end_at < ?";
        $params = [$salonId, $from, $to];
        if ($staffId !== null) {
            $sql .= ' AND staff_id = ?';
            $params[] = $staffId;
        }

        return (int) (DB::selectOne($sql, $params)['c'] ?? 0);
    }

    /**
     * خلاصهٔ امروزِ سالن (یا یک نفر)، در یک کوئری.
     *
     * نوبت امروز یعنی رزروِ امروز، یا مراجعهٔ حضوریِ امروز. هر دو شاخه
     * روی ایندکس (salon_id, scheduled_at) و (salon_id, queued_at) می‌نشینند.
     *
     * @return array{total:int,completed:int,waiting:int,in_chair:int,no_show:int,cancelled:int,pending:int}
     */
    public function todaySummary(int $salonId, ?int $staffId = null): array
    {
        [$from, $to] = self::range(Now::today()->format('Y-m-d'));
        $staffSql = $staffId !== null ? ' AND staff_id = ' . (int) $staffId : '';

        $row = DB::selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'completed') AS completed,
                    SUM(status IN ('confirmed','queued')) AS waiting,
                    SUM(status = 'in_chair') AS in_chair,
                    SUM(status = 'no_show') AS no_show,
                    SUM(status = 'cancelled') AS cancelled,
                    SUM(status = 'pending') AS pending
               FROM appointments
              WHERE salon_id = ? {$staffSql}
                AND ((scheduled_at >= ? AND scheduled_at < ?)
                     OR (scheduled_at IS NULL AND queued_at >= ? AND queued_at < ?))",
            [$salonId, $from, $to, $from, $to]
        );

        $out = [];
        foreach (['total', 'completed', 'waiting', 'in_chair', 'no_show', 'cancelled', 'pending'] as $key) {
            $out[$key] = (int) ($row[$key] ?? 0);
        }

        return $out;
    }

    /**
     * کارهای تمام‌شدهٔ امروز که هنوز تسویه نشده‌اند.
     *
     * آرایشگر «تمام شد» را می‌زند و مشتری سراغ پیشخوان می‌رود؛ پذیرش
     * باید همین فهرست را ببیند، وگرنه پولِ کارِ انجام‌شده جا می‌ماند.
     *
     * @return array<int,array>
     */
    public function awaitingSettlement(int $salonId, int $days = 2): array
    {
        $from = Now::today()->modify('-' . max(0, $days - 1) . ' days')->format('Y-m-d 00:00:00');

        return DB::select(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, st.name AS staff_name,
                    (SELECT COALESCE(SUM(ai.price), 0) FROM appointment_items ai WHERE ai.appointment_id = a.id) AS total_price
               FROM appointments a
               JOIN customers c ON c.id = a.customer_id
               LEFT JOIN staff st ON st.id = a.staff_id
              WHERE a.salon_id = ? AND a.status = 'completed' AND a.actual_end_at >= ?
                AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.appointment_id = a.id AND p.kind = 'settlement')
              ORDER BY a.actual_end_at DESC
              LIMIT 50",
            [$salonId, $from]
        );
    }

    /**
     * رزروهای در انتظار تأیید بیعانه.
     *
     * @return array<int,array>
     */
    public function pendingDeposits(int $salonId): array
    {
        return DB::select(
            "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone, st.name AS staff_name,
                    (SELECT GROUP_CONCAT(sv.name ORDER BY ai.id SEPARATOR '، ')
                       FROM appointment_items ai JOIN services sv ON sv.id = ai.service_id
                      WHERE ai.appointment_id = a.id) AS service_names
               FROM appointments a
               JOIN customers c ON c.id = a.customer_id
               LEFT JOIN staff st ON st.id = a.staff_id
              WHERE a.salon_id = ? AND a.status = 'pending' AND a.deposit_amount > 0
              ORDER BY a.hold_expires_at, a.id
              LIMIT 50",
            [$salonId]
        );
    }

    /**
     * [شروع روز اول، شروع روزِ بعد از روز آخر] برای فیلتر بازه‌ای.
     *
     * @return array{0:string,1:string}
     */
    public static function range(string $fromDate, ?string $toDate = null): array
    {
        $toDate ??= $fromDate;
        $end = (new \DateTimeImmutable($toDate))->modify('+1 day');

        return [$fromDate . ' 00:00:00', $end->format('Y-m-d') . ' 00:00:00'];
    }
}
