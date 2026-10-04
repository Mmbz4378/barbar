<?php
/**
 * محتوای مشترک <head> همهٔ قالب‌ها.
 *
 * @var string|null $title
 * @var string|null $description
 * @var string|null $pwaSlug   اگر باشد، مانیفستِ همان سالن (اپ نصب‌شده با صفحهٔ سالن باز می‌شود)
 * @var string|null $pwaTitle
 */
$pwaSlug = $pwaSlug ?? null;
$manifestUrl = url('manifest.webmanifest') . ($pwaSlug !== null ? '?s=' . rawurlencode($pwaSlug) : '');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') !== '' ? $title . ' · رشن' : 'رشن') ?></title>
<?php if (!empty($description)): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f6f6f4" id="theme-color">
<meta name="format-detection" content="telephone=no">
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-Regular.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-DemiBold.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/reshen.css')) ?>">
<link rel="manifest" href="<?= e($manifestUrl) ?>">
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('icons/icon-192.png')) ?>" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>" sizes="180x180">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e($pwaTitle ?? 'رشن') ?>">
<script nonce="<?= e(csp_nonce()) ?>">
/* روشن/تیره پیش از اولین رنگ‌آمیزی، تا «پرش سفید» دیده نشود. */
(function () {
  window.reshenApplyMode = function (dark) {
    var html = document.documentElement;
    html.classList.toggle('dark', dark);
    var m = document.getElementById('theme-color');
    if (m) m.setAttribute('content', dark ? '#111110' : '#f6f6f4');
  };
  var saved = null;
  try { saved = localStorage.getItem('reshen-mode'); } catch (e) {}
  var dark = saved ? saved === 'dark' : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  window.reshenApplyMode(!!dark);
})();
</script>
