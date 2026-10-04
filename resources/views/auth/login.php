<div class="stack">
  <div class="stack stack-xs">
    <h1 class="title-md">ورود کارکنان سالن</h1>
    <p class="text-sm muted">شمارهٔ موبایلتان را وارد کنید تا کد یک‌بارمصرف برایتان پیامک شود. اگر سالن ندارید، پس از ورود می‌توانید بسازید.</p>
  </div>
  <form method="post" action="<?= e(url('login')) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="phone">شمارهٔ موبایل</label>
      <input class="input input--lg input--ltr num" id="phone" type="tel" name="phone" inputmode="tel" autocomplete="tel" autofocus required placeholder="09123456789" dir="ltr" value="<?= e((string) old('phone')) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
      <?= partial('field-error', ['key' => 'phone']) ?>
    </div>
    <button type="submit" class="btn btn--primary btn--lg btn--block">ارسال کد</button>
  </form>
  <p class="text-xs muted center">مشتری هستید؟ <a href="<?= e(url('me')) ?>">نوبت‌های من</a> · <a href="<?= e(url('discover')) ?>">کشف سالن</a></p>
</div>
