<?php /** @var array $pages @var array $suggested */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">صفحه‌ها</h1>
    <p class="page-head__sub">صفحه‌های محتوایی سایت، مثل «درباره»، «قوانین» و «حریم خصوصی». صفحه‌های منتشرشده در پانویس همهٔ صفحه‌های عمومی و نقشهٔ سایت می‌آیند.</p>
  </div>
  <div class="page-head__actions"><a class="btn btn--primary" href="<?= e(url('platform/pages/new')) ?>"><?= icon('plus') ?> صفحهٔ تازه</a></div>
</div>
<div class="stack stack-lg">
  <?php if ($suggested !== []): ?>
    <div class="card card--flat"><div class="card__body cluster" style="--gap:8px">
      <span class="text-sm muted">پیشنهاد:</span>
      <?php foreach ($suggested as $slug => $title): ?><a class="chip" href="<?= e(url('platform/pages/new?slug=' . rawurlencode($slug))) ?>"><?= icon('plus') ?> <?= e($title) ?></a><?php endforeach; ?>
    </div></div>
  <?php endif; ?>
  <?php if ($pages === []): ?>
    <?= partial('empty-state', ['icon' => 'layers', 'title' => 'هنوز صفحه‌ای نساخته‌اید', 'text' => 'دست‌کم «قوانین» و «حریم خصوصی» را بسازید؛ برای درگاه پرداخت و اینماد هم لازم‌اند.']) ?>
  <?php else: ?>
    <ul class="list card" role="list">
      <?php foreach ($pages as $p): ?>
        <li class="list-row<?= (int) $p['is_published'] !== 1 ? ' is-inactive' : '' ?>">
          <span class="icon-tile"><?= icon('layers') ?></span>
          <a class="list-row__body link-plain" href="<?= e(url('platform/pages/' . $p['id'] . '/edit')) ?>">
            <span class="list-row__title"><?= e($p['title']) ?></span>
            <span class="list-row__meta"><span class="ltr">/p/<?= e($p['slug']) ?></span> · <?= (int) $p['is_published'] === 1 ? 'منتشرشده' : 'پیش‌نویس' ?><?= (int) $p['show_in_footer'] === 1 ? ' · در پانویس' : '' ?> · ویرایش <?= e(jdate((string) $p['updated_at'], 'Y/m/d')) ?></span>
          </a>
          <span class="list-row__end"><?php if ((int) $p['is_published'] === 1): ?><a class="btn btn--ghost btn--icon" href="<?= e(url('p/' . $p['slug'])) ?>" target="_blank" rel="noopener" aria-label="نمایش «<?= e($p['title']) ?>» در سایت"><?= icon('external') ?></a><?php endif; ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
