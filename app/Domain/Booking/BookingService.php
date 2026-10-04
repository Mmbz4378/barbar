<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\DB;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Messaging\SmsManager;
use App\Domain\Messaging\SmsNotifier;
use App\Support\Clock;
use App\Support\JalaliCalendar;
use App\Domain\Appointment\AppointmentRepository;
use DateTimeImmutable;
use RuntimeException;

/**
 * مسیر رزرو عمومی — بدون نصب، بدون رمز.
 *
 * مشتری QR را اسکن می‌کند و تا ثبت نوبت هیچ حسابی نمی‌سازد و هیچ
 * رمزی نمی‌گذارد. هر گامی که اضافه شود، درصدی از مشتری‌ها همان‌جا
 * می‌ایستند — و آرایشگاه دوباره برمی‌گردد سر تلفن.
 */
final class BookingService
{
    private SlotFinder $slots;

    public function __construct()
    {
        $this->slots = new SlotFinder();
    }

    /** @return array<string,int[]> «ساعت:دقیقه» ← آرایشگرهای آزاد در آن لحظه (برای گزینهٔ «هر آرایشگری») */
    public function freeSlots(int $salonId, ?int $staffId, DateTimeImmutable $date, int $durationMinutes): array
    {
        // تقویم برای هر روزِ ماه یک بار اینجا می‌آید؛ فهرست آرایشگرها
        // در طول یک درخواست عوض نمی‌شود.
        $staffIds = $staffId !== null ? [$staffId] : $this->activeStaffIds($salonId);

        $byTime = [];
        foreach ($staffIds as $sid) {
            foreach ($this->slots->freeSlotsForStaff($salonId, (int) $sid, $date, $durationMinutes) as $time) {
                $byTime[$time][] = (int) $sid;
            }
        }
        ksort($byTime);

        return $byTime;
    }

    /**
     * طول یک سانس سالن، مستقل از خدمتی که مشتری انتخاب می‌کند.
     *
     * مسیر رزرو اول روز و سانس را می‌گیرد و بعد خدمت را می‌پرسد، پس
     * موقع ساختن سانس‌ها هنوز مدت خدمت معلوم نیست. مبنا همان طول
     * سانسِ خود سالن است؛ جا شدن خدمت در سانس را پنل آرایشگاه
     * بررسی می‌کند.
     */
    public function sessionMinutes(int $salonId): int
    {
        return $this->slots->stepMinutes($salonId);
    }

    /** @var array<int,int[]> */
    private static array $staffCache = [];

    /** @return int[] */
    private function activeStaffIds(int $salonId): array
    {
        if (!isset(self::$staffCache[$salonId])) {
            self::$staffCache[$salonId] = array_map('intval', array_column(
                DB::select('SELECT id FROM staff WHERE salon_id = ? AND is_active = 1 ORDER BY sort_order, id', [$salonId]),
                'id'
            ));
        }

        return self::$staffCache[$salonId];
    }

    /** کشِ درون‌درخواستی را خالی می‌کند — برای تست. */
    public static function flushCache(): void
    {
        self::$staffCache = [];
        SlotFinder::flushCache();
    }

