<?php
/** قالب پنل پلتفرم — بیرون از هر سالن. */
$items = [
    ['/platform', 'سالن‌ها', 'store', true],
    ['/platform/moderation', 'بررسی انتشار و نظرها', 'shield', false],
    ['/platform/holidays', 'تعطیلات رسمی', 'calendar', false],
];
?>
<!doctype html>
<html lang="fa" dir="rtl" <?= theme_attr('ink') ?>>
<head>
<?php include BASE_PATH . '/resources/views/components/head.php'; ?>
</head>
<body>
<?php include BASE_PATH . '/resources/views/components/body-start.php'; ?>
<div class="app">
  <aside class="app__sidebar" aria-label="ناوبری پلتفرم">
    <div class="sidebar__brand"><span class="brand-mark"><?= icon('shield') ?></span><span class="brand-text"><strong>پنل پلتفرم رشن</strong><span>مدیر کل</span></span></div>
    <nav class="sidebar__nav">
      <?php foreach ($items as [$href, $label, $symbol, $exact]): ?>
        <a class="nav-item" href="<?= e(url($href)) ?>" <?= is_path($href, $exact) ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer"><a class="nav-item" href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a></div>
  </aside>
  <div class="app__main">
    <header class="appbar">
      <div class="appbar__title"><span class="brand-text only-mobile"><strong>پنل پلتفرم</strong></span></div>
      <div class="appbar__actions"><?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?></div>
    </header>
    <main id="main" class="app__content" tabindex="-1">
      <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<nav class="tabbar" aria-label="ناوبری پلتفرم">
  <?php foreach ($items as [$href, $label, $symbol, $exact]): ?>
    <a class="tabbar__item" href="<?= e(url($href)) ?>" <?= is_path($href, $exact) ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?><span><?= e($label) ?></span></a>
  <?php endforeach; ?>
  <a class="tabbar__item" href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a>
</nav>
<?php $withInstall = false; include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
