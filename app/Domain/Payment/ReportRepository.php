<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Core\DB;
use App\Domain\Appointment\AppointmentRepository;

/**
 * گزارش‌های فروش و عملکرد.
 *
 * همهٔ فیلترهای تاریخ بازه‌ای‌اند (ایندکس‌پذیر). فروش هر نفر از روی
 * پرداخت‌ها به نوبت‌های همان نفر نسبت داده می‌شود، و «سهم» تخمینی است
 * از درصد ثبت‌شده در پروفایل — تسویه با کارکنان در این نسخه نیست.
 */
final class ReportRepository
{
    /** @return array{revenue:int,tips:int,discounts:int,settlements:int,deposits:int,avg_ticket:int} */
    public function totals(int $salonId, string $from, string $to): array
    {
        [$a, $b] = AppointmentRepository::range($from, $to);
        $row = DB::selectOne(
            "SELECT COALESCE(SUM(amount),0) AS revenue, COALESCE(SUM(tip_amount),0) AS tips,
                    COALESCE(SUM(discount_amount),0) AS discounts,
                    SUM(kind = 'settlement') AS settlements,
                    COALESCE(SUM(CASE WHEN kind = 'deposit' THEN amount ELSE 0 END),0) AS deposits
               FROM payments WHERE salon_id = ? AND paid_at >= ? AND paid_at < ?",
            [$salonId, $a, $b]
        ) ?? [];
        $settlements = (int) ($row['settlements'] ?? 0);

        return [
            'revenue' => (int) ($row['revenue'] ?? 0),
            'tips' => (int) ($row['tips'] ?? 0),
            'discounts' => (int) ($row['discounts'] ?? 0),
            'settlements' => $settlements,
            'deposits' => (int) ($row['deposits'] ?? 0),
            'avg_ticket' => $settlements > 0 ? (int) round(((int) ($row['revenue'] ?? 0) - (int) ($row['deposits'] ?? 0)) / $settlements) : 0,
        ];
    }

    /** @return array<int,array> */
    public function byStaff(int $salonId, string $from, string $to): array
    {
        [$a, $b] = AppointmentRepository::range($from, $to);

        return DB::select(
            "SELECT st.id, st.name, st.color, st.commission_percent,
                    COUNT(DISTINCT CASE WHEN p.kind = 'settlement' THEN p.appointment_id END) AS visits,
                    COALESCE(SUM(p.amount),0) AS revenue, COALESCE(SUM(p.tip_amount),0) AS tips
               FROM staff st
               LEFT JOIN appointments ap ON ap.staff_id = st.id AND ap.salon_id = st.salon_id
               LEFT JOIN payments p ON p.appointment_id = ap.id AND p.paid_at >= ? AND p.paid_at < ?
              WHERE st.salon_id = ?
              GROUP BY st.id, st.name, st.color, st.commission_percent
             HAVING revenue > 0 OR st.id IN (SELECT id FROM staff WHERE salon_id = ? AND is_active = 1)
              ORDER BY revenue DESC",
            [$a, $b, $salonId, $salonId]
        );
    }

    /** @return array<int,array> پرفروش‌ترین خدمات (بر اساس نوبت‌های انجام‌شده) */
    public function topServices(int $salonId, string $from, string $to, int $limit = 8): array
    {
        [$a, $b] = AppointmentRepository::range($from, $to);

        return DB::select(
            "SELECT sv.name, COUNT(*) AS times, COALESCE(SUM(ai.price),0) AS value
               FROM appointments ap
               JOIN appointment_items ai ON ai.appointment_id = ap.id
               JOIN services sv ON sv.id = ai.service_id
              WHERE ap.salon_id = ? AND ap.status = 'completed' AND ap.actual_end_at >= ? AND ap.actual_end_at < ?
              GROUP BY sv.id, sv.name ORDER BY times DESC, value DESC LIMIT " . max(1, $limit),
            [$salonId, $a, $b]
        );
    }