    /** @param int[] $serviceIds */
    public function createBooking(
        int $salonId,
        ?int $preferredStaffId,
        array $serviceIds,
        DateTimeImmutable $date,
        string $time,
        string $phoneRaw,
        ?string $name,
        ?string $ip = null,
    ): array {
        if ($serviceIds === []) {
            throw new RuntimeException('حداقل یک خدمت را انتخاب کنید.');
        }

        $today = new DateTimeImmutable('today');
        if ($date < $today || $date > $today->modify('+'.(int)\App\Core\Config::get('reshen.booking.max_days_ahead',30).' days') || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/',$time)) throw new RuntimeException('روز یا ساعت رزرو معتبر نیست.');
        $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));
        $appointment = DB::transaction(function () use ($salonId, $preferredStaffId, $serviceIds, $date, $time, $phoneRaw, $name, $ip) {
        $salon = DB::selectOne('SELECT id FROM salons WHERE id = ? AND is_active = 1 FOR UPDATE', [$salonId]);
        if (!$salon) throw new RuntimeException('این سالن در حال حاضر فعال نیست.');
        self::flushCache();
        $serviceRepo = new ServiceRepository();
        foreach ($serviceIds as $serviceId) {
            $service = $serviceRepo->find($salonId, $serviceId);
            if (!$service || !(bool)$service['is_active']) throw new RuntimeException('یکی از خدمات دیگر قابل رزرو نیست.');
        }

        $customer = (new CustomerRepository())->findOrCreate($salonId, $name, $phoneRaw);
        $duplicate = DB::selectOne("SELECT * FROM appointments WHERE salon_id=? AND customer_id=? AND scheduled_at=? AND status IN ('confirmed','queued','in_chair')", [$salonId,$customer['id'],$date->format('Y-m-d').' '.$time.':00']);
        if ($duplicate) return ['appointment'=>$duplicate,'customer'=>$customer];
        $free = $this->freeSlotsForServices($salonId, $preferredStaffId, $date, $serviceIds);
        if (!isset($free[$time]) || $free[$time] === []) {
            throw new RuntimeException('این بازه دیگر آزاد نیست. لطفاً بازهٔ دیگری انتخاب کنید.');
        }

        $staffId = $preferredStaffId ?? $free[$time][0];
        $scheduledAt = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time);

        $customer = (new CustomerRepository())->findOrCreate($salonId, $name, $phoneRaw);

        $appointments = new AppointmentRepository();
        $appointmentId = $appointments->create($salonId, [
            'customer_id' => $customer['id'],
            'staff_id' => $staffId,
            'kind' => 'booked',
            'status' => 'confirmed',
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            // برای محدودیت نرخ (ت-۳۵) — نه برای چیز دیگری
            'created_ip' => $ip,
        ]);

        foreach ($serviceIds as $serviceId) {
            $effective = $serviceRepo->effective($salonId, $staffId, $serviceId);
            $appointments->addItem($salonId, $appointmentId, $serviceId, $effective['price'], $effective['duration_minutes']);
        }

        $appointment = $appointments->find($salonId, $appointmentId);
        return ['appointment' => $appointment, 'customer' => $customer];
        });
        try { $this->sendConfirmation($salonId, $appointment['appointment'], $appointment['customer']); }
        catch (\Throwable $e) { error_log('Booking confirmation could not be sent for appointment '. $appointment['appointment']['id']); }
        return $appointment['appointment'];

    }

    /** Availability uses each staff member's actual duration override. */
    public function freeSlotsForServices(int $salonId, ?int $staffId, DateTimeImmutable $date, array $serviceIds): array
    {
        $services = new ServiceRepository();
        foreach ($serviceIds as $id) {
            $s = $services->find($salonId, (int)$id);
            if (!$s || !$s['is_active']) return [];
        }
        $result = [];
        $active = $this->activeStaffIds($salonId);
        foreach ($active as $sid) {
            if ($staffId !== null && $staffId !== $sid) continue;
            $duration = 0;
            foreach (array_unique($serviceIds) as $id) $duration += $services->effective($salonId, $sid, (int)$id)['duration_minutes'];
            foreach ($this->slots->freeSlotsForStaff($salonId, $sid, $date, max(5,$duration)) as $time) $result[$time][]=$sid;
        }
        ksort($result);
        return $result;
    }

    public function cancelByToken(string $token, string $reason = ''): bool
    {
        $appointments = new AppointmentRepository();
        $appt = $appointments->findByToken($token);
        if ($appt === null || !in_array($appt['status'], ['confirmed', 'queued'], true)) {
            return false;
        }
        $changed = DB::update('appointments', [
            'status' => 'cancelled',
            'cancel_reason' => $reason ?: 'لغو توسط مشتری',
            // این مسیر فقط از دست مشتری می‌آید (کارت نوبت یا «نوبت‌های من»)
            'cancelled_by' => 'customer',
        ], "id=:id AND salon_id=:sid AND status IN ('confirmed','queued')", ['id'=>$appt['id'],'sid'=>$appt['salon_id']]);

        return $changed > 0;
    }

    /**
     * پیامک تأیید رزرو.
     *
     * از راه الگو می‌رود نه متن آزاد (ت-۲۹): روی خط خدماتی، متنِ آزاد
     * تحویل نمی‌شود ولی در پنل «ارسال شد» می‌خورد.
     *
     * لینک پیگیری از متن حذف شد چون الگوی ثبت‌شده نمی‌تواند لینکِ متغیر
     * داشته باشد. مشتری از همان صفحه‌ای که رزرو کرده لینکش را می‌بیند.
     */
    private function sendConfirmation(int $salonId, array $appointment, array $customer): void
    {
        if ($customer['phone'] === null) {
            return;
        }

        $salon = DB::selectOne('SELECT name FROM salons WHERE id = ?', [$salonId]);
        $at = new DateTimeImmutable($appointment['scheduled_at']);

        (new SmsNotifier())->notify($salonId, $appointment, 'booking_confirmed', [
            'name' => trim(explode(' ', (string) ($customer['name'] ?? 'مشتری'))[0]) ?: 'مشتری',
            'salon' => $salon['name'] ?? '',
            'date' => JalaliCalendar::humanDate($at),
            'time' => Clock::hm($at->format('H:i')),
        ]);
    }
}

