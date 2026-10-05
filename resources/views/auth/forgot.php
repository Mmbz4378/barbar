<div class="stack">
  <div class="stack stack-xs">
    <h1 class="title-md">فراموشی رمز</h1>
    <p class="text-sm muted">شمارهٔ موبایل حسابتان را وارد کنید. کدی پیامک می‌شود و با آن رمز تازه می‌گذارید.</p>
  </div>
  <form method="post" action="<?= e(url('login/forgot')) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="phone">شمارهٔ موبایل</label>
      <input class="input input--lg input--ltr num" id="phone" type="tel" name="phone" inputmode="tel" autocomplete="tel" autofocus required placeholder="09123456789" dir="ltr" value="<?= e((string) old('phone')) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
      <?= partial('field-error', ['key' => 'phone']) ?>
    </div>
    <button type="submit" class="btn btn--primary btn--lg btn--block">ارسال کد</button>
  </form>
  <p class="text-sm muted">پیامک نمی‌رسد؟ مدیر سامانه (یا صاحب سالن برای کارکنانش) می‌تواند رمزتان را بازنشانی کند.</p>
  <a class="btn btn--ghost btn--block" href="<?= e(url('login')) ?>">بازگشت به ورود</a>
</div>
