<?php
/**
 * قالب ورود و ثبت‌نام.
 *
 * تا پیش از ورود، سالنی در کار نیست که پالتش را بدهد — پس پالت پیش‌فرض
 * می‌نشیند. ولی روشن/تیره همین‌جا هم باید در دسترس باشد: کسی که شب
 * وارد می‌شود، نباید اول یک صفحهٔ سفید بخورد توی صورتش.
 */
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr(null) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/design-tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/refined.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/comfort.css')) ?>">
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-Regular.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php include BASE_PATH . '/resources/views/components/pwa-head.php'; ?>
<meta name="color-scheme" content="light dark">
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="auth-page min-h-dvh flex items-center justify-center p-4">

<?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>
<a class="skip-to-content" href="#main-content">رفتن به محتوای اصلی</a>

<div class="absolute top-3 left-3">
  <?php include BASE_PATH . '/resources/views/components/theme-toggle.php'; ?>
</div>

<main id="main-content" class="w-full max-w-sm relative">
  <div class="text-center mb-6">
    <span class="brand-icon brand-icon--hero mb-3" aria-hidden="true"><?= icon('barber-mark') ?></span>
    <h1 class="page-title">رشن</h1>
    <p class="text-xs text-ink-400 mt-1">زمانِ راست می‌گوید</p>
  </div>
  <div class="auth-card glass rounded-2xl p-6">
    <?= $content ?>
  </div>
</main>
<script src="<?= e(asset('js/network-status.js')) ?>" defer></script>
<script src="<?= e(asset('js/comfort.js')) ?>" defer></script>
</body>
</html>
