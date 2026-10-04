<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Payment\PaymentRepository;
use App\Domain\Appointment\AppointmentRepository;
use App\Support\Money;

/** D01/D02 — one-tap settlement, glued to "تمام شد" so it can't be skipped. */
final class PaymentController extends Controller
{
    public function show(Request $request): Response
    {
        $salonId = Auth::salonId();
        $appointmentId = (int) $request->param('id');
        $appointments = new AppointmentRepository();
        $appt = $appointments->find($salonId, $appointmentId);

        if ($appt === null) {
            return $this->withError('نوبت یافت نشد.', '/panel');
        }

        $existing = (new PaymentRepository())->forAppointment($salonId, $appointmentId);
        if ($existing !== null) {
            return $this->withSuccess('این نوبت قبلاً تسویه شده.', '/panel');
        }

        $items = $appointments->itemsFor($salonId, $appointmentId);
        $customer = DB::selectOne('SELECT * FROM customers WHERE id = ?', [$appt['customer_id']]);
        $amount = (int) $request->query('amount', (string) array_sum(array_column($items, 'price')));

        return $this->page('layouts.panel', 'panel.queue.pay', [
            'title' => 'تسویه',
            'appointment' => $appt,
            'items' => $items,
            'customer' => $customer,
            'amount' => $amount,
        ]);
    }

    public function store(Request $request): Response
    {
        $salonId = Auth::salonId();
        $appointmentId = (int) $request->param('id');
        $method = (string) $request->input('method', 'cash');
        $amount = Money::fromToman((int) $request->input('amount_toman', 0))->rials;
        $tip = Money::fromToman((int) $request->input('tip_toman', 0))->rials;

        try {
            (new PaymentRepository())->record($salonId, $appointmentId, $method, $amount, $tip, Auth::id());
        } catch (\RuntimeException $e) { return $this->withError($e->getMessage(), '/panel'); }

        return $this->withSuccess('تسویه ثبت شد.', '/panel');
    }
}
