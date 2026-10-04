<?php /** @var array $memberships */ ?>
<div class="stack">
  <div class="stack stack-xs">
    <h1 class="title-md">انتخاب سالن</h1>
    <p class="text-sm muted">در چند سالن عضو هستید. کدام را باز کنیم؟</p>
  </div>
  <ul class="list card card--flat">
    <?php foreach ($memberships as $m): ?>
      <li><a class="list-row" href="<?= e(url('salons/' . $m['salon_id'] . '/switch')) ?>">
        <span class="icon-tile"><?= icon('store') ?></span>
        <span class="list-row__body"><span class="list-row__title"><?= e($m['salon_name']) ?></span><span class="list-row__meta"><?= e(role_label($m['role'])) ?></span></span>
        <?= icon('chevron-end', 'list-row__chevron') ?>
      </a></li>
    <?php endforeach; ?>
  </ul>
  <div class="btn-row justify-between">
    <a class="btn btn--ghost" href="<?= e(url('onboarding/new')) ?>"><?= icon('plus') ?> ساخت سالن تازه</a>
    <a class="btn btn--ghost" href="<?= e(url('logout')) ?>">خروج</a>
  </div>
</div>
