<?php
/** @var array $memberships */
$isAdmin = App\Core\Auth::isPlatformAdmin();
$canCreate = App\Domain\System\SiteSettings::registrationMode() !== App\Domain\System\SiteSettings::REG_CLOSED;
?>
<div class="stack">
  <div class="stack stack-xs">
    <h1 class="title-md">انتخاب پنل</h1>
    <p class="text-sm muted"><?= $isAdmin ? 'مدیر کل سامانه هستید و در سالن هم عضویت دارید. کدام را باز کنیم؟' : 'در چند سالن عضو هستید. کدام را باز کنیم؟' ?></p>
  </div>
  <ul class="list card card--flat">
    <?php if ($isAdmin): ?>
      <li><a class="list-row" href="<?= e(url('platform')) ?>">
        <span class="icon-tile"><?= icon('shield') ?></span>
        <span class="list-row__body"><span class="list-row__title">پنل مدیریت کل</span><span class="list-row__meta">همهٔ سالن‌ها، کاربران، تنظیمات و گزارش‌ها</span></span>
        <?= icon('chevron-end', 'list-row__chevron') ?>
      </a></li>
    <?php endif; ?>
    <?php foreach ($memberships as $m): ?>
      <li><a class="list-row" href="<?= e(url('salons/' . $m['salon_id'] . '/switch')) ?>">
        <span class="icon-tile"><?= icon('store') ?></span>
        <span class="list-row__body"><span class="list-row__title"><?= e($m['salon_name']) ?></span><span class="list-row__meta"><?= e(role_label($m['role'])) ?></span></span>
        <?= icon('chevron-end', 'list-row__chevron') ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
  <div class="btn-row justify-between">
    <?php if ($isAdmin): ?>
      <a class="btn btn--ghost" href="<?= e(url('platform/salons/new')) ?>"><?= icon('plus') ?> ساخت سالن تازه</a>
    <?php elseif ($canCreate): ?>
      <a class="btn btn--ghost" href="<?= e(url('onboarding/new')) ?>"><?= icon('plus') ?> ساخت سالن تازه</a>
    <?php endif; ?>
    <a class="btn btn--ghost" href="<?= e(url('account')) ?>"><?= icon('user') ?> حساب من</a>
    <a class="btn btn--ghost" href="<?= e(url('logout')) ?>">خروج</a>
  </div>
</div>
