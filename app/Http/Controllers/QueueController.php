<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Access\Access;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Booking\AvailabilityCache;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Payment\PaymentRepository;
use App\Domain\Queue\QueueService;
use App\Domain\Staff\StaffRepository;
use App\Support\Version;
use RuntimeException;

/**
 * «امروز» — صف زنده، پذیرش حضوری و گردش خدمت.
 */
final class QueueController extends Controller
{
    public function index(Request $request): Response
    {
        $salonId = (int) Auth::salonId();

        /*
         * تازه‌سازی خودکار هر ۳۰ ثانیه کل صفحه را می‌خواست و همه‌چیز از نو ساخته
         * می‌شد — روی هر دستگاهی که صفحه باز است. حالا صفحه نسخه‌ای ارزان (بدون
         * کوئری) می‌فرستد و JS همان را برمی‌گرداند؛ اگر نه نوبتی، نه پرداختی، نه
         * تنظیمی عوض شده و دقیقه هم نگذشته (ساعت‌های تخمینی)، پاسخ ۲۰۴ بی‌بدنه است.
         * فقط با APCu: بدون کش مشترک، نسخه بین درخواست‌ها معنا ندارد.
         */
        $version = null;
        if (Cache::shared()) {
            $version = substr(sha1(implode('|', [
                Version::current(), $salonId, (int) Auth::id(), (string) Auth::role(), Auth::isImpersonating() ? 1 : 0,
                AvailabilityCache::version($salonId), Cache::version("salon:$salonId"), intdiv(time(), 60),
            ])), 0, 16);
            if ($request->header('X-Refresh-Version') === $version) {
                return new Response('', 204, ['X-Refresh-Version' => $version, 'Cache-Control' => 'private, no-store']);
            }
        }

        $myStaffId = Auth::staffId();
        $appointments = new AppointmentRepository();
        $payments = new PaymentRepository();
        $today = date('Y-m-d');
        $desk = Access::allows(Access::BOOK_FOR_OTHERS);

        $snapshot = (new QueueService())->salonSnapshot($salonId);
        if (!$desk) {
            // آرایشگر فقط صف خودش را می‌بیند
            $snapshot = array_values(array_filter($snapshot, static fn ($g) => (int) $g['staff']['id'] === $myStaffId));
        }

        $services = new ServiceRepository();

        $response = $this->page('layouts.panel', 'panel.queue.index', [
            'title' => 'امروز',
            'snapshot' => $snapshot,
            'desk' => $desk,
            'myStaffId' => $myStaffId,
            'serviceGroups' => $desk ? $services->grouped($salonId, true) : [],
            'staffList' => $desk ? (new StaffRepository())->all($salonId, true) : [],
            'summary' => $appointments->todaySummary($salonId, $desk ? null : $myStaffId),
            'myEarnings' => $myStaffId !== null ? $payments->dailyTotal($salonId, $today, $myStaffId) : null,
            'salonEarnings' => Access::allows(Access::VIEW_SALON_EARNINGS) ? $payments->dailyTotal($salonId, $today) : null,
            'awaiting' => Access::allows(Access::TAKE_PAYMENT) ? $appointments->awaitingSettlement($salonId) : [],
            'pendingDeposits' => $desk ? count($appointments->pendingDeposits($salonId)) : 0,
            'canPay' => Access::allows(Access::TAKE_PAYMENT),
            'refreshVersion' => $version,
        ]);
        if ($version !== null) {
            $response->headers['X-Refresh-Version'] = $version;
        }

        return $response;
    }

    /**
     * JSON صف برای ادغام‌های بیرونی (نمایشگر سالن و…). صفحهٔ امروز خودش
     * با تازه‌سازی بخشی HTML کار می‌کند.
     */
    public function poll(Request $request): Response
    {
        $snapshot = (new QueueService())->salonSnapshot((int) Auth::salonId());
        $body = (string) json_encode(array_map(static fn ($group) => [
            'staff' => ['id' => (int) $group['staff']['id'], 'name' => $group['staff']['name']],
            'queue' => array_map(static fn ($row) => [
                'id' => (int) $row['id'],
                'status' => $row['status'],
                'kind' => $row['kind'],
                'display' => $row['display'],
            ], $group['queue']),
        ], $snapshot), JSON_UNESCAPED_UNICODE);
        $etag = '"' . md5($body) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return new Response('', 304, ['ETag' => $etag]);
        }

        return new Response($body, 200, ['Content-Type' => 'application/json; charset=UTF-8', 'ETag' => $etag, 'Cache-Control' => 'private, no-cache']);
    }

    public function addWalkin(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $staffRaw = (string) $request->input('staff_id', '');

        try {
            (new QueueService())->addWalkin(
                $salonId,
                trim((string) $request->input('name', '')) ?: null,
                trim((string) $request->input('phone', '')) ?: null,
                $staffRaw !== '' ? (int) $staffRaw : null,
                (array) $request->input('service_ids', [])
            );
        } catch (RuntimeException $e) {
            \App\Core\Session::flash('_old', array_diff_key($request->all(), ['_csrf' => 1]));
            \App\Core\Session::flash('walkin_open', true);

            return $this->withError($e->getMessage(), '/panel#walkin');
        }

        return $this->withSuccess('به صف اضافه شد.', '/panel');
    }

    /** آرایشگر فقط روی نوبت خودش کار می‌کند. */
    private function denyForeignAppointment(int $appointmentId): ?Response
    {
        $appointment = (new AppointmentRepository())->find((int) Auth::salonId(), $appointmentId);
        if ($appointment === null) {
            return $this->withError('نوبت یافت نشد.', '/panel');
        }
        if (!Access::canActOnAppointment($appointment)) {
            return $this->withError('این نوبت برای شما نیست.', '/panel');
        }

        return null;
    }

    public function start(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($deny = $this->denyForeignAppointment($id)) {
            return $deny;
        }

        try {
            (new QueueService())->startService((int) Auth::salonId(), $id);
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel');
        }

        return $this->redirect('/panel');
    }

    public function complete(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($deny = $this->denyForeignAppointment($id)) {
            return $deny;
        }

        try {
            (new QueueService())->completeService((int) Auth::salonId(), $id);
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel');
        }

        /*
         * کسی که اجازهٔ تسویه دارد مستقیم به صفحهٔ تسویه می‌رود. آرایشگر
         * نه — قبلاً به صفحه‌ای فرستاده می‌شد که ۴۰۳ می‌داد؛ حالا کار در
         * فهرست «منتظر تسویه»ی پذیرش می‌نشیند.
         */
        if (Access::allows(Access::TAKE_PAYMENT)) {
            return $this->redirect('/panel/pay/' . $id);
        }

        return $this->withSuccess('خدمت تمام شد. تسویه در پذیرش انجام می‌شود.', '/panel');
    }

    public function noShow(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($deny = $this->denyForeignAppointment($id)) {
            return $deny;
        }
        (new QueueService())->markNoShow((int) Auth::salonId(), $id);

        return $this->withSuccess('غیبت ثبت شد.', '/panel');
    }

    public function cancel(Request $request): Response
    {
        $id = (int) $request->param('id');
        if ($deny = $this->denyForeignAppointment($id)) {
            return $deny;
        }
        (new QueueService())->cancel((int) Auth::salonId(), $id, trim((string) $request->input('reason', '')));

        $back = (string) $request->input('back', '');

        return $this->withSuccess('نوبت لغو شد. اگر نوبتِ آینده بود، به مشتری پیامک لغو فرستاده می‌شود.', str_starts_with($back, '/panel/bookings') || $back === '/panel' ? $back : '/panel');
    }
}
