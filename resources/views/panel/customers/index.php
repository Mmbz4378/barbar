<?php /** @var array $customers @var bool $hasNext @var int $page @var string $q @var int $total */ ?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">مشتریان</h1>
    <p class="page-head__sub"><?= e(fa_num($total)) ?> پرونده · مرتب‌شده بر اساس آخرین مراجعه</p>
  </div>
</div>

<form method="get" action="<?= e(url('panel/customers')) ?>" class="mb-4" role="search">
  <label class="input-search">
    <?= icon('search') ?>
    <span class="sr-only">جست‌وجوی مشتری با نام یا شماره</span>
    <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="نام یا شمارهٔ موبایل…" autocomplete="off">
  </label>
</form>

<?php if ($customers === []): ?>
  <div class="card"><?= partial('empty-state', $q !== ''
      ? ['icon' => 'search', 'title' => 'مشتری‌ای پیدا نشد', 'text' => 'نام یا بخشی از شماره را امتحان کنید.', 'actionHref' => url('panel/customers'), 'actionLabel' => 'همهٔ مشتریان']
      : ['icon' => 'users', 'title' => 'هنوز مشتری‌ای ثبت نشده', 'text' => 'با اولین رزرو یا پذیرش حضوری، پروندهٔ مشتری خودکار ساخته می‌شود.']) ?></div>
<?php else: ?>
  <div id="customer-list" data-skeleton-region>
  <template data-skeleton-tpl><?= partial('skeleton', ['variant' => 'list', 'count' => 6]) ?></template>
  <ul class="card list">
    <?php foreach ($customers as $c): ?>
      <li><a class="list-row" href="<?= e(url('panel/customers/' . $c['id'])) ?>">
        <span class="avatar avatar--sm avatar--any" aria-hidden="true"><?= e(initial($c['name'] ?: '؟')) ?></span>
        <span class="list-row__body">
          <span class="list-row__title"><?= e($c['name'] ?: 'بدون نام') ?><?php if ((int) $c['no_show_count'] > 0): ?> <span class="badge badge--warning"><?= e(fa_num($c['no_show_count'])) ?> غیبت</span><?php endif; ?></span>
          <span class="list-row__meta ltr num"><?= $c['phone'] ? e(phone_local($c['phone'])) : '—' ?></span>
        </span>
        <span class="list-row__end text-sm muted"><span><?= e(fa_num($c['visit_count'])) ?> مراجعه<?php if ($c['last_visit_at']): ?><br><?= e(jdate($c['last_visit_at'], 'j M Y')) ?><?php endif; ?></span><?= icon('chevron-end', 'list-row__chevron') ?></span>
      </a></li>
    <?php endforeach; ?>
  </ul>
  </div>
  <div class="mt-4"><?= partial('pagination', ['page' => $page, 'hasNext' => $hasNext, 'url' => static fn (int $p): string => url('panel/customers?' . http_build_query(['q' => $q, 'page' => $p])), 'label' => 'صفحه‌های فهرست مشتریان', 'skeletonFor' => 'customer-list']) ?></div>
<?php endif; ?>
