<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Core\DB;
use App\Support\Jalali;
use DateTimeImmutable;

/**
 * گزارش‌های سراسری مدیر کل — همهٔ سالن‌ها با هم، یا با فیلتر سالن، شهر و
 * نوع سالن.
 *
 * مبنای «نوبت» زمان خودِ نوبت (scheduled_at) است، نه زمان ثبت؛ مبنای
 * «درآمد» زمان پرداخت (paid_at). مبالغ به ریال‌اند (مثل همهٔ سامانه).
 */
final class PlatformReports
{
    /** @param array{salon_id?:?int,audience?:?string,city?:?string} $filters */
    public function __construct(private readonly array $filters = [])
    {
    }

    // ─── سرجمع‌ها ─────────────────────────────────────────────────────

    /** @return array<string,int|float> */
    public function kpis(ReportRange $range): array
    {
        [$w, $p] = $this->where('a');
        $appts = DB::selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(a.status = 'completed') AS completed,
                    SUM(a.status = 'cancelled') AS cancelled,
                    SUM(a.status = 'no_show') AS no_show,
                    SUM(a.kind = 'walkin') AS walkins,
                    SUM(a.kind = 'booked' AND a.created_by_user_id IS NULL) AS online,
                    COUNT(DISTINCT a.salon_id) AS active_salons,
                    COUNT(DISTINCT a.customer_id) AS customers
               FROM appointments a {$this->join('a')}
              WHERE a.scheduled_at BETWEEN ? AND ? {$w}",
            array_merge([$range->start(), $range->end()], $p)
        ) ?? [];

        [$w, $p] = $this->where('pm');
        $money = DB::selectOne(
            "SELECT COALESCE(SUM(pm.amount), 0) AS revenue, COALESCE(SUM(pm.tip_amount), 0) AS tips,
                    COALESCE(SUM(pm.discount_amount), 0) AS discounts,
                    COALESCE(SUM(CASE WHEN pm.kind = 'deposit' THEN pm.amount END), 0) AS deposits,
                    SUM(pm.kind = 'settlement') AS settlements,
                    COALESCE(SUM(CASE WHEN pm.kind = 'settlement' THEN pm.amount END), 0) AS settlement_amount
               FROM payments pm {$this->join('pm')}
              WHERE pm.paid_at BETWEEN ? AND ? {$w}",
            array_merge([$range->start(), $range->end()], $p)
        ) ?? [];

        [$w, $p] = $this->where('c');
        $newCustomers = (int) (DB::selectOne(
            "SELECT COUNT(*) AS n FROM customers c {$this->join('c')} WHERE c.created_at BETWEEN ? AND ? AND c.deleted_at IS NULL {$w}",
            array_merge([$range->start(), $range->end()], $p)
        )['n'] ?? 0);

        [$w, $p] = $this->where('m');
        $sms = DB::selectOne(
            "SELECT SUM(m.status = 'sent') AS sent, SUM(m.status = 'failed') AS failed, SUM(m.status IN ('queued','sending')) AS queued
               FROM sms_messages m {$this->join('m')}
              WHERE m.created_at BETWEEN ? AND ? {$w}",
            array_merge([$range->start(), $range->end()], $p)
        ) ?? [];

        $newSalons = $this->filters === [] || array_filter($this->filters) === []
            ? (int) (DB::selectOne('SELECT COUNT(*) AS n FROM salons WHERE created_at BETWEEN ? AND ?', [$range->start(), $range->end()])['n'] ?? 0)
            : 0;

        $total = (int) ($appts['total'] ?? 0);
        $settlements = (int) ($money['settlements'] ?? 0);

