<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Payment\PaymentRepository;
use RuntimeException;

/**
 * تسویه پس از خدمت.
 *
 * مبلغ پیش‌فرض از اقلام همان نوبت (قیمت لحظهٔ رزرو) می‌آید، نه از
 * نشانی صفحه. تخفیف و انعام جدا ثبت می‌شوند تا گزارش بداند فروش واقعی
 * چقدر بوده؛ بیعانهٔ دریافت‌شده از مبلغ قابل دریافت کم می‌شود.
 */
final class PaymentController extends Controller
{
    public function show(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $appointments = new AppointmentRepository();
        $appt = $appointments->find($salonId, $id);

        if ($appt === null) {
            return $this->withError('نوبت یافت نشد.', '/panel');
        }
        if ($appt['status'] !== 'completed') {
            return $this->withError('تسویه پس از «تمام شد» ممکن است.', '/panel');
        }

        $payments = new PaymentRepository();
        if ($payments->forAppointment($salonId, $id) !== null) {
            return $this->withSuccess('این نوبت قبلاً تسویه شده است.', '/panel');
        }

        $items = $appointments->itemsFor($salonId, $id);
        $group = $appointments->group($appt);
        $deposit = 0;
        if (count($group) === 1 || (int) $group[0]['id'] === $id) {
            $paid = $payments->depositFor($salonId, (int) $group[0]['id']);
            $deposit = $paid !== null ? (int) $paid['amount'] : 0;
        }

        return $this->page('layouts.panel', 'panel.queue.pay', [
            'title' => 'تسویه',
            'appointment' => $appt,
            'items' => $items,
            'customer' => DB::selectOne('SELECT * FROM customers WHERE id = ? AND salon_id = ?', [$appt['customer_id'], $salonId]),
            'staff' => $appt['staff_id'] !== null ? DB::selectOne('SELECT name FROM staff WHERE id = ? AND salon_id = ?', [$appt['staff_id'], $salonId]) : null,
            'subtotal' => array_sum(array_map(static fn ($i) => (int) $i['price'], $items)),
            'deposit' => $deposit,
            'groupSize' => count($group),
        ]);
    }

    public function store(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $id = (int) $request->param('id');
        $method = (string) $request->input('method', 'cash');
        $amount = int_input($request->input('amount_toman'));
        $tip = int_input($request->input('tip_toman')) ?? 0;
        $discount = int_input($request->input('discount_toman')) ?? 0;

        if ($amount === null || $amount < 0) {
            return $this->invalid($request, ['amount_toman' => 'مبلغ دریافتی را به تومان وارد کنید.'], '/panel/pay/' . $id);
        }

        $discount = max(0, min($discount, $amount));

        try {
            // مبلغ ثبت‌شده همان دریافتیِ خدمات است (پس از تخفیف)؛ انعام جدا
            (new PaymentRepository())->record($salonId, $id, $method, ($amount - $discount) * 10, max(0, $tip) * 10, Auth::id(), $discount * 10);
        } catch (RuntimeException $e) {
            return $this->withError($e->getMessage(), '/panel/pay/' . $id);
        }

        return $this->withSuccess('تسویه ثبت شد.', '/panel');
    }
}
