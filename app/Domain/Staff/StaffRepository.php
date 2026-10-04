<?php

declare(strict_types=1);

namespace App\Domain\Staff;

use App\Core\DB;

final class StaffRepository
{
    public function all(int $salonId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM staff WHERE salon_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, id';

        return DB::select($sql, [$salonId]);
    }

    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne('SELECT * FROM staff WHERE salon_id = ? AND id = ?', [$salonId, $id]);
    }

    public function create(int $salonId, array $data): int
    {
        return (int) DB::insert('staff', array_merge($data, ['salon_id' => $salonId]));
    }

    public function update(int $salonId, int $id, array $data): void
    {
        DB::update('staff', $data, 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);
    }

    public function setActive(int $salonId, int $id, bool $active): void
    {
        $this->update($salonId, $id, ['is_active' => $active ? 1 : 0]);
    }

    public function countActive(int $salonId): int
    {
        $row = DB::selectOne('SELECT COUNT(*) AS c FROM staff WHERE salon_id = ? AND is_active = 1', [$salonId]);

        return (int) ($row['c'] ?? 0);
    }
}
