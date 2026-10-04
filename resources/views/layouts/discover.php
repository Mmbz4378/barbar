<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr(null) ?>>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/design-tokens.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/refined.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/comfort.css')) ?>">
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-Regular.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<meta name="theme-color" id="theme-color" content="#f2f6f3">
<?php include BASE_PATH.'/resources/views/components/theme-boot.php'; include BASE_PATH.'/resources/views/components/pwa-head.php'; ?>
</head><body class="customer-surface">
<?php include BASE_PATH.'/resources/views/components/icons.svg'; ?>
<a class="skip-to-content" href="#main-content">رفتن به محتوای اصلی</a>
<header class="discovery-header"><a class="discovery-brand" href="<?= e(url('discover')) ?>"><span class="brand-icon"><?= icon('barber-mark') ?></span><strong>رشن</strong></a>
<?php include BASE_PATH.'/resources/views/components/customer-navigation.php'; ?>
<?php include BASE_PATH.'/resources/views/components/theme-toggle.php'; ?></header>
<main id="main-content" class="discovery-main">
<?php foreach(['success','error'] as $kind): if($message=flash($kind)): ?><p class="glass p-4 mb-4" role="<?= $kind==='error'?'alert':'status' ?>"><?= e($message) ?></p><?php endif; endforeach; ?>
<?= $content ?></main>
<footer class="discovery-footer">رشن · زمان برای خودت <a href="<?= e(url('login')) ?>">ورود مدیران سالن</a></footer>
<?php include BASE_PATH.'/resources/views/components/install-prompt.php'; ?>
<script src="<?= e(asset('js/discovery.js')) ?>" defer></script>
<script src="<?= e(asset('js/network-status.js')) ?>" defer></script>
<script src="<?= e(asset('js/comfort.js')) ?>" defer></script>
</body></html>
