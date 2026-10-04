<?php
/** قالب صفحه‌های خطا: مستقل از نشست و سالن. */
?>
<!doctype html>
<html lang="fa" dir="rtl" <?= theme_attr($theme ?? null) ?>>
<head>
<?php include BASE_PATH . '/resources/views/components/head.php'; ?>
</head>
<body>
<?php include BASE_PATH . '/resources/views/components/body-start.php'; ?>
<main id="main" class="auth" tabindex="-1">
  <div class="auth__card"><div class="card"><?= $content ?></div></div>
</main>
<?php $withInstall = false; include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
