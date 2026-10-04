<?php /** @var string $message */ ?>
<div class="card"><div class="card__body stack">
  <div class="alert alert--warning"><?= icon('alert') ?><div class="alert__body"><span class="alert__title">نتیجهٔ پرداخت هنوز مشخص نیست</span><?= e($message) ?></div></div>
  <p class="text-sm muted">اگر مبلغ از حسابتان کم شده، پیش از پرداخت دوباره نتیجه را بررسی کنید؛ پرداخت تکراری ثبت نمی‌شود.</p>
  <div class="btn-row">
    <a class="btn btn--primary" href="<?= e($_SERVER['REQUEST_URI'] ?? url('me')) ?>">بررسی دوباره</a>
    <a class="btn btn--secondary" href="<?= e(url('me')) ?>">نوبت‌های من</a>
  </div>
</div></div>
