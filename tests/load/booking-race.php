<?php

declare(strict_types=1);

/**
 * آزمون رزرو هم‌زمان روی دیتابیس واقعی (بدون تراکنشِ دربرگیرنده).
 *
 *   php tests/load/booking-race.php [--n=12]
 *
 * چند پردازش PHP دقیقاً در یک لحظه رها می‌شوند و همه یک کار را می‌کنند:
 *   ۱. یک سانس، یک نفر، شماره‌های مختلف   ← دقیقاً یکی موفق؛ بقیه «دیگر آزاد نیست»
 *   ۲. یک سانس، یک شماره (دوبار زدن)      ← یک نوبت و یک مشتری
 *   ۳. قفل سالن را کسی ۸ ثانیه نگه داشته  ← رزرو پس از حدود ۵ ثانیه با پیام «شلوغ است»
 *                                            شکست می‌خورد، نه پنجاه ثانیه و نه خطای خام SQL
 *
 * دادهٔ آزمون با شمارهٔ 0912999xxxx و تاریخی دور ساخته و در پایان پاک می‌شود.
 * پیش‌نیاز: tools/seed-demo.php
 */

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\DB;
use App\Domain\Booking\BookingService;

const PHONE_PREFIX = '0912999';

$opts = getopt('', ['n::', 'child::', 'at::', 'mode::', 'phone::', 'ctx::']);

// ─── فرزند ─────────────────────────────────────────────────────────────
if (isset($opts['child'])) {
    $ctx = json_decode(base64_decode((string) $opts['ctx']), true);
    time_sleep_until((float) $opts['at']);
    $t0 = microtime(true);
    $out = ['child' => (int) $opts['child']];
    try {
        if ($opts['mode'] === 'hold') {
            DB::transaction(static function (): void {
                DB::selectOne('SELECT id FROM salons WHERE id = ? FOR UPDATE', [$GLOBALS['ctx']['salon']]);
                sleep(8);
            });
            $out['result'] = 'held';
        } else {
            $appt = (new BookingService())->createBooking(
                (int) $ctx['salon'],
                (int) $ctx['staff'],
                [(int) $ctx['service']],
                new DateTimeImmutable($ctx['date']),
                $ctx['time'],
                (string) $opts['phone'],
                'آزمون هم‌زمانی',
                '127.0.0.1',
                ['online' => false]
            );
            $out['result'] = 'ok';
            $out['appointment'] = (int) $appt['id'];
        }
    } catch (App\Core\LockConflict $e) {
        $out['result'] = 'busy';
        $out['message'] = $e->getMessage();
    } catch (RuntimeException $e) {
        $out['result'] = $e instanceof PDOException ? 'raw_sql_error' : 'rejected';
        $out['message'] = $e->getMessage();
    }
    $out['ms'] = (int) round((microtime(true) - $t0) * 1000);
    echo json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
    exit(0);
}

// ─── والد ──────────────────────────────────────────────────────────────
$n = max(2, (int) ($opts['n'] ?? 12));

$salon = DB::selectOne("SELECT id FROM salons WHERE slug = 'araishgah-parsa'");
if ($salon === null) {
    fwrite(STDERR, "دادهٔ نمونه نیست: php tools/seed-demo.php\n");
    exit(1);
}
$salonId = (int) $salon['id'];
$service = DB::selectOne("SELECT id FROM services WHERE salon_id = ? AND is_active = 1 ORDER BY id LIMIT 1", [$salonId]);
$staff = DB::selectOne("SELECT id FROM staff WHERE salon_id = ? AND is_active = 1 ORDER BY id LIMIT 1", [$salonId]);

// روزی کاری و دور، بدون نوبت
$date = new DateTimeImmutable('today +45 days');
while (in_array((int) $date->format('N'), [5], true)) {   // جمعه
    $date = $date->modify('+1 day');
}
$ctx = ['salon' => $salonId, 'staff' => (int) $staff['id'], 'service' => (int) $service['id'], 'date' => $date->format('Y-m-d'), 'time' => '10:00'];

$cleanup = static function () use ($salonId): void {
    $ids = array_column(DB::select(
        'SELECT a.id FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE a.salon_id = ? AND c.phone LIKE ?',
        [$salonId, '+98' . substr(PHONE_PREFIX, 1) . '%']
    ), 'id');
    if ($ids !== []) {
        $in = implode(',', array_map('intval', $ids));
        DB::statement("DELETE FROM sms_messages WHERE appointment_id IN ($in)");
        DB::statement("DELETE FROM appointment_items WHERE appointment_id IN ($in)");
        DB::statement("DELETE FROM appointments WHERE id IN ($in)");
    }
    DB::statement('DELETE FROM customers WHERE salon_id = ? AND phone LIKE ?', [$salonId, '+98' . substr(PHONE_PREFIX, 1) . '%']);
};

/** @return array<int,array> */
$race = static function (array $children) use ($ctx): array {
    $at = microtime(true) + 1.5;
    $procs = [];
    foreach ($children as $i => [$mode, $phone, $delay]) {
        $cmd = sprintf(
            'php %s --child=%d --mode=%s --phone=%s --at=%.4f --ctx=%s',
            escapeshellarg(__FILE__), $i, $mode, escapeshellarg($phone), $at + $delay,
            escapeshellarg(base64_encode((string) json_encode($ctx)))
        );
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $procs[$i] = [$procs[$i], $pipes];
    }
    $results = [];
    foreach ($procs as $i => [$proc, $pipes]) {
        $line = trim((string) stream_get_contents($pipes[1]));
        $err = trim((string) stream_get_contents($pipes[2]));
        proc_close($proc);
        $results[$i] = json_decode($line, true) ?? ['result' => 'crash', 'message' => $err ?: $line];
    }

    return $results;
};

