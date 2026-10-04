<?php
/**
 * سرآیندهای PWA.
 *
 * $pwaSalonSlug اگر باشد، مانیفستِ همان سالن را می‌دهد تا اپِ نصب‌شده
 * با صفحهٔ همان آرایشگاه باز شود، نه با صفحهٔ ورود پنل.
 *
 * iOS مانیفست را برای نصب نمی‌خواند — آیکون و عنوان را از همین
 * متاتگ‌های apple-* برمی‌دارد. بدون این‌ها، آیکونِ اپ روی آیفون یک
 * اسکرین‌شات تارِ صفحه می‌شود.
 */
$pwaSalonSlug = $pwaSalonSlug ?? null;
$manifestUrl = url('manifest.webmanifest') . ($pwaSalonSlug !== null ? '?s=' . rawurlencode($pwaSalonSlug) : '');
?>
<link rel="manifest" href="<?= e($manifestUrl) ?>">
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('icons/icon-192.png')) ?>" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>" sizes="180x180">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($pwaAppTitle ?? 'رشن') ?>">

<script>
/*
 * ثبت سرویس‌ورکر.
 *
 * در هر سه قالب می‌آید، نه فقط پنل: صفحهٔ اختصاصی سالن هم باید بدون
 * اینترنت **باز شود**، وگرنه مشتری‌ای که اپ را نصب کرده و وای‌فای سالن
 * قطع است، صفحهٔ سفید می‌بیند.
 *
 * سرویس‌ورکر فقط در بستر امن (HTTPS یا localhost) کار می‌کند. روی
 * http ساده خطا می‌دهد، پس داخل شرط است تا کنسول را کثیف نکند.
 */
(function () {
  if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('<?= e(url('service-worker.js')) ?>').catch(function () {
      /* ثبت نشد — برنامه بدون آن هم کار می‌کند، فقط آفلاین ندارد */
    });
  });
})();
</script>
