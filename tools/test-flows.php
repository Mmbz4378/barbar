<?php

declare(strict_types=1);

/**
 * آزمون جریان‌های اصلی روی دیتابیس واقعی — بدون هیچ وابستگی.
 *
 *   php tools/test-flows.php
 *
 * همه‌چیز داخل یک تراکنش بیرونی اجرا و در پایان برگردانده می‌شود؛
 * دیتابیس دست‌نخورده می‌ماند. (DB::transaction تودرتو را با SAVEPOINT
 * پشتیبانی می‌کند.) زمان روی شنبهٔ آینده ساعت ۸ صبح ثابت می‌شود تا
 * نتیجه به ساعت اجرا بستگی نداشته باشد.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Core\Migrator;
use App\Domain\Appointment\AppointmentRepository;
use App\Domain\Booking\AvailabilityCache;
use App\Domain\Booking\BookingPlanner;
use App\Domain\Booking\BookingService;
use App\Domain\Booking\SlotFinder;
use App\Domain\Catalog\CatalogTemplates;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Payment\PaymentRepository;
use App\Domain\Payment\ReportRepository;
use App\Domain\Queue\QueueService;
use App\Domain\Salon\DiscoveryRepository;
use App\Domain\Salon\HolidayRepository;
use App\Domain\Salon\SalonRepository;
use App\Domain\Salon\SalonSetupService;
use App\Domain\Staff\TimeOffRepository;
use App\Domain\Identity\UserRepository;
use App\Support\Audience;
use App\Support\IranMobile;
use App\Support\Now;

if (Config::get('app.env') === 'production') {
    fwrite(STDERR, "روی production اجرا نمی‌شود.\n");
    exit(1);
}

$passed = 0;
$failed = [];
$section = '';

function section(string $title): void
{
    global $section;
    $section = $title;
    echo "\n── {$title}\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $section;
    if ($ok) {
        $passed++;
        echo "  ✓ {$label}\n";
    } else {
        $failed[] = "[{$section}] {$label}" . ($detail !== '' ? " — {$detail}" : '');
        echo "  ✗ {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function throws(callable $fn): ?string
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return null;
}

function fresh(): void
{
    BookingService::flushCache();
    ServiceRepository::flushCache();
    SlotFinder::flushCache();
    App\Domain\Salon\SalonRepository::forget(0);
    // آزمون دیتابیس را مستقیم دست‌کاری می‌کند؛ «درخواست بعدی» کش مشترک را هم تازه می‌بیند.
    App\Core\Cache::flushAll();
}

/** @return array<string,int> نام خدمت ← شناسه */
function serviceIds(int $salonId): array
{
    return array_map('intval', array_column((new ServiceRepository())->all($salonId), 'id', 'name'));
}

function prices(string $audience, array $map): array
{
    $out = [];
    foreach (CatalogTemplates::for($audience) as $ci => $category) {
        foreach ($category['services'] as $si => $service) {
            if (isset($map[$service['name']])) {
                $out[] = ['template' => $ci . ':' . $si, 'price' => $map[$service['name']]];
            }
        }
    }

    return $out;
}

// ─── بدون دیتابیس ─────────────────────────────────────────────────────
section('ابزارها');
$parts = Migrator::splitStatements("CREATE TABLE a (x TEXT DEFAULT 'a;b'); -- c;d\nINSERT INTO a VALUES ('e\\'f;g'); /* h;i */ SELECT 1;");
check('تفکیک دستورهای SQL با «;» درون رشته و توضیح', count($parts) === 3, json_encode($parts, JSON_UNESCAPED_UNICODE));
check('عدد با ارقام فارسی و جداکننده', int_input('۱۲۰٬۰۰۰') === 120000);
check('عدد نامعتبر ← null', int_input('abc') === null);
check('واژهٔ کارکنان مردانه', Audience::term('staff', 'men') === 'آرایشگر');
check('واژهٔ کارکنان بانوان', Audience::term('staff', 'women') === 'متخصص');
check('روند پیش‌فرض بانوان اول خدمت', Audience::defaultBookingFlow('women') === 'service_first');