$failed = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$failed): void {
    echo ($ok ? '  ✓ ' : '  ✗ ') . $label . ($ok || $detail === '' ? '' : "  [$detail]") . "\n";
    $failed += $ok ? 0 : 1;
};
$count = static fn (array $rs, string $r): int => count(array_filter($rs, static fn ($x) => ($x['result'] ?? '') === $r));

$cleanup();
printf("سالن %d، کارمند %d، خدمت %d، %s ساعت %s — %d پردازش هم‌زمان\n\n", $salonId, $ctx['staff'], $ctx['service'], $ctx['date'], $ctx['time'], $n);

// ۰) تلاش دوباره در بیرونی‌ترین سطح (این‌جا تراکنشی باز نیست)
$lockError = static function (int $code): PDOException {
    $e = new PDOException('lock');
    $e->errorInfo = ['HY000', $code, 'lock'];

    return $e;
};
$calls = 0;
$r = DB::retryOnLockConflict(static function () use (&$calls, $lockError) {
    if (++$calls === 1) {
        throw $lockError(1213);
    }

    return 'ok';
});
$check('بن‌بست یک بار دوباره تلاش می‌شود', $r === 'ok' && $calls === 2, "calls=$calls");
$calls = 0;
try {
    DB::retryOnLockConflict(static function () use (&$calls, $lockError) {
        $calls++;
        throw $lockError(1213);
    });
    $outcome = 'no-throw';
} catch (App\Core\LockConflict) {
    $outcome = 'busy';
}
$check('بن‌بستِ پیاپی ← پیام «شلوغ است»', $outcome === 'busy' && $calls === 2, "$outcome calls=$calls");
$calls = 0;
try {
    DB::retryOnLockConflict(static function () use (&$calls, $lockError) {
        $calls++;
        throw $lockError(1205);
    });
    $outcome = 'no-throw';
} catch (App\Core\LockConflict) {
    $outcome = 'busy';
}
$check('پایان مهلت قفل دوباره تلاش نمی‌شود (انتظار دو برابر نمی‌شود)', $outcome === 'busy' && $calls === 1, "$outcome calls=$calls");
echo "\n";

// ۱) یک سانس، شماره‌های مختلف
$children = [];
for ($i = 0; $i < $n; $i++) {
    $children[] = ['book', PHONE_PREFIX . sprintf('%04d', $i), 0.0];
}
$rs = $race($children);
$check(
    'یک سانس، ' . $n . ' شمارهٔ مختلف: دقیقاً یکی موفق',
    $count($rs, 'ok') === 1 && $count($rs, 'rejected') === $n - 1,
    json_encode(array_count_values(array_column($rs, 'result')))
);
$check('هیچ خطای خام SQL به کاربر نرسید', $count($rs, 'raw_sql_error') === 0 && $count($rs, 'crash') === 0);
$slowest = max(array_column($rs, 'ms'));
$check("کندترین پاسخ زیر ۳ ثانیه ({$slowest}ms)", $slowest < 3000);

// ۲) دوبار زدن با یک شماره (سانسی دیگر)
$cleanup();
$ctx['time'] = '11:00';
$children = [];
for ($i = 0; $i < $n; $i++) {
    $children[] = ['book', PHONE_PREFIX . '9999', 0.0];
}
$rs = $race($children);
$phoneE164 = '+98' . substr(PHONE_PREFIX, 1) . '9999';
$customers = (int) DB::selectOne('SELECT COUNT(*) AS c FROM customers WHERE salon_id = ? AND phone = ?', [$salonId, $phoneE164])['c'];
$appts = (int) DB::selectOne(
    "SELECT COUNT(*) AS c FROM appointments a JOIN customers c ON c.id = a.customer_id
      WHERE a.salon_id = ? AND c.phone = ? AND a.status IN ('pending','confirmed')",
    [$salonId, $phoneE164]
)['c'];
$check("یک شماره، {$n} بار هم‌زمان: یک مشتری و یک نوبت", $customers === 1 && $appts === 1, "customers=$customers appointments=$appts");

// ۳) قفل سالن نگه داشته شده
$cleanup();
$ctx['time'] = '12:00';
$rs = $race([['hold', PHONE_PREFIX . '0000', 0.0], ['book', PHONE_PREFIX . '0001', 0.4]]);
$booker = $rs[1];
$check(
    'قفلِ گرفته‌شده: رزرو با پیام «شلوغ است» شکست می‌خورد',
    ($booker['result'] ?? '') === 'busy',
    json_encode($booker, JSON_UNESCAPED_UNICODE)
);
$check(
    'و حدود ۵ ثانیه، نه ۵۰ (' . ($booker['ms'] ?? '?') . 'ms)',
    isset($booker['ms']) && $booker['ms'] >= 4000 && $booker['ms'] < 8000
);

$cleanup();
echo "\n" . ($failed === 0 ? 'همه موفق' : "{$failed} مورد ناموفق") . "\n";
exit($failed === 0 ? 0 : 1);
