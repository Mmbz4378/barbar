<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\DB;

final class WorkingHoursRepository
{
    /** @return array<int,array> به تفکیک روز هفته (۰ تا ۶)، ساعت پیش‌فرض کل سالن (staff_id خالی) */
    public function salonDefaults(int $salonId): array
    {
        $rows = DB::select(
            'SELECT * FROM working_hours WHERE salon_id = ? AND staff_id IS NULL ORDER BY weekday',
            [$salonId]
        );
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(int) $r['weekday']] = $r;
        }

        return $byDay;
    }

    /**
     * ساعت کاری یک روز هفته برای کل سالن.
     *
     * استراحت اختیاری است. اگر شروع و پایانش منطقی نباشد (خالی، یا
     * پایان پیش از شروع، یا بیرون از ساعت کاری) نادیده گرفته می‌شود —
     * بهتر از این است که یک بازهٔ معیوب، کل روز را از رزرو دربیاورد.
     */
    public function setSalonDay(
        int $salonId,
        int $weekday,
        string $opensAt,
        string $closesAt,
        bool $closed,
        ?string $breakStart = null,
        ?string $breakEnd = null,
    ): void {
        $existing = DB::selectOne(
            'SELECT id FROM working_hours WHERE salon_id = ? AND staff_id IS NULL AND weekday = ?',
            [$salonId, $weekday]
        );

        [$breakStart, $breakEnd] = self::sanitiseBreak($opensAt, $closesAt, $breakStart, $breakEnd);

        $data = [
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'is_closed' => $closed ? 1 : 0,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
        ];

        if ($existing) {
            DB::update('working_hours', $data, 'id = :id', ['id' => $existing['id']]);

            return;
        }

        DB::insert('working_hours', array_merge($data, ['salon_id' => $salonId, 'staff_id' => null, 'weekday' => $weekday]));
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private static function sanitiseBreak(
        string $opensAt,
        string $closesAt,
        ?string $start,
        ?string $end,
    ): array {
        $start = trim((string) $start);
        $end = trim((string) $end);

        if ($start === '' || $end === '') {
            return [null, null];
        }

        // مقایسهٔ رشته‌ای برای «HH:MM» درست کار می‌کند چون هم‌طول و صفرپیشوند است.
        if ($end <= $start || $start < substr($opensAt, 0, 5) || $end > substr($closesAt, 0, 5)) {
            return [null, null];
        }

        return [$start, $end];
    }
}
