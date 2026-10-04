<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\DB;

/**
 * وقتی مشتری فرد خاصی نخواسته: کم‌کارترین را بده.
 *
 * یعنی کسی که کمترین نفر جلویش ایستاده. فهرست نامزدها از بیرون می‌آید
 * (کسانی که این خدمات را انجام می‌دهند)؛ اینجا فقط بینشان انتخاب می‌شود.
 */
final class StaffAssigner
{
    /** @param int[]|null $candidates null یعنی همهٔ کارکنان فعال */
    public function pickLeastBusy(int $salonId, ?array $candidates = null): ?int
    {
        if ($candidates === []) {
            return null;
        }

        $filter = '';
        $params = [$salonId];
        if ($candidates !== null) {
            $filter = ' AND st.id IN (' . implode(',', array_fill(0, count($candidates), '?')) . ')';
            $params = array_merge($params, array_values($candidates));
        }

        $row = DB::selectOne(
            "SELECT st.id
             FROM staff st
             LEFT JOIN appointments a ON a.staff_id = st.id AND a.salon_id = st.salon_id AND a.status IN ('queued','in_chair')
             WHERE st.salon_id = ? AND st.is_active = 1 {$filter}
             GROUP BY st.id, st.sort_order
             ORDER BY COUNT(a.id) ASC, st.sort_order ASC, st.id ASC
             LIMIT 1",
            $params
        );

        return $row !== null ? (int) $row['id'] : null;
    }
}
