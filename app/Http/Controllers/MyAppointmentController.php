<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Booking\BookingService;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Queue\QueueService;

/** A10 — the passwordless "نوبت من" page a customer reaches from their SMS link. */
final class MyAppointmentController extends Controller
{
    public function show(Request $request): Response
    {
        $token = (string) $request->param('token');
        $view = (new QueueService())->customerView($token);

        if ($view === null) {
            return Response::html('نوبتی با این لینک پیدا نشد.', 404);
        }

        $appt = $view['appointment'];
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$appt['salon_id']]);
        $items = (new AppointmentRepository())->itemsFor((int) $appt['salon_id'], (int) $appt['id']);

        return $this->page('layouts.booking', 'booking.my_appointment', [
            'title' => 'نوبت من',
            'salon' => $salon,
            'appointment' => $appt,
            'items' => $items,
            'display' => $view['display'],
            'payment' => (new \App\Domain\Payment\PaymentRepository())->forAppointment((int)$appt['salon_id'],(int)$appt['id']),
            'pendingPayment' => DB::selectOne("SELECT authority FROM online_payment_attempts WHERE appointment_id=? AND status='pending' ORDER BY id DESC LIMIT 1",[$appt['id']]),
        ]);
    }

    public function cancel(Request $request): Response
    {
        $token = (string) $request->param('token');
        (new BookingService())->cancelByToken($token, 'لغو توسط مشتری');

        return $this->redirect('/q/' . $token);
    }
}
