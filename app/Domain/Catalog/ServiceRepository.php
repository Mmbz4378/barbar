<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Core\DB;

final class ServiceRepository
{
    public function all(int $salonId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM services WHERE salon_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id';

        return DB::select($sql, [$salonId]);
    }

    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne('SELECT * FROM services WHERE salon_id = ? AND id = ?', [$salonId, $id]);
    }

    public function create(int $salonId, array $data): int
    {
        return (int) DB::insert('services', array_merge($data, ['salon_id' => $salonId]));
    }

    public function update(int $salonId, int $id, array $data): void
    {
        DB::update('services', $data, 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);
    }

    public function setActive(int $salonId, int $id, bool $active): void
    {
        $this->update($salonId, $id, ['is_active' => $active ? 1 : 0]);
    }

    /** @return array<int,array> ردیف‌های staff_service — استثناهای هر آرایشگر برای یک خدمت */
    public function overridesFor(int $salonId, int $serviceId): array
    {
        return DB::select(
            'SELECT ss.*, st.name AS staff_name FROM staff_service ss
             JOIN staff st ON st.id = ss.staff_id
             WHERE ss.salon_id = ? AND ss.service_id = ?',
            [$salonId, $serviceId]
        );
    }

    public function setOverride(int $salonId, int $staffId, int $serviceId, ?int $duration, ?int $price): void
    {
        if (!$this->find($salonId, $serviceId) || !DB::selectOne('SELECT id FROM staff WHERE salon_id=? AND id=?', [$salonId,$staffId])) {
            throw new \RuntimeException('خدمت یا آرایشگر متعلق به این سالن نیست.');
        }
        $duration = $duration === null ? null : max(5,min(720,$duration));
        $price = $price === null ? null : max(0,$price);
        $existing = DB::selectOne('SELECT id FROM staff_service WHERE salon_id = ? AND staff_id = ? AND service_id = ?', [$salonId,$staffId,$serviceId]);

        if ($duration === null && $price === null) {
            if ($existing) {
                DB::delete('staff_service', 'id = ?', [$existing['id']]);
            }

            return;
        }

        if ($existing) {
            DB::update('staff_service', ['duration_minutes' => $duration, 'price' => $price], 'id = :id', ['id' => $existing['id']]);

            return;
        }

        DB::insert('staff_service', [
            'salon_id' => $salonId,
            'staff_id' => $staffId,
            'service_id' => $serviceId,
            'duration_minutes' => $duration,
            'price' => $price,
        ]);
    }

    /**
     * مدت و قیمت واقعی یک خدمت وقتی آرایشگرِ مشخصی انجامش می‌دهد.
     *
     * هر آرایشگر می‌تواند برای یک خدمت زمان و قیمت خودش را داشته باشد —
     * استادکار همان اصلاح را زودتر و گران‌تر انجام می‌دهد. اگر استثنایی
     * ثبت نشده باشد، مقدار خودِ خدمت برمی‌گردد.
     */
    public function effective(int $salonId, int $staffId, int $serviceId): array
    {
        $row = DB::selectOne(
            'SELECT s.duration_minutes AS base_duration, s.price AS base_price,
                    ss.duration_minutes AS staff_duration, ss.price AS staff_price
             FROM services s
             LEFT JOIN staff_service ss ON ss.service_id = s.id AND ss.staff_id = ?
             WHERE s.salon_id = ? AND s.id = ?',
            [$staffId, $salonId, $serviceId]
        );

        if ($row === null) {
            return ['duration_minutes' => 30, 'price' => 0];
        }

        return [
            'duration_minutes' => (int) ($row['staff_duration'] ?? $row['base_duration']),
            'price' => (int) ($row['staff_price'] ?? $row['base_price']),
        ];
    }
}
