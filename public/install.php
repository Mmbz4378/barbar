<?php
/**
 * دروازهٔ نصاب.
 *
 * این فایل عمداً با سینتکس قدیمی PHP نوشته شده — نه type، نه نوع
 * بازگشتی، نه هیچ چیزی که بعد از PHP 5 آمده باشد.
 *
 * چرا: خطای پارس **پیش از اجرای هر خطی** رخ می‌دهد. نصاب اصلی با
 * ویژگی‌های PHP 8 نوشته شده، پس روی هاستی که PHP قدیمی دارد اصلاً
 * پارس نمی‌شود و کاربر به‌جای پیام راهنما، فقط «HTTP ERROR 500»
 * می‌بیند — یعنی ابزاری که ساخته شده بود تا مشکل محیط را بگوید،
 * دقیقاً روی همان مشکل می‌مُرد و هیچ سرنخی نمی‌داد.
 *
 * حالا این دروازه روی هر نسخه‌ای بالا می‌آید، نسخه را می‌سنجد، و
 * فقط اگر به‌اندازهٔ کافی جدید بود نصاب واقعی را صدا می‌زند.
 */

define('RESHEN_MIN_PHP', '8.1.0');

/*
 * فقط برای همین صفحه، خطاها را نشان بده.
 *
 * پیش‌فرض هاست‌ها خاموش است و هر خطای مرگبار به «HTTP ERROR 500»
 * تبدیل می‌شود — صفحه‌ای که هیچ سرنخی ندارد و کاربر بدون SSH هیچ
 * راهی برای فهمیدنش ندارد. برای یک نصاب، دیدن متن خطا از پنهان کردنش
 * خیلی باارزش‌تر است: هنوز نه دیتایی هست و نه سایتی که منتشر شده
 * باشد، و به‌محض پایان نصب این فایل قفل می‌شود.
 *
 * بقیهٔ برنامه این کار را نمی‌کند؛ آنجا APP_DEBUG تصمیم می‌گیرد.
 */
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (version_compare(PHP_VERSION, RESHEN_MIN_PHP, '<')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نسخهٔ PHP قدیمی است</title>
<style>
  body{margin:0;background:#FAFAF9;color:#1C1917;
       font:15px/1.8 Tahoma,'Segoe UI',sans-serif;
       display:grid;place-items:center;min-height:100vh;padding:20px}
  .card{background:#fff;border:1px solid #E7E5E4;border-radius:16px;
        padding:28px;max-width:520px;width:100%}
  h1{font-size:18px;margin:0 0 12px}
  p{margin:0 0 12px;color:#44403C}
  code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;
       background:#F5F5F4;border-radius:6px;padding:2px 6px;direction:ltr;
       display:inline-block}
  ol{margin:0;padding-inline-start:20px;color:#44403C}
  li{margin-bottom:8px}
  .now{background:#FEF2F2;border:1px solid #FECACA;color:#991B1B;
       border-radius:10px;padding:10px 14px;margin:0 0 16px;font-size:14px}
</style>
</head>
<body>
  <div class="card">
    <h1>نسخهٔ PHP این هاست قدیمی است</h1>

    <p class="now">
      نسخهٔ فعلی: <code><?php echo htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8'); ?></code>
      &nbsp;—&nbsp; لازم: <code><?php echo RESHEN_MIN_PHP; ?></code> یا بالاتر
    </p>

    <p>این خطای شما نیست و چیزی خراب نشده. فقط باید نسخهٔ PHP را عوض کنید:</p>

    <ol>
      <li>وارد cPanel شوید.</li>
      <li>دنبال <strong>Select PHP Version</strong> (یا <strong>MultiPHP Manager</strong>) بگردید.</li>
      <li>برای همین دامنه، نسخه را روی <strong>۸.۱</strong> یا بالاتر بگذارید و ذخیره کنید.</li>
      <li>همین صفحه را دوباره باز کنید.</li>
    </ol>
  </div>
</body>
</html>
    <?php
    exit;
}

define('RESHEN_INSTALLER', true);
require dirname(__DIR__) . '/app/Setup/installer.php';
