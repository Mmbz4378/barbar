<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\DB;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Messaging\SmsNotifier;
use App\Domain\Payment\PaymentRepository;
use App\Support\Clock;
use App\Support\JalaliCalendar;
use App\Support\Now;
use App\Support\Str;
use DateTimeImmutable;
use RuntimeException;

/**
 * ثبت و لغو نوبت — مسیر مشترکِ رزرو آنلاین و رزرو از پنل.
 */
final class BookingService
{
    private BookingPlanner $planner;

    public function __construct(?BookingPlanner $planner = null)
    {
        $this->planner = $planner ?? new BookingPlanner();
    }

    public function planner(): BookingPlanner
    {
        return $this->planner;
    }

    /**
     * ثبت نوبت.
     *
     * $options:
     *   online      true برای رزرو مشتری (حداقل فاصله، افق، بیعانه)؛ false برای پنل
     *   note        توضیح مشتری
     *   created_by  شناسهٔ کاربری که از پنل ثبت کرد
     *
     * @param int[] $serviceIds
     * @return array ردیف اولین نوبت (کارت نوبت با public_token آن باز می‌شود)
     */
    public function createBooking(
        int $salonId,
        ?int $preferredStaffId,
        array $serviceIds,
        DateTimeImmutable $date,
        string $time,
        string $phoneRaw,
        ?string $name,
        ?string $ip = null,
        array $options = [],
    ): array {
        $online = (bool) ($options['online'] ?? true);
        $note = isset($options['note']) ? mb_substr(trim((string) $options['note']), 0, 300) : '';
        $createdBy = isset($options['created_by']) ? (int) $options['created_by'] : null;

        $serviceIds = BookingPlanner::normalizeIds($serviceIds);
        if ($serviceIds === []) {
            throw new RuntimeException('حداقل یک خدمت را انتخاب کنید.');
        }
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            throw new RuntimeException('ساعت رزرو معتبر نیست.');
        }
        $start = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time . ':00');

        $result = DB::transaction(function () use ($salonId, $preferredStaffId, $serviceIds, $start, $phoneRaw, $name, $ip, $online, $note, $createdBy) {
            /*
             * قفل روی ردیف سالن: رزرو آنلاین، رزرو پنل و اکشن‌های صف همه
             * از همین قفل رد می‌شوند، پس دو درخواست هم‌زمان نمی‌توانند یک
             * سانس را دو بار بگیرند.
             */
            $salon = DB::selectOne(
                'SELECT id, name, deposit_card_number, deposit_hold_minutes FROM salons WHERE id = ? AND is_active = 1 FOR UPDATE',
                [$salonId]
            );
            if (!$salon) {
                throw new RuntimeException('این سالن در حال حاضر نوبت نمی‌دهد.');
            }

            // داده‌ای که پیش از قفل خوانده شده ممکن است کهنه باشد
            SlotFinder::flushCache();
            ServiceRepository::flushCache();

            if (($error = $this->planner->validate($salonId, $serviceIds, $online)) !== null) {
                throw new RuntimeException($error);
            }

            $customer = (new CustomerRepository())->findOrCreate($salonId, $name, $phoneRaw);

            // دو بار زدنِ «ثبت» یا برگشت و ارسال دوباره: همان نوبت قبلی
            $duplicate = DB::selectOne(
                "SELECT * FROM appointments WHERE salon_id = ? AND customer_id = ? AND scheduled_at = ?
                    AND status IN ('pending','confirmed','queued','in_chair') ORDER BY id LIMIT 1",
                [$salonId, $customer['id'], $start->format('Y-m-d H:i:s')]
            );
            if ($duplicate) {
                return ['appointment' => $duplicate, 'customer' => $customer, 'confirmed' => false];
            }

            $plan = $this->planner->plan($salonId, $serviceIds, $start, $preferredStaffId, $online);
            if ($plan === null) {
                throw new RuntimeException($preferredStaffId !== null
                    ? 'این ساعت برای فرد انتخاب‌شده دیگر آزاد نیست. ساعت یا فرد دیگری انتخاب کنید.'
                    : 'این ساعت دیگر آزاد نیست. لطفاً ساعت دیگری انتخاب کنید.');
            }

            $needsDeposit = $online && $plan['deposit'] > 0 && trim((string) $salon['deposit_card_number']) !== '';
            $status = $needsDeposit ? 'pending' : 'confirmed';
            $holdUntil = $needsDeposit
                ? Now::get()->modify('+' . max(15, (int) $salon['deposit_hold_minutes']) . ' minutes')->format('Y-m-d H:i:s')
                : null;
            $groupToken = $plan['multi'] ? Str::token(12) : null;

            $appointments = new AppointmentRepository();
            $firstId = null;
            foreach ($plan['segments'] as $index => $segment) {
                $id = $appointments->create($salonId, [
                    'customer_id' => $customer['id'],
                    'staff_id' => $segment['staff_id'],
                    'kind' => 'booked',
                    'status' => $status,
                    'scheduled_at' => $segment['start']->format('Y-m-d H:i:s'),
                    'group_token' => $groupToken,
                    'customer_note' => $note !== '' ? $note : null,
                    'created_by_user_id' => $createdBy,
                    // بیعانه یک بار، روی بخش اول
                    'deposit_amount' => $index === 0 && $needsDeposit ? $plan['deposit'] : 0,
                    'hold_expires_at' => $holdUntil,
                    'created_ip' => $ip,
                ]);
                $firstId ??= $id;

                $lastItem = count($segment['items']) - 1;
                foreach ($segment['items'] as $i => $item) {
                    // آماده‌سازی فقط پس از آخرین خدمتِ هر نفر
                    $appointments->addItem($salonId, $id, $item['service_id'], $item['price'], $item['minutes'], $i === $lastItem ? $segment['buffer'] : 0);
                }
            }

            return [
                'appointment' => $appointments->find($salonId, (int) $firstId),
                'customer' => $customer,
                'confirmed' => $status === 'confirmed',
            ];
        });

        if ($result['confirmed']) {
            $this->sendConfirmation($salonId, $result['appointment'], $result['customer']);
        }

        return $result['appointment'];
    }

    /**
     * آیا مشتری خودش می‌تواند این نوبت را لغو کند؟
     *
     * @return array{ok:bool,reason:?string}
     */
    public function customerCancellation(array $appointment): array
    {
        if (!in_array($appointment['status'], ['pending', 'confirmed', 'queued'], true)) {
            return ['ok' => false, 'reason' => null];
        }

        $notice = (new SlotFinder())->settings((int) $appointment['salon_id'])['cancel_notice'];
        if ($notice > 0 && !empty($appointment['scheduled_at'])) {
            $deadline = (new DateTimeImmutable((string) $appointment['scheduled_at']))->modify('-' . $notice . ' minutes');
            if (Now::get() > $deadline) {
                return ['ok' => false, 'reason' => 'مهلت لغو آنلاین این نوبت گذشته است. برای تغییر با سالن تماس بگیرید.'];
            }
        }

        return ['ok' => true, 'reason' => null];
    }

    /**
     * لغو با لینک نوبت (یا از «نوبت‌های من»). همهٔ بخش‌های یک رزرو
     * چندنفره با هم لغو می‌شوند.
     *
     * @return string cancelled | not_found | not_cancellable | too_late
     */
    public function cancelByToken(string $token, string $reason = ''): string
    {
        $appointments = new AppointmentRepository();
        $appt = $appointments->findByToken($token);
        if ($appt === null) {
            return 'not_found';
        }

        $check = $this->customerCancellation($appt);
        if (!$check['ok']) {
            return $check['reason'] !== null ? 'too_late' : 'not_cancellable';
        }

        $changed = 0;
        foreach ($appointments->group($appt) as $part) {
            $changed += DB::update('appointments', [
                'status' => 'cancelled',
                'cancel_reason' => $reason ?: 'لغو توسط مشتری',
                'cancelled_by' => 'customer',
                'hold_expires_at' => null,
            ], "id = :id AND salon_id = :sid AND status IN ('pending','confirmed','queued')", ['id' => $part['id'], 'sid' => $part['salon_id']]);
        }
        if ($changed > 0) {
            AvailabilityCache::bump((int) $appt['salon_id']);
        }

        // پیامک لغو فقط وقتی سالن لغو می‌کند می‌رود (QueueService)؛ مشتری
        // که خودش لغو کرده، همان لحظه نتیجه را روی صفحه می‌بیند.
        return $changed > 0 ? 'cancelled' : 'not_cancellable';
    }

    /**
     * پیامک «نوبت شما لغو شد» وقتی سالن نوبتِ آینده‌ای را لغو می‌کند.
     */
    public function notifySalonCancellation(int $salonId, array $appointment): void
    {
        if (empty($appointment['scheduled_at']) || new DateTimeImmutable((string) $appointment['scheduled_at']) < Now::get()) {
            return;
        }

        try {
            (new SmsNotifier())->notify($salonId, $appointment, 'booking_cancelled', $this->smsVars($salonId, $appointment));
        } catch (\Throwable $e) {
            error_log('Cancellation SMS failed for appointment ' . $appointment['id']);
        }
    }

    /**
     * تأیید دریافت بیعانه از پنل: همهٔ بخش‌های رزرو قطعی می‌شوند.
     */
    public function confirmDeposit(int $salonId, int $appointmentId, string $method, ?int $userId): void
    {
        $confirmed = DB::transaction(function () use ($salonId, $appointmentId, $method, $userId) {
            $appointments = new AppointmentRepository();
            $appt = DB::selectOne('SELECT * FROM appointments WHERE salon_id = ? AND id = ? FOR UPDATE', [$salonId, $appointmentId]);
            if ($appt === null) {
                throw new RuntimeException('نوبت یافت نشد.');
            }
            if ($appt['status'] !== 'pending') {
                return null;
            }

            $group = $appointments->group($appt);
            $deposit = array_sum(array_map(static fn ($a) => (int) $a['deposit_amount'], $group));
            if ($deposit > 0) {
                (new PaymentRepository())->recordDeposit($salonId, (int) $group[0]['id'], $method, $deposit, $userId);
            }

            foreach ($group as $part) {
                $appointments->update($salonId, (int) $part['id'], ['status' => 'confirmed', 'hold_expires_at' => null]);
            }

            return $appointments->find($salonId, (int) $group[0]['id']);
        });

        if ($confirmed !== null) {
            $customer = DB::selectOne('SELECT * FROM customers WHERE id = ? AND salon_id = ?', [$confirmed['customer_id'], $salonId]);
            if ($customer !== null) {
                $this->sendConfirmation($salonId, $confirmed, $customer);
            }
        }
    }

    /**
     * رزروهای منتظر بیعانه که مهلتشان گذشته — کار کرون.
     */
    public function expireHolds(): int
    {
        $expired = DB::statement(
            "UPDATE appointments
                SET status = 'cancelled', cancelled_by = 'system',
                    cancel_reason = 'بیعانه در مهلت تعیین‌شده تأیید نشد', hold_expires_at = NULL
              WHERE status = 'pending' AND hold_expires_at IS NOT NULL AND hold_expires_at < ?",
            [Now::get()->format('Y-m-d H:i:s')]
        )->rowCount();
        if ($expired > 0) {
            AvailabilityCache::bumpAll();
        }

        return $expired;
    }

    /** طول سانس سالن — مبنای ساعت‌های مسیرِ اول-زمان. */
    public function sessionMinutes(int $salonId): int
    {
        return (new SlotFinder())->stepMinutes($salonId);
    }

    /** کشِ درون‌درخواستی را خالی می‌کند — برای آزمون. */
    public static function flushCache(): void
    {
        SlotFinder::flushCache();
        ServiceRepository::flushCache();
    }

    /** پیامک تأیید رزرو؛ شکستش نباید ثبت نوبت را برگرداند. */
    private function sendConfirmation(int $salonId, array $appointment, array $customer): void
    {
        if (empty($customer['phone'])) {
            return;
        }

        try {
            (new SmsNotifier())->notify($salonId, $appointment, 'booking_confirmed', $this->smsVars($salonId, $appointment, $customer));
        } catch (\Throwable $e) {
            error_log('Booking confirmation could not be sent for appointment ' . $appointment['id']);
        }
    }

    /** @return array<string,string> */
    private function smsVars(int $salonId, array $appointment, ?array $customer = null): array
    {
        $customer ??= DB::selectOne('SELECT name FROM customers WHERE id = ?', [$appointment['customer_id']]);
        $salon = DB::selectOne('SELECT name FROM salons WHERE id = ?', [$salonId]);
        $at = new DateTimeImmutable((string) ($appointment['scheduled_at'] ?? 'now'));

        return [
            'name' => trim(explode(' ', (string) ($customer['name'] ?? 'مشتری'))[0]) ?: 'مشتری',
            'salon' => (string) ($salon['name'] ?? ''),
            'date' => JalaliCalendar::humanDate($at),
            'time' => Clock::hm($at->format('H:i')),
        ];
    }
}
