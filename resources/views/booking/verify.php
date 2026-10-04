<?php /** @var array $salon @var array $stepper @var string $phone @var ?string $debugLine */ ?>
<div class="wizard">
  <div class="wizard__main">
    <?= partial('stepper', ['steps' => $stepper]) ?>
    <div class="step-head">
      <h1 class="step-head__title">کد تأیید</h1>
      <p class="step-head__sub">کد پنج‌رقمی پیامک‌شده به <span class="ltr num strong"><?= e(phone_local($phone)) ?></span> را وارد کن.</p>
    </div>
    <?php if ($debugLine): ?><div class="alert alert--info mb-4" dir="ltr"><?= icon('info') ?><div class="alert__body">DEV: <?= e($debugLine) ?></div></div><?php endif; ?>
    <form method="post" action="<?= e(url('s/' . $salon['slug'] . '/verify')) ?>" class="stack">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="booking-code">کد تأیید</label>
        <input class="input input--code num" id="booking-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="5" required autofocus data-numeric>
      </div>
      <button type="submit" class="btn btn--primary btn--lg btn--block">تأیید و ثبت نوبت</button>
      <a class="btn btn--ghost btn--block" href="<?= e(url('s/' . $salon['slug'] . '/phone')) ?>">تغییر شماره</a>
    </form>
  </div>
  <?php include __DIR__ . '/_aside.php'; ?>
</div>
