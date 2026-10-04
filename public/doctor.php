<?php
/**
 * دروازهٔ صفحهٔ سلامت.
 *
 * این فایل عمداً با سینتکس قدیمی PHP نوشته شده — نه type، نه نوع
 * بازگشتی، نه هیچ چیزی که بعد از PHP 5 آمده باشد.
 *
 * چرا: صفحهٔ سلامت همان صفحه‌ای است که وقتی هیچ‌چیز کار نمی‌کند باید
 * باز شود. ولی خودش `app/bootstrap.php` را می‌خواند و آن هم
 * `vendor/autoload.php` را — یعنی اگر مشکل دقیقاً در نسخهٔ PHP یا در
 * وابستگی‌ها باشد، ابزار عیب‌یابی با همان خطایی می‌میرد که قرار بود
 * توضیحش بدهد، و کاربر باز هم فقط «HTTP ERROR 500» می‌بیند.
 *
 * حالا این دروازه روی هر نسخه‌ای بالا می‌آید، سه چیزی را که می‌توانند
 * پیش از autoload همه‌چیز را بخوابانند خودش می‌سنجد، و فقط اگر همه
 * سالم بودند صفحهٔ واقعی را صدا می‌زند.
 */

define('RESHEN_MIN_PHP', '8.1.0');

/*
 * فقط برای همین صفحه، خطاها را نشان بده. این صفحه برای دیدنِ خطا
 * ساخته شده؛ پنهان کردنشان اینجا یعنی بی‌فایده کردنش.
 */
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * صفحهٔ خطای مستقل — بدون قالب، بدون CSS بیرونی، بدون autoload.
 *
 * @param string $title عنوان
 * @param string $body  بدنهٔ HTML
 */
function reshen_doctor_stop($title, $body)
{
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>'
       . "body{margin:0;background:#FAFAF9;color:#1C1917;"
       . "font:15px/1.9 Tahoma,'Segoe UI',sans-serif;"
       . 'display:grid;place-items:center;min-height:100vh;padding:20px}'
       . '.card{background:#fff;border:1px solid #E7E5E4;border-radius:16px;'
       . 'padding:28px;max-width:560px;width:100%}'
       . 'h1{font-size:18px;margin:0 0 14px}p{margin:0 0 12px;color:#44403C}'
       . 'code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;'
       . 'background:#F5F5F4;border-radius:6px;padding:2px 6px;direction:ltr;'
       . 'display:inline-block}ol{margin:0;padding-inline-start:20px;color:#44403C}'
       . 'li{margin-bottom:8px}.now{background:#FEF2F2;border:1px solid #FECACA;'
       . 'color:#991B1B;border-radius:10px;padding:10px 14px;margin:0 0 16px;font-size:14px}'
       . '</style></head><body><div class="card"><h1>'
       . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>' . $body
       . '</div></body></html>';
    exit;
}

/* ۱) نسخهٔ PHP خودِ هاست. */
if (version_compare(PHP_VERSION, RESHEN_MIN_PHP, '<')) {
    reshen_doctor_stop(
        'نسخهٔ PHP این هاست قدیمی است',
        '<p class="now">نسخهٔ فعلی: <code>' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8')
        . '</code> &nbsp;—&nbsp; لازم: <code>' . RESHEN_MIN_PHP . '</code> یا بالاتر</p>'
        . '<p>این خطای شما نیست و چیزی خراب نشده. فقط باید نسخهٔ PHP را عوض کنید:</p>'
        . '<ol><li>وارد cPanel شوید.</li>'
        . '<li>دنبال <strong>Select PHP Version</strong> (یا <strong>MultiPHP Manager</strong>) بگردید.</li>'
        . '<li>برای همین دامنه، نسخه را روی <strong>۸.۱</strong> یا بالاتر بگذارید و ذخیره کنید.</li>'
        . '<li>همین صفحه را دوباره باز کنید.</li></ol>'
    );
}

/* ۲) پوشهٔ vendor. */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    reshen_doctor_stop(
        'پوشهٔ vendor آپلود نشده',
        '<p class="now">پیدا نشد: <code>vendor/autoload.php</code></p>'
        . '<p>بسته ناقص آپلود شده است. معمولاً یعنی اکسترکت وسط کار قطع شده'
        . ' یا پوشهٔ <code>vendor</code> جا مانده.</p>'
        . '<ol><li>فایل ZIP را دوباره آپلود و اکسترکت کنید.</li>'
        . '<li>مطمئن شوید پوشهٔ <code>vendor</code> کنار <code>app</code> هست.</li></ol>'
    );
}

/*
 * ۳) آیا وابستگی‌ها نسخهٔ بالاتری از PHP می‌خواهند؟
 *
 * این همان تله‌ای است که یک بار افتاد: بسته روی ماشینی با PHP جدیدتر
 * ساخته شده بود، کامپوزر `platform_check.php` را برای همان نسخه نوشته
 * بود، و روی هاست ۸.۱ اولین `require` خطای مرگبار می‌داد. آن فایل
 * پیش از هر چیزِ دیگری اجرا می‌شود، پس اینجا خودمان جلوتر می‌خوانیمش
 * تا به‌جای متن انگلیسیِ کامپوزر، دلیل و راه‌حل را فارسی بگوییم.
 */
$check = dirname(__DIR__) . '/vendor/composer/platform_check.php';
if (is_file($check)) {
    $src = file_get_contents($check);
    if (preg_match_all('/PHP_VERSION_ID >= (\d+)/', $src, $m)) {
        $wanted = max(array_map('intval', $m[1]));
        if (PHP_VERSION_ID < $wanted) {
            $major = (int) floor($wanted / 10000);
            $minor = (int) floor(($wanted % 10000) / 100);
            reshen_doctor_stop(
                'بستهٔ نصب‌شده با PHP این هاست جور نیست',
                '<p class="now">PHP این هاست: <code>' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8')
                . '</code> &nbsp;—&nbsp; بسته می‌خواهد: <code>' . $major . '.' . $minor . '</code> یا بالاتر</p>'
                . '<p>این نسخه از بسته روی ماشینی با PHP جدیدتر ساخته شده، پس'
                . ' کتابخانه‌هایش روی این هاست اجرا نمی‌شوند. خطایی که در بقیهٔ'
                . ' صفحات می‌بینید (<code>Composer detected issues in your platform</code>)'
                . ' از همین‌جاست.</p>'
                . '<p><strong>دو راه دارید</strong> — اولی ساده‌تر است:</p>'
                . '<ol><li><strong>بستهٔ تازه را آپلود کنید.</strong> نسخه‌های جدید'
                . ' برای PHP ۸.۱ ساخته می‌شوند و همین مشکل را ندارند.</li>'
                . '<li>یا در cPanel → <strong>Select PHP Version</strong> نسخه را روی <code>'
                . $major . '.' . $minor . '</code> یا بالاتر بگذارید.</li></ol>'
            );
        }
    }
}

define('RESHEN_DOCTOR', true);
require dirname(__DIR__) . '/app/Setup/doctor.php';
