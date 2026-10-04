<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Messaging\QueueNotificationService;
use DateTimeImmutable;
use RuntimeException;

/**
 * چرخهٔ عمر صف را می‌گرداند.
 *
 * یک قاعده اینجا قابل مذاکره نیست: «تمام شد» و «تسویه» یک دکمه‌اند.
 *
 * دلیلش رفتار واقعی آرایشگاه است. اگر دو کار جدا باشند، آرایشگر تسویه
 * می‌کند و ثبت «تمام شد» را فراموش می‌کند — و آن‌وقت سیستم هیچ‌وقت
 * یاد نمی‌گیرد که این خدمت چقدر طول کشید، صف جلو نمی‌رود، و تخمین‌های
 * بعدی همه غلط می‌شوند. با یکی بودنشان، همان لحظه که پول گرفته شد
 * مشتری بعدی خودکار روی صندلی می‌نشیند.
 */
final class QueueService
{
    private array $notifyPending = [];

    /** @var array<int,array> نوبت‌هایی که سالن لغو کرد؛ پیامکشان پس از commit می‌رود */
    private array $cancelledBySalon = [];

    /** Serialize writes with booking and defer notifications until commit. */
    private function mutate(int $salonId, callable $callback): mixed
    {
        if (DB::connection()->inTransaction()) return $callback();
        try {
        $result = DB::transaction(function() use($salonId,$callback) {
            if (!DB::selectOne('SELECT id FROM salons WHERE id=? AND is_active=1 FOR UPDATE',[$salonId])) throw new RuntimeException('سالن فعال نیست.');
            return $callback();
        });
        } catch (\Throwable $e) {
            $this->notifyPending = [];
            $this->cancelledBySalon = [];
            throw $e;
        }
        $cancelled = $this->cancelledBySalon; $this->cancelledBySalon = [];
        foreach ($cancelled as $appt) {
            (new \App\Domain\Booking\BookingService())->notifySalonCancellation($salonId, $appt);
        }
        $pending = $this->notifyPending; $this->notifyPending = [];
        foreach ($pending as [$sid,$staffId]) {
            try { (new QueueNotificationService())->syncStaffQueue($sid,$staffId); }
            catch (\Throwable $e) { error_log('Queue notification failed for salon '.$sid); }
        }
        return $result;
    }

    private function notify(int $salonId, int $staffId): void
    {
        $this->notifyPending[$salonId.':'.$staffId]=[$salonId,$staffId];
    }

    public function addWalkin(int $salonId, ?string $name, ?string $phone, ?int $staffId, array $services): array
    { return $this->mutate($salonId,fn()=>$this->addWalkinUnlocked($salonId,$name,$phone,$staffId,$services)); }
    public function startService(int $salonId,int $appointmentId): void
    { $this->mutate($salonId,fn()=>$this->startServiceUnlocked($salonId,$appointmentId)); }
    public function completeService(int $salonId,int $appointmentId): array
    { return $this->mutate($salonId,fn()=>$this->completeServiceUnlocked($salonId,$appointmentId)); }
    public function cancel(int $salonId,int $appointmentId,string $reason=''): void
    { $this->mutate($salonId,fn()=>$this->cancelUnlocked($salonId,$appointmentId,$reason)); }
    public function markNoShow(int $salonId,int $appointmentId): void
    { $this->mutate($salonId,fn()=>$this->markNoShowUnlocked($salonId,$appointmentId)); }

    private AppointmentRepository $appointments;

    private QueueOrderingService $ordering;

    private EtaEngine $eta;

    public function __construct()
    {
        $this->appointments = new AppointmentRepository();
        $this->ordering = new QueueOrderingService();
        $this->eta = new EtaEngine();
    }

