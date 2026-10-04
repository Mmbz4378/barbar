<?php /** @var string $phone @var ?string $debugLine */ ?>
<div class="stack stack-lg" style="max-width:440px;margin-inline:auto">
  <div class="step-head">
    <h1 class="step-head__title">کد تأیید</h1>
    <p class="step-head__sub">کد پیامک‌شده به <span class="ltr num strong"><?= e(phone_local($phone)) ?></span> را بزن.</p>
  </div>
  <?php if ($debugLine): ?><div class="alert alert--info" dir="ltr"><?= icon('info') ?><div class="alert__body"><?= e($debugLine) ?></div></div><?php endif; ?>
  <form method="post" action="<?= e(url('me/verify')) ?>" class="card"><div class="card__body stack">
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="me-code">کد تأیید</label>
      <input class="input input--code num" id="me-code" type="text" name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="6" data-numeric>
    </div>
    <button type="submit" class="btn btn--primary btn--lg btn--block">ورود</button>
  </div></form>
  <a class="btn btn--ghost" href="<?= e(url('me/login')) ?>">شماره را اشتباه زدم</a>
</div>
