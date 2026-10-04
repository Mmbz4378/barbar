<?php
/**
 * @var ?array $service
 * @var ?int $categoryId
 * @var array $categories
 * @var array $staff
 * @var array<int,array> $overrides
 */
use App\Support\SalonContext;

$s = $service ?? [];
$v = static fn (string $key, mixed $fallback = '') => old($key, $fallback);
$isNew = $service === null;
$priceType = (string) $v('price_type', $s['price_type'] ?? 'fixed');
?>
<a class="back-link" href="<?= e(url('panel/services')) ?>"><?= icon('chevron-start') ?> خدمات</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= $isNew ? 'خدمت جدید' : e($s['name']) ?></h1>
    <?php if (!$isNew): ?><p class="page-head__sub"><?= (bool) $s['is_active'] ? '<span class="badge badge--success">فعال</span>' : '<span class="badge badge--warning">غیرفعال</span>' ?></p><?php endif; ?>
  </div>
</div>

<div class="grid grid-main-aside" style="--gap:24px">
  <form method="post" action="<?= e(url($isNew ? 'panel/services' : 'panel/services/' . $s['id'])) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <section class="card"><div class="card__body stack">
      <div class="field">
        <label class="field__label" for="s-name">نام خدمت</label>
        <input class="input" id="s-name" name="name" maxlength="120" required value="<?= e((string) $v('name', $s['name'] ?? '')) ?>" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'name']) ?>
      </div>
      <div class="form-grid form-grid--2">
        <div class="field">
          <label class="field__label" for="s-cat">دسته</label>
          <select class="select" id="s-cat" name="category_id">
            <option value="">سایر خدمات</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $v('category_id', (string) $categoryId) === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php if (SalonContext::audience() === 'unisex'): ?>
          <div class="field">
            <label class="field__label" for="s-aud">برای</label>
            <select class="select" id="s-aud" name="audience">
              <?php foreach (['all' => 'همه', 'women' => 'بانوان', 'men' => 'آقایان'] as $key => $label): ?><option value="<?= $key ?>" <?= (string) $v('audience', $s['audience'] ?? 'all') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
      <div class="field">
        <label class="field__label" for="s-desc">توضیح برای مشتری <span class="field__optional">(اختیاری)</span></label>
        <textarea class="textarea" id="s-desc" name="description" maxlength="300" rows="2" placeholder="مثلاً شامل شست‌وشو و سشوار"><?= e((string) $v('description', $s['description'] ?? '')) ?></textarea>
        <p class="field__hint">مشتری‌ای که بداند چه چیزی شامل می‌شود، کمتر سر قیمت سؤال دارد.</p>
      </div>
    </div></section>

    <section class="card" aria-labelledby="time-price"><div class="card__header"><h2 class="card__title" id="time-price">زمان و قیمت</h2></div><div class="card__body stack">
      <div class="form-grid form-grid--2">
        <div class="field">
          <label class="field__label" for="s-dur">مدت انجام</label>
          <div class="input-group"><input class="input num" id="s-dur" name="duration_minutes" inputmode="numeric" required value="<?= e((string) $v('duration_minutes', $s['duration_minutes'] ?? 30)) ?>" data-numeric <?= field_error('duration_minutes') ? 'aria-invalid="true" aria-describedby="duration_minutes-error"' : '' ?>><span class="input-group__addon">دقیقه</span></div>
          <?= partial('field-error', ['key' => 'duration_minutes']) ?>
        </div>
        <div class="field">
          <label class="field__label" for="s-buf">زمان آماده‌سازی پس از خدمت</label>
          <div class="input-group"><input class="input num" id="s-buf" name="buffer_minutes" inputmode="numeric" value="<?= e((string) $v('buffer_minutes', $s['buffer_minutes'] ?? 0)) ?>" data-numeric><span class="input-group__addon">دقیقه</span></div>
          <p class="field__hint">نظافت، ضدعفونی ابزار یا خشک شدن لاک؛ نوبت بعدی پس از آن شروع می‌شود.</p>
        </div>
        <div class="field">
          <label class="field__label" for="s-price">قیمت پایه</label>
          <div class="input-group"><input class="input num" id="s-price" name="price_toman" inputmode="numeric" required value="<?= e((string) $v('price_toman', isset($s['price']) ? intdiv((int) $s['price'], 10) : '')) ?>" data-numeric <?= field_error('price_toman') ? 'aria-invalid="true" aria-describedby="price_toman-error"' : '' ?>><span class="input-group__addon">تومان</span></div>
          <?= partial('field-error', ['key' => 'price_toman']) ?>
        </div>
        <fieldset class="field">
          <legend class="field__label mb-2">نوع قیمت</legend>
          <div class="segmented">
            <label class="<?= $priceType === 'fixed' ? 'is-active' : '' ?>"><input type="radio" name="price_type" value="fixed" <?= $priceType === 'fixed' ? 'checked' : '' ?>><span>قیمت ثابت</span></label>
            <label class="<?= $priceType === 'from' ? 'is-active' : '' ?>"><input type="radio" name="price_type" value="from" <?= $priceType === 'from' ? 'checked' : '' ?>><span>«از …»</span></label>
          </div>
          <p class="field__hint">«از …» برای خدمتی که قیمتش به طول و حجم مو یا جزئیات کار بستگی دارد.</p>
        </fieldset>
      </div>
    </div></section>

    <section class="card" aria-labelledby="booking-rules"><div class="card__header"><h2 class="card__title" id="booking-rules">رزرو</h2></div><div class="card__body stack">
      <label class="check">
        <input type="checkbox" name="online_booking" value="1" <?= (string) $v('online_booking', (string) ($s['online_booking'] ?? 1)) === '1' ? 'checked' : '' ?>>
        <span class="check__text"><span class="strong">رزرو آنلاین</span><span class="check__hint">اگر خاموش باشد، در منو با «رزرو تلفنی» نمایش داده می‌شود (مثلاً عروس یا اصلاح رنگ که مشاوره لازم دارد).</span></span>
      </label>
      <div class="field">
        <label class="field__label" for="s-dep">بیعانهٔ رزرو آنلاین <span class="field__optional">(اختیاری)</span></label>
        <div class="input-group w-lg"><input class="input num" id="s-dep" name="deposit_toman" inputmode="numeric" value="<?= e((string) $v('deposit_toman', !empty($s['deposit_amount']) ? intdiv((int) $s['deposit_amount'], 10) : '')) ?>" data-numeric><span class="input-group__addon">تومان</span></div>
        <p class="field__hint">نوبت تا تأیید واریز نگه داشته می‌شود. شمارهٔ کارت و مهلت پرداخت در <a class="link" href="<?= e(url('panel/settings#rules')) ?>">تنظیمات ← قوانین رزرو</a>.</p>
      </div>
    </div></section>

    <?php if ($staff !== []): ?>
    <section class="card" aria-labelledby="who"><div class="card__header"><h2 class="card__title" id="who">چه کسی انجام می‌دهد؟</h2></div><div class="card__body stack stack-sm">
      <p class="text-sm muted">تیک را برای کسی که این خدمت را انجام نمی‌دهد بردارید. قیمت و مدت خالی یعنی همان مقدار پایه.</p>
      <div class="table-wrap">
        <table class="table table--stack">
          <thead><tr><th>نام</th><th>انجام می‌دهد</th><th>مدت اختصاصی</th><th>قیمت اختصاصی (تومان)</th></tr></thead>
          <tbody>
          <?php foreach ($staff as $st): $o = $overrides[(int) $st['id']] ?? null; $offered = $o === null || (int) $o['is_offered'] === 1; ?>
            <tr>
              <td data-label="نام"><span class="row" style="--gap:8px"><span class="avatar avatar--sm" style="--avatar-bg:<?= e(staff_color($st['color'])) ?>" aria-hidden="true"><?= e(initial($st['name'])) ?></span><?= e($st['name']) ?></span></td>
              <td data-label="انجام می‌دهد"><input type="hidden" name="staff[<?= (int) $st['id'] ?>][offered]" value="0"><label class="switch"><input type="checkbox" name="staff[<?= (int) $st['id'] ?>][offered]" value="1" <?= $offered ? 'checked' : '' ?> aria-label="<?= e($st['name']) ?> این خدمت را انجام می‌دهد"><span class="switch__track"></span></label></td>
              <td data-label="مدت اختصاصی"><input class="input num w-xs" name="staff[<?= (int) $st['id'] ?>][duration]" inputmode="numeric" placeholder="<?= e(fa_num($s['duration_minutes'] ?? '')) ?>" value="<?= e($o && $o['duration_minutes'] !== null ? (string) $o['duration_minutes'] : '') ?>" aria-label="مدت اختصاصی <?= e($st['name']) ?> به دقیقه" data-numeric></td>
              <td data-label="قیمت اختصاصی"><input class="input num w-sm" name="staff[<?= (int) $st['id'] ?>][price]" inputmode="numeric" placeholder="<?= isset($s['price']) ? e(fa_num(intdiv((int) $s['price'], 10))) : '' ?>" value="<?= e($o && $o['price'] !== null ? (string) intdiv((int) $o['price'], 10) : '') ?>" aria-label="قیمت اختصاصی <?= e($st['name']) ?> به تومان" data-numeric></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div></section>
    <?php endif; ?>

    <div class="form-actions">
      <button type="submit" class="btn btn--primary btn--lg"><?= $isNew ? 'افزودن خدمت' : 'ذخیرهٔ تغییرات' ?></button>
      <a class="btn btn--ghost btn--lg" href="<?= e(url('panel/services')) ?>">انصراف</a>
    </div>
  </form>

  <aside class="stack">
    <?php if (!$isNew): ?>
      <section class="card" aria-labelledby="photo-title"><div class="card__header"><h2 class="card__title" id="photo-title">عکس خدمت</h2></div><div class="card__body stack stack-sm">
        <?= service_media($s, 'service-thumb service-thumb--lg') ?>
        <p class="text-sm muted"><?= !empty($s['image_file']) ? 'عکس واقعی سالن.' : 'هنوز عکسی بارگذاری نشده؛ نماد دسته نمایش داده می‌شود.' ?></p>
        <form method="post" action="<?= e(url('panel/services/' . $s['id'] . '/image')) ?>" enctype="multipart/form-data" class="stack stack-sm">
          <?= csrf_field() ?>
          <label class="field__label" for="s-img">انتخاب عکس (JPG، PNG یا WebP)</label>
          <input class="input" id="s-img" type="file" name="image" accept="image/jpeg,image/png,image/webp">
          <button type="submit" class="btn btn--secondary btn--sm"><?= icon('download') ?> بارگذاری</button>
        </form>
        <?php if (!empty($s['image_file'])): ?>
          <form method="post" action="<?= e(url('panel/services/' . $s['id'] . '/image')) ?>" data-confirm="عکس این خدمت حذف شود؟"><?= csrf_field() ?><input type="hidden" name="remove" value="1"><button class="btn btn--danger-ghost btn--sm" type="submit"><?= icon('trash') ?> حذف عکس</button></form>
        <?php endif; ?>
      </div></section>
      <form method="post" action="<?= e(url('panel/services/' . $s['id'] . '/toggle')) ?>" class="card"><div class="card__body stack stack-sm">
        <?= csrf_field() ?>
        <strong><?= (bool) $s['is_active'] ? 'خدمت فعال است' : 'خدمت غیرفعال است' ?></strong>
        <p class="text-sm muted"><?= (bool) $s['is_active'] ? 'غیرفعال کردن، خدمت را از منو و رزرو برمی‌دارد ولی سوابق می‌ماند.' : 'پس از فعال شدن در منو و صفحهٔ رزرو دیده می‌شود.' ?></p>
        <button type="submit" class="btn <?= (bool) $s['is_active'] ? 'btn--secondary' : 'btn--primary' ?> btn--sm"><?= (bool) $s['is_active'] ? 'غیرفعال کن' : 'فعال کن' ?></button>
      </div></form>
    <?php else: ?>
      <div class="alert alert--info"><?= icon('info') ?><div class="alert__body">پس از ذخیره می‌توانید عکس واقعی خدمت را هم بارگذاری کنید.</div></div>
    <?php endif; ?>
  </aside>
</div>
