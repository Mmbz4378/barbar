<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Cron;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Reports\PlatformReports;
use App\Domain\Reports\ReportRange;
use App\Domain\System\SiteSettings;
use App\Domain\System\Updater;
use App\Support\Now;

/**
 * داشبورد مدیر کل: نگاه یک‌صفحه‌ای به کل سامانه و هر چیزی که کار دارد.
 */
final class PlatformDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = new PlatformReports();
        $range = ReportRange::lastDays(30);
        $today = ReportRange::lastDays(1);

        $kpis = $reports->kpis($range);
        $previous = $reports->kpis($range->previous());

        return $this->page('layouts.platform', 'platform.dashboard', [
            'title' => 'داشبورد',
            'range' => $range,
            'kpis' => $kpis,
            'previous' => $previous,
            'todayKpis' => $reports->kpis($today),
            'daily' => $reports->daily($range),
            'topSalons' => array_slice(array_filter($reports->salons($range, 'bookings', 5), static fn (array $s): bool => (int) $s['bookings'] > 0), 0, 5),
            'totals' => DB::selectOne(
                "SELECT COUNT(*) AS salons, SUM(is_active = 1) AS active,
                        (SELECT COUNT(*) FROM users u WHERE u.is_platform_admin = 1 OR EXISTS (SELECT 1 FROM salon_user su WHERE su.user_id = u.id AND su.is_active = 1)) AS panel_users
                   FROM salons"
            ) ?? [],
            'alerts' => $this->alerts(),
            'events' => DB::select(
                "SELECT al.action, al.created_at, al.salon_id, s.name AS salon_name, u.name AS actor_name
                   FROM audit_logs al LEFT JOIN salons s ON s.id = al.salon_id LEFT JOIN users u ON u.id = al.actor_user_id
                  ORDER BY al.id DESC LIMIT 8"
            ),
        ]);
    }

    /**
     * هر چیزی که منتظر اقدام مدیر است، با پیوند مستقیم به جای رسیدگی.
     *
     * @return array<int,array{tone:string,icon:string,text:string,href:string}>
     */
    private function alerts(): array
    {
        $alerts = [];
        $add = static function (string $tone, string $icon, string $text, string $href) use (&$alerts): void {
            $alerts[] = compact('tone', 'icon', 'text', 'href');
        };

        if (SiteSettings::maintenanceOn()) {
            $add('warning', 'cog', 'حالت تعمیر روشن است؛ سایت برای بازدیدکننده‌ها بسته است.', '/platform/settings?tab=maintenance');
        }
        $cron = Cron::lastRunAt();
        if ($cron === null || time() - $cron > 20 * 60) {
            $add('danger', 'clock', $cron === null ? 'کرون هرگز اجرا نشده؛ یادآورها و پیامک‌های مانده فرستاده نمی‌شوند.' : 'کرون بیش از ۲۰ دقیقه است اجرا نشده.', '/doctor.php');
        }
        if ((new Updater())->available() !== null) {
            $add('info', 'refresh', 'نسخهٔ تازهٔ سامانه آماده است.', '/system/updates');
        }

        $row = DB::selectOne(
            "SELECT
                (SELECT COUNT(*) FROM salons WHERE publication_status = 'pending') AS pending_pub,
                (SELECT COUNT(*) FROM reviews WHERE moderation_status = 'pending') AS pending_reviews,
                (SELECT COUNT(*) FROM salons WHERE is_active = 1 AND sms_credit < 20) AS low_sms,
                (SELECT COUNT(*) FROM salons WHERE is_active = 1 AND plan_code = 'trial' AND trial_ends_at IS NOT NULL AND trial_ends_at <= ?) AS trial_ending,
                (SELECT COUNT(*) FROM salons s WHERE NOT EXISTS (SELECT 1 FROM salon_user su JOIN users u ON u.id = su.user_id
                    WHERE su.salon_id = s.id AND su.role = 'owner' AND su.is_active = 1 AND u.is_active = 1)) AS ownerless,
                (SELECT COUNT(*) FROM sms_messages WHERE status IN ('queued','sending') AND created_at < ?) AS stuck_sms,
                (SELECT COUNT(*) FROM login_events WHERE success = 0 AND created_at >= ?) AS failed_logins",
            [
                Now::get()->modify('+7 days')->format('Y-m-d H:i:s'),
                date('Y-m-d H:i:s', time() - 15 * 60),
                date('Y-m-d H:i:s', time() - 86400),
            ]
        ) ?? [];

        if (($n = (int) ($row['pending_pub'] ?? 0) + (int) ($row['pending_reviews'] ?? 0)) > 0) {
            $add('warning', 'shield', fa_num($n) . ' مورد انتشار یا نظر منتظر بررسی است.', '/platform/moderation');
        }
        if (($n = (int) ($row['ownerless'] ?? 0)) > 0) {
            $add('danger', 'user-x', fa_num($n) . ' سالن صاحب فعال ندارد.', '/platform/salons');
        }
        if (($n = (int) ($row['low_sms'] ?? 0)) > 0) {
            $add('warning', 'message', fa_num($n) . ' سالن کمتر از ۲۰ پیامک اعتبار دارد.', '/platform/salons?status=low_sms');
        }
        if (($n = (int) ($row['trial_ending'] ?? 0)) > 0) {
            $add('info', 'hourglass', 'دورهٔ آزمایشی ' . fa_num($n) . ' سالن تا یک هفتهٔ دیگر تمام می‌شود.', '/platform/salons?status=trial_ending');
        }
        if (($n = (int) ($row['stuck_sms'] ?? 0)) > 0) {
            $add('warning', 'message', fa_num($n) . ' پیامک بیش از ۱۵ دقیقه در صف مانده؛ اپراتور یا کرون را بررسی کنید.', '/platform/reports?section=sms');
        }
        if (($n = (int) ($row['failed_logins'] ?? 0)) >= 20) {
            $add('warning', 'lock', fa_num($n) . ' ورود ناموفق در ۲۴ ساعت گذشته.', '/platform/audit?tab=logins&result=failed');
        }

        return $alerts;
    }
}
