<?php

declare(strict_types=1);

namespace App\Domain\Staff;

use App\Core\DB;
use App\Domain\Catalog\ServiceRepository;
use App\Support\Now;

final class StaffRepository
{
    public function all(int $salonId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM staff WHERE salon_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        return DB::select($sql . ' ORDER BY sort_order, id', [$salonId]);
    }

    /**
     * کارکنان با نقش دسترسی حساب متصلشان — برای صفحهٔ تیم.
     *
     * @return array<int,array>
     */
    public function withAccounts(int $salonId): array
    {
        return DB::select(
            "SELECT st.*, su.role AS account_role, su.is_active AS account_active, u.phone AS account_phone,
                    (SELECT COUNT(*) FROM appointments a WHERE a.salon_id = st.salon_id AND a.staff_id = st.id
                        AND a.status IN ('pending','confirmed','queued') AND COALESCE(a.scheduled_at, a.queued_at) >= ?) AS future_count
               FROM staff st
               LEFT JOIN salon_user su ON su.salon_id = st.salon_id AND su.user_id = st.user_id
               LEFT JOIN users u ON u.id = st.user_id
              WHERE st.salon_id = ?
              ORDER BY st.is_active DESC, st.sort_order, st.id",
            // زمان از PHP، نه CURDATE(): ساعت‌ها با منطقهٔ زمانی برنامه ذخیره می‌شوند
            [\App\Support\Now::today()->format('Y-m-d H:i:s'), $salonId]
        );
    }

    public function find(int $salonId, int $id): ?array
    {
        return DB::selectOne('SELECT * FROM staff WHERE salon_id = ? AND id = ?', [$salonId, $id]);
    }

    public function create(int $salonId, array $data): int
    {
        $next = (int) (DB::selectOne('SELECT COALESCE(MAX(sort_order), 0) + 1 AS n FROM staff WHERE salon_id = ?', [$salonId])['n'] ?? 1);
        $id = (int) DB::insert('staff', array_merge(['sort_order' => $next], $data, ['salon_id' => $salonId]));
        ServiceRepository::flushCache();

        return $id;
    }

    public function update(int $salonId, int $id, array $data): void
    {
        DB::update('staff', $data, 'salon_id = :salon_id AND id = :id', ['salon_id' => $salonId, 'id' => $id]);
        ServiceRepository::flushCache();
    }

    public function setActive(int $salonId, int $id, bool $active): void
    {
        $this->update($salonId, $id, ['is_active' => $active ? 1 : 0]);
    }

    public function countActive(int $salonId): int
    {
        return (int) (DB::selectOne('SELECT COUNT(*) AS c FROM staff WHERE salon_id = ? AND is_active = 1', [$salonId])['c'] ?? 0);
    }

    /** نوبت‌های آیندهٔ یک نفر — پیش از غیرفعال کردنش. */
    public function futureBookings(int $salonId, int $staffId): int
    {
        return (int) (DB::selectOne(
            "SELECT COUNT(*) AS c FROM appointments
              WHERE salon_id = ? AND staff_id = ? AND status IN ('pending','confirmed','queued') AND COALESCE(scheduled_at, queued_at) >= ?",
            [$salonId, $staffId, Now::today()->format('Y-m-d H:i:s')]
        )['c'] ?? 0);
    }

    /** @return array<int,array> ساعت اختصاصی به تفکیک روز هفته */
    public function hours(int $salonId, int $staffId): array
    {
        $out = [];
        foreach (DB::select('SELECT * FROM working_hours WHERE salon_id = ? AND staff_id = ? ORDER BY id', [$salonId, $staffId]) as $row) {
            $out[(int) $row['weekday']] = $row;
        }

        return $out;
    }

    /**
     * ساعت اختصاصی هر روز: «مثل سالن» (بدون ردیف)، «تعطیل»، یا بازهٔ خاص.
     *
     * @param array<int,array{mode:string,opens:?string,closes:?string}> $days
     */
    public function setHours(int $salonId, int $staffId, array $days): void
    {
        DB::transaction(static function () use ($salonId, $staffId, $days) {
            DB::delete('working_hours', 'salon_id = ? AND staff_id = ?', [$salonId, $staffId]);
            foreach ($days as $weekday => $day) {
                if ($day['mode'] === 'salon') {
                    continue;
                }
                $opens = $day['opens'] ?? '09:00';
                $closes = $day['closes'] ?? '21:00';
                DB::insert('working_hours', [
                    'salon_id' => $salonId,
                    'staff_id' => $staffId,
                    'weekday' => (int) $weekday,
                    'opens_at' => $opens,
                    'closes_at' => $closes,
                    'is_closed' => $day['mode'] === 'off' || $closes <= $opens ? 1 : 0,
                ]);
            }
        });
    }
}