section('کش HTTP');
// در CLI نشستی لمس نمی‌شود، پس رفتارِ «مهمانِ بی‌نشست» قطعی است.
$send = static fn (App\Core\Response $r): App\Core\Response => $r->prepare();
$_SERVER['REQUEST_METHOD'] = 'GET';
$pub = $send((new App\Core\Response('<p>x</p>', 200, []))->publicCache(30, 60));
check('صفحهٔ عمومیِ مهمان کش عمومی و ETag می‌گیرد', str_starts_with($pub->headers['Cache-Control'] ?? '', 'public,') && ($pub->headers['Vary'] ?? '') === 'Cookie' && isset($pub->headers['ETag']));
$_SERVER['HTTP_IF_NONE_MATCH'] = (string) $pub->headers['ETag'];
$hit = $send((new App\Core\Response('<p>x</p>', 200, []))->publicCache(30, 60));
check('ETag برابر ← ۳۰۴ بدون بدنه', $hit->status === 304 && $hit->body === '');
$_SERVER['HTTP_IF_NONE_MATCH'] = substr((string) $pub->headers['ETag'], 0, -1) . '-gzip"';
check('ETagِ gzipشدهٔ آپاچی هم می‌خورد', $send((new App\Core\Response('<p>x</p>', 200, []))->publicCache())->status === 304);
unset($_SERVER['HTTP_IF_NONE_MATCH']);
$nonce = App\Core\Security::nonce();
$withNonce = $send((new App\Core\Response('<script nonce="' . $nonce . '"></script>', 200, []))->publicCache());
$noNonce = $send((new App\Core\Response('<script nonce=""></script>', 200, []))->publicCache());
check('nonce در ETag اثری ندارد', $withNonce->headers['ETag'] === $noNonce->headers['ETag']);
check('سربرگ صریح مسیر مقدم است', $send((new App\Core\Response('x', 200, ['Cache-Control' => 'no-store']))->publicCache())->headers['Cache-Control'] === 'no-store');
check('صفحهٔ بدون اعلام ← no-cache', ($send(new App\Core\Response('x', 200, []))->headers['Cache-Control'] ?? '') === 'no-cache');
check('خطا هرگز عمومی کش نمی‌شود', ($send((new App\Core\Response('x', 404, []))->publicCache())->headers['Cache-Control'] ?? '') === 'no-cache');
$_SERVER['REQUEST_METHOD'] = 'POST';
check('POST هرگز عمومی کش نمی‌شود', ($send((new App\Core\Response('x', 200, []))->publicCache())->headers['Cache-Control'] ?? '') === 'no-cache');
$_SERVER['REQUEST_METHOD'] = 'GET';

section('به‌روزرسان');
check('مقایسهٔ نسخه‌ها', App\Support\Version::compare('14.10.0', '14.9.3') > 0 && App\Support\Version::compare('v14.2.0', '14.2.0') === 0);
check('پیش‌انتشار از نسخهٔ نهایی قدیمی‌تر است', App\Support\Version::compare('15.0.0-beta.1', '15.0.0') < 0);
check('نسخهٔ نامعتبر رد می‌شود', !App\Support\Version::isValid('latest') && !App\Support\Version::isNewer('../../x'));
$unsafe = ['../etc/passwd', '/abs/path', 'a/../../b', 'C:/win', "a\0b", 'a\\b', './x', 'a//b'];
check('مسیرهای ناامن ZIP رد می‌شوند', array_filter($unsafe, [App\Domain\System\Updater::class, 'safeRelativePath']) === [], json_encode(array_values(array_filter($unsafe, [App\Domain\System\Updater::class, 'safeRelativePath']))));
check('مسیر عادی پذیرفته می‌شود', App\Domain\System\Updater::safeRelativePath('app/Core/DB.php') && App\Domain\System\Updater::safeRelativePath('نصب.md'));
if (function_exists('sodium_crypto_sign_keypair')) {
    $pair = sodium_crypto_sign_keypair();
    $sha = hash('sha256', 'package');
    $release = ['version' => '14.9.0', 'sha256' => $sha, 'signature' => base64_encode(sodium_crypto_sign_detached(App\Domain\System\ReleaseSource::signatureMessage('14.9.0', $sha), sodium_crypto_sign_secretkey($pair)))];
    $pub = base64_encode(sodium_crypto_sign_publickey($pair));
    check('امضای درست پذیرفته می‌شود', App\Domain\System\ReleaseSource::verifySignature($release, $pub));
    check('امضا برای نسخهٔ دیگر معتبر نیست', !App\Domain\System\ReleaseSource::verifySignature(['version' => '14.9.1'] + $release, $pub));
    check('امضا برای بستهٔ دیگر معتبر نیست', !App\Domain\System\ReleaseSource::verifySignature(['sha256' => hash('sha256', 'other')] + $release, $pub));
}
check('نشانی HTTP بیرونی رد می‌شود', throws(fn () => App\Domain\System\HttpFetcher::assertAllowedUrl('http://example.com/x.zip')) !== null);
check('نشانی HTTPS پذیرفته می‌شود', throws(fn () => App\Domain\System\HttpFetcher::assertAllowedUrl('https://github.com/x/y')) === null);

