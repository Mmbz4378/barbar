<?php
/**
 * تسویه.
 *
 * @var array $appointment
 * @var array $items
 * @var ?array $customer
 * @var ?array $staff
 * @var int $subtotal  ریال
 * @var int $deposit   ریال
 * @var int $groupSize
 */
use App\Domain\Payment\PaymentRepository;

$dueToman = max(0, intdiv($subtotal - $deposit, 10));
$method = (string) old('method', 'cash');
?>
<div style="max-width:560px;margin-inline:auto">
  <a class="back-link" href="<?= e(url('panel')) ?>"><?= icon('chevron-start') ?> امروز</a>
  <div class="page-head">
    <div class="page-head__text">
      <h1 class="page-head__title">تسویه</h1>
      <p class="page-head__sub"><?= e($customer['name'] ?? 'مشتری') ?><?= $staff ? ' · ' . e($staff['name']) : '' ?><?= $groupSize > 1 ? ' · بخشی از رزرو چندنفره' : '' ?></p>
    </div>
  </div>

  <section class="card mb-4" aria-label="اقلام">
    <div class="card__body">
      <dl class="kv">
        <?php foreach ($items as $it): ?>
          <div class="kv__row"><dt><?= e($it['service_name']) ?></dt><dd class="num"><?= e(toman((int) $it['price'])) ?></dd></div>
        <?php endforeach; ?>
        <?php if ($deposit > 0): ?>
          <div class="kv__row"><dt>بیعانهٔ دریافت‌شده</dt><dd class="num success-text">− <?= e(toman($deposit)) ?></dd></div>
        <?php endif; ?>
        <div class="kv__row kv__row--total"><dt>قابل دریافت</dt><dd class="num"><?= e(toman($dueToman * 10)) ?></dd></div>
      </dl>
      <?php if (array_filter($items, static fn ($i) => ($i['price_type'] ?? '') === 'from')): ?>
        <p class="text-sm muted mt-2">بعضی خدمات «از …» قیمت‌گذاری شده‌اند؛ اگر کار بیشتری انجام شده، مبلغ را اصلاح کنید.</p>
      <?php endif; ?>
    </div>
  </section>

  <form method="post" action="<?= e(url('panel/pay/' . $appointment['id'])) ?>" class="card"><div class="card__body stack">
    <?= csrf_field() ?>
    <div class="form-grid form-grid--2">
      <div class="field">
        <label class="field__label" for="amount_toman">مبلغ خدمات</label>
        <div class="input-group"><input class="input input--lg num" id="amount_toman" name="amount_toman" inputmode="numeric" required value="<?= e((string) old('amount_toman', (string) $dueToman)) ?>" data-numeric <?= field_error('amount_toman') ? 'aria-invalid="true" aria-describedby="amount_toman-error"' : '' ?>><span class="input-group__addon">تومان</span></div>
        <?= partial('field-error', ['key' => 'amount_toman']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="discount_toman">تخفیف <span class="field__optional">(اختیاری)</span></label>
        <div class="input-group"><input class="input num" id="discount_toman" name="discount_toman" inputmode="numeric" value="<?= e((string) old('discount_toman', '0')) ?>" data-numeric><span class="input-group__addon">تومان</span></div>
      </div>
      <div class="field">
        <label class="field__label" for="tip_toman">انعام <span class="field__optional">(اختیاری)</span></label>
        <div class="input-group"><input class="input num" id="tip_toman" name="tip_toman" inputmode="numeric" value="<?= e((string) old('tip_toman', '0')) ?>" data-numeric><span class="input-group__addon">تومان</span></div>
      </div>
    </div>
    <fieldset class="field">
      <legend class="field__label mb-2">روش دریافت</legend>
      <div class="choice-grid" style="--min:120px">
        <?php foreach (['cash' => ['نقدی', 'banknote'], 'card_to_card' => ['کارت‌به‌کارت', 'card'], 'pos' => ['کارتخوان', 'wallet']] as $val => [$label, $symbol]): ?>
          <label class="choice choice--compact">
            <input class="choice__input" type="radio" name="method" value="<?= $val ?>" <?= $method === $val ? 'checked' : '' ?>>
            <span class="choice__card"><?= icon($symbol) ?> <?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <div class="card card--accent"><div class="card__body spread">
      <span>مبلغ دریافتی (با انعام، منهای تخفیف)</span>
      <strong class="title-sm num" data-sum="amount_toman,tip_toman" data-sum-minus="discount_toman" role="status" aria-live="polite"><?= e(toman($dueToman * 10)) ?></strong>
    </div></div>
    <p class="text-sm muted">«مبلغ خدمات» همان مبلغ پس از کسر بیعانه است. تخفیف جدا ثبت می‌شود تا در گزارش دیده شود.</p>
    <button type="submit" class="btn btn--primary btn--lg btn--block">تأیید دریافت و ثبت</button>
  </div></form>
</div>
