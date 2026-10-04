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
<div style="position:fixed;inset-block-start:12px;inset-inline-end:12px"><?php include BASE_PATH . '/resources/views/components/mode-toggle.php'; ?></div>
<main id="main" class="auth" tabindex="-1">
  <div class="auth__card" <?= !empty($wideCard) ? 'style="max-width:640px"' : '' ?>>
    <div class="auth__brand">
      <span class="brand-mark" style="width:56px;height:56px;border-radius:16px"><?= icon('scissors') ?></span>
      <div><p class="title-md">رشن</p><p class="text-sm muted">مدیریت نوبت آرایشگاه و سالن زیبایی</p></div>
    </div>
    <?php include BASE_PATH . '/resources/views/components/flash.php'; ?>
    <div class="card"><div class="card__body"><?= $content ?></div></div>
  </div>
</main>
<?php $withInstall = false; include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
