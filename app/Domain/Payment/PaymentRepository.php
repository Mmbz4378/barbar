<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Core\DB;
use App\Domain\Appointment\AppointmentRepository;
use RuntimeException;

/**
 * دریافت‌ها: بیعانه (پیش از خدمت) و تسویه (پس از خدمت).
 *
 * هر دو در یک جدول با ستون kind، تا گزارش فروش هر دو را ببیند ولی
 * «این نوبت تسویه شده؟» فقط تسویه را بشمارد.
 */
final class PaymentRepository
{
    public const METHODS = ['cash' => 'نقدی', 'card_to_card' => 'کارت‌به‌کارت', 'pos' => 'کارتخوان', 'online' => 'درگاه آنلاین'];

    public function record(int $salonId, int $appointmentId, string $method, int $amount, int $tip, ?int $userId, int $discount = 0): int
    {
        if (!in_array($method, ['cash', 'card_to_card', 'pos'], true) || $amount < 0 || $tip < 0 || $discount < 0) {
            throw new RuntimeException('روش یا مبلغ پرداخت معتبر نیست.');
        }

        return DB::transaction(function () use ($salonId, $appointmentId, $method, $amount, $tip, $userId, $discount) {
            $appointment = DB::selectOne('SELECT id, status FROM appointments WHERE salon_id = ? AND id = ? FOR UPDATE', [$salonId, $appointmentId]);
            if (!$appointment || $appointment['status'] !== 'completed') {
                throw new RuntimeException('تسویه فقط برای نوبت انجام‌شدهٔ این سالن ممکن است.');
            }
            $existing = $this->forAppointment($salonId, $appointmentId);
            if ($existing) {
                return (int) $existing['id'];
            }
            if (DB::selectOne("SELECT id FROM online_payment_attempts WHERE appointment_id = ? AND status = 'pending'", [$appointmentId])) {
                throw new RuntimeException('یک پرداخت آنلاین در انتظار نتیجه است؛ ابتدا نتیجهٔ آن را بررسی کنید.');
            }

            return (int) DB::insert('payments', [
                'salon_id' => $salonId,
                'appointment_id' => $appointmentId,
                'kind' => 'settlement',
                'method' => $method,
                'amount' => $amount,
                'tip_amount' => $tip,
                'discount_amount' => $discount,
                'created_by_user_id' => $userId,
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    /** ثبت دریافت بیعانه. تکرارش ردیف دوم نمی‌سازد. */
    public function recordDeposit(int $salonId, int $appointmentId, string $method, int $amount, ?int $userId): void
    {
        if (!in_array($method, ['cash', 'card_to_card', 'pos'], true) || $amount <= 0) {
            throw new RuntimeException('روش یا مبلغ بیعانه معتبر نیست.');
        }
        if ($this->depositFor($salonId, $appointmentId) !== null) {
            return;
        }

        DB::insert('payments', [
            'salon_id' => $salonId,
            'appointment_id' => $appointmentId,
            'kind' => 'deposit',
            'method' => $method,
            'amount' => $amount,
            'tip_amount' => 0,
            'created_by_user_id' => $userId,
            'paid_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** تسویهٔ این نوبت، اگر ثبت شده باشد. */
    public function forAppointment(int $salonId, int $appointmentId): ?array
    {
        return DB::selectOne(
            "SELECT * FROM payments WHERE salon_id = ? AND appointment_id = ? AND kind = 'settlement' ORDER BY id LIMIT 1",
            [$salonId, $appointmentId]
        );
    }

    public function depositFor(int $salonId, int $appointmentId): ?array
    {
        return DB::selectOne(
            "SELECT * FROM payments WHERE salon_id = ? AND appointment_id = ? AND kind = 'deposit' ORDER BY id LIMIT 1",
            [$salonId, $appointmentId]
        );
    }

    /** @return array{count:int|string,total:int|string,tips:int|string} */
    public function dailyTotal(int $salonId, string $date, ?int $staffId = null): array
    {
        return $this->rangeTotal($salonId, $date, $date, $staffId);
    }

    /** @return array{count:int|string,total:int|string,tips:int|string} */
    public function rangeTotal(int $salonId, string $from, string $to, ?int $staffId = null): array
    {
        [$fromTs, $toTs] = AppointmentRepository::range($from, $to);
        $sql = "SELECT SUM(p.kind = 'settlement') AS count, COALESCE(SUM(p.amount),0) AS total, COALESCE(SUM(p.tip_amount),0) AS tips
                FROM payments p JOIN appointments a ON a.id = p.appointment_id
                WHERE p.salon_id = ? AND p.paid_at >= ? AND p.paid_at < ?";
        $params = [$salonId, $fromTs, $toTs];
        if ($staffId !== null) {
            $sql .= ' AND a.staff_id = ?';
            $params[] = $staffId;
        }

        $row = DB::selectOne($sql, $params) ?? [];

        return ['count' => (int) ($row['count'] ?? 0), 'total' => (int) ($row['total'] ?? 0), 'tips' => (int) ($row['tips'] ?? 0)];
    }

    public function methodBreakdown(int $salonId, string $from, string $to): array
    {
        [$fromTs, $toTs] = AppointmentRepository::range($from, $to);

        return DB::select(
            "SELECT method, COUNT(*) AS count, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE salon_id = ? AND paid_at >= ? AND paid_at < ?
             GROUP BY method ORDER BY total DESC",
            [$salonId, $fromTs, $toTs]
        );
    }
}
