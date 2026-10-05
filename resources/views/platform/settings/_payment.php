<?php
/** @var array $env */
use App\Domain\System\SiteSettings as S;

$driver = S::str('payment.driver');
$merchantState = S::has('payment.zarinpal.merchant_id') ? (S::secret('payment.zarinpal.merchant_id') !== '' ? 'ذخیره‌شده در پنل' : 'ذخیره‌شده ولی باز نمی‌شود؛ دوباره وارد کنید')
    : ((string) App\Core\Env::get('ZARINPAL_MERCHANT_ID', '') !== '' ? 'از فایل .env' : 'تنظیم نشده');
?>
<form method="post" action="<?= e(url('platform/settings/payment')) ?>" class="stack stack-lg">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">پرداخت آنلاین</h2>
    <p class="text-sm muted">برای بیعانه و تسویهٔ آنلاین. بدون آن، پرداخت حضوری و کارت‌به‌کارت از پنل سالن ثبت می‌شود.</p>
    <div class="field">
      <label class="field__label" for="pay-driver">درگاه</label>
      <select class="select" id="pay-driver" name="driver">
        <option value="">از فایل .env (اکنون: <?= e($env['payment_driver'] === 'zarinpal' ? 'زرین‌پال' : 'خاموش') ?>)</option>
        <option value="disabled" <?= $driver === 'disabled' ? 'selected' : '' ?>>خاموش</option>
        <option value="zarinpal" <?= $driver === 'zarinpal' ? 'selected' : '' ?>>زرین‌پال</option>
      </select>
    </div>
    <div class="field">
      <label class="field__label" for="merchant">کد پذیرندهٔ زرین‌پال (Merchant ID)</label>
      <input class="input input--ltr" id="merchant" name="merchant_id" type="password" dir="ltr" autocomplete="off" aria-describedby="merchant-hint<?= field_error('merchant_id') ? ' merchant_id-error' : '' ?>" <?= field_error('merchant_id') ? 'aria-invalid="true"' : '' ?>>
      <p class="field__hint" id="merchant-hint">وضعیت: <?= e($merchantState) ?>. خالی یعنی بدون تغییر.</p>
      <?= partial('field-error', ['key' => 'merchant_id']) ?>
    </div>
    <?php if (S::has('payment.zarinpal.merchant_id')): ?><label class="check check--compact"><input type="checkbox" name="clear_merchant_id" value="1"><span>پاک‌کردن از پنل (برگشت به .env)</span></label><?php endif; ?>
    <label class="check"><input type="checkbox" name="sandbox" value="1" <?= S::bool('payment.zarinpal.sandbox', false) ? 'checked' : '' ?>><span class="check__text"><span class="strong">حالت آزمایشی (sandbox)</span><span class="check__hint">پرداخت واقعی انجام نمی‌شود؛ فقط برای آزمون.</span></span></label>
  </div></section>
  <div><button class="btn btn--primary btn--lg" type="submit">ذخیره</button></div>
</form>
