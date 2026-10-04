<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Core\DB;

/** همان چیزهایی که دفترچهٔ آرایشگر را به‌درد‌بخور می‌کنند. */
final class CustomerPreferencesRepository
{
    public function find(int $salonId, int $customerId): ?array
    {
        return DB::selectOne('SELECT * FROM customer_preferences WHERE salon_id = ? AND customer_id = ?', [$salonId, $customerId]);
    }

    public function upsert(int $salonId, int $customerId, array $data): void
    {
        $existing = $this->find($salonId, $customerId);
        if ($existing) {
            DB::update('customer_preferences', $data, 'id = :id', ['id' => $existing['id']]);

            return;
        }

        DB::insert('customer_preferences', array_merge($data, ['salon_id' => $salonId, 'customer_id' => $customerId]));
    }
}
