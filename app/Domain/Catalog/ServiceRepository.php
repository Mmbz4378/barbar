<?php

declare(strict_types=1);

namespace App\Domain\Catalog;

use App\Core\Cache;
use App\Core\DB;
use App\Domain\Salon\SalonRepository;
use App\Domain\Salon\SalonStats;
use RuntimeException;

final class ServiceRepository
{
    /** @var array<int,array> */
    private static array $matrixCache = [];

    public function all(int $salonId, bool $activeOnly = false): array
    {
        $sql = 'SELECT sv.*, c.name AS category_name, c.visual AS category_visual, c.sort_order AS category_sort
                  FROM services sv
                  LEFT JOIN service_categories c ON c.id = sv.category_id AND c.salon_id = sv.salon_id
                 WHERE sv.salon_id = ?';
        if ($activeOnly) {
            $sql .= ' AND sv.is_active = 1';
        }
        $sql .= ' ORDER BY c.sort_order IS NULL, c.sort_order, c.id, sv.sort_order, sv.id';

        // منوی سالن پرخواندنی‌ترین دادهٔ صفحه‌های عمومی است؛ با هر تغییر خدمت،
        // دسته، کارکنان یا قیمت، نسخهٔ سالن بالا می‌رود و این تازه می‌شود.
        $ver = Cache::version("salon:$salonId");

        return Cache::remember("salon:$salonId:v$ver:svc:" . ($activeOnly ? 1 : 0), 300, static fn () => DB::select($sql, [$salonId]));
    }

