<?php

declare(strict_types=1);

/**
 * سابقهٔ ۴۵ روزهٔ نمونه برای سالن‌های دادهٔ نمونه — تا گزارش‌ها، پروفایل
 * مشتری و آمار پلتفرم خالی نباشند. فقط پس از tools/seed-demo.php.
 *
 *   php tools/seed-demo-history.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Support\Str;

if (Config::get('app.env') === 'production') {
    fwrite(STDERR, "روی production اجرا نمی‌شود.\n");
    exit(1);
}

mt_srand(1405);
$names = ['امیر', 'حسین', 'رضا', 'مهدی', 'کیان', 'آرش', 'سینا', 'پارسا', 'نیما', 'بهراد', 'ترانه', 'نازنین', 'مریم', 'سارا', 'الناز', 'نگین', 'هستی', 'یاسمن', 'پریا', 'شیوا'];
$methods = ['cash', 'card_to_card', 'pos', 'pos', 'card_to_card'];
$total = 0;

foreach (DB::select('SELECT id, audience FROM salons') as $salon) {
    $salonId = (int) $salon['id'];
    $staff = array_column(DB::select('SELECT id FROM staff WHERE salon_id = ? AND is_active = 1', [$salonId]), 'id');
    $services = DB::select('SELECT id, price, duration_minutes FROM services WHERE salon_id = ? AND is_active = 1 AND price > 0', [$salonId]);
    if ($staff === [] || $services === []) {
        continue;
    }
    $pool = $salon['audience'] === 'women' ? array_slice($names, 10) : array_slice($names, 0, 10);

    // مشتری‌های ثابت؛ بعضی چندبار می‌آیند
    $customers = [];
    for ($i = 0; $i < 18; $i++) {
        $customers[] = (int) DB::insert('customers', [
            'salon_id' => $salonId,
            'name' => $pool[$i % count($pool)] . ' ' . ['احمدی', 'کریمی', 'موسوی', 'رحیمی', 'نوری', 'صالحی'][$i % 6],
            'phone' => '0935' . str_pad((string) (1000000 + $salonId * 1000 + $i), 7, '0', STR_PAD_LEFT),
        ]);
    }

    for ($day = 45; $day >= 1; $day--) {
        $date = (new DateTimeImmutable('today'))->modify("-{$day} days");
        if ((int) $date->format('w') === 5) { // جمعه
            continue;
        }
        $visits = mt_rand(3, $salon['audience'] === 'women' ? 7 : 10);
        for ($v = 0; $v < $visits; $v++) {
            $service = $services[array_rand($services)];
            $start = $date->setTime(mt_rand(9, 19), [0, 15, 30, 45][mt_rand(0, 3)]);
            $minutes = (int) $service['duration_minutes'] + mt_rand(-5, 15);
            $end = $start->modify('+' . max(10, $minutes) . ' minutes');
            $roll = mt_rand(1, 100);
            $status = $roll <= 6 ? 'no_show' : ($roll <= 11 ? 'cancelled' : 'completed');
            $booked = mt_rand(0, 1) === 1;
            $customerId = $customers[array_rand($customers)];

            $appointmentId = (int) DB::insert('appointments', [
                'salon_id' => $salonId,
                'public_token' => Str::token(12),
                'customer_id' => $customerId,
                'staff_id' => $staff[array_rand($staff)],
                'kind' => $booked ? 'booked' : 'walkin',
                'status' => $status,
                'scheduled_at' => $booked ? $start->format('Y-m-d H:i:s') : null,
                'queued_at' => $booked ? null : $start->modify('-10 minutes')->format('Y-m-d H:i:s'),
                'estimated_start_at' => $start->format('Y-m-d H:i:s'),
                'actual_start_at' => $status === 'completed' ? $start->modify('+' . mt_rand(0, 12) . ' minutes')->format('Y-m-d H:i:s') : null,
                'actual_end_at' => $status === 'completed' ? $end->format('Y-m-d H:i:s') : null,
                'cancelled_by' => $status === 'cancelled' ? ['customer', 'customer', 'salon'][mt_rand(0, 2)] : null,
                'created_at' => $start->modify('-1 day')->format('Y-m-d H:i:s'),
            ]);
            DB::insert('appointment_items', [
                'salon_id' => $salonId,
                'appointment_id' => $appointmentId,
                'service_id' => $service['id'],
                'price' => $service['price'],
                'duration_minutes' => $service['duration_minutes'],
            ]);

            if ($status === 'completed') {
                $discount = mt_rand(1, 10) === 1 ? (int) round($service['price'] * 0.1) : 0;
                $tip = mt_rand(1, 4) === 1 ? [50000, 100000, 200000][mt_rand(0, 2)] : 0;
                DB::insert('payments', [
                    'salon_id' => $salonId,
                    'appointment_id' => $appointmentId,
                    'kind' => 'settlement',
                    'method' => $methods[array_rand($methods)],
                    'amount' => (int) $service['price'] - $discount,
                    'tip_amount' => $tip,
                    'discount_amount' => $discount,
                    'paid_at' => $end->format('Y-m-d H:i:s'),
                ]);
                DB::statement('UPDATE customers SET visit_count = visit_count + 1, last_visit_at = ? WHERE id = ?', [$end->format('Y-m-d H:i:s'), $customerId]);
                $total++;
            } elseif ($status === 'no_show') {
                DB::statement('UPDATE customers SET no_show_count = no_show_count + 1 WHERE id = ?', [$customerId]);
            }
        }
    }

    // چند نظر برای کارت «کشف»
    $done = DB::select("SELECT id, customer_id FROM appointments WHERE salon_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 6", [$salonId]);
    $comments = ['خیلی راضی بودم، دقیق و به‌موقع.', 'محیط تمیز و برخورد عالی.', 'کار خوب بود ولی کمی منتظر ماندم.', null, 'حتماً دوباره می‌آیم.', 'قیمت مناسب و کار تمیز.'];
    foreach ($done as $i => $row) {
        DB::insert('reviews', [
            'salon_id' => $salonId, 'appointment_id' => $row['id'], 'customer_id' => $row['customer_id'],
            'rating' => [5, 5, 4, 5, 4, 5][$i], 'comment' => $comments[$i],
            'moderation_status' => $i === 5 ? 'pending' : 'published',
        ]);
    }
    (new App\Domain\Salon\SalonStats())->refreshRating($salonId);
}

echo "{$total} مراجعهٔ تسویه‌شده ساخته شد.\n";