// ─── آماده‌سازی ───────────────────────────────────────────────────────
$pdo = DB::connection();
$pdo->beginTransaction();

try {
    // شنبهٔ آینده (شنبه = ۶ در PHP)، ساعت ۸
    $saturday = (new DateTimeImmutable('today'))->modify('next saturday')->setTime(8, 0);
    Now::freeze($saturday);
    $day = $saturday->modify('+2 days'); // دوشنبه
    $friday = $saturday->modify('next friday');

    $users = new UserRepository();
    $owner = $users->findOrCreate(IranMobile::parse('09129990001'), 'آزمون صاحب');
    $setup = new SalonSetupService();

    section('ساخت سالن');
    $menId = $setup->create(
        ['name' => 'آزمون مردانه', 'audience' => 'men', 'city' => 'تهران', 'address' => 'نشانی', 'phone' => '02100000000', 'seats' => 2],
        (int) $owner['id'],
        prices('men', ['کوتاهی مو' => 3500000, 'فرم و اصلاح ریش' => 1800000, 'رنگ مو' => 6000000]),
        'آزمون صاحب'
    );
    $men = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$menId]);
    check('سالن مردانه با روند اول زمان', $men['booking_flow'] === 'time_first' && $men['audience'] === 'men');
    check('ساعت کاری پیش‌فرض ساخته شد', (int) DB::selectOne('SELECT COUNT(*) c FROM working_hours WHERE salon_id = ? AND staff_id IS NULL', [$menId])['c'] === 7);
    check('صاحب سالن عضو با نقش owner', DB::selectOne("SELECT role FROM salon_user WHERE salon_id = ? AND user_id = ?", [$menId, $owner['id']])['role'] === 'owner');
    check('کمینهٔ قیمت برای «کشف» محاسبه شد', (int) $men['min_price'] === 1800000);
    $menStaffA = (int) DB::selectOne('SELECT id FROM staff WHERE salon_id = ?', [$menId])['id'];
    $menStaffB = (int) DB::insert('staff', ['salon_id' => $menId, 'name' => 'آرایشگر دوم', 'color' => '#C2410C']);
    $mS = serviceIds($menId);

    $womenId = $setup->create(
        ['name' => 'آزمون بانوان', 'audience' => 'women', 'city' => 'تهران', 'address' => 'نشانی', 'phone' => '02100000001', 'seats' => 3],
        (int) $owner['id'],
        prices('women', ['رنگ ریشه' => 15000000, 'مانیکور' => 4500000, 'کوتاهی مو' => 6000000, 'براشینگ' => 5000000, 'کراتین' => 45000000, 'میکاپ' => 0])
    );
    $women = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$womenId]);
    check('سالن بانوان با روند اول خدمت', $women['booking_flow'] === 'service_first');
    $wS = serviceIds($womenId);
    check('خدمت بی‌قیمت غیرفعال ساخته شد', (int) DB::selectOne('SELECT is_active FROM services WHERE id = ?', [$wS['میکاپ']])['is_active'] === 0);
    check('دسته‌ها از قالب ساخته شدند', (int) DB::selectOne('SELECT COUNT(*) c FROM service_categories WHERE salon_id = ?', [$womenId])['c'] >= 3);

    $colorist = (int) DB::insert('staff', ['salon_id' => $womenId, 'name' => 'رنگ‌کار', 'color' => '#BE185D', 'sort_order' => 1]);
    $nailTech = (int) DB::insert('staff', ['salon_id' => $womenId, 'name' => 'ناخن‌کار', 'color' => '#0369A1', 'sort_order' => 2]);
    $catalog = new ServiceRepository();
    foreach ([$wS['مانیکور']] as $sid) {
        $catalog->setOffered($womenId, $colorist, $sid, false);
    }
    foreach ([$wS['رنگ ریشه'], $wS['کوتاهی مو'], $wS['براشینگ'], $wS['کراتین']] as $sid) {
        $catalog->setOffered($womenId, $nailTech, $sid, false);
    }
    DB::update('salons', ['deposit_card_number' => '6037990000000000', 'deposit_hold_minutes' => 60, 'cancel_notice_minutes' => 180, 'min_notice_minutes' => 60], 'id = :id', ['id' => $womenId]);
    DB::update('services', ['deposit_amount' => 10000000], 'id = :id', ['id' => $wS['کراتین']]);
    fresh();

    // ─── مهارت‌ها و قیمت اختصاصی ─────────────────────────────────────
    section('مهارت کارکنان');
    $nailIds = array_map('intval', $catalog->staffFor($womenId, $wS['مانیکور']));
    check('مانیکور فقط با ناخن‌کار', $nailIds === [$nailTech], json_encode($nailIds));
    $planner = new BookingPlanner();
    check('رنگ ریشه + مانیکور نیاز به چند نفر دارد', $planner->needsMultipleStaff($womenId, [$wS['رنگ ریشه'], $wS['مانیکور']], true));
    check('کوتاهی + براشینگ با یک نفر', !$planner->needsMultipleStaff($womenId, [$wS['کوتاهی مو'], $wS['براشینگ']], true));
    $catalog->setOverride($menId, $menStaffA, $mS['کوتاهی مو'], 45, 4500000);
    fresh();
    $eff = $catalog->effective($menId, $menStaffA, $mS['کوتاهی مو']);
    check('قیمت و مدت اختصاصی آرایشگر', (int) $eff['price'] === 4500000 && (int) $eff['duration_minutes'] === 45, json_encode($eff));

    // ─── سانس‌ها ──────────────────────────────────────────────────────
    section('سانس‌های آزاد');
    $times = $planner->openTimes($menId, $day, true);
    check('دوشنبه سانس آزاد دارد', $times !== [], (string) count($times));
    check('اولین سانس از ساعت باز شدن', ($times[0] ?? '') === '09:00', $times[0] ?? '-');
    check('جمعه تعطیل است', $planner->openTimes($menId, $friday, true) === []);
    $sameDay = $planner->openTimes($womenId, $saturday, true);
    check('حداقل فاصله تا نوبت رعایت شد (ساعت ۸، فاصلهٔ ۶۰ دقیقه)', ($sameDay[0] ?? '99') >= '09:00');
    $closureDay = $day->modify('+1 day');
    (new TimeOffRepository())->add($menId, null, $closureDay->setTime(0, 0), $closureDay->setTime(23, 59), 'تعطیلی آزمون');
    fresh();
    check('تعطیلی کل سالن سانس‌ها را می‌بندد', $planner->openTimes($menId, $closureDay, true) === []);
    $holidayDay = $day->modify('+2 days');
    DB::insert('holidays', ['gregorian_date' => $holidayDay->format('Y-m-d'), 'jalali_label' => 'آزمون', 'is_official' => 0]);
    DB::update('salons', ['observe_official_holidays' => 1], 'id = :id', ['id' => $menId]);
    fresh();
    check('تعطیل رسمی برای سالنی که رعایت می‌کند بسته است', $planner->openTimes($menId, $holidayDay, true) === []);
    DB::update('salons', ['observe_official_holidays' => 0], 'id = :id', ['id' => $menId]);
    fresh();
    check('سالنی که رعایت نمی‌کند باز است', $planner->openTimes($menId, $holidayDay, true) !== []);
    check('فراتر از افق رزرو بسته است', !$planner->dateWithinHorizon($menId, $saturday->modify('+400 days'), true));

    // ─── رزرو مردانه ──────────────────────────────────────────────────
    section('رزرو آنلاین (مردانه)');
    $booking = new BookingService();
    $a1 = $booking->createBooking($menId, null, [$mS['کوتاهی مو']], $day, '10:00', '09351110001', 'مشتری یک', null, ['online' => true]);
    check('رزرو تأییدشده ساخته شد', $a1['status'] === 'confirmed' && $a1['group_token'] === null);
    $again = $booking->createBooking($menId, null, [$mS['کوتاهی مو']], $day, '10:00', '09351110001', 'مشتری یک', null, ['online' => true]);
    check('ثبت دوبارهٔ همان رزرو، نوبت تکراری نمی‌سازد', (int) $again['id'] === (int) $a1['id']);
    $a2 = $booking->createBooking($menId, null, [$mS['کوتاهی مو']], $day, '10:00', '09351110002', 'مشتری دو', null, ['online' => true]);
    check('رزرو هم‌زمان دوم به آرایشگر دیگر رفت', (int) $a2['staff_id'] !== (int) $a1['staff_id']);
    $err = throws(fn () => $booking->createBooking($menId, null, [$mS['کوتاهی مو']], $day, '10:00', '09351110003', 'مشتری سه', null, ['online' => true]));
    check('وقتی همه مشغول‌اند رزرو رد می‌شود', $err !== null, (string) $err);
    $err = throws(fn () => $booking->createBooking($menId, $menStaffA, [$mS['کوتاهی مو']], $day, '10:15', '09351110004', 'مشتری چهار', null, ['online' => true]));
    check('آرایشگر انتخاب‌شده در ساعت پر قابل رزرو نیست', $err !== null);
    $err = throws(fn () => $booking->createBooking($menId, null, [$mS['کوتاهی مو']], $friday, '10:00', '09351110005', null, null, ['online' => true]));
    check('رزرو روز تعطیل رد می‌شود', $err !== null);
    $err = throws(fn () => $booking->createBooking($menId, null, [], $day, '12:00', '09351110005', null, null, ['online' => true]));
    check('رزرو بدون خدمت رد می‌شود', $err !== null);
    $err = throws(fn () => $booking->createBooking($menId, null, [$wS['مانیکور']], $day, '12:00', '09351110005', null, null, ['online' => true]));
    check('خدمتِ سالن دیگر قابل رزرو نیست', $err !== null);
    $items = (new AppointmentRepository())->itemsFor($menId, (int) $a1['id']);
    $expectedPrice = (int) $a1['staff_id'] === $menStaffA ? 4500000 : 3500000;
    check('قیمت نوبت بر اساس آرایشگر واقعی', (int) ($items[0]['price'] ?? 0) === $expectedPrice);

    // ─── چندنفره ──────────────────────────────────────────────────────
    section('رزرو چندمتخصصی (بانوان)');
    $g = $booking->createBooking($womenId, null, [$wS['رنگ ریشه'], $wS['مانیکور']], $day, '10:00', '09351110010', 'ترانه', null, ['online' => true]);
    $group = (new AppointmentRepository())->group($g);
    check('دو نوبت با یک کد گروه', count($group) === 2 && $g['group_token'] !== null, (string) count($group));
    check('هر بخش با متخصص خودش', count($group) === 2 && (int) $group[0]['staff_id'] === $colorist && (int) $group[1]['staff_id'] === $nailTech);
    if (count($group) === 2) {
        $firstEnd = (new DateTimeImmutable($group[0]['scheduled_at']))->getTimestamp();
        check('بخش دوم پس از بخش اول', strtotime($group[1]['scheduled_at']) > $firstEnd);
    }
    $single = $booking->createBooking($womenId, null, [$wS['کوتاهی مو'], $wS['براشینگ']], $day, '15:00', '09351110011', 'نازنین', null, ['online' => true]);
    check('دو خدمتِ یک متخصص ← یک نوبت', $single['group_token'] === null && count((new AppointmentRepository())->itemsFor($womenId, (int) $single['id'])) === 2);

    // ─── بیعانه ───────────────────────────────────────────────────────
    section('بیعانه');
    $k = $booking->createBooking($womenId, null, [$wS['کراتین']], $day->modify('+3 days'), '10:00', '09351110020', 'کراتین', null, ['online' => true]);
    check('رزرو بیعانه‌دار در انتظار تأیید', $k['status'] === 'pending' && (int) $k['deposit_amount'] === 10000000 && $k['hold_expires_at'] !== null);
    $panel = $booking->createBooking($womenId, null, [$wS['کراتین']], $day->modify('+3 days'), '16:00', '09351110021', 'پنل', null, ['online' => false, 'created_by' => (int) $owner['id']]);
    check('رزرو از پنل بیعانه نمی‌خواهد', $panel['status'] === 'confirmed' && (int) $panel['created_by_user_id'] === (int) $owner['id']);
    $booking->confirmDeposit($womenId, (int) $k['id'], 'card_to_card', (int) $owner['id']);
    $k2 = (new AppointmentRepository())->find($womenId, (int) $k['id']);
    check('تأیید بیعانه ← تأییدشده و ثبت پرداخت', $k2['status'] === 'confirmed' && (new PaymentRepository())->depositFor($womenId, (int) $k['id']) !== null);
    $h = $booking->createBooking($womenId, null, [$wS['کراتین']], $day->modify('+1 day'), '10:00', '09351110022', 'منقضی', null, ['online' => true]);
    Now::freeze($saturday->modify('+2 hours'));
    $expired = $booking->expireHolds();
    $h2 = (new AppointmentRepository())->find($womenId, (int) $h['id']);
    check('مهلت بیعانه گذشت ← لغو خودکار', $expired >= 1 && $h2['status'] === 'cancelled' && $h2['cancelled_by'] === 'system');
    fresh();
    $retry = $booking->createBooking($womenId, null, [$wS['کراتین']], $day->modify('+1 day'), '10:00', '09351110023', 'بعدی', null, ['online' => true]);
    check('ساعت آزادشده دوباره قابل رزرو است', (int) $retry['staff_id'] === (int) $h['staff_id']);
    Now::freeze($saturday);

    // ─── لغو ──────────────────────────────────────────────────────────
    section('لغو توسط مشتری');
    check('لغو پیش از مهلت مجاز است', $booking->cancelByToken((string) $single['public_token']) === 'cancelled');
    $soon = $booking->createBooking($womenId, null, [$wS['براشینگ']], $saturday, '10:00', '09351110030', 'دیر', null, ['online' => true]);
    check('لغو در کمتر از ۳ ساعت مانده رد می‌شود', $booking->cancelByToken((string) $soon['public_token']) === 'too_late');
    check('لغو گروهی همهٔ بخش‌ها را لغو می‌کند', $booking->cancelByToken((string) $g['public_token']) === 'cancelled'
        && (int) DB::selectOne("SELECT COUNT(*) c FROM appointments WHERE group_token = ? AND status = 'cancelled'", [$g['group_token']])['c'] === 2);
    check('توکن نامعتبر', $booking->cancelByToken('nope') === 'not_found');

    // ─── صف حضوری و تسویه ─────────────────────────────────────────────
    section('صف حضوری و تسویه');
    Now::freeze(null);
    $queue = new QueueService();
    $walk = $queue->addWalkin($menId, 'حضوری', '09351110040', $menStaffB, [$mS['فرم و اصلاح ریش']]);
    $walkId = (int) ($walk['id'] ?? $walk['appointment']['id'] ?? 0);
    $walkStatus = (new AppointmentRepository())->find($menId, $walkId)['status'] ?? '-';
    check('آرایشگر آزاد ← نوبت حضوری مستقیم روی صندلی', $walkId > 0 && $walkStatus === 'in_chair', $walkStatus);
    $next = $queue->addWalkin($menId, 'نفر بعد', null, $menStaffB, [$mS['فرم و اصلاح ریش']]);
    check('آرایشگر مشغول ← نفر بعد در صف', $next['status'] === 'queued', (string) $next['status']);
    $err = throws(fn () => (new PaymentRepository())->record($menId, $walkId, 'cash', 1000, 0, null));
    check('تسویهٔ نوبت انجام‌نشده رد می‌شود', $err !== null);
    $queue->startService($menId, $walkId);
    check('شروع خدمت', (new AppointmentRepository())->find($menId, $walkId)['status'] === 'in_chair');
    $queue->completeService($menId, $walkId);
    check('پایان خدمت', (new AppointmentRepository())->find($menId, $walkId)['status'] === 'completed');
    $paymentId = (new PaymentRepository())->record($menId, $walkId, 'pos', 1600000, 100000, (int) $owner['id'], 200000);
    $again = (new PaymentRepository())->record($menId, $walkId, 'pos', 1600000, 100000, (int) $owner['id'], 200000);
    check('تسویه یک بار ثبت می‌شود', $paymentId === $again);
    $today = date('Y-m-d');
    $totals = (new ReportRepository())->totals($menId, $today, $today);
    check('گزارش روزانه فروش و انعام و تخفیف را نشان می‌دهد', $totals['revenue'] === 1600000 && $totals['tips'] === 100000 && $totals['discounts'] === 200000, json_encode($totals));
    $flow = (new ReportRepository())->flow($menId, $today, $today);
    check('گزارش جریان نوبت حضوری را می‌شمارد', $flow['walkins'] >= 1 && $flow['completed'] >= 1, json_encode($flow));
    $err = throws(fn () => (new PaymentRepository())->record($menId, $walkId, 'bitcoin', 1, 0, null));
    check('روش پرداخت نامعتبر رد می‌شود', $err !== null);

    section('بازهٔ نصب خودکار');
    $updater = new App\Domain\System\Updater();
    $updater->setWindow(23, 2);
    check('بازهٔ شبانه که از نیمه‌شب می‌گذرد', $updater->inWindow(23) && $updater->inWindow(1) && !$updater->inWindow(2) && !$updater->inWindow(12));
    $updater->setWindow(3, 5);
    check('بازهٔ عادی', $updater->inWindow(3) && $updater->inWindow(4) && !$updater->inWindow(5));
    check('بازهٔ نامعتبر رد می‌شود', throws(fn () => $updater->setWindow(4, 4)) !== null);
    App\Domain\System\SystemSettings::flush();

    // ─── جداسازی سالن‌ها ──────────────────────────────────────────────
    section('جداسازی سالن‌ها');
    check('نوبت سالن دیگر دیده نمی‌شود', (new AppointmentRepository())->find($womenId, (int) $a1['id']) === null);
    check('خدمت سالن دیگر دیده نمی‌شود', $catalog->find($menId, $wS['کراتین']) === null);
    $err = throws(fn () => (new PaymentRepository())->record($womenId, $walkId, 'cash', 1000, 0, null));
    check('تسویهٔ نوبت سالن دیگر رد می‌شود', $err !== null);
    check('مرخصی برای آرایشگر سالن دیگر رد می‌شود', (new TimeOffRepository())->add($menId, $colorist, $day->setTime(9, 0), $day->setTime(10, 0), '') !== null);
    $err = throws(fn () => $booking->confirmDeposit($menId, (int) $h['id'], 'cash', null));
    check('تأیید بیعانهٔ سالن دیگر رد می‌شود', $err !== null);

    // ─── کش و باطل‌شدن ────────────────────────────────────────────────
    // «درخواست بعدی» = پردازش تازه: کش‌های ایستا و لایهٔ محلی خالی، ولی APCu
    // مشترک می‌ماند. پس این آزمون‌ها با APCu (php -d apc.enable_cli=1) نشان
    // می‌دهند که نوشتن از مسیر واقعی، کش مشترک را هم باطل می‌کند.
    section('کش و باطل‌شدن');
    $nextRequest = static function (): void {
        BookingService::flushCache();
        ServiceRepository::flushCache();
        SlotFinder::flushCache();
    };
    $salons = new SalonRepository();
    $disc = new DiscoveryRepository();

    $sid = $mS['کوتاهی مو'];
    $catalog->all($menId, true);
    $nextRequest();
    $catalog->update($menId, $sid, ['name' => 'کوتاهی تازه']);
    $nextRequest();
    $names = array_column($catalog->all($menId, true), 'name', 'id');
    check('تغییر خدمت بی‌درنگ در منوی عمومی دیده می‌شود', ($names[$sid] ?? '') === 'کوتاهی تازه', (string) ($names[$sid] ?? '-'));

    $salons->find($menId);
    DB::update('salons', ['name' => 'نام تازهٔ آزمون'], 'id = :id', ['id' => $menId]);
    SalonRepository::forget($menId);
    $nextRequest();
    check('تنظیمات سالن پس از ذخیره بی‌درنگ تازه است', ($salons->find($menId)['name'] ?? '') === 'نام تازهٔ آزمون');

    $h2 = $day->modify('+3 days');
    DB::update('salons', ['observe_official_holidays' => 1], 'id = :id', ['id' => $menId]);
    SalonRepository::forget($menId);
    $nextRequest();
    $openBefore = $planner->openTimes($menId, $h2, true) !== [];
    (new HolidayRepository())->add($h2->format('Y-m-d'), 'آزمون کش');
    $nextRequest();
    check('تعطیل تازه بی‌درنگ سانس‌ها را می‌بندد', $openBefore && $planner->openTimes($menId, $h2, true) === []);

    $slugMen = (string) $salons->find($menId)['slug'];
    DB::update('salons', ['publication_status' => 'published', 'is_active' => 1, 'city' => 'تهران', 'address' => 'نشانی آزمون', 'phone' => '02100000000'], 'id = :id', ['id' => $menId]);
    SalonRepository::forget($menId);
    $nextRequest();
    $visible = $disc->find($slugMen) !== null;
    DB::update('salons', ['publication_status' => 'draft'], 'id = :id', ['id' => $menId]);
    SalonRepository::forget($menId);
    $nextRequest();
    $hidden = $disc->find($slugMen) === null;
    check('انتشار و لغو انتشار بی‌درنگ در کشف دیده می‌شود', $visible && $hidden, json_encode([$visible, $hidden]));

    $probe = 'rsh-cache-probe';
    $missBefore = $salons->findActiveBySlug($probe) === null;
    DB::update('salons', ['slug' => $probe, 'is_active' => 1], 'id = :id', ['id' => $menId]);
    SalonRepository::forget($menId);
    $nextRequest();
    check('«نبودِ» اسلاگ کش نمی‌شود؛ سالن تازه بی‌درنگ پیدا می‌شود', $missBefore && (int) ($salons->findActiveBySlug($probe)['id'] ?? 0) === $menId);

    // کش نمایشِ وقت‌های آزاد: یک محاسبه در هر دقیقه، باطل با هر نوشتن روی نوبت‌ها
    $calls = 0;
    $compute = static function () use (&$calls): array {
        $calls++;

        return [$calls];
    };
    $first = AvailabilityCache::remember($menId, 'probe', $compute);
    $again = AvailabilityCache::remember($menId, 'probe', $compute);
    check('وقت‌های آزاد در همان دقیقه دوباره حساب نمی‌شوند', $first === $again && $calls === 1, (string) $calls);
    (new AppointmentRepository())->update($menId, (int) $a1['id'], ['customer_note' => 'آزمون کش']);
    AvailabilityCache::remember($menId, 'probe', $compute);
    check('هر نوشتن روی نوبت‌ها کش نمایش را باطل می‌کند', $calls === 2, (string) $calls);
    (new TimeOffRepository())->add($menId, null, $day->modify('+5 days')->setTime(10, 0), $day->modify('+5 days')->setTime(11, 0), 'آزمون کش');
    AvailabilityCache::remember($menId, 'probe', $compute);
    check('مرخصی تازه کش نمایش را باطل می‌کند', $calls === 3, (string) $calls);
    AvailabilityCache::remember($womenId, 'probe', $compute);
    AvailabilityCache::remember($menId, 'probe', $compute);
    check('کش هر سالن جداست', $calls === 4, (string) $calls);
    $was = Now::get();
    Now::freeze($was->modify('+61 seconds'));
    AvailabilityCache::remember($menId, 'probe', $compute);
    Now::freeze($was);
    check('پس از یک دقیقه دوباره حساب می‌شود (سقف کهنگی)', $calls === 5, (string) $calls);
} catch (Throwable $e) {
    $failed[] = 'خطای پیش‌بینی‌نشده: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    echo "\n  ✗ " . end($failed) . "\n" . $e->getTraceAsString() . "\n";
} finally {
    Now::freeze(null);
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo "\n" . str_repeat('─', 50) . "\n";
echo "{$passed} مورد موفق، " . count($failed) . " مورد ناموفق\n";
foreach ($failed as $f) {
    echo "  • {$f}\n";
}
exit($failed === [] ? 0 : 1);
