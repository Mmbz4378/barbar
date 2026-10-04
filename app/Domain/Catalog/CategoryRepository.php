<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Core\DB;
use App\Support\ServiceVisual;
use RuntimeException;

final class CategoryRepository
{
    public function all(int $salonId): array
    {
        return DB::select(
            'SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id AND s.salon_id = c.salon_id) AS service_count
               FROM service_categories c WHERE c.salon_id = ? ORDER BY c.sort_order, c.id',
            [$salonId]
        );
    }

    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne('SELECT * FROM service_categories WHERE salon_id = ? AND id = ?', [$salonId, $id]);
    }

    public function create(int $salonId, string $name, string $visual): int
    {
        $name = mb_substr(trim($name), 0, 80);
        if ($name === '') {
            throw new RuntimeException('نام دسته را وارد کنید.');
        }

        $next = (int) (DB::selectOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM service_categories WHERE salon_id = ?', [$salonId])['n'] ?? 1);

        return (int) DB::insert('service_categories', [
            'salon_id' => $salonId,
            'name' => $name,
            'visual' => ServiceVisual::exists($visual) ? $visual : 'haircut',
            'sort_order' => $next,
        ]);
    }

    public function update(int $salonId, int $id, string $name, string $visual): void
    {
        $name = mb_substr(trim($name), 0, 80);
        if ($name === '') {
            throw new RuntimeException('نام دسته را وارد کنید.');
        }

        DB::update('service_categories', [
            'name' => $name,
            'visual' => ServiceVisual::exists($visual) ? $visual : 'haircut',
        ], 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);
        ServiceRepository::flushCache();
    }

    /** جابه‌جایی یک خانه به بالا یا پایین. */
    public function move(int $salonId, int $id, int $direction): void
    {
        $rows = $this->all($salonId);
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $index = array_search($id, $ids, true);
        if ($index === false) {
            return;
        }
        $target = $index + ($direction < 0 ? -1 : 1);
        if ($target < 0 || $target >= count($ids)) {
            return;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        DB::transaction(static function () use ($salonId, $ids) {
            foreach ($ids as $order => $categoryId) {
                DB::update('service_categories', ['sort_order' => $order + 1], 'salon_id = :s AND id = :id', ['s' => $salonId, 'id' => $categoryId]);
            }
        });
        ServiceRepository::flushCache();
    }

    /** حذف دسته؛ خدمت‌هایش پاک نمی‌شوند و به «سایر خدمات» می‌روند. */
    public function delete(int $salonId, int $id): void
    {
        DB::transaction(static function () use ($salonId, $id) {
            DB::update('services', ['category_id' => null], 'salon_id = :s AND category_id = :c', ['s' => $salonId, 'c' => $id]);
            DB::delete('service_categories', 'salon_id = ? AND id = ?', [$salonId, $id]);
        });
        ServiceRepository::flushCache();
    }

    /** آیا این دسته مال همین سالن است؟ برای اعتبارسنجی فرم خدمت. */
    public function belongs(int $salonId, ?int $id): bool
    {
        return $id === null || $this->find($salonId, $id) !== null;
    }
}
