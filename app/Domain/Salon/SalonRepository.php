<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\DB;

final class SalonRepository
{
    /** @var array<int,array> */
    private static array $byId = [];

    public function find(int $id): ?array
    {
        if (!array_key_exists($id, self::$byId)) {
            self::$byId[$id] = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$id]);
        }

        return self::$byId[$id];
    }

    /** سالنِ فعال با نشانی عمومی — برای صفحه‌های رزرو. */
    public function findActiveBySlug(string $slug): ?array
    {
        if ($slug === '' || mb_strlen($slug) > 60) {
            return null;
        }

        $salon = DB::selectOne('SELECT * FROM salons WHERE slug = ? AND is_active = 1', [$slug]);
        if ($salon !== null) {
            self::$byId[(int) $salon['id']] = $salon;
        }

        return $salon;
    }

    public static function forget(int $id): void
    {
        unset(self::$byId[$id]);
    }

    /** آیا الان ساعت کاری سالن است؟ */
    public function isOpenNow(int $salonId): bool
    {
        $now = new \DateTimeImmutable();

        return DB::selectOne(
            'SELECT 1 AS ok FROM working_hours
              WHERE salon_id = ? AND staff_id IS NULL AND weekday = ? AND is_closed = 0
                AND ? BETWEEN opens_at AND closes_at
              LIMIT 1',
            [$salonId, \App\Support\Jalali::weekday($now), $now->format('H:i:s')]
        ) !== null;
    }
}
