<?php
/**
 * قالب ناحیهٔ مشتری.
 *
 * عمداً سرصفحهٔ یک آرایشگاه خاص را ندارد: مشتری ممکن است در چند
 * آرایشگاه نوبت داشته باشد و این صفحه مال هیچ‌کدامشان نیست. پس
 * پالت پیش‌فرض سامانه استفاده می‌شود، نه پالت یک سالن.
 */
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr(null) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'نوبت‌های من') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/design-tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/refined.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/comfort.css')) ?>">
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-Regular.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<meta name="color-scheme" content="light dark">
<!--
  بدون این، مرورگر سراغ /favicon.ico می‌رود و هر بازدید یک ۴۰۴ در
  لاگ سرور می‌گذارد.
-->
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<link rel="icon" href="<?= e(asset('icons/icon-192.png')) ?>" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>" sizes="180x180">
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="min-h-dvh customer-surface">

<?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>
<a class="skip-to-content" href="#main-content">رفتن به محتوای اصلی</a>

<div class="customer-shell mx-auto min-h-dvh flex flex-col shadow-deep" style="background:var(--surface)">

  <header class="flex items-center gap-3 px-5 pt-6 pb-4">
    <h1 class="text-[17px] font-extrabold text-ink-900 flex-1"><?= e($title ?? 'نوبت‌های من') ?></h1>

    <?php include BASE_PATH . '/resources/views/components/theme-toggle.php'; ?>
    <?php if (App\Domain\Customer\CustomerAuth::check()): ?>
      <form method="post" action="<?= e(url('me/logout')) ?>">
        <?= csrf_field() ?>
        <button type="submit"
                class="h-11 px-3 rounded-xl text-[12px] font-semibold text-ink-500 hover:text-ink-800 tap">
          خروج
        </button>
      </form>
    <?php endif; ?>
  </header>

  <?php include BASE_PATH.'/resources/views/components/customer-navigation.php'; ?>
  <main id="main-content" class="flex-1 px-5 pb-6">
    <?php if ($ok = flash('success')): ?>
      <div role="status"
           class="flex items-start gap-2 bg-green-50 text-green-800 text-sm rounded-xl px-4 py-3 mb-4 border border-green-100">
        <?= icon('check', 'w-4 h-4 mt-0.5 shrink-0') ?>
        <span><?= e($ok) ?></span>
      </div>
    <?php endif; ?>

    <?php if ($error = flash('error')): ?><p role="alert" class="glass p-4 mb-4"><?= e($error) ?></p><?php endif; ?>
    <?= $content ?>
  </main>

  <footer class="text-center pb-5 pt-2">
    <span class="text-[12px] text-ink-400">رشن — زمانِ راست می‌گوید</span>
  </footer>
</div>

<script src="<?= e(asset('js/network-status.js')) ?>" defer></script>
<script src="<?= e(asset('js/comfort.js')) ?>" defer></script>
</body>
</html>
