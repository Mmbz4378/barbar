<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use App\Core\DB;
use App\Domain\Identity\UserRepository;
use App\Support\IranMobile;

final class CustomerRepository
{
    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne('SELECT * FROM customers WHERE salon_id = ? AND id = ?', [$salonId, $id]);
    }

    public function findByPhone(int $salonId, string $e164): ?array
    {
        return DB::selectOne('SELECT * FROM customers WHERE salon_id = ? AND phone = ?', [$salonId, $e164]);
    }

    /**
     * پروندهٔ مشتری در همین سالن را پیدا می‌کند، و اگر نبود می‌سازد.
     *
     * اگر شماره داده شده باشد، به هویت سراسری کاربر هم وصلش می‌کند —
     * همان چیزی که باعث می‌شود مشتری بتواند نوبت‌هایش در چند سالن را
     * یک‌جا ببیند.
     */
    public function findOrCreate(int $salonId, ?string $name, ?string $phoneRaw): array
    {
        if ($phoneRaw !== null && $phoneRaw !== '') {
            $phone = IranMobile::parse($phoneRaw);
            $existing = $this->findByPhone($salonId, $phone->e164);
            if ($existing !== null) {
                if ($name !== null && $name !== '' && $existing['name'] !== $name) {
                    DB::update('customers', ['name' => $name], 'id = :id', ['id' => $existing['id']]);
                    $existing['name'] = $name;
                }

                return $existing;
            }

            $user = (new UserRepository())->findOrCreate($phone, $name);
            $id = DB::insert('customers', [
                'salon_id' => $salonId,
                'user_id' => $user['id'],
                'name' => $name,
                'phone' => $phone->e164,
            ]);

            return $this->find($salonId, (int) $id);
        }

        $id = DB::insert('customers', ['salon_id' => $salonId, 'name' => $name]);

        return $this->find($salonId, (int) $id);
    }

    public function search(int $salonId, string $term, int $limit = 15): array
    {
        $like = '%' . $term . '%';

        return DB::select(
            'SELECT * FROM customers WHERE salon_id = ? AND deleted_at IS NULL
             AND (name LIKE ? OR phone LIKE ?) ORDER BY last_visit_at IS NULL, last_visit_at DESC LIMIT ?',
            [$salonId, $like, $like, $limit]
        );
    }

    public function recent(int $salonId, int $limit = 20): array
    {
        return DB::select(
            'SELECT * FROM customers WHERE salon_id = ? AND deleted_at IS NULL ORDER BY last_visit_at IS NULL, last_visit_at DESC LIMIT ?',
            [$salonId, $limit]
        );
    }

    public function history(int $salonId, int $customerId): array
    {
        return DB::select(
            "SELECT a.*, GROUP_CONCAT(sv.name SEPARATOR '، ') AS service_names, st.name AS staff_name
             FROM appointments a
             LEFT JOIN appointment_items ai ON ai.appointment_id = a.id
             LEFT JOIN services sv ON sv.id = ai.service_id
             LEFT JOIN staff st ON st.id = a.staff_id
             WHERE a.salon_id = ? AND a.customer_id = ? AND a.status IN ('completed','no_show','cancelled')
             GROUP BY a.id ORDER BY a.created_at DESC LIMIT 30",
            [$salonId, $customerId]
        );
    }
}
