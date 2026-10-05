<?php
/**
 * قالب صفحه‌های عمومی پلتفرم: کشف سالن و ناحیهٔ مشتری.
 *
 * مال هیچ سالنی نیست، پس رنگ پیش‌فرض رشن می‌نشیند. منوی مشتری روی
 * موبایل پایین صفحه است (سه مقصد) و روی دسکتاپ در نوار بالا.
 */
use App\Domain\Customer\CustomerAuth;

$wide = $wide ?? true;
$customerNav = [
    ['discover', 'کشف سالن', 'search', !is_path('me') && !is_path('me/favorites')],
    ['me', 'نوبت‌های من', 'calendar-days', is_path('me') && !is_path('me/favorites')],
    ['me/favorites', 'ذخیره‌شده‌ها', 'heart', is_path('me/favorites')],
];
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr(null) ?> data-sw="<?= e(sw_url()) ?>">
<head>
<?php include BASE_PATH . '/resources/views/components/head.php'; ?>
</head>
<body>
<?php include BASE_PATH . '/resources/views/components/body-start.php'; ?>
<div class="public public--with-tabbar">
  <header class="topbar">
    <div class="topbar__inner <?= $wide ? '' : 'topbar__inner--narrow' ?>">
      <a class="salon-identity" href="<?= e(url('discover')) ?>">
        <span class="brand-mark"><?php if ($brandLogo = App\Domain\System\SiteSettings::logoPath()): ?><img src="<?= e(url($brandLogo)) ?>" alt=""><?php else: ?><?= icon('scissors') ?><?php endif; ?></span>
        <span class="salon-identity__name"><?= e(brand()) ?></span>
      </a>
      <nav class="topbar__nav" aria-label="منوی مشتری">
        <?php foreach ($customerNav as [$path, $label, $symbol, $active]): ?>
          <a href="<?= e(url($path)) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?>
      <?php if (CustomerAuth::check()): ?>
        <form method="post" action="<?= e(url('me/logout')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn--ghost btn--sm" type="submit">خروج</button>
        </form>
      <?php endif; ?>
    </div>
  </header>

  <main id="main" class="public__main<?= $wide ? ' public__main--wide' : '' ?>" tabindex="-1">
    <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
    <?= $content ?>
  </main>

  <?php include BASE_PATH . '/resources/views/components/site-footer.php'; ?>
</div>

<nav class="tabbar" aria-label="منوی مشتری">
  <?php foreach ($customerNav as [$path, $label, $symbol, $active]): ?>
    <a class="tabbar__item" href="<?= e(url($path)) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?><span><?= e($label) ?></span></a>
  <?php endforeach; ?>
</nav>
<?php include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
