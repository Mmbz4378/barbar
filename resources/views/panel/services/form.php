<?php
/** @var ?array $service
 * @var array $staff
 * @var array $overrides
 */
$staff ??= [];
$overrides ??= [];
$overrideMap = [];
foreach ($overrides as $o) { $overrideMap[(int)$o['staff_id']] = $o; }
?>
<div class="max-w-lg">
<div class="flex items-center gap-3 mb-5">
  <a href="<?= url('panel/services') ?>" class="w-11 h-11 -ms-2 grid place-items-center rounded-xl text-ink-400 tap">←</a>
  <h1 class="page-title"><?= $service ? 'ویرایش خدمت' : 'خدمت جدید' ?></h1>
</div>

<form method="post" action="<?= url($service ? 'panel/services/' . $service['id'] : 'panel/services') ?>" class="glass rounded-2xl p-5 space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="name">نام خدمت</label>
    <input id="name" type="text" name="name" required value="<?= e($service['name'] ?? '') ?>" placeholder="اصلاح مو"
      class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
  </div>
  <div>
    <label for="svc-desc" class="block text-sm text-ink-600 mb-1.5">
      توضیح <span class="text-ink-400 font-normal">(اختیاری)</span>
    </label>
    <textarea name="description" id="svc-desc" rows="2" maxlength="300"
      placeholder="مثلاً: شست‌وشو، اصلاح با ماشین و قیچی، حالت‌دهی"
      class="w-full rounded-xl border border-ink-200 bg-transparent px-4 py-3 text-sm leading-relaxed
             focus:outline-none focus:ring-2 focus:ring-accent"><?= e($service['description'] ?? '') ?></textarea>
    <p class="text-[12px] text-ink-400 mt-1.5">
      در «منوی خدمات» که مشتری می‌بیند نمایش داده می‌شود.
    </p>
  </div>

  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="duration_minutes">مدت (دقیقه)</label>
      <input id="duration_minutes" inputmode="numeric" type="number" name="duration_minutes" value="<?= e((string)($service['duration_minutes'] ?? 30)) ?>"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="price_toman">قیمت (تومان)</label>
      <input id="price_toman" inputmode="numeric" type="number" name="price_toman" value="<?= e((string)($service ? App\Support\Money::fromRials((int)$service['price'])->toToman() : 0)) ?>"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
    </div>
  </div>
  <button type="submit" class="btn-ink w-full">ذخیره</button>
</form>

<?php if ($service && !empty($staff)): ?>
<div class="glass rounded-2xl p-5 mt-4">
  <h2 class="card-title mb-1">مدت و قیمت اختصاصی هر آرایشگر</h2>
  <p class="text-xs text-ink-400 mb-4">اگر خالی بگذارید، عدد عمومی بالا استفاده می‌شود.</p>
  <div class="space-y-3">
  <?php foreach ($staff as $st): $ov = $overrideMap[(int)$st['id']] ?? null; ?>
    <form method="post" action="<?= url('panel/services/' . $service['id'] . '/override') ?>" class="service-override">
      <?= csrf_field() ?>
      <input type="hidden" name="staff_id" value="<?= (int)$st['id'] ?>">
      <span class="service-override__name"><?= e($st['name']) ?></span>
      <label class="service-override__field"><span>مدت (دقیقه)</span><input inputmode="numeric" type="number" name="duration_minutes" placeholder="دقیقه"
        aria-label="مدت این خدمت برای <?= e($st['name']) ?> (دقیقه)"
        value="<?= e($ov ? (string)$ov['duration_minutes'] : '') ?>"
        class="rounded-lg border border-ink-200 px-2 py-1.5 text-sm"></label>
      <label class="service-override__field"><span>قیمت (تومان)</span><input inputmode="numeric" type="number" name="price_toman" placeholder="تومان"
        aria-label="قیمت این خدمت برای <?= e($st['name']) ?> (تومان)"
        value="<?= e($ov && $ov['price'] !== null ? (string) App\Support\Money::fromRials((int)$ov['price'])->toToman() : '') ?>"
        class="rounded-lg border border-ink-200 px-2 py-1.5 text-sm"></label>
      <button type="submit" class="btn-ink service-override__save">ذخیره</button>
    </form>
  <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($service): ?>
<form method="post" action="<?= url('panel/services/' . $service['id'] . '/toggle') ?>" class="mt-3">
  <?= csrf_field() ?>
  <button type="submit" class="w-full text-sm text-ink-400 hover:text-ink-600 py-2">
    <?= $service['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>
  </button>
</form>
<?php endif; ?>
</div>

<?php if ($service): ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('panel/services/'.$service['id'].'/image')) ?>" class="glass p-5 mt-4 max-w-lg space-y-3">
<?= csrf_field() ?><h2 class="card-title">تصویر واقعی خدمت</h2>
<?= service_photo($service['name'],'service-photo service-admin-row__photo',false,$service['image_file']??null) ?>
<label class="block">تصویر (حداکثر ۳ مگابایت)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="w-full"></label><button class="btn-ink">ذخیرهٔ تصویر</button></form>
<?php endif; ?>
