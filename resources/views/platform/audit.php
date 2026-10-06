<?php
/**
 * @var string $tab
 * @var App\Domain\Reports\ReportRange $range
 * @var array $filters
 * @var array $rows
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var array $summary
 * @var array $topIps
 * @var array $salonOptions
 */
use App\Domain\Identity\LoginEvents;
use App\Domain\Reports\ReportRange;
use App\Support\AuditLabels;

$query = array_filter($range->query() + $filters + ['tab' => $tab], static fn ($v) => $v !== '' && $v !== null);
$link = static fn (string $path, array $extra = []): string => url($path . '?' . http_build_query(array_merge($query, $extra)));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">رویدادها و ورودها</h1>
    <p class="page-head__sub">هر اقدام حساس مدیران و هر تلاش ورود ثبت می‌شود: چه کسی، کی، از کجا. <?= e($range->label()) ?></p>
  </div>
  <div class="page-head__actions"><a class="btn btn--secondary" href="<?= e($link('platform/audit/export')) ?>"><?= icon('download') ?> خروجی Excel</a></div>
</div>

<nav class="tabs" aria-label="نوع گزارش">
  <a class="tab" href="<?= e(url('platform/audit?' . http_build_query($range->query()))) ?>" <?= $tab === 'events' ? 'aria-current="page"' : '' ?>><?= icon('list') ?> رویدادها</a>
  <a class="tab" href="<?= e(url('platform/audit?' . http_build_query($range->query() + ['tab' => 'logins']))) ?>" <?= $tab === 'logins' ? 'aria-current="page"' : '' ?>><?= icon('lock') ?> ورودها</a>
</nav>

