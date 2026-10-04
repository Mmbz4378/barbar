<?php
/**
 * قالب صفحه‌های عمومی یک سالن: مسیر رزرو، منوی خدمات و کارت نوبت.
 *
 * رنگ برند همان سالن است؛ سطح‌ها و رنگ‌های وضعیت مستقل می‌مانند.
 *
 * @var array $salon
 * @var bool|null $wide  عرض دوستونی برای مسیر رزرو روی دسکتاپ
 */
use App\Support\SalonContext;

SalonContext::set($salon);
$logo = salon_logo_url($salon['logo_file'] ?? null);
$wide = $wide ?? false;
$place = trim(implode('، ', array_filter([$salon['neighborhood'] ?? null, $salon['city'] ?? null])));
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr($salon['theme'] ?? null) ?> data-sw="<?= e(url('service-worker.js')) ?>">
<head>
<?php $pwaSlug = $salon['slug']; $pwaTitle = $salon['name']; $description = $salon['introduction'] ?? ('رزرو آنلاین نوبت در ' . $salon['name']); include BASE_PATH . '/resources/views/components/head.php'; ?>
</head>
<body>
<?php include BASE_PATH . '/resources/views/components/body-start.php'; ?>
<div class="public">
  <header class="topbar">
    <div class="topbar__inner <?= $wide ? 'topbar__inner--wizard' : 'topbar__inner--narrow' ?>">
      <a class="salon-identity" href="<?= e(url('s/' . $salon['slug'])) ?>">
        <span class="brand-mark"><?= $logo ? '<img src="' . e($logo) . '" alt="">' : icon(($salon['audience'] ?? 'men') === 'women' ? 'sparkles' : 'scissors') ?></span>
        <span class="stack stack-xs" style="min-width:0">
          <span class="salon-identity__name"><?= e($salon['name']) ?></span>
          <span class="salon-identity__meta"><?= e(term('salon_type')) ?><?= $place !== '' ? ' · ' . e($place) : '' ?></span>
        </span>
      </a>
      <?php if (!empty($salon['phone'])): ?>
        <a class="btn btn--ghost btn--icon" href="tel:<?= e($salon['phone']) ?>" aria-label="تماس با سالن"><?= icon('phone') ?></a>
      <?php endif; ?>
      <?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?>
    </div>
  </header>

  <main id="main" class="public__main<?= $wide ? ' public__main--wizard' : '' ?>" tabindex="-1">
    <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
    <?= $content ?>
  </main>

  <footer class="public__footer">
    <div class="cluster" style="justify-content:center;--gap:16px">
      <a href="<?= e(url('s/' . $salon['slug'] . '/menu')) ?>">خدمات و قیمت‌ها</a>
      <a href="<?= e(url('me')) ?>">نوبت‌های من</a>
      <?php if (($salon['publication_status'] ?? '') === 'published'): ?><a href="<?= e(url('salons/view/' . $salon['slug'])) ?>">معرفی سالن</a><?php endif; ?>
    </div>
    <p class="mt-2">رزرو با رشن</p>
  </footer>
</div>
<?php include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
