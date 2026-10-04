<?php
/**
 * @var array $salons
 * @var array $metrics
 * @var array $filters
 * @var int $total
 * @var int $page
 * @var int $pages
 */
use App\Support\Audience;

$plans = ['trial' => 'آزمایشی', 'basic' => 'پایه', 'pro' => 'حرفه‌ای', 'free' => 'رایگان'];

$pubLabels = ['draft' => ['پیش‌نویس', ''], 'pending' => ['در انتظار بررسی', 'badge--warning'], 'published' => ['منتشرشده', 'badge--success'], 'rejected' => ['رد شده', 'badge--danger']];
$qs = static fn (array $over) => http_build_query(array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== null && $v !== 1));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">سالن‌ها</h1>
    <p class="page-head__sub"><?= e(fa_num($total)) ?> سالن<?= array_filter($filters) ? ' با این فیلتر' : '' ?></p>
  </div>
  <?php if ($metrics['pending'] > 0): ?>
    <div class="page-head__actions"><a class="btn btn--primary" href="<?= e(url('platform/moderation')) ?>"><?= icon('shield') ?> بررسی <?= e(fa_num($metrics['pending'])) ?> مورد</a></div>
  <?php endif; ?>
</div>

<div class="stack stack-lg">
  <div class="stats" style="--cols:4">
    <div class="stat stat--accent">
      <span class="stat__label"><?= icon('store') ?> سالن فعال</span>
      <span class="stat__value num"><?= e(fa_num($metrics['active_salons'])) ?></span>
      <span class="stat__hint">مردانه <?= e(fa_num($metrics['by_audience']['men'])) ?> · بانوان <?= e(fa_num($metrics['by_audience']['women'])) ?> · هر دو <?= e(fa_num($metrics['by_audience']['unisex'])) ?></span>
    </div>
    <div class="stat">
      <span class="stat__label"><?= icon('circle-check') ?> مراجعهٔ انجام‌شده (۷ روز)</span>
      <span class="stat__value num"><?= e(fa_num($metrics['completed_this_week'])) ?></span>
    </div>
    <div class="stat">
      <span class="stat__label"><?= icon('clock') ?> خطای تخمین زمان</span>
      <span class="stat__value num <?= $metrics['mae_minutes'] !== null && $metrics['mae_minutes'] > 12 ? 'danger-text' : '' ?>"><?= $metrics['mae_minutes'] !== null ? e(fa_num($metrics['mae_minutes'])) . ' <span class="text-sm muted">دقیقه</span>' : '—' ?></span>
      <span class="stat__hint">میانگین اختلاف شروع واقعی با تخمین (۳۰ روز)</span>
    </div>
    <div class="stat">
      <span class="stat__label"><?= icon('list') ?> نرخ ثبت پایان</span>
      <span class="stat__value num <?= $metrics['end_registration_rate'] !== null && $metrics['end_registration_rate'] < 70 ? 'danger-text' : '' ?>"><?= $metrics['end_registration_rate'] !== null ? e(fa_num($metrics['end_registration_rate'])) . '٪' : '—' ?></span>
      <span class="stat__hint"><?= $metrics['low_sms'] > 0 ? e(fa_num($metrics['low_sms'])) . ' سالن اعتبار پیامک کم دارند' : 'نوبت‌هایی که پایانشان ثبت شده' ?></span>
    </div>
  </div>

  <form method="get" action="<?= e(url('platform')) ?>" class="card card--flat"><div class="card__body cluster" style="--gap:12px">
    <div class="field grow-200">
      <label class="sr-only" for="pf-q">جست‌وجو</label>
      <input class="input input-search" id="pf-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="نام، نشانی صفحه، شهر یا تلفن">
    </div>
    <label class="sr-only" for="pf-status">وضعیت</label>
    <select class="select select--compact" id="pf-status" name="status">
      <option value="">همهٔ وضعیت‌ها</option>
      <?php foreach (['published' => 'منتشرشده', 'pending' => 'در انتظار بررسی', 'draft' => 'پیش‌نویس', 'rejected' => 'رد شده', 'inactive' => 'غیرفعال'] as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="sr-only" for="pf-aud">نوع سالن</label>
    <select class="select select--compact" id="pf-aud" name="audience">
      <option value="">همهٔ انواع</option>
      <?php foreach (Audience::options() as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['audience'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn--secondary" type="submit"><?= icon('filter') ?> اعمال</button>
    <?php if (array_filter($filters)): ?><a class="btn btn--ghost" href="<?= e(url('platform')) ?>">پاک کردن</a><?php endif; ?>
  </div></form>

  <?php if ($salons === []): ?>
    <?= partial('empty-state', ['icon' => 'store', 'title' => 'سالنی پیدا نشد', 'text' => 'فیلترها را تغییر دهید.']) ?>
  <?php else: ?>
    <div class="table-wrap" id="salon-table" data-skeleton-region>
      <template data-skeleton-tpl><?= partial('skeleton', ['variant' => 'list', 'count' => 6]) ?></template>
      <table class="table table--stack">
        <thead><tr><th scope="col">سالن</th><th scope="col">وضعیت</th><th scope="col" class="num">کارکنان</th><th scope="col" class="num">مراجعه (۳۰ روز)</th><th scope="col" class="num">پیامک</th><th scope="col">عضویت</th></tr></thead>
        <tbody>
          <?php foreach ($salons as $s): [$pubText, $pubClass] = $pubLabels[$s['publication_status']] ?? ['—', '']; ?>
            <tr>
              <td data-label="سالن">
                <a class="row link-plain" style="--gap:10px" href="<?= e(url('platform/' . $s['id'])) ?>">
                  <span class="avatar avatar--sm avatar--square" data-theme="<?= e(App\Support\Theme::resolve($s['theme'])) ?>" aria-hidden="true"><?= e(initial($s['name'])) ?></span>
                  <span class="stack gap-0"><strong><?= e($s['name']) ?></strong><span class="text-xs muted"><?= e(Audience::options()[$s['audience']] ?? '') ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></span></span>
                </a>
              </td>
              <td data-label="وضعیت">
                <?php if (!(int) $s['is_active']): ?><span class="badge badge--danger">غیرفعال</span><?php else: ?><span class="badge <?= e($pubClass) ?>"><?= e($pubText) ?></span><?php endif; ?>
              </td>
              <td data-label="کارکنان" class="num"><?= e(fa_num((int) $s['staff_count'])) ?></td>
              <td data-label="مراجعه (۳۰ روز)" class="num"><?= e(fa_num((int) $s['completed_30d'])) ?></td>
              <td data-label="پیامک" class="num <?= (int) $s['sms_credit'] < 20 ? 'warning-text' : '' ?>"><?= e(fa_num((int) $s['sms_credit'])) ?></td>
              <td data-label="عضویت"><span class="badge badge--outline"><?= e($plans[$s['plan_code']] ?? $s['plan_code']) ?></span> <span class="text-xs muted"><?= e(jdate($s['created_at'], 'Y/m/d')) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'url' => static fn (int $p): string => url('platform?' . $qs(['page' => $p])), 'label' => 'صفحه‌های فهرست سالن‌ها', 'skeletonFor' => 'salon-table']) ?>
  <?php endif; ?>
</div>
