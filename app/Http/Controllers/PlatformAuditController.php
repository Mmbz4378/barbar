<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Identity\LoginEvents;
use App\Domain\Reports\ReportRange;
use App\Support\AuditLabels;
use App\Support\Csv;

/**
 * رویدادها و ورودها: چه کسی، کی، چه کرد — برای پاسخ‌گویی و ردیابی.
 */
final class PlatformAuditController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'logins' ? 'logins' : 'events';
        $range = ReportRange::fromRequest($request, '30d');
        $page = max(1, (int) $request->query('page', '1'));
        [$where, $params, $filters] = $tab === 'logins' ? $this->loginFilters($request, $range) : $this->eventFilters($request, $range);

        if ($tab === 'logins') {
            $total = (int) (DB::selectOne("SELECT COUNT(*) AS c FROM login_events le LEFT JOIN users u ON u.id = le.user_id WHERE {$where}", $params)['c'] ?? 0);
            $rows = DB::select(
                "SELECT le.*, u.name AS user_name, u.phone AS user_phone FROM login_events le LEFT JOIN users u ON u.id = le.user_id
                  WHERE {$where} ORDER BY le.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . ((min($page, max(1, (int) ceil($total / self::PER_PAGE))) - 1) * self::PER_PAGE),
                $params
            );
            $summary = DB::selectOne(
                "SELECT SUM(success = 1) AS ok, SUM(success = 0) AS failed, COUNT(DISTINCT ip_address) AS ips
                   FROM login_events WHERE created_at BETWEEN ? AND ?",
                [$range->start(), $range->end()]
            ) ?? [];
            $topIps = DB::select(
                'SELECT ip_address, COUNT(*) AS n FROM login_events WHERE success = 0 AND created_at BETWEEN ? AND ? AND ip_address IS NOT NULL
                  GROUP BY ip_address ORDER BY n DESC LIMIT 5',
                [$range->start(), $range->end()]
            );
        } else {
            $total = (int) (DB::selectOne("SELECT COUNT(*) AS c FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id WHERE {$where}", $params)['c'] ?? 0);
            $rows = DB::select(
                "SELECT al.*, u.name AS actor_name, u.phone AS actor_phone, s.name AS salon_name
                   FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id LEFT JOIN salons s ON s.id = al.salon_id
                  WHERE {$where} ORDER BY al.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . ((min($page, max(1, (int) ceil($total / self::PER_PAGE))) - 1) * self::PER_PAGE),
                $params
            );
            $summary = [];
            $topIps = [];
        }

        return $this->page('layouts.platform', 'platform.audit', [
            'title' => $tab === 'logins' ? 'ورودها' : 'رویدادها',
            'tab' => $tab,
            'range' => $range,
            'filters' => $filters,
            'rows' => $rows,
            'total' => $total,
            'page' => min($page, max(1, (int) ceil($total / self::PER_PAGE))),
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'summary' => $summary,
            'topIps' => $topIps,
            'salonOptions' => DB::select('SELECT id, name FROM salons ORDER BY name'),
        ]);
    }

    public function export(Request $request): Response
    {
        $range = ReportRange::fromRequest($request, '30d');
        $stamp = $range->from->format('Ymd') . '-' . $range->to->format('Ymd');

        if ($request->query('tab') === 'logins') {
            [$where, $params] = $this->loginFilters($request, $range);
            $rows = DB::select(
                "SELECT le.created_at, le.identifier, u.name, u.phone, le.method, le.success, le.reason, le.ip_address, le.user_agent
                   FROM login_events le LEFT JOIN users u ON u.id = le.user_id WHERE {$where} ORDER BY le.id DESC LIMIT 20000",
                $params
            );

            return Csv::response("logins-{$stamp}.csv", ['زمان', 'شناسهٔ واردشده', 'کاربر', 'موبایل', 'روش', 'نتیجه', 'علت', 'IP', 'مرورگر'],
                array_map(static fn (array $r): array => [
                    jdate((string) $r['created_at'], 'Y/m/d H:i'), $r['identifier'], $r['name'], $r['phone'], LoginEvents::methodLabel((string) $r['method']),
                    (int) $r['success'] === 1 ? 'موفق' : 'ناموفق', LoginEvents::reasonLabel($r['reason'] ?? null), $r['ip_address'], $r['user_agent'],
                ], $rows));
        }

        [$where, $params] = $this->eventFilters($request, $range);
        $rows = DB::select(
            "SELECT al.created_at, al.action, u.name AS actor, s.name AS salon, al.subject_type, al.subject_id, al.meta_json, al.ip_address
               FROM audit_logs al LEFT JOIN users u ON u.id = al.actor_user_id LEFT JOIN salons s ON s.id = al.salon_id
              WHERE {$where} ORDER BY al.id DESC LIMIT 20000",
            $params
        );

        return Csv::response("events-{$stamp}.csv", ['زمان', 'رویداد', 'انجام‌دهنده', 'سالن', 'موضوع', 'شناسه', 'جزئیات', 'IP'],
            array_map(static fn (array $r): array => [
                jdate((string) $r['created_at'], 'Y/m/d H:i'), AuditLabels::action((string) $r['action']), $r['actor'] ?? 'سامانه',
                $r['salon'], $r['subject_type'], $r['subject_id'], implode(' · ', AuditLabels::describe($r['meta_json'] ?? null)), $r['ip_address'],
            ], $rows));
    }

    /** @return array{0:string,1:array<int,mixed>,2:array<string,string>} */
    private function eventFilters(Request $request, ReportRange $range): array
    {
        $where = ['al.created_at BETWEEN ? AND ?'];
        $params = [$range->start(), $range->end()];
        $action = (string) $request->query('action', '');
        $salon = (int) $request->query('salon', '0');
        $q = trim((string) $request->query('q', ''));
        if ($action !== '' && array_key_exists($action, AuditLabels::all())) {
            $where[] = 'al.action = ?';
            $params[] = $action;
        }
        if ($salon > 0) {
            $where[] = 'al.salon_id = ?';
            $params[] = $salon;
        }
        if ($q !== '') {
            $like = '%' . addcslashes(\App\Support\Jalali::fromPersianDigits($q), '%_\\') . '%';
            $where[] = '(u.name LIKE ? OR u.phone LIKE ? OR u.username LIKE ? OR al.ip_address LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params, ['action' => $action, 'salon' => $salon > 0 ? (string) $salon : '', 'q' => $q]];
    }

    /** @return array{0:string,1:array<int,mixed>,2:array<string,string>} */
    private function loginFilters(Request $request, ReportRange $range): array
    {
        $where = ['le.created_at BETWEEN ? AND ?'];
        $params = [$range->start(), $range->end()];
        $result = (string) $request->query('result', '');
        $method = (string) $request->query('method', '');
        $q = trim((string) $request->query('q', ''));
        if ($result === 'failed' || $result === 'ok') {
            $where[] = 'le.success = ?';
            $params[] = $result === 'ok' ? 1 : 0;
        }
        if (in_array($method, ['password', 'otp', 'link', 'reset'], true)) {
            $where[] = 'le.method = ?';
            $params[] = $method;
        }
        if ($q !== '') {
            $like = '%' . addcslashes(\App\Support\Jalali::fromPersianDigits($q), '%_\\') . '%';
            $where[] = '(le.identifier LIKE ? OR le.ip_address LIKE ? OR u.name LIKE ? OR u.phone LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        return [implode(' AND ', $where), $params, ['result' => $result, 'method' => $method, 'q' => $q]];
    }
}
