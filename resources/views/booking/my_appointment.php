<?php
/** @var array $salon @var array $appointment @var array $items @var ?array $display */
$statusLabels = [
    'pending' => 'در انتظار', 'confirmed' => 'تأییدشده', 'queued' => 'در صف',
    'in_chair' => 'روی صندلی', 'completed' => 'انجام‌شد', 'cancelled' => 'لغوشده', 'no_show' => 'غیبت',
];
$live = in_array($appointment['status'], ['confirmed','queued','in_chair'], true);
?>
<script>if (<?= $live ? 'true' : 'false' ?>) setTimeout(() => location.reload(), 20000);</script>

<h1 class="text-[15px] font-extrabold text-ink-900 mb-1">نوبت من</h1>
<p class="text-sm text-ink-500 mb-5"><?= e($salon['name']) ?></p>

<div class="bg-ink-50 rounded-2xl p-5 text-center mb-5">
  <?php if ($appointment['status'] === 'in_chair'): ?>
    <div class="text-emerald-600 font-extrabold text-2xl mb-1">خدمت شما در حال انجام است</div>
  <?php elseif ($appointment['status'] === 'completed'): ?>
    <div class="text-ink-500 font-bold text-lg">خدمت شما انجام شد. ممنون از اعتمادتان.</div>
  <?php elseif ($appointment['status'] === 'cancelled'): ?>
    <div class="text-red-500 font-bold text-lg">این نوبت لغو شده است.</div>
  <?php elseif ($appointment['status'] === 'no_show'): ?>
    <div class="text-ink-500 font-bold text-lg">این نوبت به‌عنوان غیبت ثبت شد.</div>
  <?php elseif ($display): ?>
    <div class="text-xs text-ink-400 mb-1">زمان تقریبی نوبت شما</div>
    <div class="text-2xl font-extrabold text-accent"><?= e($display['text']) ?></div>
    <?php if ($display['rough']): ?><div class="text-[12px] text-amber-600 mt-1">تخمین تقریبی</div><?php endif; ?>
  <?php else: ?>
    <div class="text-lg font-bold text-ink-600"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></div>
  <?php endif; ?>
</div>

<div class="space-y-1.5 mb-5">
  <?php foreach ($items as $it): ?>
  <div class="flex items-center justify-between text-sm bg-white border border-ink-100 rounded-xl px-4 py-2.5">
    <span class="text-ink-600"><?= e($it['service_name']) ?></span>
    <span class="font-bold text-ink-800"><?= toman((int)$it['price']) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($appointment['scheduled_at']): ?>
<div class="text-xs text-ink-400 text-center mb-5">زمان رزروشده: <?= jdate($appointment['scheduled_at'], 'D j M، H:i') ?></div>
<?php endif; ?>

<?php if (in_array($appointment['status'], ['confirmed','queued'], true)): ?>
<form method="post" action="<?= url('q/' . $appointment['public_token'] . '/cancel') ?>" onsubmit="return confirm('نوبت لغو شود؟');">
  <?= csrf_field() ?>
  <button type="submit" class="w-full text-sm text-red-500 hover:text-red-600 border border-red-100 rounded-xl py-2.5">لغو نوبت</button>
</form>
<?php endif; ?>

<!--
  راه رسیدن به «نوبت‌های من».

  بدون این، مشتری فقط همین یک نوبت را دارد و راهی نیست که بفهمد
  صفحه‌ای هم هست که همهٔ نوبت‌هایش را نشان می‌دهد.
-->
<a href="<?= e(url('me')) ?>"
   class="flex items-center justify-center gap-2 text-[12px] font-semibold text-ink-500 mt-5 py-3 tap">
  <?= icon('calendar-days', 'w-4 h-4') ?>
  همهٔ نوبت‌های من
</a>

<?php foreach(['success','error'] as $kind): if($notice=flash($kind)): ?><p class="glass p-4 mt-3" role="status"><?= e($notice) ?></p><?php endif; endforeach; ?>
<?php if(!empty($payment)): ?><p class="glass p-4 mt-4">تسویه ثبت شده · <?= e(toman((int)$payment['amount'])) ?></p>
<?php elseif($appointment['status']==='completed' && App\Domain\Payment\PaymentGatewayManager::isEnabled()): ?>
<form class="mt-4" method="post" action="<?= e(url('q/'.$appointment['public_token'].'/pay')) ?>"><?= csrf_field() ?><button class="btn-accent metal w-full">پرداخت آنلاین <?= e(toman((int)array_sum(array_column($items,'price')))) ?></button></form>
<?php if(!empty($pendingPayment)): ?><a class="btn-ink w-full mt-3" href="<?= e(url('payments/callback').'?Authority='.rawurlencode($pendingPayment['authority'])) ?>">بررسی نتیجهٔ پرداخت قبلی</a><?php endif; ?>
<?php endif; ?>
