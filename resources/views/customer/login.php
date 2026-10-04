<div class="stack stack-lg" style="max-width:440px;margin-inline:auto">
  <div class="step-head">
    <h1 class="step-head__title">نوبت‌های من</h1>
    <p class="step-head__sub">همان شماره‌ای را بزن که با آن نوبت گرفته‌ای؛ کد تأیید برایت پیامک می‌شود.</p>
  </div>
  <form method="post" action="<?= e(url('me/login')) ?>" class="card"><div class="card__body stack">
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="me-phone">شمارهٔ موبایل</label>
      <input class="input input--lg input--ltr num" id="me-phone" type="tel" name="phone" dir="ltr" required autofocus inputmode="tel" autocomplete="tel" placeholder="09123456789" value="<?= e((string) old('phone')) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
      <?= partial('field-error', ['key' => 'phone']) ?>
    </div>
    <button type="submit" class="btn btn--primary btn--lg btn--block">ارسال کد <?= icon('chevron-end') ?></button>
  </div></form>
  <p class="text-sm muted center">اگر تازه نوبت گرفته‌ای، لینک کارت نوبتت هم کار می‌کند و برای دیدنش ورود لازم نیست.</p>
</div>
