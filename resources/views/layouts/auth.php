<?php
/**
 * قالب ورود کارکنان، ثبت سالن و انتخاب سالن.
 *
 * @var bool|null $wideCard  کارت پهن‌تر (راه‌اندازی سالن)
 */
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr($theme ?? null) ?>>
<head>
<?php include BASE_PATH . '/resources/views/components/head.php'; ?>
</head>
<body>
<?php include BASE_PATH . '/resources/views/components/body-start.php'; ?>
<div class="auth__corner"><?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?></div>
<main id="main" class="auth" tabindex="-1">
  <div class="auth__card<?= !empty($wideCard) ? ' auth__card--wide' : '' ?>">
    <div class="auth__brand">
      <span class="brand-mark brand-mark--lg"><?php if ($brandLogo = App\Domain\System\SiteSettings::logoPath()): ?><img src="<?= e(url($brandLogo)) ?>" alt=""><?php else: ?><?= icon('scissors') ?><?php endif; ?></span>
      <div><p class="title-md"><?= e(brand()) ?></p><p class="text-sm muted"><?= e(App\Domain\System\SiteSettings::tagline()) ?></p></div>
    </div>
    <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
    <div class="card"><div class="card__body"><?= $content ?></div></div>
  </div>
</main>
<?php $withInstall = false; include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
