<?php
/**
 * محتوای مشترک <head> همهٔ قالب‌ها.
 *
 * @var string|null $title
 * @var string|null $description
 * @var string|null $pwaSlug   اگر باشد، مانیفستِ همان سالن (اپ نصب‌شده با صفحهٔ سالن باز می‌شود)
 * @var string|null $pwaTitle
 */
use App\Domain\System\SiteSettings;

$pwaSlug = $pwaSlug ?? null;
$manifestUrl = url('manifest.webmanifest') . ($pwaSlug !== null ? '?s=' . rawurlencode($pwaSlug) : '');
$brandName = brand();
$pageTitle = ($title ?? '') !== '' ? $title . ' · ' . $brandName : (SiteSettings::str('seo.title') !== '' ? SiteSettings::str('seo.title') : $brandName);
$metaDescription = !empty($description) ? (string) $description : SiteSettings::str('seo.description');
// پنل‌ها، ورود و صفحه‌های شخصی در جست‌وجو نمی‌آیند؛ تحلیل بازدید هم فقط روی صفحه‌های عمومی
$requestPath = '/' . trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$basePath = App\Core\Request::basePath();
if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
    $requestPath = '/' . ltrim(substr($requestPath, strlen($basePath)), '/'); // نصب در زیرپوشه
}
$privatePage = (bool) preg_match('#^/(panel|platform|system|account|login|logout|onboarding|salons(?:/\d|$)|me(?:/|$)|q/|doctor)#', $requestPath);
$noindex = $privatePage || !SiteSettings::indexingAllowed() || !empty($noindex);
$icon192 = SiteSettings::iconPath(192);
$icon180 = SiteSettings::iconPath(180);
$logo = SiteSettings::logoPath();
$siteUrl = rtrim((string) App\Core\Config::get('app.url', ''), '/');
$gaId = $privatePage ? '' : SiteSettings::gaId();
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<?php if ($metaDescription !== ''): ?><meta name="description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (($g = SiteSettings::str('seo.google_verification')) !== ''): ?><meta name="google-site-verification" content="<?= e($g) ?>"><?php endif; ?>
<?php if (($b = SiteSettings::str('seo.bing_verification')) !== ''): ?><meta name="msvalidate.01" content="<?= e($b) ?>"><?php endif; ?>
<?php if (!$privatePage): ?>
<meta property="og:site_name" content="<?= e($brandName) ?>">
<meta property="og:title" content="<?= e(($title ?? '') !== '' ? (string) $title : $pageTitle) ?>">
<?php if ($metaDescription !== ''): ?><meta property="og:description" content="<?= e($metaDescription) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="fa_IR">
<?php if ($siteUrl !== ''): ?><meta property="og:url" content="<?= e($siteUrl . ($requestPath === '/' ? '' : $requestPath)) ?>"><?php endif; ?>
<?php if ($logo !== null && $siteUrl !== ''): ?><meta property="og:image" content="<?= e($siteUrl . '/' . $logo) ?>"><?php endif; ?>
<?php endif; ?>
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f6f6f4" id="theme-color">
<meta name="format-detection" content="telephone=no">
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-Regular.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-DemiBold.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/reshen.css')) ?>">
<link rel="manifest" href="<?= e($manifestUrl) ?>">
<?php if ($icon192 !== null): ?>
<link rel="icon" href="<?= e(url($icon192)) ?>" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="<?= e(url($icon180 ?? $icon192)) ?>" sizes="180x180">
<?php else: ?>
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('icons/icon-192.png')) ?>" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>" sizes="180x180">
<?php endif; ?>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= e($pwaTitle ?? $brandName) ?>">
<?php if ($gaId !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
<script nonce="<?= e(csp_nonce()) ?>">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',<?= json_encode($gaId) ?>,{anonymize_ip:true});</script>
<?php endif; ?>
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
