<?php
/**
 * قالب پنل پلتفرم — بیرون از هر سالن.
 *
 * صاحب سالنِ نصب تک‌سالنی هم برای «به‌روزرسانی سامانه» همین قالب را
 * می‌بیند، ولی فقط با همان یک مقصد و بازگشت به پنل سالن.
 */
$isAdmin = App\Core\Auth::isPlatformAdmin();
$updateBadge = (new App\Domain\System\Updater())->available() !== null;
$groups = $isAdmin ? App\Support\PlatformNavigation::groups() : [
    'سامانه' => [
        ['href' => '/panel', 'label' => 'بازگشت به پنل سالن', 'icon' => 'home', 'exact' => true],
        ['href' => '/system/updates', 'label' => 'به‌روزرسانی سامانه', 'icon' => 'refresh', 'exact' => false],
        ['href' => '/system/design', 'label' => 'سیستم طراحی', 'icon' => 'palette', 'exact' => false],
    ],
];
$primary = $isAdmin ? App\Support\PlatformNavigation::primary() : $groups['سامانه'];
$more = $isAdmin ? App\Support\PlatformNavigation::more() : [];
$isActive = static fn (array $i): bool => is_path($i['href'], $i['exact']);
$moreActive = array_filter($more, $isActive) !== [];
$brand = App\Domain\System\SiteSettings::brandName();
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
    <div class="sidebar__brand"><span class="brand-mark"><?= icon('shield') ?></span><span class="brand-text"><strong><?= $isAdmin ? 'مدیریت ' . e($brand) : 'مدیریت سامانه' ?></strong><span><?= $isAdmin ? 'مدیر کل' : 'نسخهٔ ' . e(App\Support\Version::current()) ?></span></span></div>
    <nav class="sidebar__nav">
      <?php foreach ($groups as $label => $items): ?>
        <div class="sidebar__group">
          <?php if (count($groups) > 1): ?><p class="sidebar__label"><?= e($label) ?></p><?php endif; ?>
          <?php foreach ($items as $i): ?>
            <a class="nav-item" href="<?= e(url($i['href'])) ?>" <?= $isActive($i) ? 'aria-current="page"' : '' ?>><?= icon($i['icon']) ?><span><?= e($i['label']) ?></span><?php if ($i['href'] === '/system/updates' && $updateBadge): ?><span class="badge badge--accent">تازه</span><?php endif; ?></a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer"><a class="nav-item" href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a></div>
  </aside>
  <div class="app__main">
    <header class="appbar">
      <div class="appbar__title"><span class="brand-text only-mobile"><strong><?= $isAdmin ? 'مدیریت ' . e($brand) : 'مدیریت سامانه' ?></strong></span></div>
      <div class="appbar__actions"><?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?></div>
    </header>
    <main id="main" class="app__content" tabindex="-1">
      <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<nav class="tabbar" aria-label="ناوبری پلتفرم">
  <?php foreach ($primary as $i): if ($i['href'] === '/system/design') { continue; } ?>
    <a class="tabbar__item" href="<?= e(url($i['href'])) ?>" <?= $isActive($i) ? 'aria-current="page"' : '' ?>><?= icon($i['icon']) ?><span><?= e($i['label']) ?></span><?php if ($i['href'] === '/system/updates' && $updateBadge): ?><span class="tabbar__badge" aria-label="نسخهٔ تازه">۱</span><?php endif; ?></a>
  <?php endforeach; ?>
  <?php if ($more !== []): ?>
    <button type="button" class="tabbar__item" data-open="platform-more" aria-haspopup="dialog" <?= $moreActive ? 'aria-current="page"' : '' ?>><?= icon('menu') ?><span>بیشتر</span></button>
  <?php else: ?>
    <a class="tabbar__item" href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a>
  <?php endif; ?>
</nav>
<?php if ($more !== []): ?>
<dialog class="sheet" id="platform-more" aria-labelledby="platform-more-title">
  <div class="sheet__handle" aria-hidden="true"></div>
  <div class="sheet__head">
    <h2 class="sheet__title" id="platform-more-title">بخش‌های دیگر</h2>
    <button type="button" class="btn btn--ghost btn--icon" data-close aria-label="بستن"><?= icon('x') ?></button>
  </div>
  <div class="sheet__body">
    <nav class="menu-grid" aria-label="بخش‌های دیگر مدیریت">
      <?php foreach ($more as $i): if ($i['href'] === '/system/design') { continue; } ?>
        <a href="<?= e(url($i['href'])) ?>" <?= $isActive($i) ? 'aria-current="page"' : '' ?>><?= icon($i['icon']) ?><span><?= e($i['label']) ?></span></a>
      <?php endforeach; ?>
      <a href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a>
    </nav>
  </div>
</dialog>
<?php endif; ?>
<?php $withInstall = false; include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
