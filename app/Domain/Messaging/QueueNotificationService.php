<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Config;
use App\Core\DB;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Queue\EtaEngine;
use App\Domain\Queue\QueueOrderingService;
use App\Support\Clock;
use App\Support\Jalali;
use DateTimeImmutable;

/** Builds the actual SMS bodies and decides when to fire them (doc 8.6 "پیامک‌های صف"). */
final class QueueNotificationService
{
    private AppointmentRepository $appointments;

    private QueueOrderingService $ordering;

    private EtaEngine $eta;

    private SmsNotifier $notifier;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->ordering = new QueueOrderingService();
        $this->eta = new EtaEngine();
        $this->notifier = new SmsNotifier();
    }

    public function syncStaffQueue(int $salonId, int $staffId): void
    {
        $now = new DateTimeImmutable();
        $active = $this->appointments->activeForStaff($salonId, $staffId);
        $ordered = $this->ordering->order($active, $now);
        $etas = $this->eta->computeForStaffQueue($ordered, $now);

        $imminentLow = (int) Config::get('reshen.sms.nearly_up_window_min', 20);
        $imminentHigh = (int) Config::get('reshen.sms.nearly_up_window_max', 30);
        $delayThreshold = (int) Config::get('reshen.sms.delay_threshold_minutes', 20);

        $salon = DB::selectOne('SELECT name FROM salons WHERE id = ?', [$salonId]);
        $staff = DB::selectOne('SELECT name FROM staff WHERE id = ?', [$staffId]);

        // رتبه فقط میان کسانی که هنوز *منتظرند* شمرده می‌شود.
        //
        // «نفر بعدی تویی» یعنی اول صفِ پشتِ کسی که روی صندلی است، نه
        // اول فهرست خام — که همیشه نفرِ روی صندلی را در جایگاه صفر
        // می‌گذارد. بدون این تفکیک، به کسی که دو نفر جلویش است پیامک
        // «نوبت شماست» می‌رفت.
        $waitingRank = 0;

        foreach ($ordered as $appt) {
            if ($appt['status'] === 'in_chair') {
                continue;
            }
            $e = $etas[(int) $appt['id']] ?? null;
            if ($e === null) {
                continue;
            }
            $rank = $waitingRank;
            $waitingRank++;

            $minutesUntil = max(0, (int) round(($e['start_p50']->getTimestamp() - $now->getTimestamp()) / 60));

            // "عقب افتادیم" — ETA slipped more than the threshold since the last one we told them.
            if ($appt['estimated_start_at'] !== null) {
                $previousMinutes = (int) round((strtotime($appt['estimated_start_at']) - $now->getTimestamp()) / 60);
                if ($minutesUntil - $previousMinutes >= $delayThreshold) {
                    $this->notifier->notify($salonId, $appt, 'queue_delayed', [
                        'name' => $this->firstName($appt),
                        'time' => Clock::hm($e['start_p50']->format('H:i')),
                    ]);
                }
            }

            $this->appointments->update($salonId, (int) $appt['id'], [
                'estimated_start_at' => $e['start_p50']->format('Y-m-d H:i:s'),
                'estimated_start_max_at' => $e['start_p80']->format('Y-m-d H:i:s'),
                'position_snapshot' => $rank,
            ]);

            // "صندلی آماده‌ست" — became next in line (right after whoever's in the chair).
            if ($rank === 0 && !$this->notifier->alreadySent((int) $appt['id'], 'queue_chair_ready')) {
                $this->notifier->notify($salonId, $appt, 'queue_chair_ready', [
                    'name' => $this->firstName($appt),
                    'staff' => $staff['name'],
                ]);

                continue;
            }

            // "نوبتت نزدیکه" — ETA fell inside the imminent window.
            if ($minutesUntil >= $imminentLow && $minutesUntil <= $imminentHigh
                && !$this->notifier->alreadySent((int) $appt['id'], 'queue_nearly_up')) {
                /*
                 * لینک صف از متن پیامک حذف شد: الگوی ثبت‌شده نمی‌تواند
                 * لینک متغیر داشته باشد (اپراتور لینک را در متنِ تأییدشده
                 * می‌خواهد، نه به‌عنوان متغیر). مشتری لینک را از پیامک
                 * تأیید رزرو دارد.
                 */
                $this->notifier->notify($salonId, $appt, 'queue_nearly_up', [
                    'name' => $this->firstName($appt),
                    'ahead' => Jalali::toPersianDigits((string) $rank),
                    'minutes' => Jalali::toPersianDigits((string) $minutesUntil),
                ]);
            }
        }
    }

    private function firstName(array $appointment): string
    {
        $name = DB::selectOne('SELECT name FROM customers WHERE id = ?', [$appointment['customer_id']])['name'] ?? null;
        if ($name === null || trim($name) === '') {
            return 'مشتری';
        }

        return trim(explode(' ', $name)[0]);
    }

    public function sendUpcomingReminders(): int
    {
        $hoursList = (array) Config::get('reshen.sms.reminder_hours_before', [24, 2]);
        $sentCount = 0;

        foreach ($hoursList as $hours) {
            $templateCode = "reminder_{$hours}h";
            $target = (new DateTimeImmutable())->modify("+{$hours} hours");
            $windowStart = $target->modify('-2 minutes')->format('Y-m-d H:i:s');
            $windowEnd = $target->modify('+2 minutes')->format('Y-m-d H:i:s');

            $rows = DB::select(
                "SELECT a.*, s.name AS salon_name FROM appointments a JOIN salons s ON s.id = a.salon_id
                 WHERE a.kind = 'booked' AND a.status = 'confirmed'
                 AND a.scheduled_at BETWEEN ? AND ?",
                [$windowStart, $windowEnd]
            );

            foreach ($rows as $appt) {
                if ($this->notifier->alreadySent((int) $appt['id'], $templateCode)) {
                    continue;
                }
                $when = Clock::hm((new DateTimeImmutable($appt['scheduled_at']))->format('H:i'));
                if ($this->notifier->notify((int) $appt['salon_id'], $appt, $templateCode, [
                    'name' => $this->firstName($appt),
                    'salon' => $appt['salon_name'],
                    'time' => $when,
                ])) {
                    $sentCount++;
                }
            }
        }

        return $sentCount;
    }
}