    /** @return array{completed:int,no_show:int,cancelled_customer:int,cancelled_salon:int,cancelled_system:int,walkins:int,online:int,panel:int} */
    public function flow(int $salonId, string $from, string $to): array
    {
        [$a, $b] = AppointmentRepository::range($from, $to);
        $row = DB::selectOne(
            "SELECT SUM(status = 'completed') AS completed, SUM(status = 'no_show') AS no_show,
                    SUM(status = 'cancelled' AND cancelled_by = 'customer') AS cancelled_customer,
                    SUM(status = 'cancelled' AND cancelled_by = 'salon') AS cancelled_salon,
                    SUM(status = 'cancelled' AND cancelled_by = 'system') AS cancelled_system,
                    SUM(kind = 'walkin') AS walkins,
                    SUM(kind = 'booked' AND created_by_user_id IS NULL) AS online,
                    SUM(kind = 'booked' AND created_by_user_id IS NOT NULL) AS panel
               FROM appointments
              WHERE salon_id = ?
                AND ((scheduled_at >= ? AND scheduled_at < ?) OR (scheduled_at IS NULL AND queued_at >= ? AND queued_at < ?))",
            [$salonId, $a, $b, $a, $b]
        ) ?? [];

        $out = [];
        foreach (['completed', 'no_show', 'cancelled_customer', 'cancelled_salon', 'cancelled_system', 'walkins', 'online', 'panel'] as $key) {
            $out[$key] = (int) ($row[$key] ?? 0);
        }

        return $out;
    }

    /** @return array{new:int,returning:int} */
    public function customerMix(int $salonId, string $from, string $to): array
    {
        [$a, $b] = AppointmentRepository::range($from, $to);
        $row = DB::selectOne(
            "SELECT SUM(first_visit >= ?) AS new_customers, SUM(first_visit < ?) AS returning_customers
               FROM (SELECT ap.customer_id,
                            (SELECT MIN(x.actual_end_at) FROM appointments x WHERE x.salon_id = ap.salon_id AND x.customer_id = ap.customer_id AND x.status = 'completed') AS first_visit
                       FROM appointments ap
                      WHERE ap.salon_id = ? AND ap.status = 'completed' AND ap.actual_end_at >= ? AND ap.actual_end_at < ?
                      GROUP BY ap.customer_id, ap.salon_id) t",
            [$a, $a, $salonId, $a, $b]
        ) ?? [];

        return ['new' => (int) ($row['new_customers'] ?? 0), 'returning' => (int) ($row['returning_customers'] ?? 0)];
    }

    /** @return array<string,int> روز ← فروش */
    public function dailySeries(int $salonId, string $from, string $to): array
    {
        [$a, $b] = AppointmentRepository::range($from, $to);
        $out = [];
        foreach (DB::select(
            'SELECT DATE(paid_at) AS d, COALESCE(SUM(amount),0) AS total FROM payments
              WHERE salon_id = ? AND paid_at >= ? AND paid_at < ? GROUP BY DATE(paid_at)',
            [$salonId, $a, $b]
        ) as $row) {
            $out[(string) $row['d']] = (int) $row['total'];
        }

        return $out;
    }

    /** نوبت‌هایی که پس از پیامک یادآوری انجام شدند — ارزش محافظه‌کارانه. */
    public function remindedAndCompleted(int $salonId, string $from, string $to): array
    {
        [$a, $b] = AppointmentRepository::range($from, $to);
        $row = DB::selectOne(
            "SELECT COUNT(DISTINCT ap.id) AS count, COALESCE(SUM(ai.price),0) AS value
               FROM appointments ap
               JOIN appointment_items ai ON ai.appointment_id = ap.id
              WHERE ap.salon_id = ? AND ap.status = 'completed' AND ap.actual_end_at >= ? AND ap.actual_end_at < ?
                AND EXISTS (SELECT 1 FROM sms_messages sm WHERE sm.appointment_id = ap.id AND sm.template_code IN ('reminder_24h','reminder_2h') AND sm.status = 'sent')",
            [$salonId, $a, $b]
        ) ?? [];

        return ['count' => (int) ($row['count'] ?? 0), 'value' => (int) ($row['value'] ?? 0)];
    }
}
