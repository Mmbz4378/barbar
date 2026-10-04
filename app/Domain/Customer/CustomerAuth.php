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
         * آینده رو به جلو (نزدیک‌ترین اول)، گذشته رو به عقب (تازه‌ترین اول).
         * بخش‌های یک رزرو چندنفره یک کارت می‌شوند: فقط بخش اول برگردانده
         * می‌شود و نام همهٔ خدمات و افراد گروه روی آن جمع می‌شود.
         */
        $where = $upcoming
            ? "COALESCE(a.scheduled_at, a.queued_at) >= ? AND a.status IN ('pending','confirmed','queued','in_chair')"
            : "(COALESCE(a.scheduled_at, a.queued_at) < ? OR a.status IN ('completed','cancelled','no_show'))";
        $order = $upcoming ? 'ASC' : 'DESC';

        $rows = DB::select(
            "SELECT a.*, s.name AS salon_name, s.slug AS salon_slug, s.theme AS salon_theme, s.audience AS salon_audience,
                    st.name AS staff_name,
                    (SELECT COALESCE(SUM(ai.price), 0) FROM appointment_items ai WHERE ai.appointment_id = a.id) AS total_price,
                    (SELECT GROUP_CONCAT(sv.name ORDER BY ai.id SEPARATOR '، ')
                       FROM appointment_items ai JOIN services sv ON sv.id = ai.service_id
                      WHERE ai.appointment_id = a.id) AS service_names,
                    (SELECT r.id FROM reviews r WHERE r.appointment_id = a.id LIMIT 1) AS review_id
               FROM appointments a
               JOIN customers c ON c.id = a.customer_id
               JOIN salons s ON s.id = a.salon_id
               LEFT JOIN staff st ON st.id = a.staff_id
              WHERE c.phone = ? AND {$where}
              ORDER BY COALESCE(a.scheduled_at, a.queued_at) {$order}, a.id
              LIMIT 80",
            [$e164, $now]
        );

        $cards = [];
        foreach ($rows as $row) {
            $key = $row['group_token'] !== null ? 'g:' . $row['group_token'] : 'a:' . $row['id'];
            if (!isset($cards[$key])) {
                $row['part_count'] = 1;
                $row['staff_names'] = array_filter([$row['staff_name']]);
                $cards[$key] = $row;
                continue;
            }
            $card = &$cards[$key];
            $card['part_count']++;
            $card['total_price'] += (int) $row['total_price'];
            $card['service_names'] = trim($card['service_names'] . '، ' . $row['service_names'], '، ');
            if ($row['staff_name'] !== null) {
                $card['staff_names'][] = $row['staff_name'];
            }
            // کارت گروه همیشه با زودترین بخش نمایش داده می‌شود
            if (strcmp((string) $row['scheduled_at'], (string) $card['scheduled_at']) < 0) {
                foreach (['id', 'public_token', 'scheduled_at', 'status'] as $field) {
                    $card[$field] = $row[$field];
                }
            }
            unset($card);
        }

        return array_slice(array_values($cards), 0, 50);
    }
}
