<?php
/**
 * @var string $tab
 * @var array  $env
 * @var array  $system
 */
use App\Http\Controllers\PlatformSettingsController as PSC;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">تنظیمات سایت</h1>
    <p class="page-head__sub">همه‌چیز از همین‌جا؛ دیگر لازم نیست فایل .env را ویرایش کنید. مقداری که اینجا خالی بماند، از .env خوانده می‌شود.</p>
  </div>
</div>

<nav class="tabs" aria-label="بخش‌های تنظیمات">
  <?php foreach (PSC::TABS as $key => [$label, $symbol]): ?>
    <a class="tab" href="<?= e(url('platform/settings?tab=' . $key)) ?>" <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?> <?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<div class="container-md">
  <?= App\Core\View::render('platform.settings._' . $tab, ['env' => $env, 'system' => $system]) ?>
</div>
