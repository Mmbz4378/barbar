<?php
/**
 * صفحهٔ «دسترسی نداری».
 *
 * به عمد نمی‌گوید چه چیزی آن‌طرف در است. اگر بنویسیم «فقط صاحب سالن
 * می‌تواند گزارش‌ها را ببیند»، ساختار دسترسی‌ها را لو داده‌ایم.
 */
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr(null) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>دسترسی ندارید</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/design-tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/refined.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/comfort.css')) ?>">
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="min-h-dvh grid place-items-center px-5" style="background:var(--bg)">
  <?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>
  <main class="w-full max-w-sm text-center">
    <div class="glass rounded-2xl px-6 py-10">
      <?= icon('shield', 'w-10 h-10 mx-auto text-ink-300 mb-4') ?>
      <h1 class="text-[16px] font-extrabold text-ink-900 mb-1.5">دسترسی ندارید</h1>
      <p class="text-[13px] text-ink-500 leading-relaxed mb-6">
        این بخش برای حساب شما باز نیست. اگر فکر می‌کنید اشتباهی شده،
        از صاحب آرایشگاه بخواهید سطح دسترسی‌تان را تغییر دهد.
      </p>
      <a href="<?= e(url('panel')) ?>" class="btn-ink w-full">بازگشت به پنل</a>
    </div>
  </main>
</body>
</html>