    /**
     * افزودن مراجعهٔ بدون نوبت، با یک ضربه.
     *
     * اگر آرایشگری انتخاب نشده باشد، کم‌کارترینِ فعال را می‌دهد. سرعت
     * اینجا مهم‌تر از دقت است: مشتری جلوی پیشخوان ایستاده.
     *
     * @param int[] $serviceIds
     */
    private function addWalkinUnlocked(int $salonId, ?string $customerName, ?string $customerPhone, ?int $staffId, array $serviceIds): array
    {
        $serviceIds = \App\Domain\Booking\BookingPlanner::normalizeIds($serviceIds);
        if ($serviceIds === []) {
            throw new RuntimeException('حداقل یک خدمت را انتخاب کنید.');
        }

        \App\Domain\Catalog\ServiceRepository::flushCache();
        $planner = new \App\Domain\Booking\BookingPlanner();
        if (($error = $planner->validate($salonId, $serviceIds, false)) !== null) {
            throw new RuntimeException($error);
        }

        /*
         * فقط کسی که همهٔ این خدمات را انجام می‌دهد. در سالن بانوان
         * «کم‌کارترین» ممکن است ناخن‌کار باشد و مشتری برای رنگ آمده.
         */
        $capable = $planner->capableStaff($salonId, $serviceIds, false);
        if ($capable === []) {
            throw new RuntimeException('هیچ‌کس به‌تنهایی همهٔ این خدمات را انجام نمی‌دهد. برای هر بخش جداگانه پذیرش کنید.');
        }

        if ($staffId !== null) {
            if (!DB::selectOne('SELECT id FROM staff WHERE salon_id=? AND id=? AND is_active=1', [$salonId, $staffId])) {
                throw new RuntimeException('فرد انتخاب‌شده معتبر نیست.');
            }
            if (!in_array($staffId, $capable, true)) {
                throw new RuntimeException('فرد انتخاب‌شده همهٔ این خدمات را انجام نمی‌دهد.');
            }
        }
        $staffId ??= (new StaffAssigner())->pickLeastBusy($salonId, $capable);
        if ($staffId === null) {
            throw new RuntimeException('هیچ فرد فعالی برای تخصیص وجود ندارد.');
        }

        if ($customerPhone !== null && $customerPhone !== '' && \App\Support\IranMobile::tryParse($customerPhone) === null) {
            throw new RuntimeException('شمارهٔ موبایل نامعتبر است. خالی بگذارید یا درست وارد کنید.');
        }

        $customer = (new CustomerRepository())->findOrCreate($salonId, $customerName, $customerPhone);

        $appointmentId = $this->appointments->create($salonId, [
            'customer_id' => $customer['id'],
            'staff_id' => $staffId,
            'kind' => 'walkin',
            'status' => 'queued',
            'queued_at' => date('Y-m-d H:i:s'),
            'created_by_user_id' => \App\Core\Auth::id(),
        ]);

        $services = new \App\Domain\Catalog\ServiceRepository();
        $lastIndex = count($serviceIds) - 1;
        foreach ($serviceIds as $i => $serviceId) {
            $effective = $services->effective($salonId, $staffId, $serviceId);
            $this->appointments->addItem($salonId, $appointmentId, $serviceId, $effective['price'], $effective['duration_minutes'], $i === $lastIndex ? $effective['buffer_minutes'] : 0);
        }

        $this->autoStartIfChairFree($salonId, $staffId);
        $this->notify($salonId, $staffId);

        return $this->appointments->find($salonId, $appointmentId);
    }

    /** A04: manual "شروع" — only needed when the chair didn't auto-advance. */
    private function startServiceUnlocked(int $salonId, int $appointmentId): void
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        if ($appt === null) {
            throw new RuntimeException('نوبت یافت نشد.');
        }

        if ($appt['status']==='in_chair') return;
        if (!in_array($appt['status'],['confirmed','queued','in_chair'],true)) throw new RuntimeException('این نوبت قابل شروع نیست.');
        $current = $this->appointments->inChairFor($salonId, (int) $appt['staff_id']);
        if ($current !== null && (int) $current['id'] !== $appointmentId) {
            throw new RuntimeException('این آرایشگر همین الان مشغول است.');
        }

        $this->appointments->update($salonId, $appointmentId, [
            'status' => 'in_chair',
            'actual_start_at' => date('Y-m-d H:i:s'),
        ]);

