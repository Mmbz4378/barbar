<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Core\DB;

final class PaymentRepository
{
    public function record(int $salonId, int $appointmentId, string $method, int $amount, int $tip, ?int $userId): int
    {
        if (!in_array($method, ['cash','card_to_card','pos'], true) || $amount < 0 || $tip < 0) throw new \RuntimeException('روش یا مبلغ پرداخت معتبر نیست.');
        return DB::transaction(function () use ($salonId,$appointmentId,$method,$amount,$tip,$userId) {
        $appointment = DB::selectOne('SELECT id,status FROM appointments WHERE salon_id=? AND id=? FOR UPDATE',[$salonId,$appointmentId]);
        if (!$appointment || $appointment['status'] !== 'completed') throw new \RuntimeException('تسویه فقط برای نوبت انجام‌شدهٔ این سالن ممکن است.');
        $existing = $this->forAppointment($salonId,$appointmentId);
        if ($existing) return (int)$existing['id'];
        if (DB::selectOne("SELECT id FROM online_payment_attempts WHERE appointment_id=? AND status='pending'",[$appointmentId]))throw new \RuntimeException('یک پرداخت آنلاین در انتظار نتیجه است؛ ابتدا نتیجهٔ آن را بررسی کنید.');
        return (int) DB::insert('payments', [
            'salon_id' => $salonId,
            'appointment_id' => $appointmentId,
            'method' => $method,
            'amount' => $amount,
            'tip_amount' => $tip,
            'created_by_user_id' => $userId,
            'paid_at' => date('Y-m-d H:i:s'),
        ]);
        });
    }

    public function forAppointment(int $salonId, int $appointmentId): ?array
    {
        return DB::selectOne('SELECT * FROM payments WHERE salon_id = ? AND appointment_id = ?', [$salonId, $appointmentId]);
    }

    public function dailyTotal(int $salonId, string $date, ?int $staffId = null): array
    {
        $sql = "SELECT COUNT(*) AS count, COALESCE(SUM(p.amount),0) AS total, COALESCE(SUM(p.tip_amount),0) AS tips
                FROM payments p JOIN appointments a ON a.id = p.appointment_id
                WHERE p.salon_id = ? AND DATE(p.paid_at) = ?";
        $params = [$salonId, $date];
        if ($staffId !== null) {
            $sql .= ' AND a.staff_id = ?';
            $params[] = $staffId;
        }

        return DB::selectOne($sql, $params) ?? ['count' => 0, 'total' => 0, 'tips' => 0];
    }

    public function rangeTotal(int $salonId, string $from, string $to, ?int $staffId = null): array
    {
        $sql = "SELECT COUNT(*) AS count, COALESCE(SUM(p.amount),0) AS total, COALESCE(SUM(p.tip_amount),0) AS tips
                FROM payments p JOIN appointments a ON a.id = p.appointment_id
                WHERE p.salon_id = ? AND DATE(p.paid_at) BETWEEN ? AND ?";
        $params = [$salonId, $from, $to];
        if ($staffId !== null) {
            $sql .= ' AND a.staff_id = ?';
            $params[] = $staffId;
        }

        return DB::selectOne($sql, $params) ?? ['count' => 0, 'total' => 0, 'tips' => 0];
    }

    public function methodBreakdown(int $salonId, string $from, string $to): array
    {
        return DB::select(
            "SELECT method, COUNT(*) AS count, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE salon_id = ? AND DATE(paid_at) BETWEEN ? AND ?
             GROUP BY method",
            [$salonId, $from, $to]
        );
    }
}
