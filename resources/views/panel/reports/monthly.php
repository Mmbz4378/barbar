<?php
/** @var int $jy @var int $jm @var array $totals @var array $breakdown @var array $rescued @var array $dailySeries */
$methodLabels = ['cash'=>'نقدی','card_to_card'=>'کارت‌به‌کارت','pos'=>'کارتخوان','online'=>'آنلاین'];
$monthNames = [1=>'فروردین',2=>'اردیبهشت',3=>'خرداد',4=>'تیر',5=>'مرداد',6=>'شهریور',7=>'مهر',8=>'آبان',9=>'آذر',10=>'دی',11=>'بهمن',12=>'اسفند'];
$maxDaily = max(array_map(fn($d) => (int)$d['total'], $dailySeries) ?: [1]);
?>
<div class="flex items-center justify-between mb-5">
  <h1 class="page-title">گزارش ماهانه — <?= e($monthNames[$jm]) ?> <?= fa_num($jy) ?></h1>
  <a href="<?= url('panel/reports') ?>" class="text-xs text-accent hover:underline">گزارش روزانه ←</a>
</div>

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

<div class="glass rounded-2xl p-5 mb-5">
  <h2 class="card-title mb-4">روند فروش روزانه</h2>
  <div class="flex items-end gap-1 h-32">
    <?php foreach ($dailySeries as $d): $h = max(4, (int)round(((int)$d['total'] / $maxDaily) * 100)); ?>
    <div class="flex-1 bg-gold-500 rounded-t" style="height:<?= $h ?>%" title="<?= e($d['d']) ?>"></div>
    <?php endforeach; ?>
    <?php if (empty($dailySeries)): ?><p class="text-xs text-ink-400">داده‌ای برای این ماه نیست.</p><?php endif; ?>
  </div>
</div>

<div class="glass rounded-2xl p-5">
  <h2 class="card-title mb-3">به تفکیک روش پرداخت</h2>
  <?php foreach ($breakdown as $b): ?>
  <div class="flex items-center justify-between text-sm py-1.5">
    <span class="text-ink-600"><?= e($methodLabels[$b['method']] ?? $b['method']) ?></span>
    <span class="font-bold text-ink-800"><?= toman((int)$b['total']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
