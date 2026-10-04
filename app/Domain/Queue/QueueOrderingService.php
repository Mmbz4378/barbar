<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use DateTimeImmutable;

/**
 * قاعدهٔ ترتیب صف — یک صف، نه دو تا.
 *
 *   ۱. هر کسی که همین حالا روی صندلی است (حداکثر یک نفر)
 *   ۲. نوبت رزروشده، اگر در بازهٔ اولویتش باشد (۱۰ دقیقه قبل تا ۱۰
 *      دقیقه بعد از ساعتش)، بر هر مراجعهٔ بدون نوبت مقدم است
 *   ۳. بقیه، به ترتیب رسیدن
 *
 * همین قاعده است که «یک صف» را منصف و قابل توضیح می‌کند: کسی که ساعت
 * ۶ رزرو کرده و ۴۰ دقیقه دیر آمده، جلوی کسی که نیم ساعت آنجا ایستاده
 * نمی‌پرد. و آرایشگر می‌تواند همین یک جمله را به مشتری معترض بگوید.
 */
final class QueueOrderingService
{
    /**
     * @param array<int,array> $appointments ردیف‌های خام (وضعیت: confirmed/queued/in_chair)
     * @return array<int,array> همان ردیف‌ها، مرتب‌شده
     */
    public function order(array $appointments, ?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable();
        $windowMinutes = (int) Config::get('reshen.queue.priority_window_minutes', 10);

        $inChair = [];
        $priorityBooked = [];
        $rest = [];

        foreach ($appointments as $appt) {
            if ($appt['status'] === 'in_chair') {
                $inChair[] = $appt;

                continue;
            }

            if ($appt['kind'] === 'booked' && $appt['scheduled_at'] !== null && $this->withinPriorityWindow($appt['scheduled_at'], $now, $windowMinutes)) {
                $priorityBooked[] = $appt;

                continue;
            }

            $rest[] = $appt;
        }

        usort($priorityBooked, static fn ($a, $b) => strtotime($a['scheduled_at']) <=> strtotime($b['scheduled_at']));
        usort($rest, static function ($a, $b) {
            $aKey = strtotime($a['queued_at'] ?? $a['scheduled_at'] ?? $a['created_at']);
            $bKey = strtotime($b['queued_at'] ?? $b['scheduled_at'] ?? $b['created_at']);

            return $aKey <=> $bKey;
        });

        return array_merge($inChair, $priorityBooked, $rest);
    }

    private function withinPriorityWindow(string $scheduledAt, DateTimeImmutable $now, int $windowMinutes): bool
    {
        $scheduled = strtotime($scheduledAt);
        $nowTs = $now->getTimestamp();

        return $nowTs >= ($scheduled - $windowMinutes * 60) && $nowTs <= ($scheduled + $windowMinutes * 60);
    }
}
