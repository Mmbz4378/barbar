<?php

declare(strict_types=1);

namespace App\Domain\Staff;

use App\Core\DB;
use DateTimeImmutable;

/**
 * مرخصی و بستنِ موردیِ سانس.
 *
 * جدولش از اول بود و SlotFinder هم می‌خواندش، ولی هیچ صفحه‌ای برای
 * ساختن ردیف نداشت — یعنی قابلیتی که کار می‌کرد و دست کسی نمی‌رسید.
 *
 * با ساعت کاری فرق دارد: ساعت کاری قاعدهٔ هر هفته است، این استثنای
 * یک روز یا چند ساعت است. «پنجشنبه بعدازظهر می‌روم عروسی» را نباید
 * با عوض کردن ساعت کاریِ همهٔ پنجشنبه‌ها حل کرد.
 *
 * staff_id تهی یعنی کل آرایشگاه بسته است.
 */
final class TimeOffRepository
{
    /** @return array<int,array> از امروز به بعد، نزدیک‌ترین اول */
    public function upcoming(int $salonId, int $limit = 60): array
    {
        return DB::select(
            'SELECT t.*, s.name AS staff_name
               FROM time_offs t
               LEFT JOIN staff s ON s.id = t.staff_id
              WHERE t.salon_id = ? AND t.ends_at >= NOW()
              ORDER BY t.starts_at
              LIMIT ' . max(1, $limit),
            [$salonId]
        );
    }

    /**
     * ثبت یک بازهٔ بسته.
     *
     * @return string|null پیام خطا، یا null اگر ثبت شد
     */
    public function add(
        int $salonId,
        ?int $staffId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $reason
    ): ?string {
        if ($end <= $start) {
            return 'ساعت پایان باید بعد از ساعت شروع باشد.';
        }

        /*
         * آرایشگر باید مال همین آرایشگاه باشد. بدون این بررسی، یک
         * شناسهٔ دستکاری‌شده در فرم می‌توانست آرایشگرِ آرایشگاهِ دیگری
         * را مرخصی بزند.
         */
        if ($staffId !== null) {
            $owned = DB::selectOne(
                'SELECT id FROM staff WHERE id = ? AND salon_id = ?',
                [$staffId, $salonId]
            );
            if ($owned === null) {
                return 'این آرایشگر در این آرایشگاه نیست.';
            }
        }

        DB::insert('time_offs', [
            'salon_id' => $salonId,
            'staff_id' => $staffId,
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end->format('Y-m-d H:i:s'),
            'reason' => $reason !== '' ? $reason : null,
        ]);

        return null;
    }

    public function remove(int $salonId, int $id): void
    {
        DB::delete('time_offs', 'id = ? AND salon_id = ?', [$id, $salonId]);
    }

    /**
     * نوبت‌هایی که داخل این بازه می‌افتند.
     *
     * بستن یک بازه، نوبت‌های ثبت‌شده را لغو نمی‌کند — ولی صاحب سالن
     * باید بداند چند نفر را باید خبر کند، وگرنه مرخصی می‌گذارد و
     * روزش سه نفر پشت در می‌مانند.
     *
     * @return array<int,array>
     */
    public function clashingAppointments(int $salonId, ?int $staffId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $sql = "SELECT a.id, a.scheduled_at, c.name AS customer_name, c.phone
                  FROM appointments a
                  JOIN customers c ON c.id = a.customer_id
                 WHERE a.salon_id = ?
                   AND a.status IN ('confirmed','queued','in_chair')
                   AND a.scheduled_at >= ? AND a.scheduled_at < ?";
        $args = [$salonId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];

        if ($staffId !== null) {
            $sql .= ' AND a.staff_id = ?';
            $args[] = $staffId;
        }

        return DB::select($sql . ' ORDER BY a.scheduled_at', $args);
    }
}
