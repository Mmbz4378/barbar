<?php
/**
 * قالب پنل پلتفرم — بیرون از هر سالن.
 *
 * صاحب سالنِ نصب تک‌سالنی هم برای «به‌روزرسانی سامانه» همین قالب را
 * می‌بیند، ولی فقط با همان یک مقصد و بازگشت به پنل سالن.
 */
$isAdmin = App\Core\Auth::isPlatformAdmin();
$updateBadge = (new App\Domain\System\Updater())->available() !== null;
$items = $isAdmin ? [
    ['/platform', 'سالن‌ها', 'store', true, 'سالن‌ها'],
    ['/platform/moderation', 'بررسی انتشار و نظرها', 'shield', false, 'بررسی'],
    ['/platform/holidays', 'تعطیلات رسمی', 'calendar', false, 'تعطیلات'],
    ['/system/updates', 'به‌روزرسانی سامانه', 'refresh', false, 'نسخه'],
    ['/system/design', 'سیستم طراحی', 'palette', false, 'طراحی'],
] : [
    ['/panel', 'بازگشت به پنل سالن', 'home', true, 'پنل'],
    ['/system/updates', 'به‌روزرسانی سامانه', 'refresh', false, 'نسخه'],
    ['/system/design', 'سیستم طراحی', 'palette', false, 'طراحی'],
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
    <div class="sidebar__brand"><span class="brand-mark"><?= icon('shield') ?></span><span class="brand-text"><strong><?= $isAdmin ? 'پنل پلتفرم رشن' : 'مدیریت سامانه' ?></strong><span><?= $isAdmin ? 'مدیر کل' : 'نسخهٔ ' . e(App\Support\Version::current()) ?></span></span></div>
    <nav class="sidebar__nav">
      <?php foreach ($items as [$href, $label, $symbol, $exact]): ?>
        <a class="nav-item" href="<?= e(url($href)) ?>" <?= is_path($href, $exact) ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?><span><?= e($label) ?></span><?php if ($href === '/system/updates' && $updateBadge): ?><span class="badge badge--accent">تازه</span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer"><a class="nav-item" href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a></div>
  </aside>
  <div class="app__main">
    <header class="appbar">
      <div class="appbar__title"><span class="brand-text only-mobile"><strong><?= $isAdmin ? 'پنل پلتفرم' : 'مدیریت سامانه' ?></strong></span></div>
      <div class="appbar__actions"><?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?></div>
    </header>
    <main id="main" class="app__content" tabindex="-1">
      <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<nav class="tabbar" aria-label="ناوبری پلتفرم">
  <?php foreach ($items as [$href, $label, $symbol, $exact, $short]): if ($href === '/system/design') { continue; } // ابزار توسعه؛ فقط در منوی دسکتاپ ?>
    <a class="tabbar__item" href="<?= e(url($href)) ?>" <?= is_path($href, $exact) ? 'aria-current="page"' : '' ?> aria-label="<?= e($label) ?>"><?= icon($symbol) ?><span><?= e($short) ?></span><?php if ($href === '/system/updates' && $updateBadge): ?><span class="tabbar__badge" aria-label="نسخهٔ تازه">۱</span><?php endif; ?></a>
  <?php endforeach; ?>
  <a class="tabbar__item" href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a>
</nav>
<?php $withInstall = false; include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
