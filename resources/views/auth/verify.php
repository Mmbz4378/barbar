<?php /** @var string $phone @var ?string $debugLine */ ?>
<div class="stack">
  <div class="stack stack-xs">
    <h1 class="title-md">کد تأیید</h1>
    <p class="text-sm muted">کد پیامک‌شده به <span class="ltr num strong"><?= e(phone_local($phone)) ?></span> را وارد کنید.</p>
  </div>
  <?php if ($debugLine): ?><div class="alert alert--info" dir="ltr"><?= icon('info') ?><div class="alert__body">DEV: <?= e($debugLine) ?></div></div><?php endif; ?>
  <form method="post" action="<?= e(url('login/verify')) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="otp-code">کد تأیید</label>
      <input class="input input--code num" type="text" id="otp-code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="6" required data-numeric>
    </div>
    <button type="submit" class="btn btn--primary btn--lg btn--block">تأیید و ورود</button>
  </form>
  <a class="btn btn--ghost btn--block" href="<?= e(url('login')) ?>">تغییر شماره</a>
</div>
