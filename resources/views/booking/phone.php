<?php
/**
 * آخرین گام رزرو.
 *
 * @var array $salon
 * @var bool $needsVerification
 * @var ?array $summary  خلاصهٔ انتخاب‌ها
 */
?>
<h1 class="text-[15px] font-extrabold text-ink-900 mb-1">مرور و ثبت نوبت</h1>
<p class="text-[13px] text-ink-500 mb-4 leading-relaxed">
  <?php if ($needsVerification): ?>
    یک کد برایت پیامک می‌شود تا نوبت قطعی شود.
  <?php else: ?>
    انتخاب‌ها را بررسی کن و شمارهٔ موبایل را برای پیگیری نوبت بنویس.
  <?php endif; ?>
</p>

<?php if ($error = flash('error')): ?>
  <div role="alert"
       class="flex items-start gap-2 bg-red-50 text-red-800 text-[13px] rounded-xl px-4 py-3 mb-4 border border-red-100">
    <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<?php if (!empty($summary)): ?>
  <!-- خلاصهٔ انتخاب‌ها: مشتری پیش از دادن شماره باید ببیند چه چیزی را
       تأیید می‌کند، نه اینکه از حافظه‌اش یاد بیاورد. -->
  <div class="review-card glass rounded-2xl p-4 mb-4 space-y-2"><h2 class="card-title">خلاصهٔ نوبت</h2>
    <?php foreach ($summary as $label => $value): ?>
      <div class="flex items-baseline justify-between gap-3 text-[12.5px]">
        <span class="text-ink-400 shrink-0"><?= e($label) ?></span>
        <span class="review-value"><span><?= e($value) ?></span><?php $editPaths=['خدمت'=>'services','آرایشگر'=>'staff','زمان'=>'']; if(isset($editPaths[$label])): ?><a class="text-action" aria-label="<?= e('ویرایش '.$label) ?>" href="<?= e(url('s/'.$salon['slug'].($editPaths[$label] ? '/'.$editPaths[$label] : ''))) ?>">ویرایش</a><?php endif; ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('s/' . $salon['slug'] . '/phone')) ?>" class="space-y-3">
  <?= csrf_field() ?>

  <div>
    <label for="bk-phone" class="block text-[12px] font-bold text-ink-600 mb-1.5">شمارهٔ موبایل</label>
    <input type="tel" value="<?= e($savedPhone ?? '') ?>" name="phone" id="bk-phone" required dir="ltr"
           inputmode="numeric" autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹"
           class="w-full h-12 rounded-xl border border-ink-200 bg-transparent px-4 text-left text-[17px]
                  tracking-wider tabular-nums focus:outline-none focus:ring-2 focus:ring-accent">
    <p class="text-[12px] text-ink-400 mt-1.5">برای یادآوری نوبت و خبر دادن وقتی نوبتت نزدیک شد.</p>
  </div>

  <div>
    <label for="bk-name" class="block text-[12px] font-bold text-ink-600 mb-1.5">
      نام <span class="font-normal text-ink-400">(اختیاری)</span>
    </label>
    <input type="text" value="<?= e($savedName ?? '') ?>" maxlength="120" name="name" id="bk-name" autocomplete="name"
           class="w-full h-12 rounded-xl border border-ink-200 bg-transparent px-4 text-[14px]
                  focus:outline-none focus:ring-2 focus:ring-accent">
  </div>

<?php
  echo App\Core\View::render('components.sticky-action', [
      'label' => $needsVerification ? 'ارسال کد تأیید' : 'تأیید و ثبت نوبت',
      'hint' => 'این مرحله نوبت را ثبت می‌کند؛ پرداخت پس از انجام خدمت است.',
  ]);
  ?>
</form>