        $this->notify($salonId, (int) $appt['staff_id']);
    }

    /**
     * A04 + D02: the giant "تمام شد" button. Records the actual end time,
     * feeds the learning engine, advances the chair, and returns the
     * payout-ready totals — payment itself is recorded by PaymentService
     * right after this in the same controller action.
     */
    private function completeServiceUnlocked(int $salonId, int $appointmentId): array
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        if ($appt && $appt['status']==='completed') return ['appointment'=>$appt,'items'=>$this->appointments->itemsFor($salonId,$appointmentId)];
        if ($appt === null || $appt['status'] !== 'in_chair') {
            throw new RuntimeException('این نوبت روی صندلی نیست.');
        }

        $items = $this->appointments->itemsFor($salonId, $appointmentId);
        $customer = DB::selectOne('SELECT * FROM customers WHERE id = ?', [$appt['customer_id']]);

        $this->appointments->update($salonId, $appointmentId, [
            'status' => 'completed',
            'actual_end_at' => date('Y-m-d H:i:s'),
        ]);
        $appt['actual_end_at'] = date('Y-m-d H:i:s');
        $appt['status'] = 'completed';

        (new DurationLearner())->recordCompletion($salonId, $appt, $items, $customer);

        $this->autoStartIfChairFree($salonId, (int) $appt['staff_id']);
        $this->notify($salonId, (int) $appt['staff_id']);

        return ['appointment' => $appt, 'items' => $items];
    }

    private function cancelUnlocked(int $salonId, int $appointmentId, string $reason = ''): void
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        if (!$appt || !in_array($appt['status'], ['pending', 'confirmed', 'queued', 'in_chair'], true)) {
            return;
        }
        $this->appointments->update($salonId, $appointmentId, [
            'status' => 'cancelled',
            'cancel_reason' => $reason !== '' ? mb_substr($reason, 0, 150) : null,
            // از پنل لغو شده، یعنی کار خود آرایشگاه
            'cancelled_by' => 'salon',
            'hold_expires_at' => null,
        ]);
        $this->cancelledBySalon[] = $appt;
        if ($appt['staff_id'] !== null) {
            if ($appt['status'] === 'in_chair') {
                $this->autoStartIfChairFree($salonId, (int) $appt['staff_id']);
            }
            $this->notify($salonId, (int) $appt['staff_id']);
        }
    }

    private function markNoShowUnlocked(int $salonId, int $appointmentId): void
    {
        $appt = $this->appointments->find($salonId, $appointmentId);
        if (!$appt || !in_array($appt['status'], ['confirmed', 'queued'], true)) {
            return;
        }
        $this->appointments->update($salonId, $appointmentId, ['status' => 'no_show']);
        // افزایش اتمی؛ خواندن و نوشتن جدا، در دو درخواست هم‌زمان یکی را گم می‌کرد
        DB::statement(
            'UPDATE customers SET no_show_count = no_show_count + 1 WHERE id = ? AND salon_id = ?',
            [$appt['customer_id'], $salonId]
        );
        if ($appt['staff_id'] !== null) {
            $this->notify($salonId, (int) $appt['staff_id']);
        }
    }

    /**
     * اگر صندلی خالی است، نفر بعدیِ صف همان آرایشگر خودکار می‌نشیند.
     *
     * لایهٔ دوم دفاع در برابر فراموشی: آرایشگر وسط کار یادش می‌رود دکمه
     * بزند، و بدون این، صف روی صفحهٔ مشتری‌ها یخ می‌زند.
     */
    private function autoStartIfChairFree(int $salonId, int $staffId): void
    {
        $inChair = $this->appointments->inChairFor($salonId, $staffId);
        if ($inChair !== null) {
            return;
        }

        $queue = $this->appointments->activeForStaff($salonId, $staffId);
        $queue = array_filter($queue, static fn ($a) => $a['status'] === 'queued');
        $ordered = $this->ordering->order(array_values($queue));

        if ($ordered !== []) {
            $this->startService($salonId, (int) $ordered[0]['id']);
        }
    }

    /**
     * عکس لحظه‌ای کل سالن — برای پنل و صفحهٔ عمومی صف.
     *
     * صف همهٔ آرایشگرهای فعال، مرتب‌شده، با ساعت تخمینی و متنی که
     * مستقیم قابل نشان دادن است.
     */
    public function salonSnapshot(int $salonId): array
    {
        $staffRows = DB::select('SELECT id, name, title, color FROM staff WHERE salon_id = ? AND is_active = 1 ORDER BY sort_order, id', [$salonId]);
        $now = new DateTimeImmutable();

        /*
         * یک کوئری برای نوبت‌های همه و یک کوئری برای خدمت‌هایشان. صفحهٔ
         * امروز هر ۱۵ ثانیه تازه می‌شود؛ کوئری به‌ازای هر نفر، با ده نفر
         * کادر یعنی ده رفت‌وبرگشت اضافه در هر تازه‌سازیِ هر دستگاه.
         */
        $byStaff = $this->appointments->activeTodayByStaff($salonId);
        $allIds = [];
        foreach ($byStaff as $rows) {
            foreach ($rows as $row) {
                $allIds[] = (int) $row['id'];
            }
        }
        $itemsByAppointment = $this->appointments->itemsForMany($salonId, $allIds);

        $snapshot = [];
        foreach ($staffRows as $staff) {
            $ordered = $this->ordering->order($byStaff[(int) $staff['id']] ?? [], $now);
            $etas = $this->eta->computeForStaffQueue($ordered, $now);

            $rows = [];
            foreach ($ordered as $appt) {
                $e = $etas[(int) $appt['id']] ?? null;
                $display = $e !== null ? $this->eta->displayText($e['position'], $e['start_p50'], $e['start_p80'], $now) : null;
                $rows[] = array_merge($appt, [
                    'eta' => $e,
                    'display' => $display,
                    'items' => $itemsByAppointment[(int) $appt['id']] ?? [],
                ]);
            }

            $snapshot[] = ['staff' => $staff, 'queue' => $rows];
        }

        return $snapshot;
    }

    /** همان نما، ولی از دید یک مشتری — برای لینک «نوبت من». */
    public function customerView(string $publicToken): ?array
    {
        $appt = $this->appointments->findByToken($publicToken);
        if ($appt === null || $appt['staff_id'] === null) {
            return $appt !== null ? ['appointment' => $appt, 'eta' => null, 'display' => null] : null;
        }

        $now = new DateTimeImmutable();
        $queue = $this->appointments->activeForStaff((int) $appt['salon_id'], (int) $appt['staff_id']);
        $ordered = $this->ordering->order($queue, $now);
        $etas = $this->eta->computeForStaffQueue($ordered, $now);

        $e = $etas[(int) $appt['id']] ?? null;
        $display = $e !== null ? $this->eta->displayText($e['position'], $e['start_p50'], $e['start_p80'], $now) : null;

        return ['appointment' => $appt, 'eta' => $e, 'display' => $display];
    }
}