<form method="get" action="<?= e(url('platform/audit')) ?>" class="card card--flat mb-4"><div class="card__body cluster items-end" style="--gap:12px">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="field">
    <label class="field__label" for="a-range">بازه</label>
    <select class="select select--compact" id="a-range" name="range">
      <?php foreach (ReportRange::PRESETS as $key => $label): if ($key === 'custom') { continue; } ?><option value="<?= e($key) ?>" <?= $range->preset === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php if ($tab === 'events'): ?>
    <div class="field">
      <label class="field__label" for="a-action">رویداد</label>
      <select class="select select--compact" id="a-action" name="action">
        <option value="">همه</option>
        <?php foreach (AuditLabels::all() as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['action'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="field__label" for="a-salon">سالن</label>
      <select class="select select--compact" id="a-salon" name="salon">
        <option value="">همه</option>
        <?php foreach ($salonOptions as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $filters['salon'] === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  <?php else: ?>
    <div class="field">
      <label class="field__label" for="a-result">نتیجه</label>
      <select class="select select--compact" id="a-result" name="result">
        <option value="">همه</option>
        <option value="ok" <?= $filters['result'] === 'ok' ? 'selected' : '' ?>>موفق</option>
        <option value="failed" <?= $filters['result'] === 'failed' ? 'selected' : '' ?>>ناموفق</option>
      </select>
    </div>
    <div class="field">
      <label class="field__label" for="a-method">روش</label>
      <select class="select select--compact" id="a-method" name="method">
        <option value="">همه</option>
        <?php foreach (['password', 'otp', 'link', 'reset'] as $m): ?><option value="<?= e($m) ?>" <?= $filters['method'] === $m ? 'selected' : '' ?>><?= e(LoginEvents::methodLabel($m)) ?></option><?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="field grow-200">
    <label class="field__label" for="a-q">جست‌وجو</label>
    <input class="input input-search" id="a-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= $tab === 'events' ? 'نام، موبایل یا IP انجام‌دهنده' : 'شناسهٔ واردشده، نام، موبایل یا IP' ?>">
  </div>
  <button class="btn btn--secondary" type="submit"><?= icon('filter') ?> اعمال</button>
</div></form>

<?php if ($tab === 'logins'): ?>
  <div class="stats mb-4" style="--cols:3">
    <div class="stat"><span class="stat__label"><?= icon('circle-check') ?> ورود موفق</span><span class="stat__value num"><?= e(fa_num((int) ($summary['ok'] ?? 0))) ?></span></div>
    <div class="stat"><span class="stat__label"><?= icon('x') ?> ورود ناموفق</span><span class="stat__value num"><?= e(fa_num((int) ($summary['failed'] ?? 0))) ?></span><?php if ($topIps !== []): ?><span class="stat__hint">بیشترین: <span class="ltr num"><?= e($topIps[0]['ip_address']) ?></span> (<?= e(fa_num((int) $topIps[0]['n'])) ?>)</span><?php endif; ?></div>
    <div class="stat"><span class="stat__label"><?= icon('navigation') ?> نشانی IP متفاوت</span><span class="stat__value num"><?= e(fa_num((int) ($summary['ips'] ?? 0))) ?></span></div>
  </div>
<?php endif; ?>

<?php if ($rows === []): ?>
  <?= partial('empty-state', ['icon' => 'list', 'title' => 'موردی پیدا نشد', 'text' => 'بازه یا فیلترها را تغییر دهید.']) ?>
<?php else: ?>
  <p class="text-sm muted mb-2"><?= e(fa_num($total)) ?> مورد</p>
  <div class="table-wrap">
    <?php if ($tab === 'logins'): ?>
      <table class="table table--stack">
        <thead><tr><th scope="col">زمان</th><th scope="col">کاربر</th><th scope="col">روش</th><th scope="col">نتیجه</th><th scope="col">IP</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td data-label="زمان" class="num nowrap"><?= e(jdate((string) $r['created_at'], 'Y/m/d H:i')) ?></td>
              <td data-label="کاربر"><?php if ($r['user_id']): ?><a href="<?= e(url('platform/users/' . $r['user_id'])) ?>"><?= e($r['user_name'] ?: phone_local((string) $r['user_phone'])) ?></a><?php else: ?><span class="ltr text-sm"><?= e((string) ($r['identifier'] ?? '—')) ?></span> <span class="text-xs muted">(ناشناس)</span><?php endif; ?></td>
              <td data-label="روش"><?= e(LoginEvents::methodLabel((string) $r['method'])) ?></td>
              <td data-label="نتیجه"><?php if ((int) $r['success'] === 1): ?><span class="badge badge--success">موفق</span><?php else: ?><span class="badge badge--danger">ناموفق</span> <span class="text-xs muted"><?= e(LoginEvents::reasonLabel($r['reason'] ?? null)) ?></span><?php endif; ?></td>
              <td data-label="IP" class="ltr text-xs muted"><?= e((string) ($r['ip_address'] ?? '—')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <table class="table table--stack">
        <thead><tr><th scope="col">زمان</th><th scope="col">رویداد</th><th scope="col">انجام‌دهنده</th><th scope="col">سالن</th><th scope="col">جزئیات</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): $details = AuditLabels::describe($r['meta_json'] ?? null); ?>
            <tr>
              <td data-label="زمان" class="num nowrap"><?= e(jdate((string) $r['created_at'], 'Y/m/d H:i')) ?></td>
              <td data-label="رویداد"><?= e(AuditLabels::action((string) $r['action'])) ?><?php if ($r['subject_type'] === 'user' && $r['subject_id']): ?> · <a class="link text-sm" href="<?= e(url('platform/users/' . $r['subject_id'])) ?>">کاربر</a><?php endif; ?></td>
              <td data-label="انجام‌دهنده"><?= $r['actor_user_id'] ? '<a href="' . e(url('platform/users/' . $r['actor_user_id'])) . '">' . e($r['actor_name'] ?: phone_local((string) $r['actor_phone'])) . '</a>' : 'سامانه' ?><span class="text-xs muted ltr"> <?= e((string) ($r['ip_address'] ?? '')) ?></span></td>
              <td data-label="سالن"><?= $r['salon_id'] ? '<a href="' . e(url('platform/salons/' . $r['salon_id'])) . '">' . e((string) ($r['salon_name'] ?? '#' . $r['salon_id'])) . '</a>' : '—' ?></td>
              <td data-label="جزئیات" class="text-sm muted"><?= $details !== [] ? e(implode(' · ', $details)) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'url' => static fn (int $p): string => $link('platform/audit', ['page' => $p]), 'label' => 'صفحه‌های فهرست']) ?>
<?php endif; ?>
