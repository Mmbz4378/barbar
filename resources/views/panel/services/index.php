<?php
/**
 * @var array $groups
 * @var array $categories
 * @var array<int,int> $offerCounts
 * @var int $staffCount
 */
use App\Support\ServiceVisual;

$catById = [];
foreach ($categories as $c) { $catById[(int) $c['id']] = $c; }
$visualOptions = ServiceVisual::options();
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">خدمات و قیمت‌ها</h1>
    <p class="page-head__sub">دسته‌بندی همان ترتیبی است که مشتری در منو و رزرو می‌بیند. در رزرو چندنفره هم خدمات به همین ترتیب انجام می‌شوند.</p>
  </div>
  <div class="page-head__actions">
    <button type="button" class="btn btn--secondary" data-toggle="new-category" aria-expanded="false"><?= icon('layers') ?> دستهٔ جدید</button>
    <a class="btn btn--primary" href="<?= e(url('panel/services/create')) ?>"><?= icon('plus') ?> خدمت جدید</a>
  </div>
</div>

<form method="post" action="<?= e(url('panel/categories')) ?>" class="card mb-6" id="new-category" hidden>
  <div class="card__body form-grid form-grid--3" style="align-items:end">
    <?= csrf_field() ?>
    <div class="field"><label class="field__label" for="nc-name">نام دسته</label><input class="input" id="nc-name" name="name" maxlength="80" required placeholder="مثلاً ناخن، ریش و صورت"></div>
    <div class="field"><label class="field__label" for="nc-visual">نماد</label><select class="select" id="nc-visual" name="visual"><?php foreach ($visualOptions as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
    <button type="submit" class="btn btn--primary">افزودن دسته</button>
  </div>
</form>

<?php if ($groups === []): ?>
  <div class="card"><?= partial('empty-state', ['icon' => 'tag', 'title' => 'هنوز خدمتی تعریف نشده', 'text' => 'بدون خدمت فعال، صفحهٔ رزرو چیزی برای انتخاب ندارد.', 'actionHref' => url('panel/services/create'), 'actionLabel' => 'افزودن اولین خدمت']) ?></div>
<?php endif; ?>

<div class="stack stack-lg">
  <?php foreach ($groups as $gi => $group): $cat = $group['id'] !== null ? ($catById[$group['id']] ?? null) : null; ?>
    <section class="card" aria-labelledby="cat-<?= (int) ($group['id'] ?? 0) ?>">
      <div class="card__header card__header--divided">
        <h2 class="card__title row" id="cat-<?= (int) ($group['id'] ?? 0) ?>"><span class="icon-tile" style="width:36px;height:36px"><?= icon(ServiceVisual::icon($group['visual'] ?? 'haircut')) ?></span><?= e($group['name']) ?> <span class="badge"><?= e(fa_num(count($group['services']))) ?></span></h2>
        <?php if ($cat !== null): ?>
          <details class="more-menu">
            <summary class="btn btn--ghost btn--icon btn--sm" aria-label="مدیریت دستهٔ <?= e($group['name']) ?>"><?= icon('more') ?></summary>
            <div class="more-menu__panel" style="min-width:260px">
              <form method="post" action="<?= e(url('panel/categories/' . $cat['id'])) ?>" class="stack stack-sm" style="padding:8px">
                <?= csrf_field() ?>
                <label class="field__label" for="cn-<?= (int) $cat['id'] ?>">نام دسته</label>
                <input class="input" id="cn-<?= (int) $cat['id'] ?>" name="name" value="<?= e($cat['name']) ?>" maxlength="80">
                <label class="field__label" for="cv-<?= (int) $cat['id'] ?>">نماد</label>
                <select class="select" id="cv-<?= (int) $cat['id'] ?>" name="visual"><?php foreach ($visualOptions as $key => $label): ?><option value="<?= e($key) ?>" <?= $cat['visual'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                <button type="submit" name="action" value="save" class="btn btn--primary btn--sm">ذخیره</button>
              </form>
              <form method="post" action="<?= e(url('panel/categories/' . $cat['id'])) ?>"><?= csrf_field() ?>
                <button type="submit" name="action" value="up" class="btn btn--ghost" <?= $gi === 0 ? 'disabled' : '' ?>><?= icon('arrow-up') ?> بالاتر</button>
                <button type="submit" name="action" value="down" class="btn btn--ghost"><?= icon('arrow-down') ?> پایین‌تر</button>
              </form>
              <form method="post" action="<?= e(url('panel/categories/' . $cat['id'])) ?>" data-confirm="دستهٔ «<?= e($cat['name']) ?>» حذف شود؟ خدماتش حذف نمی‌شوند و به «سایر خدمات» می‌روند." data-confirm-ok="حذف دسته"><?= csrf_field() ?>
                <button type="submit" name="action" value="delete" class="btn btn--danger-ghost"><?= icon('trash') ?> حذف دسته</button>
              </form>
            </div>
          </details>
        <?php endif; ?>
      </div>
      <ul class="list">
        <?php foreach ($group['services'] as $s): $active = (bool) $s['is_active']; $offers = $offerCounts[(int) $s['id']] ?? 0; ?>
          <li class="list-row" style="<?= $active ? '' : 'opacity:.7' ?>">
            <?= service_media($s, 'service-thumb service-thumb--sm') ?>
            <a class="list-row__body" href="<?= e(url('panel/services/' . $s['id'] . '/edit')) ?>" style="color:inherit;text-decoration:none">
              <span class="list-row__title"><?= e($s['name']) ?></span>
              <span class="list-row__meta"><?= e(duration_text((int) $s['duration_minutes'])) ?><?= (int) $s['buffer_minutes'] > 0 ? ' + ' . e(fa_num($s['buffer_minutes'])) . ' آماده‌سازی' : '' ?> · <span class="num"><?= e(price_text((int) $s['price'], (string) $s['price_type'])) ?></span></span>
              <span class="cluster" style="--gap:4px">
                <?php if (!$active): ?><span class="badge badge--warning">غیرفعال</span><?php endif; ?>
                <?php if ((int) $s['online_booking'] !== 1): ?><span class="badge">فقط تلفنی</span><?php endif; ?>
                <?php if (!empty($s['deposit_amount'])): ?><span class="badge badge--info">بیعانه <?= e(toman((int) $s['deposit_amount'])) ?></span><?php endif; ?>
                <?php if ($staffCount > 0): ?><span class="badge <?= $offers === 0 ? 'badge--danger' : '' ?>"><?= $offers === 0 ? 'هیچ‌کس انجام نمی‌دهد' : e(fa_num($offers)) . ' از ' . e(fa_num($staffCount)) . ' نفر' ?></span><?php endif; ?>
                <?php if (($s['audience'] ?? 'all') !== 'all'): ?><span class="badge"><?= $s['audience'] === 'women' ? 'بانوان' : 'آقایان' ?></span><?php endif; ?>
              </span>
            </a>
            <span class="list-row__end">
              <form method="post" action="<?= e(url('panel/services/' . $s['id'] . '/toggle')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm"><?= $active ? 'غیرفعال کن' : 'فعال کن' ?></button>
              </form>
              <a class="btn btn--secondary btn--sm btn--icon" href="<?= e(url('panel/services/' . $s['id'] . '/edit')) ?>" aria-label="ویرایش <?= e($s['name']) ?>"><?= icon('edit') ?></a>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($group['id'] !== null): ?>
        <div class="card__footer"><a class="btn btn--link btn--sm" href="<?= e(url('panel/services/create?category=' . $group['id'])) ?>"><?= icon('plus') ?> خدمت در این دسته</a></div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
  <?php foreach ($categories as $c): if ((int) $c['service_count'] > 0) { continue; } ?>
    <div class="card card--sunken"><div class="card__body spread"><span><?= icon(ServiceVisual::icon($c['visual'])) ?> <?= e($c['name']) ?> <span class="muted text-sm">— بدون خدمت</span></span><a class="btn btn--secondary btn--sm" href="<?= e(url('panel/services/create?category=' . $c['id'])) ?>"><?= icon('plus') ?> افزودن خدمت</a></div></div>
  <?php endforeach; ?>
</div>
