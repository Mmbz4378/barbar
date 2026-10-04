<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Core\Auth;
use App\Core\DB;
use App\Support\Audience;

/**
 * منوی پنل برای هر نقش.
 *
 * از همان سیاستی می‌خواند که مسیرها را می‌بندد (Access)، پس لینکی که
 * ۴۰۳ بدهد در منو دیده نمی‌شود. روی موبایل حداکثر چهار مقصد اصلی و
 * «بیشتر»؛ آرایشگر منوی کامل مدیر را نمی‌بیند.
 */
final class PanelNavigation
{
    /** @return array{primary:array<int,array>,groups:array<string,array<int,array>>,more:array<int,array>} */
    public static function build(): array
    {
        $role = Auth::role();
        $manage = Access::allows(Access::MANAGE_SALON);
        $desk = Access::allows(Access::BOOK_FOR_OTHERS);
        $badges = self::badges($desk);

        $daily = [
            ['href' => '/panel', 'label' => 'امروز', 'icon' => 'home', 'exact' => true, 'badge' => $badges['settle']],
            ['href' => '/panel/bookings', 'label' => $role === 'staff' ? 'نوبت‌های من' : 'رزروها', 'icon' => 'calendar-days', 'badge' => $badges['deposit']],
        ];
        if (Access::allows(Access::VIEW_CUSTOMERS)) {
            $daily[] = ['href' => '/panel/customers', 'label' => 'مشتریان', 'icon' => 'users'];
        }
        if ($manage) {
            $daily[] = ['href' => '/panel/reports', 'label' => 'گزارش‌ها', 'icon' => 'chart'];
        }

        $management = [];
        if ($manage) {
            $management = [
                ['href' => '/panel/services', 'label' => 'خدمات و قیمت‌ها', 'icon' => 'tag'],
                ['href' => '/panel/staff', 'label' => 'تیم و دسترسی‌ها', 'icon' => 'user-check'],
                ['href' => '/panel/settings', 'label' => 'تنظیمات سالن', 'icon' => 'cog'],
                ['href' => '/panel/publication', 'label' => 'صفحهٔ عمومی سالن', 'icon' => 'store'],
                ['href' => '/panel/sms', 'label' => 'پیامک‌ها', 'icon' => 'message'],
            ];
        }
        $tools = [['href' => '/panel/qr', 'label' => 'لینک و کد QR', 'icon' => 'qr']];

        $groups = ['کارهای روزانه' => $daily];
        if ($management !== []) {
            $groups['مدیریت سالن'] = $management;
        }
        $groups['ابزارها'] = $tools;

        $all = array_merge($daily, $management, $tools);
        $primary = array_slice($daily, 0, 4);
        $more = array_values(array_filter($all, static fn ($i) => !in_array($i, $primary, true)));

        // اگر فقط یک مقصد اضافه بماند، «بیشتر» لازم نیست
        if (count($more) === 1 && count($primary) < 5) {
            $primary[] = $more[0];
            $more = [];
        }

        return ['primary' => $primary, 'groups' => $groups, 'more' => $more];
    }

    /** @return array{settle:int,deposit:int} */
    private static function badges(bool $desk): array
    {
        $salonId = Auth::salonId();
        if (!$desk || $salonId === null) {
            return ['settle' => 0, 'deposit' => 0];
        }

        $row = DB::selectOne(
            "SELECT
                (SELECT COUNT(*) FROM appointments WHERE salon_id = ? AND status = 'pending' AND deposit_amount > 0) AS deposit,
                (SELECT COUNT(*) FROM appointments a WHERE a.salon_id = ? AND a.status = 'completed'
                    AND a.actual_end_at >= CURDATE()
                    AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.appointment_id = a.id AND p.kind = 'settlement')) AS settle",
            [$salonId, $salonId]
        );

        return ['settle' => (int) ($row['settle'] ?? 0), 'deposit' => (int) ($row['deposit'] ?? 0)];
    }

    public static function roleLabel(): string
    {
        return Auth::isImpersonating() ? 'پشتیبانی پلتفرم' : role_label(Auth::role());
    }

    public static function audienceLabel(): string
    {
        return Audience::term('salon_type');
    }
}