        return [
            'bookings' => $total,
            'completed' => (int) ($appts['completed'] ?? 0),
            'cancelled' => (int) ($appts['cancelled'] ?? 0),
            'no_show' => (int) ($appts['no_show'] ?? 0),
            'walkins' => (int) ($appts['walkins'] ?? 0),
            'online' => (int) ($appts['online'] ?? 0),
            'active_salons' => (int) ($appts['active_salons'] ?? 0),
            'customers' => (int) ($appts['customers'] ?? 0),
            'new_customers' => $newCustomers,
            'new_salons' => $newSalons,
            'revenue' => (int) ($money['revenue'] ?? 0),
            'tips' => (int) ($money['tips'] ?? 0),
            'discounts' => (int) ($money['discounts'] ?? 0),
            'deposits' => (int) ($money['deposits'] ?? 0),
            'avg_ticket' => $settlements > 0 ? (int) round((int) $money['settlement_amount'] / $settlements) : 0,
            'cancel_rate' => $total > 0 ? round(((int) ($appts['cancelled'] ?? 0)) / $total * 100, 1) : 0.0,
            'no_show_rate' => $total > 0 ? round(((int) ($appts['no_show'] ?? 0)) / $total * 100, 1) : 0.0,
            'sms_sent' => (int) ($sms['sent'] ?? 0),
            'sms_failed' => (int) ($sms['failed'] ?? 0),
            'sms_queued' => (int) ($sms['queued'] ?? 0),
        ];
    }

    // ─── سری زمانی ────────────────────────────────────────────────────

    /** @return array<int,array{date:string,label:string,bookings:int,completed:int,cancelled:int,revenue:int}> */
    public function daily(ReportRange $range): array
    {
        [$w, $p] = $this->where('a');
        $rows = DB::select(
            "SELECT DATE(a.scheduled_at) AS d, COUNT(*) AS bookings, SUM(a.status = 'completed') AS completed,
                    SUM(a.status IN ('cancelled','no_show')) AS cancelled
               FROM appointments a {$this->join('a')}
              WHERE a.scheduled_at BETWEEN ? AND ? {$w}
              GROUP BY DATE(a.scheduled_at)",
            array_merge([$range->start(), $range->end()], $p)
        );
        [$w2, $p2] = $this->where('pm');
        $money = DB::select(
            "SELECT DATE(pm.paid_at) AS d, SUM(pm.amount) AS revenue
               FROM payments pm {$this->join('pm')}
              WHERE pm.paid_at BETWEEN ? AND ? {$w2}
              GROUP BY DATE(pm.paid_at)",
            array_merge([$range->start(), $range->end()], $p2)
        );
        $byDate = array_column($rows, null, 'd');
        $revenue = array_column($money, 'revenue', 'd');

        $out = [];
        foreach ($range->dates() as $date) {
            $row = $byDate[$date] ?? null;
            $out[] = [
                'date' => $date,
                'label' => Jalali::format(new DateTimeImmutable($date), 'j M'),
                'bookings' => (int) ($row['bookings'] ?? 0),
                'completed' => (int) ($row['completed'] ?? 0),
                'cancelled' => (int) ($row['cancelled'] ?? 0),
                'revenue' => (int) ($revenue[$date] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * شلوغی هر روز هفته × ساعت (شنبه = ۰). نوبت‌های لغوشده حساب نمی‌شوند.
     *
     * @return array{grid:array<int,array<int,int>>,max:int,hours:array<int,int>}
     */
    public function heatmap(ReportRange $range): array
    {
        [$w, $p] = $this->where('a');
        $rows = DB::select(
            "SELECT MOD(DAYOFWEEK(a.scheduled_at), 7) AS wd, HOUR(a.scheduled_at) AS h, COUNT(*) AS n
               FROM appointments a {$this->join('a')}
              WHERE a.scheduled_at BETWEEN ? AND ? AND a.status <> 'cancelled' {$w}
              GROUP BY wd, h",
            array_merge([$range->start(), $range->end()], $p)
        );
        $grid = array_fill(0, 7, []);
        $max = 0;
        $minHour = 9;
        $maxHour = 21;
        foreach ($rows as $r) {
            $h = (int) $r['h'];
            $grid[(int) $r['wd']][$h] = (int) $r['n'];
            $max = max($max, (int) $r['n']);
            $minHour = min($minHour, $h);
            $maxHour = max($maxHour, $h);
        }

        return ['grid' => $grid, 'max' => $max, 'hours' => range($minHour, $maxHour)];
    }

    /**
     * ماه‌به‌ماه (شمسی) برای ۱۲ ماه اخیر: سالن تازه، کاربر پنل تازه، نوبت.
     *
     * @return array<int,array{label:string,salons:int,users:int,bookings:int}>
     */
    public function growth(int $months = 12): array
    {
        $today = new DateTimeImmutable('today');
        [$jy, $jm] = Jalali::fromDateTime($today);
        $buckets = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $y = $jy;
            $m = $jm - $i;
            while ($m < 1) {
                $m += 12;
                $y--;
            }
            $buckets[sprintf('%04d-%02d', $y, $m)] = ['label' => Jalali::format(Jalali::toDateTime($y, $m, 1), 'M Y'), 'salons' => 0, 'users' => 0, 'bookings' => 0];
        }
        $first = array_key_first($buckets);
        [$fy, $fm] = array_map('intval', explode('-', (string) $first));
        $start = Jalali::toDateTime($fy, $fm, 1)->format('Y-m-d 00:00:00');

        $bucket = static function (string $date): string {
            [$y, $m] = Jalali::fromDateTime(new DateTimeImmutable($date));

            return sprintf('%04d-%02d', $y, $m);
        };
        $add = static function (array $rows, string $key) use (&$buckets, $bucket): void {
            foreach ($rows as $r) {
                $k = $bucket((string) $r['d']);
                if (isset($buckets[$k])) {
                    $buckets[$k][$key] += (int) $r['n'];
                }
            }
        };

        $add(DB::select('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM salons WHERE created_at >= ? GROUP BY DATE(created_at)', [$start]), 'salons');
        $add(DB::select(
            'SELECT DATE(u.created_at) AS d, COUNT(*) AS n FROM users u
              WHERE u.created_at >= ? AND (u.is_platform_admin = 1 OR EXISTS (SELECT 1 FROM salon_user su WHERE su.user_id = u.id))
              GROUP BY DATE(u.created_at)',
            [$start]
        ), 'users');
        [$w, $p] = $this->where('a');
        $add(DB::select(
            "SELECT DATE(a.scheduled_at) AS d, COUNT(*) AS n FROM appointments a {$this->join('a')}
              WHERE a.scheduled_at >= ? AND a.scheduled_at <= ? {$w} GROUP BY DATE(a.scheduled_at)",
            array_merge([$start, $today->format('Y-m-d 23:59:59')], $p)
        ), 'bookings');

        return array_values($buckets);
    }

    // ─── توزیع‌ها ─────────────────────────────────────────────────────

    /** @return array<string,int> */
    public function statuses(ReportRange $range): array
    {
        [$w, $p] = $this->where('a');

        return array_map('intval', array_column(DB::select(
            "SELECT a.status, COUNT(*) AS n FROM appointments a {$this->join('a')}
              WHERE a.scheduled_at BETWEEN ? AND ? {$w} GROUP BY a.status ORDER BY n DESC",
            array_merge([$range->start(), $range->end()], $p)
        ), 'n', 'status'));
    }

    /** @return array<string,array{count:int,amount:int}> */
    public function paymentMethods(ReportRange $range): array
    {
        [$w, $p] = $this->where('pm');
        $out = [];
        foreach (DB::select(
            "SELECT pm.method, COUNT(*) AS n, SUM(pm.amount) AS amount FROM payments pm {$this->join('pm')}
              WHERE pm.paid_at BETWEEN ? AND ? {$w} GROUP BY pm.method ORDER BY amount DESC",
            array_merge([$range->start(), $range->end()], $p)
        ) as $r) {
            $out[(string) $r['method']] = ['count' => (int) $r['n'], 'amount' => (int) $r['amount']];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function salons(ReportRange $range, string $sort = 'bookings', int $limit = 100): array
    {
        $order = match ($sort) {
            'revenue' => 'revenue DESC',
            'completed' => 'completed DESC',
            'cancel_rate' => 'cancel_rate DESC',
            'no_show' => 'no_show DESC',
            'new_customers' => 'new_customers DESC',
            'sms' => 'sms DESC',
            default => 'bookings DESC',
        };
        [$w, $p] = $this->where('s', true);

        return DB::select(
            "SELECT s.id, s.name, s.city, s.audience, s.is_active,
                    COALESCE(ap.bookings, 0) AS bookings, COALESCE(ap.completed, 0) AS completed,
                    COALESCE(ap.cancelled, 0) AS cancelled, COALESCE(ap.no_show, 0) AS no_show,
                    COALESCE(ap.walkins, 0) AS walkins,
                    CASE WHEN COALESCE(ap.bookings, 0) > 0 THEN ROUND(ap.cancelled / ap.bookings * 100, 1) ELSE 0 END AS cancel_rate,
                    COALESCE(py.revenue, 0) AS revenue, COALESCE(cu.n, 0) AS new_customers, COALESCE(sm.n, 0) AS sms
               FROM salons s
               LEFT JOIN (SELECT salon_id, COUNT(*) AS bookings, SUM(status = 'completed') AS completed,
                                 SUM(status = 'cancelled') AS cancelled, SUM(status = 'no_show') AS no_show, SUM(kind = 'walkin') AS walkins
                            FROM appointments WHERE scheduled_at BETWEEN ? AND ? GROUP BY salon_id) ap ON ap.salon_id = s.id
               LEFT JOIN (SELECT salon_id, SUM(amount) AS revenue FROM payments WHERE paid_at BETWEEN ? AND ? GROUP BY salon_id) py ON py.salon_id = s.id
               LEFT JOIN (SELECT salon_id, COUNT(*) AS n FROM customers WHERE created_at BETWEEN ? AND ? AND deleted_at IS NULL GROUP BY salon_id) cu ON cu.salon_id = s.id
               LEFT JOIN (SELECT salon_id, COUNT(*) AS n FROM sms_messages WHERE status = 'sent' AND created_at BETWEEN ? AND ? GROUP BY salon_id) sm ON sm.salon_id = s.id
              WHERE 1 = 1 {$w}
              ORDER BY {$order}, s.name
              LIMIT " . max(1, min(1000, $limit)),
            array_merge([$range->start(), $range->end(), $range->start(), $range->end(), $range->start(), $range->end(), $range->start(), $range->end()], $p)
        );
    }

    /** @return array<int,array{name:string,count:int,revenue:int,salons:int}> */
    public function services(ReportRange $range, int $limit = 20): array
    {
        [$w, $p] = $this->where('a');

        return array_map(static fn (array $r): array => [
            'name' => (string) $r['name'],
            'count' => (int) $r['n'],
            'revenue' => (int) $r['revenue'],
            'salons' => (int) $r['salons'],
        ], DB::select(
            "SELECT sv.name, COUNT(*) AS n, SUM(CASE WHEN a.status = 'completed' THEN ai.price ELSE 0 END) AS revenue,
                    COUNT(DISTINCT a.salon_id) AS salons
               FROM appointment_items ai
               JOIN appointments a ON a.id = ai.appointment_id
               JOIN services sv ON sv.id = ai.service_id
               {$this->join('a')}
              WHERE a.scheduled_at BETWEEN ? AND ? AND a.status <> 'cancelled' {$w}
              GROUP BY sv.name ORDER BY n DESC LIMIT " . max(1, min(200, $limit)),
            array_merge([$range->start(), $range->end()], $p)
        ));
    }

    /** @return array<int,array{city:string,bookings:int,salons:int}> */
    public function cities(ReportRange $range): array
    {
        [$w, $p] = $this->where('s', true);

        return array_map(static fn (array $r): array => ['city' => (string) ($r['city'] ?: 'نامشخص'), 'bookings' => (int) $r['n'], 'salons' => (int) $r['salons']], DB::select(
            "SELECT s.city, COUNT(*) AS n, COUNT(DISTINCT a.salon_id) AS salons
               FROM appointments a JOIN salons s ON s.id = a.salon_id
              WHERE a.scheduled_at BETWEEN ? AND ? {$w}
              GROUP BY s.city ORDER BY n DESC LIMIT 20",
            array_merge([$range->start(), $range->end()], $p)
        ));
    }

    /** @return array{new:int,returning:int} */
    public function customerMix(ReportRange $range): array
    {
        [$w, $p] = $this->where('a');
        $row = DB::selectOne(
            "SELECT SUM(c.created_at >= ?) AS fresh, SUM(c.created_at < ?) AS repeat_n
               FROM (SELECT DISTINCT a.customer_id FROM appointments a {$this->join('a')}
                      WHERE a.scheduled_at BETWEEN ? AND ? AND a.status = 'completed' {$w}) x
               JOIN customers c ON c.id = x.customer_id",
            array_merge([$range->start(), $range->start(), $range->start(), $range->end()], $p)
        ) ?? [];

        return ['new' => (int) ($row['fresh'] ?? 0), 'returning' => (int) ($row['repeat_n'] ?? 0)];
    }

    /** @return array{statuses:array<string,int>,templates:array<int,array{template:string,sent:int,failed:int,skipped:int}>} */
    public function sms(ReportRange $range): array
    {
        [$w, $p] = $this->where('m');
        $statuses = array_map('intval', array_column(DB::select(
            "SELECT m.status, COUNT(*) AS n FROM sms_messages m {$this->join('m')}
              WHERE m.created_at BETWEEN ? AND ? {$w} GROUP BY m.status ORDER BY n DESC",
            array_merge([$range->start(), $range->end()], $p)
        ), 'n', 'status'));
        $templates = array_map(static fn (array $r): array => [
            'template' => (string) $r['template_code'],
            'sent' => (int) $r['sent'],
            'failed' => (int) $r['failed'],
            'skipped' => (int) $r['skipped'],
        ], DB::select(
            "SELECT m.template_code, SUM(m.status = 'sent') AS sent, SUM(m.status = 'failed') AS failed,
                    SUM(m.status LIKE 'skipped%') AS skipped
               FROM sms_messages m {$this->join('m')}
              WHERE m.created_at BETWEEN ? AND ? {$w}
              GROUP BY m.template_code ORDER BY sent DESC",
            array_merge([$range->start(), $range->end()], $p)
        ));

        return ['statuses' => $statuses, 'templates' => $templates];
    }

    // ─── فیلترها ──────────────────────────────────────────────────────

    /** join با salons فقط وقتی فیلتر شهر یا نوع داریم */
    private function join(string $alias): string
    {
        return !empty($this->filters['audience']) || !empty($this->filters['city'])
            ? "JOIN salons fs ON fs.id = {$alias}.salon_id"
            : '';
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function where(string $alias, bool $isSalonTable = false): array
    {
        $sql = '';
        $params = [];
        $salonCol = $isSalonTable ? "{$alias}.id" : "{$alias}.salon_id";
        $salonAlias = $isSalonTable ? $alias : 'fs';
        if (!empty($this->filters['salon_id'])) {
            $sql .= " AND {$salonCol} = ?";
            $params[] = (int) $this->filters['salon_id'];
        }
        if (!empty($this->filters['audience'])) {
            $sql .= " AND {$salonAlias}.audience = ?";
            $params[] = (string) $this->filters['audience'];
        }
        if (!empty($this->filters['city'])) {
            $sql .= " AND {$salonAlias}.city = ?";
            $params[] = (string) $this->filters['city'];
        }

        return [$sql, $params];
    }
}
