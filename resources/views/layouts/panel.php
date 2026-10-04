<?php
/**
 * قالب پنل سالن.
 *
 * دسکتاپ (≥۱۰۲۴): نوار کناری با گروه‌های «کارهای روزانه / مدیریت /
 * ابزارها». موبایل و تبلت: نوار بالا + منوی پایین با حداکثر پنج مقصد؛
 * بقیه در شیتِ «بیشتر».
 */
use App\Core\Auth;
use App\Domain\Access\PanelNavigation;
use App\Support\SalonContext;

$nav = PanelNavigation::build();
$salon = SalonContext::get();
$salonName = SalonContext::name();
$logo = salon_logo_url($salon['logo_file'] ?? null);
$mark = ($salon['audience'] ?? 'men') === 'women' ? 'sparkles' : 'scissors';
$isActive = static fn (array $item): bool => is_path($item['href'], !empty($item['exact']));
$moreActive = array_filter($nav['more'], $isActive) !== [];
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr(SalonContext::theme()) ?> data-sw="<?= e(url('service-worker.js')) ?>">
<head>
<?php $pwaTitle = $salonName; include BASE_PATH . '/resources/views/components/head.php'; ?>
</head>
<body>
<?php include BASE_PATH . '/resources/views/components/body-start.php'; ?>

<div class="app">
  <aside class="app__sidebar" aria-label="ناوبری پنل">
    <div class="sidebar__brand">
      <span class="brand-mark"><?= $logo ? '<img src="' . e($logo) . '" alt="">' : icon($mark) ?></span>
      <span class="brand-text">
        <strong><?= e($salonName) ?></strong>
        <span><?= e(PanelNavigation::roleLabel()) ?> · <?= e(PanelNavigation::audienceLabel()) ?></span>
      </span>
    </div>
    <nav class="sidebar__nav">
      <?php foreach ($nav['groups'] as $label => $items): ?>
        <div class="sidebar__group">
          <p class="sidebar__label"><?= e($label) ?></p>
          <?php foreach ($items as $item): ?>
            <a class="nav-item" href="<?= e(url($item['href'])) ?>" <?= $isActive($item) ? 'aria-current="page"' : '' ?>>
              <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
              <?php if (!empty($item['badge'])): ?><span class="badge badge--danger"><?= e(fa_num($item['badge'])) ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer stack stack-xs">
      <?php if (Auth::isPlatformAdmin()): ?>
        <a class="nav-item" href="<?= e(url('platform')) ?>"><?= icon('shield') ?><span>پنل پلتفرم</span></a>
      <?php endif; ?>
      <?php if (count(Auth::memberships()) > 1): ?>
        <a class="nav-item" href="<?= e(url('salons')) ?>"><?= icon('store') ?><span>تغییر سالن</span></a>
      <?php endif; ?>
      <a class="nav-item" href="<?= e(url('logout')) ?>"><?= icon('logout') ?><span>خروج</span></a>
    </div>
  </aside>

  <div class="app__main">
    <header class="appbar">
      <div class="appbar__title">
        <span class="brand-mark brand-mark--sm only-mobile"><?= $logo ? '<img src="' . e($logo) . '" alt="">' : icon($mark) ?></span>
        <span class="brand-text only-mobile"><strong><?= e($salonName) ?></strong><span><?= e(PanelNavigation::roleLabel()) ?></span></span>
      </div>
      <div class="appbar__actions">
        <?php if (!empty($salon['slug'])): ?>
          <a class="btn btn--ghost btn--icon only-desktop" href="<?= e(url('s/' . $salon['slug'])) ?>" target="_blank" rel="noopener" aria-label="صفحهٔ رزرو سالن (پنجرهٔ تازه)" title="صفحهٔ رزرو سالن"><?= icon('external') ?></a>
        <?php endif; ?>
        <?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?>
        <a class="btn btn--ghost btn--icon only-mobile" href="<?= e(url('logout')) ?>" aria-label="خروج از حساب"><?= icon('logout') ?></a>
      </div>
    </header>

    <?php if (Auth::isImpersonating()): ?>
      <div class="banner" role="status">
        <span><?= icon('shield', 'icon') ?> حالت پشتیبانی — در حال مشاهدهٔ «<?= e($salonName) ?>»</span>
        <a class="btn btn--sm btn--secondary" href="<?= e(url('platform/impersonate/stop')) ?>">خروج از پشتیبانی</a>
      </div>
    <?php endif; ?>

    <main id="main" class="app__content" tabindex="-1">
      <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>

<nav class="tabbar" aria-label="ناوبری اصلی">
  <?php foreach ($nav['primary'] as $item): ?>
    <a class="tabbar__item" href="<?= e(url($item['href'])) ?>" <?= $isActive($item) ? 'aria-current="page"' : '' ?>>
      <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
      <?php if (!empty($item['badge'])): ?><span class="tabbar__badge" aria-label="<?= e(fa_num($item['badge'])) ?> مورد"><?= e(fa_num($item['badge'])) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <?php if ($nav['more'] !== []): ?>
    <button type="button" class="tabbar__item" data-open="more-sheet" aria-haspopup="dialog" <?= $moreActive ? 'aria-current="page"' : '' ?>>
      <?= icon('menu') ?><span>بیشتر</span>
    </button>
  <?php endif; ?>
</nav>

<?php if ($nav['more'] !== []): ?>
<dialog class="sheet" id="more-sheet" aria-labelledby="more-sheet-title">
  <div class="sheet__handle" aria-hidden="true"></div>
  <div class="sheet__head">
    <h2 class="sheet__title" id="more-sheet-title">بخش‌های دیگر</h2>
    <button type="button" class="btn btn--ghost btn--icon" data-close aria-label="بستن"><?= icon('x') ?></button>
  </div>
  <div class="sheet__body">
    <nav class="menu-grid" aria-label="بخش‌های دیگر پنل">
      <?php foreach ($nav['more'] as $item): ?>
        <a href="<?= e(url($item['href'])) ?>" <?= $isActive($item) ? 'aria-current="page"' : '' ?>><?= icon($item['icon']) ?><span><?= e($item['label']) ?></span></a>
      <?php endforeach; ?>
      <?php if (count(Auth::memberships()) > 1): ?>
        <a href="<?= e(url('salons')) ?>"><?= icon('store') ?><span>تغییر سالن</span></a>
      <?php endif; ?>
      <?php if (Auth::isPlatformAdmin()): ?>
        <a href="<?= e(url('platform')) ?>"><?= icon('shield') ?><span>پنل پلتفرم</span></a>
      <?php endif; ?>
    </nav>
  </div>
</dialog>
<?php endif; ?>

<?php include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
