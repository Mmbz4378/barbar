<?php
/**
 * @var array $salons
 * @var array $counts
 * @var array $filters
 * @var int $total
 * @var int $page
 * @var int $pages
 */
use App\Http\Controllers\PlatformSalonController as PSC;
use App\Support\Audience;

$pubLabels = ['draft' => ['پیش‌نویس', ''], 'pending' => ['در انتظار بررسی', 'badge--warning'], 'published' => ['منتشرشده', 'badge--success'], 'rejected' => ['رد شده', 'badge--danger']];
$qs = static fn (array $over) => http_build_query(array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== null && $v !== 1));
$chips = [
    '' => ['همه', $counts['total'] ?? 0],
    'active' => ['فعال', $counts['active'] ?? 0],
    'inactive' => ['غیرفعال', $counts['inactive'] ?? 0],
    'pending' => ['در انتظار بررسی', $counts['pending'] ?? 0],
    'low_sms' => ['اعتبار پیامک کم', $counts['low_sms'] ?? 0],
    'trial_ending' => ['آزمایشیِ رو به پایان', null],
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">سالن‌ها</h1>
    <p class="page-head__sub"><?= e(fa_num($total)) ?> سالن<?= array_filter($filters) ? ' با این فیلتر' : '' ?></p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--primary" href="<?= e(url('platform/salons/new')) ?>"><?= icon('plus') ?> سالن تازه</a>
  </div>
</div>

<div class="stack stack-lg">
  <nav class="chips" aria-label="وضعیت سالن">
    <?php foreach ($chips as $key => [$label, $n]): ?>
      <a class="chip" href="<?= e(url('platform/salons' . (($q = $qs(['status' => $key, 'page' => 1])) !== '' ? '?' . $q : ''))) ?>" <?= $filters['status'] === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?><?php if ($n !== null): ?> <span class="num muted"><?= e(fa_num((int) $n)) ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

  <form method="get" action="<?= e(url('platform/salons')) ?>" class="card card--flat"><div class="card__body cluster" style="--gap:12px">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <div class="field grow-200">
      <label class="sr-only" for="pf-q">جست‌وجو</label>
      <input class="input input-search" id="pf-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="نام سالن، شهر، تلفن، یا نام و موبایل صاحب">
    </div>
    <label class="sr-only" for="pf-aud">نوع سالن</label>
    <select class="select select--compact" id="pf-aud" name="audience">
      <option value="">همهٔ انواع</option>
      <?php foreach (Audience::options() as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['audience'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <label class="sr-only" for="pf-plan">طرح</label>
    <select class="select select--compact" id="pf-plan" name="plan">
      <option value="">همهٔ طرح‌ها</option>
      <?php foreach (PSC::PLANS as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['plan'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn--secondary" type="submit"><?= icon('filter') ?> اعمال</button>
    <?php if (array_filter($filters)): ?><a class="btn btn--ghost" href="<?= e(url('platform/salons')) ?>">پاک کردن</a><?php endif; ?>
  </div></form>

  <?php if ($salons === []): ?>
    <?= partial('empty-state', ['icon' => 'store', 'title' => $total === 0 && !array_filter($filters) ? 'هنوز سالنی نساخته‌اید' : 'سالنی پیدا نشد', 'text' => $total === 0 && !array_filter($filters) ? 'سالن و صاحبش را با دکمهٔ «سالن تازه» بسازید.' : 'فیلترها را تغییر دهید.']) ?>
  <?php else: ?>
    <div class="table-wrap" id="salon-table" data-skeleton-region>
      <template data-skeleton-tpl><?= partial('skeleton', ['variant' => 'list', 'count' => 6]) ?></template>
      <table class="table table--stack">
        <thead><tr><th scope="col">سالن</th><th scope="col">صاحب</th><th scope="col">وضعیت</th><th scope="col" class="num">کارکنان</th><th scope="col" class="num">مراجعه (۳۰ روز)</th><th scope="col" class="num">پیامک</th><th scope="col">طرح</th></tr></thead>
        <tbody>
          <?php foreach ($salons as $s): [$pubText, $pubClass] = $pubLabels[$s['publication_status']] ?? ['—', '']; ?>
            <tr>
              <td data-label="سالن">
                <a class="row link-plain" style="--gap:10px" href="<?= e(url('platform/salons/' . $s['id'])) ?>">
                  <span class="avatar avatar--sm avatar--square" data-theme="<?= e(App\Support\Theme::resolve($s['theme'])) ?>" aria-hidden="true"><?= e(initial($s['name'])) ?></span>
                  <span class="stack gap-0"><strong><?= e($s['name']) ?></strong><span class="text-xs muted"><?= e(Audience::options()[$s['audience']] ?? '') ?><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></span></span>
                </a>
              </td>
              <td data-label="صاحب"><?php if ($s['owner_phone']): ?><span class="stack gap-0"><span><?= e($s['owner_name'] ?: 'بی‌نام') ?></span><span class="text-xs muted ltr num"><?= e(phone_local((string) $s['owner_phone'])) ?></span></span><?php else: ?><span class="badge badge--warning">بی‌صاحب</span><?php endif; ?></td>
              <td data-label="وضعیت">
                <?php if (!(int) $s['is_active']): ?><span class="badge badge--danger">غیرفعال</span><?php else: ?><span class="badge <?= e($pubClass) ?>"><?= e($pubText) ?></span><?php endif; ?>
              </td>
              <td data-label="کارکنان" class="num"><?= e(fa_num((int) $s['staff_count'])) ?></td>
              <td data-label="مراجعه (۳۰ روز)" class="num"><?= e(fa_num((int) $s['completed_30d'])) ?></td>
              <td data-label="پیامک" class="num <?= (int) $s['sms_credit'] < 20 ? 'warning-text' : '' ?>"><?= e(fa_num((int) $s['sms_credit'])) ?></td>
              <td data-label="طرح"><span class="badge badge--outline"><?= e(PSC::PLANS[$s['plan_code']] ?? $s['plan_code']) ?></span><?php if ($s['plan_code'] === 'trial' && $s['trial_ends_at']): ?> <span class="text-xs muted">تا <?= e(jdate($s['trial_ends_at'], 'Y/m/d')) ?></span><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'url' => static fn (int $p): string => url('platform/salons?' . $qs(['page' => $p])), 'label' => 'صفحه‌های فهرست سالن‌ها', 'skeletonFor' => 'salon-table']) ?>
  <?php endif; ?>
</div>
