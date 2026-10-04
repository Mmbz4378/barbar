<?php
/** @var array $customer @var ?array $preferences @var array $history @var array $staff */
$p = $preferences ?? [];
?>
<div class="flex items-center gap-3 mb-5">
  <a href="<?= url('panel/customers') ?>" class="w-11 h-11 -ms-2 grid place-items-center rounded-xl text-ink-400 tap">←</a>
  <h1 class="page-title"><?= e($customer['name'] ?: 'بدون نام') ?></h1>
</div>

<div class="grid lg:grid-cols-2 gap-5">
  <div class="glass rounded-2xl p-5">
    <h2 class="card-title mb-4">مشخصات و دفترچهٔ آرایشگر</h2>
    <form method="post" action="<?= url('panel/customers/' . $customer['id']) ?>" class="space-y-3">
      <?= csrf_field() ?>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-ink-500 mb-1" for="name">نام</label>
          <input id="name" type="text" name="name" value="<?= e($customer['name'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-xs text-ink-500 mb-1" for="phone">موبایل</label>
          <input id="phone" type="tel" inputmode="numeric" autocomplete="tel" dir="ltr" name="phone" value="<?= $customer['phone'] ? e(\App\Support\IranMobile::parse($customer['phone'])->localCompact()) : '' ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm text-left">
        </div>
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1" for="preferred_staff_id">آرایشگر ترجیحی</label>
        <select id="preferred_staff_id" name="preferred_staff_id" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
          <option value="">—</option>
          <?php foreach ($staff as $st): ?>
          <option value="<?= (int)$st['id'] ?>" <?= (int)($customer['preferred_staff_id'] ?? 0) === (int)$st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-ink-500 mb-1" for="clipper_size">شمارهٔ تیغ</label>
          <input id="clipper_size" type="text" name="clipper_size" value="<?= e($p['clipper_size'] ?? '') ?>" placeholder="مثلاً شمارهٔ ۲" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
        </div>
        <div>
          <label class="block text-xs text-ink-500 mb-1" for="hair_shape">فرم مو</label>
          <input id="hair_shape" type="text" name="hair_shape" value="<?= e($p['hair_shape'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1" for="beard_notes">ریش</label>
        <input id="beard_notes" type="text" name="beard_notes" value="<?= e($p['beard_notes'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1" for="skin_sensitivity">حساسیت پوستی</label>
        <input id="skin_sensitivity" type="text" name="skin_sensitivity" value="<?= e($p['skin_sensitivity'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1" for="last_barber_said">دفعهٔ قبل چه گفت</label>
        <input id="last_barber_said" type="text" name="last_barber_said" value="<?= e($p['last_barber_said'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm">
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1" for="notes">یادداشت آزاد</label>
        <textarea id="notes" name="notes" rows="3" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm"><?= e($customer['notes'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn-accent metal w-full">ذخیره</button>
    </form>
  </div>

  <div>
    <div class="glass rounded-2xl p-5 mb-4 grid grid-cols-3 text-center divide-x divide-x-reverse divide-ink-100">
      <div><div class="text-xl font-extrabold text-ink-800"><?= fa_num($customer['visit_count']) ?></div><div class="text-[12px] text-ink-400">مراجعه</div></div>
      <div><div class="text-xl font-extrabold text-ink-800"><?= fa_num($customer['no_show_count']) ?></div><div class="text-[12px] text-ink-400">غیبت</div></div>
      <div><div class="text-xl font-extrabold text-ink-800"><?= fa_num($customer['trust_score']) ?></div><div class="text-[12px] text-ink-400">امتیاز اعتبار</div></div>
    </div>

    <div class="glass rounded-2xl p-5">
      <h2 class="card-title mb-3">تاریخچهٔ نوبت‌ها</h2>
      <div class="space-y-2">
        <?php foreach ($history as $h): ?>
        <div class="flex items-center justify-between text-sm border-b border-ink-50 pb-2">
          <div>
            <div class="text-ink-700"><?= e($h['service_names'] ?: '—') ?></div>
            <div class="text-[12px] text-ink-400"><?= e($h['staff_name'] ?? '') ?> · <?= jdate($h['created_at'], 'Y/m/d') ?></div>
          </div>
          <span class="text-[12px] px-2 py-0.5 rounded-full <?= $h['status']==='completed' ? 'bg-emerald-50 text-emerald-600' : ($h['status']==='no_show' ? 'bg-amber-50 text-amber-600' : 'bg-ink-50 text-ink-500') ?>">
            <?= e(['completed'=>'انجام‌شد','no_show'=>'غیبت','cancelled'=>'لغو'][$h['status']] ?? $h['status']) ?>
          </span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($history)): ?><p class="text-xs text-ink-400">هنوز تاریخچه‌ای نیست.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
