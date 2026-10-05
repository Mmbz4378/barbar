<?php

declare(strict_types=1);

/**
 * دادهٔ نمونه برای توسعه و آزمون رابط — هرگز روی تولید اجرا نکنید.
 *
 *   php tools/seed-demo.php
 *
 * دو سالن می‌سازد: یک آرایشگاه مردانه (اول زمان) و یک سالن بانوان
 * (اول خدمت، با مهارت‌های جدا و بیعانه)، به‌همراه کاربران نقش‌های
 * مختلف و چند نوبت امروز.
 *
 *   ۰۹۱۲۰۰۰۰۰۰۱  صاحب هر دو سالن
 *   ۰۹۱۲۰۰۰۰۰۰۲  پذیرش سالن بانوان
 *   ۰۹۱۲۰۰۰۰۰۰۳  آرایشگرِ آرایشگاه مردانه
 *   ۰۹۱۲۰۰۰۰۰۰۹  مدیر پلتفرم
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Domain\Booking\BookingService;
use App\Domain\Catalog\CatalogTemplates;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Identity\UserRepository;
use App\Domain\Queue\QueueService;
use App\Domain\Salon\SalonSetupService;
use App\Support\IranMobile;

if (Config::get('app.env') === 'production') {
    fwrite(STDERR, "روی production اجرا نمی‌شود.\n");
    exit(1);
}

$users = new UserRepository();
$owner = $users->findOrCreate(IranMobile::parse('09120000001'), 'مهدی رضایی');
$reception = $users->findOrCreate(IranMobile::parse('09120000002'), 'سارا کریمی');
$barberUser = $users->findOrCreate(IranMobile::parse('09120000003'), 'علی محمدی');
$admin = $users->findOrCreate(IranMobile::parse('09120000009'), 'مدیر پلتفرم');
DB::update('users', ['is_platform_admin' => 1], 'id = :id', ['id' => $admin['id']]);

$setup = new SalonSetupService();
$prices = static function (string $audience, array $map): array {
    $out = [];
    foreach (CatalogTemplates::for($audience) as $ci => $category) {
        foreach ($category['services'] as $si => $service) {
            if (isset($map[$service['name']])) {
                $out[] = ['template' => $ci . ':' . $si, 'price' => $map[$service['name']] * 10];
            }
        }
    }

    return $out;
};

// ─── آرایشگاه مردانه ──────────────────────────────────────────────────
$menId = $setup->create(
    ['name' => 'آرایشگاه پارسا', 'audience' => 'men', 'city' => 'تهران', 'address' => 'خیابان ولیعصر، کوچهٔ نهم، پلاک ۱۲', 'phone' => '02188776655', 'seats' => 3],
    (int) $owner['id'],
    $prices('men', [
        'کوتاهی مو' => 350000, 'کوتاهی فید و ماشینی' => 420000, 'اصلاح موی کودک' => 250000,
        'فرم و اصلاح ریش' => 180000, 'اصلاح صورت با تیغ' => 150000, 'رنگ مو' => 600000,
        'رنگ ریش' => 300000, 'کراتین و صافی مو' => 1800000, 'پاکسازی پوست' => 700000, 'پکیج داماد' => 4500000,
    ]),
    'مهدی رضایی'
);
DB::update('salons', ['neighborhood' => 'ونک', 'introduction' => 'آرایشگاه مردانه با سه صندلی، بیش از ده سال سابقه در کوتاهی کلاسیک و فید.', 'publication_status' => 'published', 'map_lat' => 35.7575, 'map_lng' => 51.4100], 'id = :id', ['id' => $menId]);
$barberA = (int) DB::insert('staff', ['salon_id' => $menId, 'user_id' => $barberUser['id'], 'name' => 'علی محمدی', 'title' => 'استادکار', 'phone' => $barberUser['phone'], 'color' => '#0F766E', 'sort_order' => 2]);
DB::insert('salon_user', ['salon_id' => $menId, 'user_id' => $barberUser['id'], 'role' => 'staff']);
$barberB = (int) DB::insert('staff', ['salon_id' => $menId, 'name' => 'رضا حسینی', 'title' => 'آرایشگر', 'color' => '#C2410C', 'sort_order' => 3]);

// ─── سالن زیبایی بانوان ──────────────────────────────────────────────
$womenId = $setup->create(
    ['name' => 'سالن زیبایی گلاره', 'audience' => 'women', 'city' => 'تهران', 'address' => 'سعادت‌آباد، میدان کاج، ساختمان نگین، طبقهٔ دوم', 'phone' => '02122334455', 'seats' => 5],
    (int) $owner['id'],
    $prices('women', [
        'کوتاهی مو' => 600000, 'براشینگ' => 500000, 'سشوار و حالت' => 400000, 'شینیون' => 2500000,
        'رنگ ریشه' => 1500000, 'رنگ کامل مو' => 3000000, 'هایلایت و لایت' => 5000000, 'بالیاژ و آمبره' => 7000000,
        'کراتین' => 4500000, 'احیا و پروتئین‌تراپی' => 2500000, 'ماسک مو' => 400000,
        'مانیکور' => 450000, 'پدیکور' => 550000, 'لاک ژل' => 600000, 'کاشت ناخن' => 1500000, 'ترمیم کاشت' => 900000,
        'اصلاح ابرو' => 200000, 'لیفت ابرو' => 900000, 'لیفت مژه' => 1100000, 'اکستنشن مژه' => 2200000,
        'پاکسازی پوست' => 1200000, 'اپیلاسیون صورت' => 250000, 'میکاپ' => 2500000, 'پکیج عروس' => 25000000,
    ])
);
DB::update('salons', [
    'neighborhood' => 'سعادت‌آباد',
    'introduction' => 'سالن تخصصی رنگ، کراتین، ناخن و میکاپ عروس. هر خدمت با متخصص همان کار.',
    'publication_status' => 'published',
    'deposit_card_number' => '6037997512345678',
    'deposit_card_holder' => 'گلاره احمدی',
    'deposit_hold_minutes' => 180,
    'min_notice_minutes' => 60,
    'cancel_notice_minutes' => 180,
], 'id = :id', ['id' => $womenId]);
DB::insert('salon_user', ['salon_id' => $womenId, 'user_id' => $reception['id'], 'role' => 'reception']);

$specialists = [
    ['نگار صادقی', 'مدیر هنری و رنگ‌کار', '#BE185D', ['کوتاهی و حالت‌دهی', 'رنگ و لایت', 'مراقبت مو']],
    ['مریم رستمی', 'آرایشگر مو', '#6D28D9', ['کوتاهی و حالت‌دهی', 'مراقبت مو']],
    ['الهام نوری', 'ناخن‌کار', '#0369A1', ['ناخن']],
    ['شیما فراهانی', 'ابرو، مژه و پوست', '#9F1239', ['ابرو و مژه', 'پوست', 'اپیلاسیون', 'میکاپ و عروس']],
];
$services = new ServiceRepository();
$catalog = $services->all($womenId);
foreach ($specialists as $i => [$name, $title, $color, $categories]) {
    $staffId = (int) DB::insert('staff', ['salon_id' => $womenId, 'name' => $name, 'title' => $title, 'color' => $color, 'sort_order' => $i + 1]);
    foreach ($catalog as $service) {
        if (!in_array($service['category_name'], $categories, true)) {
            $services->setOffered($womenId, $staffId, (int) $service['id'], false);
        }
    }
}
// بیعانه برای خدمت‌های طولانی
DB::statement("UPDATE services SET deposit_amount = 5000000 WHERE salon_id = ? AND name IN ('پکیج عروس')", [$womenId]);
DB::statement("UPDATE services SET deposit_amount = 1000000 WHERE salon_id = ? AND name IN ('کراتین','بالیاژ و آمبره','هایلایت و لایت')", [$womenId]);
DB::statement("UPDATE services SET online_booking = 0, description = 'برای عروس، هماهنگی و مشاوره پیش از رزرو لازم است.' WHERE salon_id = ? AND name = 'پکیج عروس'", [$womenId]);
DB::statement("UPDATE services SET description = 'شامل شست‌وشو و سشوار. قیمت نهایی به طول مو بستگی دارد.' WHERE salon_id = ? AND name IN ('رنگ کامل مو','کراتین')", [$womenId]);

// ─── چند نوبت امروز ──────────────────────────────────────────────────
ServiceRepository::flushCache();
$menServices = array_column($services->all($menId, true), 'id', 'name');
$queue = new QueueService();
$queue->addWalkin($menId, 'امیر', '09351112233', $barberA, [(int) $menServices['کوتاهی مو']]);
$queue->addWalkin($menId, 'حسین', null, $barberA, [(int) $menServices['فرم و اصلاح ریش']]);
$queue->addWalkin($menId, null, null, null, [(int) $menServices['کوتاهی فید و ماشینی']]);

$booking = new BookingService();
$tomorrow = new DateTimeImmutable('tomorrow');
try {
    $booking->createBooking($menId, null, [(int) $menServices['کوتاهی مو'], (int) $menServices['فرم و اصلاح ریش']], $tomorrow, '11:00', '09121234567', 'کیان', null, ['online' => true]);
} catch (Throwable $e) {
    echo 'booking: ' . $e->getMessage() . "\n";
}

$womenServices = array_column($services->all($womenId, true), 'id', 'name');
try {
    // رنگ ریشه + مانیکور: هیچ‌کس هر دو را انجام نمی‌دهد ← نوبت چندنفره
    $booking->createBooking($womenId, null, [(int) $womenServices['رنگ ریشه'], (int) $womenServices['مانیکور']], $tomorrow, '10:00', '09127654321', 'ترانه', null, ['online' => true]);
    $booking->createBooking($womenId, null, [(int) $womenServices['کراتین']], $tomorrow, '14:00', '09125556677', 'نازنین', null, ['online' => true]);
} catch (Throwable $e) {
    echo 'booking: ' . $e->getMessage() . "\n";
}

// صفحه‌های محتوایی نمونه (پانویس سایت و آزمون رابط)
foreach ([
    ['terms', 'قوانین و مقررات', "## رزرو نوبت\nبا ثبت نوبت، شمارهٔ موبایل شما برای پیامک تأیید و یادآوری استفاده می‌شود.\n\n## لغو\n- لغو تا ۲ ساعت پیش از نوبت رایگان است.\n- بیعانهٔ پرداخت‌شده طبق قانون هر سالن برمی‌گردد."],
    ['privacy', 'حریم خصوصی', "اطلاعات شما فقط برای مدیریت نوبت استفاده می‌شود و در اختیار دیگران قرار نمی‌گیرد."],
] as $i => [$slug, $title, $body]) {
    if (DB::selectOne('SELECT id FROM pages WHERE slug = ?', [$slug]) === null) {
        DB::insert('pages', ['slug' => $slug, 'title' => $title, 'body' => $body, 'is_published' => 1, 'show_in_footer' => 1, 'sort_order' => $i]);
    }
}

echo "سالن مردانه: /s/" . DB::selectOne('SELECT slug FROM salons WHERE id=?', [$menId])['slug'] . "\n";
echo "سالن بانوان: /s/" . DB::selectOne('SELECT slug FROM salons WHERE id=?', [$womenId])['slug'] . "\n";
echo "ورود: php tools/login-link.php 09120000001\n";
