<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Core\DB;
use App\Core\Session;

/**
 * هویت مشتری — سطح سومِ دسترسی، جدا از حساب‌های آرایشگاه.
 *
 * چرا کلید نشستِ جدا و نه همان user_id:
 *
 * مشتری در جدول users نیست. او با شمارهٔ موبایلش شناخته می‌شود و در
 * هر آرایشگاهی که رفته یک ردیف customers دارد. اگر شناسه‌اش را در
 * همان کلیدی می‌گذاشتیم که ورود کارکنان استفاده می‌کند، یک اشتباه
 * کوچک در آینده — مثلاً میدل‌وری که فقط Session::get('user_id') را
 * چک کند — در پنل آرایشگاه را به روی مشتری باز می‌کرد.
 *
 * با کلید جدا، آن اشتباه ممکن نیست: مشتریِ واردشده از نظر Auth اصلاً
 * وارد نشده است.
 */
final class CustomerAuth
{
    private const KEY = 'customer_phone';

    public static function login(string $e164): void
    {
        Session::regenerate();
        Session::put(self::KEY, $e164);
    }

    public static function logout(): void
    {
        Session::forget(self::KEY);
    }

    public static function check(): bool
    {
        return self::phone() !== null;
    }

    public static function phone(): ?string
    {
        $phone = Session::get(self::KEY);

        return is_string($phone) && $phone !== '' ? $phone : null;
    }

    /**
     * نوبت‌های این شماره در همهٔ آرایشگاه‌ها.
     *
     * شماره، نه شناسهٔ مشتری: یک نفر ممکن است در سه آرایشگاه سه ردیف
     * customers داشته باشد و انتظار دارد همهٔ نوبت‌هایش را یک‌جا ببیند.
     *
     * @return array<int,array>
     */
    public static function appointments(string $e164, bool $upcoming): array
    {
        $now = date('Y-m-d H:i:s');

        /*
         * نوبت‌های آینده رو به جلو مرتب می‌شوند (نزدیک‌ترین اول) و
         * گذشته رو به عقب (تازه‌ترین اول) — چون در هر دو حالت، چیزی
         * که کاربر می‌خواهد ببیند به «الان» نزدیک‌تر است.
         */
        $where = $upcoming
            ? "COALESCE(a.scheduled_at, a.queued_at) >= ? AND a.status IN ('confirmed','queued','in_chair')"
            : "(COALESCE(a.scheduled_at, a.queued_at) < ? OR a.status IN ('completed','cancelled','no_show'))";
        $order = $upcoming ? 'ASC' : 'DESC';

        return DB::select(
            "SELECT a.*, s.name AS salon_name, s.slug AS salon_slug, s.theme AS salon_theme,
                    st.name AS staff_name,
                    COALESCE(SUM(ai.price), 0) AS total_price,
                    GROUP_CONCAT(sv.name ORDER BY ai.id SEPARATOR '، ') AS service_names
               FROM appointments a
               JOIN customers c ON c.id = a.customer_id
               JOIN salons s ON s.id = a.salon_id
               LEFT JOIN staff st ON st.id = a.staff_id
               LEFT JOIN appointment_items ai ON ai.appointment_id = a.id
               LEFT JOIN services sv ON sv.id = ai.service_id
              WHERE c.phone = ? AND {$where}
              GROUP BY a.id
              ORDER BY COALESCE(a.scheduled_at, a.queued_at) {$order}
              LIMIT 50",
            [$e164, $now]
        );
    }
}
