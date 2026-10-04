<?php
/** @var string $date @var array $totals @var array $breakdown @var array $byStaff @var array $rescued */
$methodLabels = ['cash'=>'نقدی','card_to_card'=>'کارت‌به‌کارت','pos'=>'کارتخوان','online'=>'آنلاین'];
?>
<div class="flex items-center justify-between mb-5">
  <h1 class="page-title">گزارش روزانه</h1>
  <a href="<?= e(url('panel/reports/monthly')) ?>"
     class="glass h-11 inline-flex items-center gap-1.5 rounded-xl px-3.5
            text-[12px] font-bold text-accent tap shrink-0">
    گزارش ماهانه
    <?= icon('chevron-end', 'w-3.5 h-3.5') ?>
  </a>
</div>

<form method="get" class="mb-5 flex flex-wrap items-center gap-2">
  <?php $name = 'date'; $value = $date; $label = 'گزارش'; $years = [-2, 0];
        include BASE_PATH . '/resources/views/components/jalali-date-input.php'; ?>
  <button type="submit" class="btn-ink h-11 text-[13px] px-4">نمایش</button>
  <?php if ($date !== date('Y-m-d')): ?>
    <a href="<?= e(url('panel/reports')) ?>"
       class="h-11 grid place-items-center rounded-xl px-4 text-[13px] font-bold text-ink-600 glass tap">امروز</a>
  <?php endif; ?>
</form>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
  <div class="glass rounded-2xl p-4 text-center">
    <div class="text-2xl font-extrabold text-accent"><?= toman((int)$totals['total']) ?></div>
    <div class="text-[12px] text-ink-400 mt-1">فروش کل</div>
  </div>
  <div class="glass rounded-2xl p-4 text-center">
    <div class="text-2xl font-extrabold text-ink-800"><?= fa_num($totals['count']) ?></div>
    <div class="text-[12px] text-ink-400 mt-1">تعداد نوبت</div>
  </div>
  <div class="glass rounded-2xl p-4 text-center">
    <div class="text-2xl font-extrabold text-ink-800"><?= toman((int)$totals['tips']) ?></div>
    <div class="text-[12px] text-ink-400 mt-1">انعام</div>
  </div>
  <div class="bg-green-50 rounded-2xl border border-green-100 p-4 text-center">
    <div class="text-2xl font-extrabold text-green-800"><?= fa_num($rescued['count']) ?></div>
    <div class="text-[12px] text-green-800 mt-1">نجات‌یافته با یادآور</div>
  </div>
</div>

<div class="grid md:grid-cols-2 gap-5">
  <div class="glass rounded-2xl p-5">
    <h2 class="card-title mb-3">به تفکیک روش پرداخت</h2>
    <?php foreach ($breakdown as $b): ?>
    <div class="flex items-center justify-between text-sm py-1.5">
      <span class="text-ink-600"><?= e($methodLabels[$b['method']] ?? $b['method']) ?></span>
      <span class="font-bold text-ink-800"><?= toman((int)$b['total']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($breakdown)): ?><p class="text-xs text-ink-400">پرداختی ثبت نشده.</p><?php endif; ?>
  </div>
  <div class="glass rounded-2xl p-5">
    <h2 class="card-title mb-3">به تفکیک آرایشگر</h2>
    <?php foreach ($byStaff as $s): ?>
    <div class="flex items-center justify-between text-sm py-1.5">
      <span class="text-ink-600"><?= e($s['name']) ?> <span class="text-[12px] text-ink-400">(<?= fa_num($s['count']) ?>)</span></span>
      <span class="font-bold text-ink-800"><?= toman((int)$s['total']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($rescued['count'] > 0): ?>
<div class="bg-green-50 border border-green-100 rounded-2xl p-5 mt-5 text-sm text-green-900">
  <?= fa_num($rescued['count']) ?> نوبتی که با پیامک یادآور نجات پیدا کرد ≈ <?= toman((int)$rescued['value']) ?>
</div>
<?php endif; ?>
