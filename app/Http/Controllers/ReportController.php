<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Payment\PaymentRepository;
use App\Domain\Payment\ReportRepository;
use App\Support\Jalali;
use App\Support\JalaliCalendar;
use App\Support\Now;
use DateTimeImmutable;

final class ReportController extends Controller
{
    public function daily(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $date = jalali_date_from_request($request, 'date') ?? Now::today()->format('Y-m-d');

        return $this->page('layouts.panel', 'panel.reports.daily', array_merge(
            ['title' => 'گزارش روزانه', 'date' => $date, 'label' => JalaliCalendar::humanDate(new DateTimeImmutable($date), true)],
            $this->report($salonId, $date, $date)
        ));
    }

    public function monthly(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        [$nowY, $nowM] = Jalali::fromDateTime(Now::today());
        $jy = (int) $request->query('jy', (string) $nowY);
        $jm = (int) $request->query('jm', (string) $nowM);
        if ($jm < 1 || $jm > 12 || $jy < 1390 || $jy > 1500) {
            [$jy, $jm] = [$nowY, $nowM];
        }
        $days = Jalali::daysInJalaliMonth($jy, $jm);
        $from = Jalali::toDateTime($jy, $jm, 1)->format('Y-m-d');
        $to = Jalali::toDateTime($jy, $jm, $days)->format('Y-m-d');

        $series = (new ReportRepository())->dailySeries($salonId, $from, $to);
        $bars = [];
        for ($d = 1; $d <= $days; $d++) {
            $key = Jalali::toDateTime($jy, $jm, $d)->format('Y-m-d');
            $bars[] = ['day' => $d, 'date' => $key, 'total' => $series[$key] ?? 0];
        }

        return $this->page('layouts.panel', 'panel.reports.monthly', array_merge(
            [
                'title' => 'گزارش ماهانه',
                'jy' => $jy,
                'jm' => $jm,
                'label' => JalaliCalendar::MONTHS[$jm] . ' ' . fa_num($jy),
                'bars' => $bars,
                'prev' => $jm === 1 ? [$jy - 1, 12] : [$jy, $jm - 1],
                'next' => $jm === 12 ? [$jy + 1, 1] : [$jy, $jm + 1],
                'isCurrent' => $jy === $nowY && $jm === $nowM,
            ],
            $this->report($salonId, $from, $to)
        ));
    }

    private function report(int $salonId, string $from, string $to): array
    {
        $reports = new ReportRepository();

        return [
            'totals' => $reports->totals($salonId, $from, $to),
            'methods' => (new PaymentRepository())->methodBreakdown($salonId, $from, $to),
            'byStaff' => $reports->byStaff($salonId, $from, $to),
            'topServices' => $reports->topServices($salonId, $from, $to),
            'flow' => $reports->flow($salonId, $from, $to),
            'mix' => $reports->customerMix($salonId, $from, $to),
            'reminded' => $reports->remindedAndCompleted($salonId, $from, $to),
        ];
    }
}
