<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Reports\PlatformReports;
use App\Domain\Reports\ReportRange;
use App\Support\Audience;
use App\Support\Csv;

/**
 * گزارش‌های سراسری مدیر کل با بازهٔ زمانی و فیلتر، و خروجی CSV برای Excel.
 */
final class PlatformReportController extends Controller
{
    public const STATUS_LABELS = [
        'pending' => 'در انتظار تأیید',
        'confirmed' => 'تأییدشده',
        'queued' => 'در صف',
        'in_chair' => 'در حال انجام',
        'completed' => 'انجام‌شده',
        'cancelled' => 'لغوشده',
        'no_show' => 'نیامده',
    ];

    public const METHOD_LABELS = ['cash' => 'نقدی', 'card_to_card' => 'کارت‌به‌کارت', 'pos' => 'کارتخوان', 'online' => 'آنلاین'];

    public const SMS_STATUS_LABELS = [
        'sent' => 'فرستاده‌شده', 'failed' => 'ناموفق', 'queued' => 'در صف', 'sending' => 'در حال ارسال',
        'skipped_quiet_hours' => 'ساعت سکوت', 'skipped_no_credit' => 'بی‌اعتبار', 'skipped_rate_limit' => 'سقف پیامک نوبت',
    ];

    public const SORTS = [
        'bookings' => 'نوبت', 'completed' => 'انجام‌شده', 'revenue' => 'درآمد', 'cancel_rate' => 'نرخ لغو',
        'no_show' => 'نیامده', 'new_customers' => 'مشتری تازه', 'sms' => 'پیامک',
    ];

    public function index(Request $request): Response
    {
        [$range, $filters] = $this->context($request);
        $reports = new PlatformReports($filters);
        $sort = array_key_exists((string) $request->query('sort', ''), self::SORTS) ? (string) $request->query('sort') : 'bookings';

        return $this->page('layouts.platform', 'platform.reports', [
            'title' => 'گزارش‌ها',
            'range' => $range,
            'filters' => $filters,
            'sort' => $sort,
            'kpis' => $reports->kpis($range),
            'previous' => $reports->kpis($range->previous()),
            'daily' => $reports->daily($range),
            'heatmap' => $reports->heatmap($range),
            'statuses' => $reports->statuses($range),
            'methods' => $reports->paymentMethods($range),
            'salons' => $reports->salons($range, $sort, 50),
            'services' => $reports->services($range, 15),
            'cities' => $reports->cities($range),
            'mix' => $reports->customerMix($range),
            'sms' => $reports->sms($range),
            'growth' => $reports->growth(12),
            'salonOptions' => DB::select('SELECT id, name FROM salons ORDER BY name'),
            'cityOptions' => array_column(DB::select("SELECT DISTINCT city FROM salons WHERE city IS NOT NULL AND city <> '' ORDER BY city"), 'city'),
        ]);
    }

    public function export(Request $request): Response
    {
        [$range, $filters] = $this->context($request);
        $reports = new PlatformReports($filters);
        $dataset = (string) $request->query('dataset', 'daily');
        $stamp = $range->from->format('Ymd') . '-' . $range->to->format('Ymd');

        return match ($dataset) {
            'salons' => Csv::response("salons-{$stamp}.csv",
                ['سالن', 'شهر', 'نوع', 'نوبت', 'انجام‌شده', 'لغو', 'نیامده', 'حضوری', 'نرخ لغو (٪)', 'درآمد (تومان)', 'مشتری تازه', 'پیامک'],
                array_map(static fn (array $s): array => [
                    $s['name'], $s['city'], Audience::options()[$s['audience']] ?? $s['audience'], $s['bookings'], $s['completed'],
                    $s['cancelled'], $s['no_show'], $s['walkins'], $s['cancel_rate'], intdiv((int) $s['revenue'], 10), $s['new_customers'], $s['sms'],
                ], $reports->salons($range, (string) $request->query('sort', 'bookings'), 1000))),
            'services' => Csv::response("services-{$stamp}.csv", ['خدمت', 'تعداد', 'درآمد انجام‌شده (تومان)', 'تعداد سالن'],
                array_map(static fn (array $s): array => [$s['name'], $s['count'], intdiv($s['revenue'], 10), $s['salons']], $reports->services($range, 200))),
            'cities' => Csv::response("cities-{$stamp}.csv", ['شهر', 'نوبت', 'تعداد سالن'],
                array_map(static fn (array $c): array => [$c['city'], $c['bookings'], $c['salons']], $reports->cities($range))),
            'sms' => Csv::response("sms-{$stamp}.csv", ['الگو', 'فرستاده', 'ناموفق', 'ردشده'],
                array_map(static fn (array $t): array => [$t['template'], $t['sent'], $t['failed'], $t['skipped']], $reports->sms($range)['templates'])),
            'growth' => Csv::response('growth-12-months.csv', ['ماه', 'سالن تازه', 'کاربر پنل تازه', 'نوبت'],
                array_map(static fn (array $g): array => [$g['label'], $g['salons'], $g['users'], $g['bookings']], $reports->growth(12))),
            default => Csv::response("daily-{$stamp}.csv", ['تاریخ میلادی', 'تاریخ', 'نوبت', 'انجام‌شده', 'لغو و نیامده', 'درآمد (تومان)'],
                array_map(static fn (array $d): array => [$d['date'], $d['label'], $d['bookings'], $d['completed'], $d['cancelled'], intdiv($d['revenue'], 10)], $reports->daily($range))),
        };
    }

    /** @return array{0:ReportRange,1:array{salon_id:?int,audience:?string,city:?string}} */
    private function context(Request $request): array
    {
        $salonId = (int) $request->query('salon', '0');
        $audience = (string) $request->query('audience', '');
        $city = mb_substr(trim((string) $request->query('city', '')), 0, 80);

        return [ReportRange::fromRequest($request), [
            'salon_id' => $salonId > 0 ? $salonId : null,
            'audience' => array_key_exists($audience, Audience::options()) ? $audience : null,
            'city' => $city !== '' ? $city : null,
        ]];
    }
}
