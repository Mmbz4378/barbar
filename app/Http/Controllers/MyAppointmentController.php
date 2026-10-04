<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Booking\BookingService;
use App\Domain\Payment\PaymentGatewayManager;
use App\Domain\Payment\PaymentRepository;
use App\Domain\Queue\QueueService;
use App\Domain\Salon\SalonRepository;
use DateTimeImmutable;

/**
 * کارت نوبت بدون رمز — همان لینکی که پس از رزرو و در پیامک می‌آید.
 */
final class MyAppointmentController extends Controller
{
    public function show(Request $request): Response
    {
        $token = (string) $request->param('token');
        $view = (new QueueService())->customerView($token);
        if ($view === null) {
            return $this->notFound('نوبتی با این لینک پیدا نشد. اگر لینک را از پیامک باز کرده‌اید، دوباره امتحان کنید یا از «نوبت‌های من» وارد شوید.');
        }

        $appt = $view['appointment'];
        $salonId = (int) $appt['salon_id'];
        $salon = (new SalonRepository())->find($salonId);
        \App\Support\SalonContext::set($salon);
        $repo = new AppointmentRepository();
        $parts = $repo->group($appt);
        $items = $repo->itemsForMany($salonId, array_map(static fn ($p) => (int) $p['id'], $parts));
        $staffNames = [];
        foreach (DB::select('SELECT id, name FROM staff WHERE salon_id = ?', [$salonId]) as $row) {
            $staffNames[(int) $row['id']] = $row['name'];
        }

        $first = $parts[0];
        $deposit = array_sum(array_map(static fn ($p) => (int) $p['deposit_amount'], $parts));
        $cancellation = (new BookingService())->customerCancellation($first);
        $payments = new PaymentRepository();

        return $this->page('layouts.booking', 'booking.my_appointment', [
            'title' => 'نوبت من',
            'salon' => $salon,
            'appointment' => $first,
            'parts' => $parts,
            'items' => $items,
            'staffNames' => $staffNames,
            'display' => $view['display'],
            'deposit' => $deposit,
            'depositPaid' => $payments->depositFor($salonId, (int) $first['id']) !== null,
            'cancellation' => $cancellation,
            'justBooked' => (bool) Session::flash('booked'),
            'payment' => $payments->forAppointment($salonId, (int) $first['id']),
            'onlinePay' => PaymentGatewayManager::isEnabled(),
            'pendingPayment' => DB::selectOne("SELECT authority FROM online_payment_attempts WHERE appointment_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$first['id']]),
        ]);
    }

    public function cancel(Request $request): Response
    {
        $token = (string) $request->param('token');
        $result = (new BookingService())->cancelByToken($token, 'لغو توسط مشتری');

        return match ($result) {
            'cancelled' => $this->withSuccess('نوبت لغو شد.', '/q/' . $token),
            'too_late' => $this->withError('مهلت لغو آنلاین این نوبت گذشته است. برای تغییر با سالن تماس بگیرید.', '/q/' . $token),
            'not_found' => $this->notFound('نوبتی با این لینک پیدا نشد.'),
            default => $this->withError('این نوبت دیگر قابل لغو نیست.', '/q/' . $token),
        };
    }

    /**
     * فایل تقویم — «افزودن به تقویم» روی iOS، اندروید و دسکتاپ.
     */
    public function calendar(Request $request): Response
    {
        $appt = (new AppointmentRepository())->findByToken((string) $request->param('token'));
        if ($appt === null || empty($appt['scheduled_at'])) {
            return $this->notFound('نوبتی با این لینک پیدا نشد.');
        }

        $repo = new AppointmentRepository();
        $parts = $repo->group($appt);
        $salon = (new SalonRepository())->find((int) $appt['salon_id']);
        $start = new DateTimeImmutable((string) $parts[0]['scheduled_at']);
        $minutes = 0;
        foreach ($repo->itemsForMany((int) $appt['salon_id'], array_map(static fn ($p) => (int) $p['id'], $parts)) as $rows) {
            $minutes += array_sum(array_map(static fn ($r) => (int) $r['duration_minutes'], $rows));
        }
        $end = $start->modify('+' . max(15, $minutes) . ' minutes');
        $utc = new \DateTimeZone('UTC');
        $esc = static fn (string $v): string => addcslashes(str_replace(["\r", "\n"], ' ', $v), ',;\\');

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Reshen//Booking//FA',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:' . $appt['public_token'] . '@reshen',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z'),
            'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z'),
            'SUMMARY:' . $esc('نوبت ' . ($salon['name'] ?? '')),
            'LOCATION:' . $esc(trim(($salon['city'] ?? '') . ' ' . ($salon['address'] ?? ''))),
            'URL:' . absolute_url('q/' . $appt['public_token']),
            'BEGIN:VALARM',
            'TRIGGER:-PT2H',
            'ACTION:DISPLAY',
            'DESCRIPTION:' . $esc('یادآوری نوبت'),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";

        return new Response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="reshen-' . $appt['public_token'] . '.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
