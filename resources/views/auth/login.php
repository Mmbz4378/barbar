<?php
/**
 * @var string $method           password | otp
 * @var bool   $passwordEnabled
 * @var bool   $otpEnabled
 * @var bool   $registrationOpen
 */
?>
<div class="stack">
  <div class="stack stack-xs">
    <h1 class="title-md">ورود به پنل</h1>
    <p class="text-sm muted">
      <?= $method === 'password'
        ? 'با نام کاربری یا شمارهٔ موبایل و رمزتان وارد شوید.'
        : 'شمارهٔ موبایلتان را وارد کنید تا کد یک‌بارمصرف برایتان پیامک شود.' ?>
      <?= $registrationOpen ? ' اگر سالن ندارید، پس از ورود می‌توانید بسازید.' : '' ?>
    </p>
  </div>

  <?php if ($passwordEnabled && $otpEnabled): ?>
    <div class="segmented segmented--block" role="group" aria-label="روش ورود">
      <a href="<?= e(url('login?method=password')) ?>" <?= $method === 'password' ? 'aria-current="page"' : '' ?>><?= icon('lock') ?> رمز عبور</a>
      <a href="<?= e(url('login?method=otp')) ?>" <?= $method === 'otp' ? 'aria-current="page"' : '' ?>><?= icon('message') ?> کد پیامکی</a>
    </div>
  <?php endif; ?>

  <?php if ($method === 'password'): ?>
    <form method="post" action="<?= e(url('login/password')) ?>" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="identifier">نام کاربری یا شمارهٔ موبایل</label>
        <input class="input input--lg input--ltr" id="identifier" type="text" name="identifier" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required dir="ltr" value="<?= e((string) old('identifier')) ?>" <?= field_error('identifier') ? 'aria-invalid="true" aria-describedby="identifier-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'identifier']) ?>
      </div>
      <div class="field">
        <div class="cluster justify-between">
          <label class="field__label" for="password">رمز عبور</label>
          <?php if ($otpEnabled): ?><a class="text-sm" href="<?= e(url('login/forgot')) ?>">رمز را فراموش کرده‌اید؟</a><?php endif; ?>
        </div>
        <input class="input input--lg input--ltr" id="password" type="password" name="password" autocomplete="current-password" required dir="ltr" <?= field_error('password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'password']) ?>
      </div>
      <button type="submit" class="btn btn--primary btn--lg btn--block">ورود</button>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(url('login')) ?>" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="phone">شمارهٔ موبایل</label>
        <input class="input input--lg input--ltr num" id="phone" type="tel" name="phone" inputmode="tel" autocomplete="tel" autofocus required placeholder="09123456789" dir="ltr" value="<?= e((string) old('phone')) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'phone']) ?>
      </div>
      <button type="submit" class="btn btn--primary btn--lg btn--block">ارسال کد</button>
    </form>
  <?php endif; ?>

  <p class="text-xs muted center">مشتری هستید؟ <a href="<?= e(url('me')) ?>">نوبت‌های من</a> · <a href="<?= e(url('discover')) ?>">کشف سالن</a></p>
</div>
