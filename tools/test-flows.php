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
use App\Core\Deferred;
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
use App\Domain\Identity\OtpService;
use App\Domain\Identity\PasswordAuth;
use App\Domain\Identity\UserRepository;
use App\Domain\Messaging\SmsBreaker;
use App\Domain\Messaging\SmsGatewayInterface;
use App\Domain\Messaging\SmsManager;
use App\Domain\Messaging\SmsNotifier;
use App\Domain\Messaging\SmsOutbox;
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
    // اسلاگ به سالن دیگری رسید: نگاشت کهنه خودش کنار گذاشته می‌شود
    $salons->findActiveBySlug($probe);
    DB::update('salons', ['slug' => 'rsh-renamed-men'], 'id = :id', ['id' => $menId]);
    SalonRepository::forget($menId);
    DB::update('salons', ['slug' => $probe, 'is_active' => 1], 'id = :id', ['id' => $womenId]);
    SalonRepository::forget($womenId);
    $nextRequest();
    check('اسلاگِ واگذارشده به سالن تازه‌اش می‌رسد، نه کهنه', (int) ($salons->findActiveBySlug($probe)['id'] ?? 0) === $womenId && (int) ($salons->findActiveBySlug('rsh-renamed-men')['id'] ?? 0) === $menId);

    // دیتابیس زیر فشار: نسخهٔ کهنه به‌جای خطا، فقط برای Overloaded
    App\Core\Cache::remember('t:stale', 1, static fn () => 'نسخهٔ اول');
    sleep(2);
    $stale = App\Core\Cache::remember('t:stale', 1, static function (): string {
        throw new App\Core\Overloaded('آزمون');
    });
    check('دیتابیسِ زیر فشار: نسخهٔ کمی کهنه داده می‌شود', $stale === 'نسخهٔ اول');
    $other = null;
    try {
        App\Core\Cache::remember('t:stale', 1, static function (): string {
            throw new RuntimeException('خطای دیگر');
        });
    } catch (RuntimeException $e) {
        $other = $e->getMessage();
    }
    check('خطای دیگر پنهان نمی‌شود', $other === 'خطای دیگر');
    check('پس از برگشت دیتابیس تازه می‌شود', App\Core\Cache::remember('t:stale', 60, static fn () => 'نسخهٔ دوم') === 'نسخهٔ دوم');
    $noCopy = null;
    try {
        App\Core\Cache::remember('t:never-cached', 60, static function (): string {
            throw new App\Core\Overloaded('آزمون');
        });
    } catch (App\Core\Overloaded) {
        $noCopy = 'busy';
    }
    check('بی‌نسخه: همان صفحهٔ «شلوغ است»', $noCopy === 'busy');

    // باطل‌شدن کش پس از commit، نه پیش از آن
    $versionBefore = AvailabilityCache::version($menId);
    $insideTx = null;
    DB::transaction(static function () use ($menId, &$insideTx): void {
        AvailabilityCache::bump($menId);
        $insideTx = AvailabilityCache::version($menId);
    });
    check('نسخهٔ کش تا commit عوض نمی‌شود (خوانندهٔ هم‌زمان دادهٔ قدیمی را زیر نسخهٔ تازه کش نمی‌کند)', $insideTx === $versionBefore && AvailabilityCache::version($menId) !== $versionBefore);
    $versionBefore = AvailabilityCache::version($menId);
    try {
        DB::transaction(static function () use ($menId): void {
            AvailabilityCache::bump($menId);
            throw new RuntimeException('برگشت');
        });
    } catch (RuntimeException) {
    }
    check('تراکنشِ برگشته کش را باطل نمی‌کند', AvailabilityCache::version($menId) === $versionBefore);
    $versionBefore = AvailabilityCache::version($menId);
    DB::delete('payments', 'appointment_id = ? AND kind = ?', [(int) $a1['id'], 'deposit']);
    (new PaymentRepository())->recordDeposit($menId, (int) $a1['id'], 'cash', 1000, null);
    check('پرداخت هم صفحهٔ «امروز» را تازه می‌کند (درآمد، در انتظار تسویه)', AvailabilityCache::version($menId) !== $versionBefore);

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

    // ─── تلاش دوباره روی قفل (سطح درونی) ─────────────────────────────
    // آزمون داخل تراکنش است: سطح درونی نباید خودش تلاش کند — بن‌بست کل تراکنش را
    // برگردانده و فقط بیرونی‌ترین سطح می‌تواند از نو شروع کند (tests/load/booking-race.php).
    section('قفل و هم‌زمانی');
    $deadlock = new PDOException('deadlock');
    $deadlock->errorInfo = ['40001', 1213, 'Deadlock'];
    $seen = null;
    try {
        DB::retryOnLockConflict(static function () use ($deadlock) {
            throw $deadlock;
        });
    } catch (Throwable $e) {
        $seen = $e;
    }
    check('بن‌بست در سطح درونی به بیرون سپرده می‌شود', $seen === $deadlock);
    $other = new PDOException('other');
    $other->errorInfo = ['42S02', 1146, 'missing table'];
    $seen = null;
    try {
        DB::retryOnLockConflict(static function () use ($other) {
            throw $other;
        });
    } catch (Throwable $e) {
        $seen = $e;
    }
    check('خطای غیرقفلی دست‌نخورده بالا می‌رود', $seen === $other);
    check('سقف انتظار قفل روی اتصال اعمال شده', (int) DB::selectOne('SELECT @@SESSION.innodb_lock_wait_timeout AS w')['w'] === (int) Config::get('database.lock_wait_timeout', 5));
    $pdoError = static function (string $message, int $driverCode = 0, int|string $code = 0): PDOException {
        $e = new PDOException($message, is_int($code) ? $code : 0);
        if ($driverCode !== 0) {
            $e->errorInfo = ['HY000', $driverCode, $message];
        }

        return $e;
    };
    check('سقف اتصال حساب cPanel ← «شلوغ است»', App\Core\Overloaded::isOverload($pdoError('max_user_connections', 1203)));
    check('خطای اتصال با کد در متن ← «شلوغ است»', App\Core\Overloaded::isOverload($pdoError('SQLSTATE[HY000] [1040] Too many connections')));
    check('اتصال قطع‌شده وسط کار ← «شلوغ است»', App\Core\Overloaded::isOverload($pdoError('gone away', 2006)));
    check('جدول ناموجود «شلوغ است» نیست', !App\Core\Overloaded::isOverload($pdoError('missing table', 1146)));
    check('بن‌بست «شلوغ است» نیست (تلاش دوباره دارد)', !App\Core\Overloaded::isOverload($pdoError('deadlock', 1213)));

    // ─── محدودیت نرخ ──────────────────────────────────────────────────
    section('محدودیت نرخ');
    $bucket = 'test-' . bin2hex(random_bytes(3));
    $allowed = 0;
    for ($i = 0; $i < 5; $i++) {
        $allowed += App\Core\RateLimiter::allow($bucket, 3, 60) ? 1 : 0;
    }
    if (App\Core\Cache::shared()) {
        check('با APCu: پس از سقف پس زده می‌شود', $allowed === 3, (string) $allowed);
        check('هر سطل جداست', App\Core\RateLimiter::allow($bucket . '-other', 3, 60));
        $_SERVER['REMOTE_ADDR'] = '10.9.8.' . random_int(1, 250);
        for ($i = 0; $i < 60; $i++) {
            App\Core\RateLimiter::allow('otp-ip:' . $_SERVER['REMOTE_ADDR'], 60, 60);
        }
        $burst = (new OtpService())->request(IranMobile::parse('09127770002'));
        check('سیلِ درخواست کد از یک شبکه پیش از دیتابیس پس زده می‌شود', !$burst['ok'] && ($burst['retry_after'] ?? 0) === 60, (string) ($burst['error'] ?? ''));
        unset($_SERVER['REMOTE_ADDR']);
    } else {
        check('بدون APCu: محدودیت حافظه‌ای کاری نمی‌کند (نوشتن دیتابیسی اضافه نیست)', $allowed === 5);
    }
    check('۴۲۹ سبک با Retry-After و بدون کش', App\Core\RateLimiter::tooMany(30)->status === 429 && App\Core\RateLimiter::tooMany(30)->headers['Retry-After'] === '30' && App\Core\RateLimiter::tooMany()->headers['Cache-Control'] === 'no-store');
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
    check('بدون پروکسیِ تعریف‌شده، سرآیند جعلی IP را عوض نمی‌کند', OtpService::clientIp() === '10.0.0.1');
    unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
    check('سقف IPِ کد ورود برای CGNAT واقع‌بینانه است', (int) Config::get('reshen.sms.otp_hourly_limit_ip') >= 100 && (int) Config::get('reshen.sms.otp_hourly_limit_phone') === 5);

    // ─── صندوق خروجی پیامک ────────────────────────────────────────────
    section('صندوق خروجی پیامک');
    $gw = new class implements SmsGatewayInterface {
        /** @var string[] */
        public array $sent = [];
        public bool $fail = false;

        public function send(string $e164Phone, string $message): array
        {
            return $this->go($e164Phone);
        }

        public function sendPattern(string $e164Phone, string $patternId, array $args): array
        {
            return $this->go($e164Phone);
        }

        public function name(): string
        {
            return 'fake';
        }

        private function go(string $phone): array
        {
            if ($this->fail) {
                return ['ok' => false, 'ref' => null, 'error' => 'اپراتور آزمایشی از کار افتاده'];
            }
            $this->sent[] = $phone;

            return ['ok' => true, 'ref' => 'r' . count($this->sent), 'error' => null];
        }
    };
    SmsManager::fake($gw);
    SmsBreaker::reset();
    DB::delete('sms_messages', 'appointment_id = ?', [(int) $a1['id']]);
    $notifier = new SmsNotifier();
    $vars = ['name' => 'آزمون', 'salon' => 'سالن', 'date' => 'امروز', 'time' => '۱۰:۰۰'];
    $smsRow = static fn (int $id): array => DB::selectOne('SELECT * FROM sms_messages WHERE id = ?', [$id]) ?? [];
    $lastSms = static fn (): array => DB::selectOne('SELECT * FROM sms_messages WHERE appointment_id = ? ORDER BY id DESC LIMIT 1', [(int) $a1['id']]) ?? [];

    Deferred::enable();
    $notifier->notify($menId, $a1, 'booking_confirmed', $vars, true);
    $m = $lastSms();
    check('پیامک تا پس از پاسخ در صف می‌ماند', ($m['status'] ?? '') === 'queued' && Deferred::pending() === 1 && $gw->sent === []);
    check('همان اعلانِ در صف دوباره صف نمی‌شود', $notifier->alreadySent((int) $a1['id'], 'booking_confirmed'));
    Deferred::run();
    $m = $smsRow((int) $m['id']);
    check('پس از پاسخ فرستاده می‌شود', ($m['status'] ?? '') === 'sent' && count($gw->sent) === 1 && (int) $m['attempts'] === 1);
    check('یک پیامک دو بار فرستاده نمی‌شود', SmsOutbox::deliver([(int) $m['id']]) === 0 && count($gw->sent) === 1);

    $foreign = (int) DB::insert('sms_messages', ['salon_id' => $menId, 'appointment_id' => null, 'to_phone' => '+989120000000', 'template_code' => 'booking_confirmed', 'body' => 'x', 'args_json' => '[]', 'is_critical' => 1, 'status' => 'sending', 'claim_token' => 'otherprocess0001', 'claimed_at' => date('Y-m-d H:i:s')]);
    check('پیامکی که پردازش دیگری گرفته دست نمی‌خورد', SmsOutbox::deliver([$foreign]) === 0 && count($gw->sent) === 1);

    $rowsBefore = (int) DB::selectOne('SELECT COUNT(*) AS c FROM sms_messages')['c'];
    try {
        DB::transaction(static function () use ($notifier, $menId, $a1, $vars): void {
            $notifier->notify($menId, $a1, 'booking_cancelled', $vars, true);
            throw new RuntimeException('برگشت تراکنش');
        });
    } catch (RuntimeException) {
    }
    Deferred::run();
    check('پیامکِ تراکنشِ برگشته فرستاده نمی‌شود', count($gw->sent) === 1 && (int) DB::selectOne('SELECT COUNT(*) AS c FROM sms_messages')['c'] === $rowsBefore);
    Deferred::enable(false);

    $gw->fail = true;
    $notifier->notify($menId, $a1, 'booking_cancelled', $vars, true);
    $f = $lastSms();
    check('خطای اپراتور ← تلاش دوباره با فاصله', ($f['status'] ?? '') === 'failed' && (int) $f['attempts'] === 1 && $f['next_attempt_at'] !== null);
    SmsOutbox::drain();
    check('پیش از سررسید دوباره تلاش نمی‌شود', (int) $smsRow((int) $f['id'])['attempts'] === 1);
    foreach ([2, 3] as $attempt) {
        DB::update('sms_messages', ['next_attempt_at' => date('Y-m-d H:i:s', time() - 5)], 'id = :id', ['id' => $f['id']]);
        SmsOutbox::drain();
    }
    $f = $smsRow((int) $f['id']);
    check('پس از ۳ تلاش نهایی می‌ماند', ($f['status'] ?? '') === 'failed' && (int) $f['attempts'] === 3 && $f['next_attempt_at'] === null, json_encode([$f['status'] ?? '', $f['attempts'] ?? '']));
    $gw->fail = false;
    SmsOutbox::drain();
    check('خطای نهایی دیگر تلاش نمی‌شود', ($smsRow((int) $f['id'])['status'] ?? '') === 'failed');

    $stuck = (int) DB::insert('sms_messages', ['salon_id' => $menId, 'appointment_id' => null, 'to_phone' => '+989120000000', 'template_code' => 'booking_confirmed', 'body' => 'x', 'args_json' => '[]', 'is_critical' => 1, 'status' => 'sending', 'claim_token' => 'deadprocess00001', 'claimed_at' => date('Y-m-d H:i:s', time() - 700)]);
    $drained = SmsOutbox::drain();
    check('پیامکِ پردازشِ مُرده دوباره فرستاده می‌شود', ($smsRow($stuck)['status'] ?? '') === 'sent' && $drained['requeued'] >= 1);

    // دو پردازش دیگر همین حالا مشغول فرستادن‌اند: پیامک تازه برای cron می‌ماند
    App\Core\Cache::add('sms:slot:0', 1, 30);
    App\Core\Cache::add('sms:slot:1', 1, 30);
    $sentBefore = count($gw->sent);
    $notifier->notify($menId, $a1, 'booking_confirmed', $vars, true);
    $waiting = $lastSms();
    check('سقفِ فرستادنِ هم‌زمان: پیامک اضافه برای cron می‌ماند', ($waiting['status'] ?? '') === 'queued' && count($gw->sent) === $sentBefore);
    App\Core\Cache::forget('sms:slot:0');
    App\Core\Cache::forget('sms:slot:1');
    SmsOutbox::drain();
    check('cron همان پیامک را می‌فرستد', ($smsRow((int) $waiting['id'])['status'] ?? '') === 'sent');

    SmsBreaker::reset();
    for ($i = 0; $i < 5; $i++) {
        SmsBreaker::record(false, 0.1);
    }
    check('خطای سریع مدار را باز نمی‌کند', !SmsBreaker::isOpen());
    for ($i = 0; $i < 3; $i++) {
        SmsBreaker::record(false, 5.0);
    }
    check('چند خطای کُند مدار را باز می‌کند', SmsBreaker::isOpen());
    $sentBefore = count($gw->sent);
    $notifier->notify($menId, $a1, 'booking_confirmed', $vars, true);
    check('مدارِ باز: پیامک برای cron در صف می‌ماند', ($lastSms()['status'] ?? '') === 'queued' && count($gw->sent) === $sentBefore);
    $t0 = microtime(true);
    $otp = (new OtpService())->request(IranMobile::parse('09127770001'));
    check('مدارِ باز: OTP بی‌درنگ پیام روشن می‌دهد', !$otp['ok'] && (microtime(true) - $t0) < 1.0 && count($gw->sent) === $sentBefore, (string) ($otp['error'] ?? ''));
    SmsBreaker::reset();
    SmsManager::fake(null);

    // ─── حساب‌ها و ورود با رمز (نسخهٔ ۱۵) ─────────────────────────────
    section('حساب‌ها و ورود با رمز');
    $pa = PasswordAuth::class;
    check('رمز کوتاه رد می‌شود', $pa::policyError('abc12', null, null, 8) !== null);
    check('رمز رایج رد می‌شود', $pa::policyError('12345678', null, null, 8) !== null && $pa::policyError('Password123', null, null, 8) !== null);
    check('رمز برابر موبایل رد می‌شود', $pa::policyError('09121112233', '+989121112233', null, 8) !== null);
    check('رمز برابر نام کاربری رد می‌شود', $pa::policyError('manager.ali', null, 'manager.ali', 8) !== null);
    check('رمز فقط‌عددی کوتاه رد می‌شود', $pa::policyError('48151623', null, null, 8) !== null);
    check('رمز خوب پذیرفته می‌شود', $pa::policyError('Sh4rp-Blade', null, null, 8) === null);
    check('نام کاربری عادی‌سازی می‌شود', $pa::normalizeUsername(' Ali.Barber۱ ') === 'ali.barber1');
    check('نام کاربری نامعتبر رد می‌شود', $pa::usernameError('1abc') !== null && $pa::usernameError('ab') !== null && $pa::usernameError('ali.barber1') === null);

    $pwUser = (int) DB::insert('users', ['phone' => '+989127770100', 'name' => 'آزمون رمز', 'username' => 'pwtest']);
    $pa::setPassword($pwUser, 'Good-pass-123');
    $pwAuth = new PasswordAuth();
    check('ورود با نام کاربری، بی‌حساسیت به حروف', $pwAuth->attempt('PWTest', 'Good-pass-123')['ok']);
    check('ورود با موبایل و ارقام فارسی', $pwAuth->attempt('۰۹۱۲۷۷۷۰۱۰۰', 'Good-pass-123')['ok']);
    $badPw = $pwAuth->attempt('pwtest', 'wrong-pass');
    $ghostPw = $pwAuth->attempt('nobody-here', 'wrong-pass');
    check('پیام خطا برای کاربر ناموجود و رمز غلط یکی است', !$badPw['ok'] && $badPw['error'] === $ghostPw['error']);
    for ($i = 0; $i < 6; $i++) {
        $pwAuth->attempt('pwtest', 'wrong-' . $i);
    }
    $lockedPw = $pwAuth->attempt('pwtest', 'Good-pass-123');
    check('پس از چند تلاش ناموفق، رمز درست هم تا پایان قفل رد می‌شود', !$lockedPw['ok'] && str_contains((string) $lockedPw['error'], 'دقیقه'), (string) $lockedPw['error']);
    DB::update('users', ['locked_until' => null, 'failed_logins' => 0], 'id = :id', ['id' => $pwUser]);
    $v1 = (int) DB::selectOne('SELECT auth_version FROM users WHERE id = ?', [$pwUser])['auth_version'];
    $pa::setPassword($pwUser, 'Newer-pass-456', true);
    $pwRow = DB::selectOne('SELECT auth_version, password_hash, must_change_password FROM users WHERE id = ?', [$pwUser]);
    check('رمز تازه نسخهٔ نشست را بالا می‌برد (نشست‌های دیگر بسته می‌شوند)', (int) $pwRow['auth_version'] === $v1 + 1);
    check('رمز هش می‌شود، نه متن ساده', str_starts_with((string) $pwRow['password_hash'], '$') && !str_contains((string) $pwRow['password_hash'], 'Newer'));
    check('رمز موقتِ مدیر «باید عوض شود» می‌خورد', (int) $pwRow['must_change_password'] === 1);
    DB::update('users', ['is_active' => 0], 'id = :id', ['id' => $pwUser]);
    $blockedPw = $pwAuth->attempt('pwtest', 'Newer-pass-456');
    check('حساب مسدود با رمز درست هم وارد نمی‌شود', !$blockedPw['ok'] && str_contains((string) $blockedPw['error'], 'غیرفعال'));
    DB::update('users', ['is_active' => 1], 'id = :id', ['id' => $pwUser]);
    check('کاربرِ بی‌سالن (مثل مشتری) دسترسی پنل ندارد', !UserRepository::hasPanelAccess($pwUser));
    DB::insert('salon_user', ['salon_id' => $menId, 'user_id' => $pwUser, 'role' => 'reception']);
    check('با عضویت فعال، دسترسی پنل دارد', UserRepository::hasPanelAccess($pwUser));
    check('ثبت‌نام سالن به‌طور پیش‌فرض بسته است', App\Domain\System\SiteSettings::registrationMode() === 'closed');
    $pwEvents = (int) DB::selectOne('SELECT COUNT(*) AS c FROM login_events WHERE user_id = ?', [$pwUser])['c'];
    check('همهٔ تلاش‌های ورود ثبت می‌شوند', $pwEvents >= 10, (string) $pwEvents);

    section('ساخت حساب از پنل مدیر');
    [$accErr] = App\Domain\Identity\AccountService::validateNew(['name' => 'تکراری', 'phone' => '09127770100', 'password' => 'Fine-pass-123']);
    check('موبایلِ دارای حساب برای حساب تازه پذیرفته نمی‌شود', isset($accErr['phone']));
    [$accErr] = App\Domain\Identity\AccountService::validateNew(['o_name' => 'آزمون', 'o_phone' => '09127770200', 'o_username' => 'pwtest', 'o_password' => '123'], 'o_');
    check('نام کاربری تکراری و رمز ضعیف با پیشوند فیلد گزارش می‌شوند', isset($accErr['o_username'], $accErr['o_password']));
    [$accErr, $accData] = App\Domain\Identity\AccountService::validateNew(['name' => 'آزمون', 'phone' => '۰۹۱۲۷۷۷۰۲۰۰', 'generate' => '1']);
    check('رمز تصادفی ساخته می‌شود و از قواعد رمز می‌گذرد', $accErr === [] && strlen($accData['password']) === 12 && PasswordAuth::policyError($accData['password'], $accData['phone'], null, 8) === null);
    $accId = App\Domain\Identity\AccountService::create($accData, true);
    $accRow = DB::selectOne('SELECT password_hash, must_change_password FROM users WHERE id = ?', [$accId]);
    check('حساب تازه با رمز هش‌شده و «باید عوض شود» ساخته می‌شود', password_verify($accData['password'], (string) $accRow['password_hash']) && (int) $accRow['must_change_password'] === 1);
    $ownersBefore = App\Domain\Identity\AccountService::activeOwnerCount($menId);
    DB::insert('salon_user', ['salon_id' => $menId, 'user_id' => $accId, 'role' => 'owner']);
    check('شمارش صاحبان فعال سالن', App\Domain\Identity\AccountService::activeOwnerCount($menId) === $ownersBefore + 1);
    DB::update('users', ['is_active' => 0], 'id = :id', ['id' => $accId]);
    check('صاحبِ مسدود در شمارش صاحبان فعال نیست', App\Domain\Identity\AccountService::activeOwnerCount($menId) === $ownersBefore);

    section('تنظیمات سایت و محتوا');
    $ss = App\Domain\System\SiteSettings::class;
    check('کد تأیید گوگل از تگ meta کامل برداشته می‌شود', $ss::parseVerification('<meta name="google-site-verification" content="AbC_123-xyz987" />') === 'AbC_123-xyz987');
    check('کد تأیید نامعتبر پذیرفته نمی‌شود', $ss::parseVerification('<script>alert(1)</script>') === '');
    $enamadParsed = $ss::parseEnamad('<a href="https://trustseal.enamad.ir/?id=123456&Code=AbCd1234Ef"><img src="x" onerror="alert(1)"></a>');
    check('از کد اینماد فقط شناسه و کد برداشته می‌شود', $enamadParsed === ['id' => '123456', 'code' => 'AbCd1234Ef']);
    check('متن بی‌ربط کد اینماد نیست', $ss::parseEnamad('سلام') === null);
    check('نشانی‌های خطرناک رد می‌شوند', $ss::safeUrl('javascript:alert(1)') === null && $ss::safeUrl('//evil.com') === null && $ss::safeUrl('data:text/html,x') === null);
    check('نشانی http(s) و مسیر داخلی پذیرفته می‌شوند', $ss::safeUrl('https://example.ir/x') !== null && $ss::safeUrl('/discover') === '/discover');
    $ss::set('brand.name', 'برند آزمون');
    check('نام برند از تنظیمات خوانده می‌شود', brand() === 'برند آزمون');
    $ss::set('brand.name', null);
    check('بی‌تنظیم، نام پیش‌فرض برمی‌گردد', brand() === App\Domain\System\SiteSettings::DEFAULT_BRAND);
    if ($ss::canEncrypt()) {
        $ss::setSecret('sms.kavenegar.api_key', 'top-secret-key-42');
        $rawSecret = (string) DB::selectOne("SELECT setting_value FROM system_settings WHERE setting_key = 'site.sms.kavenegar.api_key'")['setting_value'];
        check('رمز در دیتابیس رمزگذاری‌شده است، نه متن ساده', !str_contains($rawSecret, 'top-secret') && str_starts_with($rawSecret, 'enc:v1:'));
        check('رمز با همان کلید برمی‌گردد', $ss::secret('sms.kavenegar.api_key') === 'top-secret-key-42');
        $ss::set('sms.driver', 'kavenegar');
        $overlay = $ss::configOverlay();
        check('تنظیمات پنل روی config پیامک می‌نشیند', ($overlay['reshen.sms.driver'] ?? null) === 'kavenegar' && ($overlay['reshen.sms.credentials.kavenegar.api_key'] ?? null) === 'top-secret-key-42');
        $ss::setMany(['sms.driver' => null, 'sms.kavenegar.api_key' => null]);
    }
    $md = App\Support\SafeMarkdown::render("## تیتر\nمتن <script>alert(1)</script> **پررنگ** [ب](javascript:alert(1)) [خوب](https://a.ir)\n\n- یک");
    check('Markdown امن: اسکریپت به متن تبدیل می‌شود', !str_contains($md, '<script') && str_contains($md, '&lt;script&gt;'));
    check('Markdown امن: پیوند javascript ساخته نمی‌شود', !str_contains($md, 'javascript:') && str_contains($md, 'href="https://a.ir"'));
    check('Markdown امن: سرتیتر، پررنگ و فهرست', str_contains($md, '<h2>تیتر</h2>') && str_contains($md, '<strong>پررنگ</strong>') && str_contains($md, '<li>یک</li>'));
    $pageRepo = new App\Domain\Content\PageRepository();
    $pageId = $pageRepo->save(null, ['slug' => 'test-page', 'title' => 'آزمون', 'body' => 'متن', 'is_published' => 1, 'show_in_footer' => 1]);
    check('صفحهٔ منتشرشده در پانویس می‌آید', in_array('test-page', array_column(App\Domain\Content\PageRepository::footer(), 'slug'), true));
    $pageRepo->save($pageId, ['is_published' => 0]);
    check('پیش‌نویس نه در پانویس است نه با نشانی باز می‌شود', !in_array('test-page', array_column(App\Domain\Content\PageRepository::footer(), 'slug'), true) && $pageRepo->findPublished('test-page') === null);
    section('گزارش‌های سراسری');
    $rr = App\Domain\Reports\ReportRange::class;
    $_GET = ['range' => 'custom', 'from_y' => '1405', 'from_m' => '7', 'from_d' => '10', 'to_y' => '1405', 'to_m' => '7', 'to_d' => '1'];
    $swapped = $rr::fromRequest(new App\Core\Request());
    check('بازهٔ دلخواهِ وارونه درست می‌شود', $swapped->from < $swapped->to && $swapped->days() === 10, $swapped->start() . ' → ' . $swapped->end());
    $_GET = ['range' => 'custom', 'from_y' => '1390', 'from_m' => '1', 'from_d' => '1', 'to_y' => '1405', 'to_m' => '7', 'to_d' => '1'];
    check('بازهٔ خیلی بلند محدود می‌شود', $rr::fromRequest(new App\Core\Request())->days() === 401);
    $_GET = ['range' => '<bad>'];
    check('بازهٔ ناشناخته ← پیش‌فرض ۳۰ روز', $rr::fromRequest(new App\Core\Request())->days() === 30);
    $_GET = ['range' => 'month'];
    [, , $monthDay] = App\Support\Jalali::fromDateTime($rr::fromRequest(new App\Core\Request())->from);
    check('«این ماه» از روز اول ماه شمسی شروع می‌شود', $monthDay === 1);
    $_GET = [];
    $week = $rr::lastDays(7);
    $prevWeek = $week->previous();
    check('دورهٔ قبل هم‌اندازه و درست پیش از بازه است', $prevWeek->days() === 7 && $prevWeek->to->modify('+1 day') == $week->from);

    Now::freeze($day->modify('+5 days'));
    $range = $rr::lastDays(14);
    $menReports = new App\Domain\Reports\PlatformReports(['salon_id' => $menId]);
    $kpi = $menReports->kpis($range);
    $direct = DB::selectOne(
        "SELECT COUNT(*) AS n, SUM(status = 'cancelled') AS c FROM appointments WHERE salon_id = ? AND scheduled_at BETWEEN ? AND ?",
        [$menId, $range->start(), $range->end()]
    );
    check('شمار نوبت گزارش با شمارش مستقیم یکی است', $kpi['bookings'] === (int) $direct['n'] && $kpi['bookings'] > 0, $kpi['bookings'] . ' / ' . $direct['n']);
    check('فیلتر سالن فقط همان سالن را می‌شمارد', $kpi['active_salons'] === 1 && $kpi['cancelled'] === (int) $direct['c']);
    $daily = $menReports->daily($range);
    check('سری روزانه همهٔ روزهای بازه را دارد و جمعش درست است', count($daily) === 14 && array_sum(array_column($daily, 'bookings')) === $kpi['bookings']);
    $heat = $menReports->heatmap($range);
    $heatSum = 0;
    foreach ($heat['grid'] as $hours) {
        $heatSum += array_sum($hours);
    }
    check('نقشهٔ شلوغی ۷ روز هفته دارد', count($heat['grid']) === 7 && $heatSum > 0);
    $otherReports = new App\Domain\Reports\PlatformReports(['salon_id' => $womenId]);
    check('گزارش یک سالن دادهٔ سالن دیگر را نمی‌شمارد', $otherReports->kpis($range)['bookings'] + $kpi['bookings'] <= (new App\Domain\Reports\PlatformReports())->kpis($range)['bookings']);
    $ranked = (new App\Domain\Reports\PlatformReports())->salons($range, 'revenue', 500);
    $revenues = array_map('intval', array_column($ranked, 'revenue'));
    $sortedRevenues = $revenues;
    rsort($sortedRevenues);
    check('رتبه‌بندی سالن‌ها بر اساس درآمد نزولی است', $ranked !== [] && $revenues === $sortedRevenues);
    check('رتبه‌بندی با ستون ناشناخته خطا نمی‌دهد', is_array((new App\Domain\Reports\PlatformReports())->salons($range, 'name; DROP TABLE x', 5)));
    check('همهٔ گزارش‌ها روی فیلتر شهر و نوع اجرا می‌شوند', is_array((new App\Domain\Reports\PlatformReports(['city' => 'تهران', 'audience' => 'men']))->services($range)) && count((new App\Domain\Reports\PlatformReports(['audience' => 'women']))->growth(12)) === 12);
    Now::freeze(null);

    $described = implode(' · ', App\Support\AuditLabels::describe('{"changes":{"plan_code":{"from":"trial","to":"pro"}},"role":"owner"}'));
    check('جزئیات رویداد به‌جای JSON خام خوانا نوشته می‌شود', str_contains($described, 'طرح: از «آزمایشی» به «حرفه‌ای»') && str_contains($described, 'صاحب سالن'), $described);
    check('جزئیات خالی یا خراب خطا نمی‌دهد', App\Support\AuditLabels::describe(null) === [] && App\Support\AuditLabels::describe('{bad') === []);
    $csv = App\Support\Csv::response('گزارش ۱.csv', ['نام', 'مبلغ'], [['=HYPERLINK("http://x")', -5000], ['+98 912', '@SUM(A1)'], ['سالن عادی', '12']]);
    check('CSV با BOM شروع می‌شود تا Excel فارسی را درست بخواند', str_starts_with($csv->body, "\xEF\xBB\xBF"));
    check('خانه‌های فرمول‌مانند خنثی می‌شوند (CSV injection)', str_contains($csv->body, "'=HYPERLINK") && str_contains($csv->body, "'+98 912") && str_contains($csv->body, "'@SUM"));
    check('عدد منفی دست نمی‌خورد', str_contains($csv->body, ',-5000'));
    check('نام فایل فقط نویسه‌های امن دارد', preg_match('/filename="[A-Za-z0-9._-]+"/', $csv->headers['Content-Disposition']) === 1);
} catch (Throwable $e) {
    $failed[] = 'خطای پیش‌بینی‌نشده: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    echo "\n  ✗ " . end($failed) . "\n" . $e->getTraceAsString() . "\n";
} finally {
    Now::freeze(null);
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // کش مشترکِ تنظیمات و صفحه‌ها را با دیتابیسِ برگشته هماهنگ کن
    App\Core\Cache::bump('site_settings');
    App\Core\Cache::bump('pages');
}

echo "\n" . str_repeat('─', 50) . "\n";
echo "{$passed} مورد موفق، " . count($failed) . " مورد ناموفق\n";
foreach ($failed as $f) {
    echo "  • {$f}\n";
}
exit($failed === [] ? 0 : 1);
