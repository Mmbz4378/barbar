<?php
/**
 * @var array $users
 * @var array $counts
 * @var array $filters
 * @var int $total
 * @var int $page
 * @var int $pages
 */
use App\Http\Controllers\PlatformSalonController as PSC;

$qs = static fn (array $over) => http_build_query(array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== null && $v !== 1));
$types = [
    'panel' => ['کاربران پنل', $counts['panel'] ?? 0],
    'admins' => ['مدیران کل', $counts['admins'] ?? 0],
    'blocked' => ['مسدود', $counts['blocked'] ?? 0],
    'locked' => ['قفل رمز', null],
    'no_password' => ['بی‌رمز', null],
    'customers' => ['مشتری‌ها', $counts['customers'] ?? 0],
    'all' => ['همه', null],
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">کاربران</h1>
    <p class="page-head__sub"><?= e(fa_num($total)) ?> حساب در این فهرست. مشتری‌هایی که رزرو کرده‌اند هم حساب دارند، ولی پنلی ندارند.</p>
  </div>
  <div class="page-head__actions"><a class="btn btn--primary" href="<?= e(url('platform/users/new')) ?>"><?= icon('user-plus') ?> کاربر تازه</a></div>
</div>

<div class="stack stack-lg">
  <nav class="chips" aria-label="نوع کاربر">
    <?php foreach ($types as $key => [$label, $n]): ?>
      <a class="chip" href="<?= e(url('platform/users?' . $qs(['type' => $key, 'page' => 1]))) ?>" <?= $filters['type'] === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?><?php if ($n !== null): ?> <span class="num muted"><?= e(fa_num((int) $n)) ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

  <form method="get" action="<?= e(url('platform/users')) ?>" class="card card--flat"><div class="card__body cluster" style="--gap:12px">
    <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
    <div class="field grow-200">
      <label class="sr-only" for="pu-q">جست‌وجو</label>
      <input class="input input-search" id="pu-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="نام، موبایل یا نام کاربری">
    </div>
    <button class="btn btn--secondary" type="submit"><?= icon('search') ?> جست‌وجو</button>
    <?php if ($filters['q'] !== ''): ?><a class="btn btn--ghost" href="<?= e(url('platform/users?type=' . rawurlencode($filters['type']))) ?>">پاک کردن</a><?php endif; ?>
  </div></form>

  <?php if ($users === []): ?>
    <?= partial('empty-state', ['icon' => 'users', 'title' => 'کاربری پیدا نشد', 'text' => 'فیلتر یا جست‌وجو را تغییر دهید.']) ?>
  <?php else: ?>
    <div class="table-wrap" id="user-table" data-skeleton-region>
      <template data-skeleton-tpl><?= partial('skeleton', ['variant' => 'list', 'count' => 6]) ?></template>
      <table class="table table--stack">
        <thead><tr><th scope="col">کاربر</th><th scope="col">دسترسی</th><th scope="col">ورود</th><th scope="col">وضعیت</th><th scope="col">آخرین ورود</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): $locked = !empty($u['locked_until']) && strtotime((string) $u['locked_until']) > time(); ?>
            <tr>
              <td data-label="کاربر">
                <a class="row link-plain" style="--gap:10px" href="<?= e(url('platform/users/' . $u['id'])) ?>">
                  <span class="avatar avatar--sm" aria-hidden="true"><?= e(initial($u['name'] ?: 'ک')) ?></span>
                  <span class="stack gap-0"><strong><?= e($u['name'] ?: 'بدون نام') ?></strong><span class="text-xs muted"><span class="ltr num"><?= e(phone_local((string) $u['phone'])) ?></span><?= $u['username'] ? ' · <span class="ltr">' . e($u['username']) . '</span>' : '' ?></span></span>
                </a>
              </td>
              <td data-label="دسترسی">
                <?php if ((int) $u['is_platform_admin'] === 1): ?><span class="badge badge--accent">مدیر کل</span><?php endif; ?>
                <?php foreach (array_filter(explode(';;', (string) $u['memberships'])) as $mem): [$sn, $sr] = array_pad(explode('|', $mem, 2), 2, ''); ?>
                  <span class="badge badge--outline"><?= e($sn) ?> · <?= e(PSC::ROLES[$sr] ?? $sr) ?></span>
                <?php endforeach; ?>
                <?php if ((int) $u['is_platform_admin'] !== 1 && empty($u['memberships'])): ?><span class="text-sm muted">مشتری</span><?php endif; ?>
              </td>
              <td data-label="ورود"><?= (int) $u['has_password'] ? 'رمز + پیامک' : '<span class="muted">فقط پیامک</span>' ?><?= (int) $u['must_change_password'] ? ' <span class="badge badge--warning">رمز موقت</span>' : '' ?></td>
              <td data-label="وضعیت"><?php if ((int) $u['is_active'] !== 1): ?><span class="badge badge--danger">مسدود</span><?php elseif ($locked): ?><span class="badge badge--warning">قفل رمز</span><?php else: ?><span class="badge badge--success">فعال</span><?php endif; ?></td>
              <td data-label="آخرین ورود" class="num text-sm"><?= $u['last_login_at'] ? e(jdate((string) $u['last_login_at'], 'Y/m/d H:i')) : '<span class="muted">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= partial('pagination', ['page' => $page, 'pages' => $pages, 'url' => static fn (int $p): string => url('platform/users?' . $qs(['page' => $p])), 'label' => 'صفحه‌های فهرست کاربران', 'skeletonFor' => 'user-table']) ?>
  <?php endif; ?>
</div>