    /**
     * خدمات به تفکیک دسته — برای منو، انتخاب خدمت و صفحهٔ مدیریت.
     *
     * خدمت بدون دسته زیر «سایر خدمات» می‌آید، نه اینکه گم شود.
     *
     * @return array<int,array{id:?int,name:string,visual:?string,services:array<int,array>}>
     */
    public function grouped(int $salonId, bool $activeOnly = false): array
    {
        $groups = [];
        foreach ($this->all($salonId, $activeOnly) as $service) {
            $key = $service['category_id'] !== null && $service['category_name'] !== null ? (int) $service['category_id'] : 0;
            $groups[$key] ??= [
                'id' => $key ?: null,
                'name' => $key ? (string) $service['category_name'] : 'سایر خدمات',
                'visual' => $key ? (string) $service['category_visual'] : null,
                'services' => [],
            ];
            $groups[$key]['services'][] = $service;
        }

        // «سایر» همیشه آخر
        if (isset($groups[0])) {
            $other = $groups[0];
            unset($groups[0]);
            $groups[0] = $other;
        }

        return array_values($groups);
    }

    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne(
            'SELECT sv.*, c.name AS category_name, c.visual AS category_visual
               FROM services sv
               LEFT JOIN service_categories c ON c.id = sv.category_id AND c.salon_id = sv.salon_id
              WHERE sv.salon_id = ? AND sv.id = ?',
            [$salonId, $id]
        );
    }

    public function create(int $salonId, array $data): int
    {
        $id = (int) DB::insert('services', array_merge($data, ['salon_id' => $salonId]));
        $this->changed($salonId);

        return $id;
    }

    public function update(int $salonId, int $id, array $data): void
    {
        DB::update('services', $data, 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);
        $this->changed($salonId);
    }

    public function setActive(int $salonId, int $id, bool $active): void
    {
        $this->update($salonId, $id, ['is_active' => $active ? 1 : 0]);
    }

    /** @return array<int,array> ردیف‌های staff_service — استثناهای هر نفر برای یک خدمت */
    public function overridesFor(int $salonId, int $serviceId): array
    {
        return DB::select(
            'SELECT ss.*, st.name AS staff_name FROM staff_service ss
             JOIN staff st ON st.id = ss.staff_id
             WHERE ss.salon_id = ? AND ss.service_id = ?',
            [$salonId, $serviceId]
        );
    }

    /**
     * مدت، قیمت و «انجام می‌دهد یا نه» برای یک نفر و یک خدمت.
     *
     * ردیف فقط وقتی نگه داشته می‌شود که چیزی با پیش‌فرض فرق کند؛ وگرنه
     * پاک می‌شود تا جدول به‌اندازهٔ «همه × همه» باد نکند.
     */
    public function setOverride(int $salonId, int $staffId, int $serviceId, ?int $duration, ?int $price, bool $offered = true): void
    {
        if (!$this->find($salonId, $serviceId) || !DB::selectOne('SELECT id FROM staff WHERE salon_id=? AND id=?', [$salonId, $staffId])) {
            throw new RuntimeException('خدمت یا فرد انتخاب‌شده متعلق به این سالن نیست.');
        }
        $duration = $duration === null ? null : max(5, min(720, $duration));
        $price = $price === null ? null : max(0, $price);
        $existing = DB::selectOne(
            'SELECT id FROM staff_service WHERE salon_id = ? AND staff_id = ? AND service_id = ?',
            [$salonId, $staffId, $serviceId]
        );

        if ($duration === null && $price === null && $offered) {
            if ($existing) {
                DB::delete('staff_service', 'id = ?', [$existing['id']]);
            }
            self::flushCache();
            SalonRepository::forget($salonId);

            return;
        }

        $data = ['duration_minutes' => $duration, 'price' => $price, 'is_offered' => $offered ? 1 : 0];
        if ($existing) {
            DB::update('staff_service', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            DB::insert('staff_service', array_merge($data, [
                'salon_id' => $salonId,
                'staff_id' => $staffId,
                'service_id' => $serviceId,
            ]));
        }
        self::flushCache();
        SalonRepository::forget($salonId);
    }

    /**
     * فقط «انجام می‌دهد یا نه» را عوض می‌کند و قیمت/مدت اختصاصی را نگه
     * می‌دارد — برای فرم مهارت‌های کارمند.
     */
    public function setOffered(int $salonId, int $staffId, int $serviceId, bool $offered): void
    {
        $existing = DB::selectOne(
            'SELECT duration_minutes, price FROM staff_service WHERE salon_id = ? AND staff_id = ? AND service_id = ?',
            [$salonId, $staffId, $serviceId]
        );
        $this->setOverride(
            $salonId,
            $staffId,
            $serviceId,
            $existing && $existing['duration_minutes'] !== null ? (int) $existing['duration_minutes'] : null,
            $existing && $existing['price'] !== null ? (int) $existing['price'] : null,
            $offered,
        );
    }

    /**
     * مدت و قیمت واقعی یک خدمت وقتی فرد مشخصی انجامش می‌دهد.
     *
     * @return array{duration_minutes:int,price:int,buffer_minutes:int,offered:bool}
     */
    public function effective(int $salonId, int $staffId, int $serviceId): array
    {
        $matrix = $this->matrix($salonId);
        $service = $matrix['services'][$serviceId] ?? null;
        if ($service === null) {
            return ['duration_minutes' => 30, 'price' => 0, 'buffer_minutes' => 0, 'offered' => false];
        }

        $override = $matrix['overrides'][$staffId][$serviceId] ?? null;

        return [
            'duration_minutes' => (int) ($override['duration_minutes'] ?? $service['duration_minutes']),
            'price' => (int) ($override['price'] ?? $service['price']),
            'buffer_minutes' => (int) $service['buffer_minutes'],
            'offered' => $override === null || (int) $override['is_offered'] === 1,
        ];
    }

    /**
     * همهٔ خدمت‌ها، کارکنان و استثناهای یک سالن — سه کوئری، یک بار.
     *
     * برنامه‌ریز رزرو برای هر ساعتِ هر روز، «چه کسی چه چیزی را چقدر
     * طول می‌کشد» را می‌پرسد؛ این داده در طول درخواست ثابت است.
     *
     * @return array{services:array<int,array>,staff:array<int,array>,overrides:array<int,array<int,array>>}
     */
    public function matrix(int $salonId): array
    {
        if (isset(self::$matrixCache[$salonId])) {
            return self::$matrixCache[$salonId];
        }

        $ver = Cache::version("salon:$salonId");

        return self::$matrixCache[$salonId] = Cache::remember("salon:$salonId:v$ver:matrix", 300, function () use ($salonId): array {
            $services = [];
            foreach ($this->all($salonId) as $row) {
                $services[(int) $row['id']] = $row;
            }

            $staff = [];
            foreach (DB::select('SELECT * FROM staff WHERE salon_id = ? ORDER BY sort_order, id', [$salonId]) as $row) {
                $staff[(int) $row['id']] = $row;
            }

            $overrides = [];
            foreach (DB::select('SELECT staff_id, service_id, duration_minutes, price, is_offered FROM staff_service WHERE salon_id = ?', [$salonId]) as $row) {
                $overrides[(int) $row['staff_id']][(int) $row['service_id']] = $row;
            }

            return ['services' => $services, 'staff' => $staff, 'overrides' => $overrides];
        });
    }

    /**
     * کسانی که این خدمت را انجام می‌دهند.
     *
     * @return int[]
     */
    public function staffFor(int $salonId, int $serviceId, bool $onlineOnly = false): array
    {
        $matrix = $this->matrix($salonId);
        $memoKey = $serviceId . ($onlineOnly ? ':o' : ':a');
        if (isset(self::$matrixCache[$salonId]['staff_for'][$memoKey])) {
            return self::$matrixCache[$salonId]['staff_for'][$memoKey];
        }

        $ids = [];
        foreach ($matrix['staff'] as $id => $staff) {
            if ((int) $staff['is_active'] !== 1 || ($onlineOnly && (int) $staff['accepts_online'] !== 1)) {
                continue;
            }
            $override = $matrix['overrides'][$id][$serviceId] ?? null;
            if ($override === null || (int) $override['is_offered'] === 1) {
                $ids[] = $id;
            }
        }

        return self::$matrixCache[$salonId]['staff_for'][$memoKey] = $ids;
    }

    public static function flushCache(): void
    {
        self::$matrixCache = [];
        Cache::flushLocal();
    }

    private function changed(int $salonId): void
    {
        self::flushCache();
        (new SalonStats())->refreshPrices($salonId);
    }
}
