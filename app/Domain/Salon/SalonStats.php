<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\DB;

/**
 * آمار نمایشیِ کارت سالن در «کشف».
 *
 * قبلاً هر جست‌وجو کمینهٔ قیمت و میانگین امتیاز را با زیرکوئری روی کل
 * جدول خدمات و نظرهای همهٔ سالن‌ها حساب می‌کرد — با هزار سالن، هر
 * بازدید صفحهٔ کشف یعنی پیمایش ده‌ها هزار ردیف. حالا این دو عدد روی
 * خود سالن نگه داشته و فقط هنگام تغییرشان به‌روز می‌شوند.
 */
final class SalonStats
{
    public function refreshPrices(int $salonId): void
    {
        DB::statement(
            'UPDATE salons SET min_price = (
                SELECT MIN(price) FROM services WHERE salon_id = ? AND is_active = 1 AND price > 0
             ) WHERE id = ?',
            [$salonId, $salonId]
        );
        SalonRepository::forget($salonId);
    }

    public function refreshRating(int $salonId): void
    {
        $row = DB::selectOne(
            "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total
               FROM reviews WHERE salon_id = ? AND moderation_status = 'published'",
            [$salonId]
        );

        DB::update('salons', [
            'rating_avg' => $row && (int) $row['total'] > 0 ? round((float) $row['avg_rating'], 2) : null,
            'rating_count' => (int) ($row['total'] ?? 0),
        ], 'id = :id', ['id' => $salonId]);
        SalonRepository::forget($salonId);
    }
}
