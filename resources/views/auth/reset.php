<?php /** @var string $phone @var ?string $debugLine @var int $minLength */ ?>
<div class="stack">
  <div class="stack stack-xs">
    <h1 class="title-md">رمز تازه</h1>
    <p class="text-sm muted">کد پیامک‌شده به <span class="ltr num strong"><?= e(phone_local($phone)) ?></span> و رمز تازه را وارد کنید.</p>
  </div>
  <?php if ($debugLine): ?><div class="alert alert--info" dir="ltr"><?= icon('info') ?><div class="alert__body">DEV: <?= e($debugLine) ?></div></div><?php endif; ?>
  <form method="post" action="<?= e(url('login/reset')) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="otp-code">کد تأیید</label>
      <input class="input input--code num" type="text" id="otp-code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="6" required data-numeric>
    </div>
    <div class="field">
      <label class="field__label" for="password">رمز تازه</label>
      <input class="input input--ltr" id="password" type="password" name="password" autocomplete="new-password" required minlength="<?= (int) $minLength ?>" dir="ltr" aria-describedby="password-hint<?= field_error('password') ? ' password-error' : '' ?>" <?= field_error('password') ? 'aria-invalid="true"' : '' ?>>
      <p class="field__hint" id="password-hint">دست‌کم <?= e(fa_num($minLength)) ?> نویسه؛ ترکیب حرف و عدد امن‌تر است.</p>
      <?= partial('field-error', ['key' => 'password']) ?>
    </div>
    <div class="field">
      <label class="field__label" for="password_confirm">تکرار رمز تازه</label>
      <input class="input input--ltr" id="password_confirm" type="password" name="password_confirm" autocomplete="new-password" required dir="ltr">
    </div>
    <button type="submit" class="btn btn--primary btn--lg btn--block">ذخیرهٔ رمز و ورود</button>
  </form>
  <a class="btn btn--ghost btn--block" href="<?= e(url('login/forgot')) ?>">ارسال دوبارهٔ کد</a>
</div>
