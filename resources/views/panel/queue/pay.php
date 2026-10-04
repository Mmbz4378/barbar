<?php
/** @var array $appointment
 * @var array $items
 * @var ?array $customer
 * @var int $amount
 */
use App\Support\Money;
?>
<div class="max-w-md mx-auto">
  <a class="text-action mb-3" href="<?= e(url('panel')) ?>">بازگشت به امروز</a><h1 class="page-title mb-1">ثبت پرداخت</h1>
  <p class="text-sm text-ink-500 mb-5"><?= e($customer['name'] ?? 'مشتری') ?></p>

  <div class="glass rounded-2xl p-5 mb-4">
    <?php foreach ($items as $it): ?>
    <div class="flex items-center justify-between text-sm py-1.5">
      <span class="text-ink-600"><?= e($it['service_name']) ?></span>
      <span class="font-bold text-ink-800"><?= toman((int)$it['price']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <form method="post" action="<?= url('panel/pay/' . $appointment['id']) ?>" class="glass rounded-2xl p-5 space-y-4">
    <?= csrf_field() ?>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="amount_toman">مبلغ کل (تومان)</label>
      <input id="amount_toman" inputmode="numeric" type="number" name="amount_toman" required min="0" step="1" value="<?= (int) Money::fromRials($amount)->toToman() ?>"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 text-lg font-bold focus:outline-none focus:ring-2 focus:ring-accent">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="tip_toman">انعام (اختیاری)</label>
      <input id="tip_toman" inputmode="numeric" type="number" name="tip_toman" min="0" step="1" value="0" class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-2">روش پرداخت</label>
      <div class="payment-methods" role="radiogroup" aria-label="روش پرداخت">
        <?php $methods = ['cash'=>'نقدی','card_to_card'=>'کارت‌به‌کارت','pos'=>'کارتخوان']; ?>
        <?php foreach ($methods as $val=>$label): ?>
        <label class="flex items-center justify-center gap-1.5 text-sm border border-ink-200 rounded-xl py-2.5 cursor-pointer has-[:checked]:bg-gold-50 has-[:checked]:border-gold-600 has-[:checked]:text-accent">
          <input type="radio" name="method" value="<?= $val ?>" class="sr-only" <?= $val==='cash'?'checked':'' ?>><?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="payment-total" role="status">مبلغ دریافتی <strong data-payment-total></strong></div><p class="text-sm text-ink-500">پس از دریافت وجه، پرداخت را ثبت کن.</p><button type="submit" class="btn-accent metal w-full">تأیید دریافت و ثبت پرداخت</button>
  </form>
</div>
