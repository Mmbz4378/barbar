<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Env;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';
Autoloader::register('App', BASE_PATH . '/app');

/*
 * اتولودر کامپوزر — اگر باشد.
 *
 * هستهٔ برنامه عمداً هیچ وابستگی زمان‌اجرایی ندارد (تصمیم ت-۰۱) و این
 * خط آن را عوض نمی‌کند: بدون vendor هم بالا می‌آید. فقط یک قابلیت
 * جانبی (ساخت QR) به کتابخانه تکیه دارد و خودش بررسی می‌کند که در
 * دسترس هست یا نه.
 *
 * فایل نصب‌شده از zip همیشه vendor دارد؛ این شرط برای کسی است که ریپو
 * را مستقیم clone کرده و هنوز composer install نزده.
 */
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
// تنظیمات پیامک و پرداختی که مدیر کل در پنل ذخیره کرده روی .env می‌نشیند (تنبل)
Config::setOverlay(static fn (): array => App\Domain\System\SiteSettings::configOverlay());
require BASE_PATH . '/app/Support/helpers.php';

date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Tehran'));

View::setBasePath(BASE_PATH . '/resources/views');

Session::start();

// سربرگ‌های امنیتی و CSP روی هر پاسخ (عادی، خطا، حالت تعمیر) — مستقل از
// اینکه میزبان آپاچی است یا نه. nonce همین‌جا ساخته می‌شود تا تگ‌های
// اسکریپت درون‌خطی نماها با سربرگ CSP یکی باشند.
Security::send();

if (Config::get('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

set_exception_handler(static function (Throwable $e): void {
    // سرور زیر فشار: صفحهٔ سبکِ «شلوغ است» با تلاش دوبارهٔ خودکار، نه «خطایی رخ داد»
    if ($e instanceof App\Core\Overloaded) {
        error_log('[overloaded] ' . ($e->getPrevious()?->getMessage() ?? $e->getMessage()));
        if (PHP_SAPI === 'cli') {
            // ابزار خط فرمان و کرون: متن ساده، نه صفحهٔ HTML
            fwrite(STDERR, 'دیتابیس در دسترس نیست: ' . ($e->getPrevious()?->getMessage() ?? $e->getMessage()) . "\n");
            exit(1);
        }
        App\Core\Overloaded::respond();

        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Cache-Control: no-store');
    }
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    // ستون یا جدولِ ناموجود تقریباً همیشه یعنی فایل‌های نسخهٔ تازه بالا رفته
    // ولی مهاجرت دیتابیس هنوز اجرا نشده (رایج‌ترین اشتباه ارتقا در cPanel)
    $schemaOutdated = $e instanceof PDOException && in_array((string) $e->getCode(), ['42S22', '42S02'], true);
    if (Config::get('app.debug') && !$schemaOutdated) {
        echo '<pre style="direction:ltr;text-align:left;padding:2rem;background:#1e1e1e;color:#f66;white-space:pre-wrap">';
        echo htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString());
        echo '</pre>';

        return;
    }
    try {
        echo View::renderWithLayout('layouts.minimal', 'errors.500', ['title' => 'خطا', 'schemaOutdated' => $schemaOutdated]);
    } catch (Throwable) {
        echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><body style="font-family:sans-serif;text-align:center;padding:4rem"><h1>خطایی رخ داد. لطفاً دوباره تلاش کنید.</h1></body></html>';
    }
});
