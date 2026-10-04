<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Payment\PaymentRepository;
use App\Support\Jalali;
use DateTimeImmutable;

/** D04/D05/D12 — daily & monthly sales, and the "گزارش نجات‌یافته‌ها" retention report. */
final class ReportController extends Controller
{
    public function daily(Request $request): Response
    {
        $salonId = Auth::salonId();
        // فیلتر تاریخ، انتخابگر شمسی است (سه فیلد) نه ورودی میلادی.
        $date = jalali_date_from_request($request, 'date') ?? date('Y-m-d');
        $payments = new PaymentRepository();

        $totals = $payments->dailyTotal($salonId, $date);
        $breakdown = $payments->methodBreakdown($salonId, $date, $date);

        $byStaff = DB::select(
            "SELECT st.id, st.name, COUNT(p.id) AS count, COALESCE(SUM(p.amount),0) AS total
             FROM staff st
             LEFT JOIN appointments a ON a.staff_id = st.id AND DATE(a.actual_end_at) = ?
             LEFT JOIN payments p ON p.appointment_id = a.id
             WHERE st.salon_id = ?
             GROUP BY st.id ORDER BY total DESC",
            [$date, $salonId]
        );

        $rescued = $this->rescuedReport($salonId, $date, $date);

        return $this->page('layouts.panel', 'panel.reports.daily', [
            'title' => 'گزارش روزانه',
            'date' => $date,
            'totals' => $totals,
            'breakdown' => $breakdown,
            'byStaff' => $byStaff,
            'rescued' => $rescued,
        ]);
    }

    public function monthly(Request $request): Response
    {
        $salonId = Auth::salonId();
        $jy = (int) $request->query('jy', Jalali::fromDateTime(new DateTimeImmutable())[0]);
        $jm = (int) $request->query('jm', Jalali::fromDateTime(new DateTimeImmutable())[1]);

        [$gy1, $gm1, $gd1] = Jalali::toGregorian($jy, $jm, 1);
        $daysInMonth = Jalali::daysInJalaliMonth($jy, $jm);
        [$gy2, $gm2, $gd2] = Jalali::toGregorian($jy, $jm, $daysInMonth);
        $from = sprintf('%04d-%02d-%02d', $gy1, $gm1, $gd1);
        $to = sprintf('%04d-%02d-%02d', $gy2, $gm2, $gd2);

        $payments = new PaymentRepository();
        $totals = $payments->rangeTotal($salonId, $from, $to);
        $breakdown = $payments->methodBreakdown($salonId, $from, $to);
        $rescued = $this->rescuedReport($salonId, $from, $to);

        $dailySeries = DB::select(
            "SELECT DATE(paid_at) AS d, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE salon_id = ? AND DATE(paid_at) BETWEEN ? AND ?
             GROUP BY DATE(paid_at) ORDER BY d",
            [$salonId, $from, $to]
        );

        return $this->page('layouts.panel', 'panel.reports.monthly', [
            'title' => 'گزارش ماهانه',
            'jy' => $jy,
            'jm' => $jm,
            'totals' => $totals,
            'breakdown' => $breakdown,
            'rescued' => $rescued,
            'dailySeries' => $dailySeries,
        ]);
    }

    /**
     * D12 — "گزارش نجات‌یافته‌ها": appointments a reminder/nearly-up SMS
     * plausibly saved from becoming a no-show, valued conservatively at the
     * appointment's own price — this is what makes renewal an easy call
     * for the owner, so it must stay defensible, never inflated.
     */
    private function rescuedReport(int $salonId, string $from, string $to): array
    {
        $rows = DB::select(
            "SELECT COUNT(DISTINCT a.id) AS count, COALESCE(SUM(ai.price),0) AS value
             FROM appointments a
             JOIN sms_messages sm ON sm.appointment_id = a.id AND sm.template_code IN ('reminder_24h','reminder_2h') AND sm.status = 'sent'
             JOIN appointment_items ai ON ai.appointment_id = a.id
             WHERE a.salon_id = ? AND a.status = 'completed' AND DATE(a.actual_end_at) BETWEEN ? AND ?",
            [$salonId, $from, $to]
        );

        return $rows[0] ?? ['count' => 0, 'value' => 0];
    }
}
