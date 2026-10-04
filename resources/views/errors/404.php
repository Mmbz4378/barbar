<?php /** @var string $message */ ?>
<div class="empty">
  <span class="icon-tile icon-tile--lg icon-tile--neutral"><?= icon('search') ?></span>
  <h1 class="empty__title">پیدا نشد</h1>
  <p class="empty__text"><?= e($message ?? 'صفحه‌ای که دنبالش بودید پیدا نشد.') ?></p>
  <div class="btn-row justify-center">
    <a class="btn btn--primary" href="<?= e(url('discover')) ?>">جست‌وجوی سالن</a>
    <a class="btn btn--secondary" href="<?= e(url('me')) ?>">نوبت‌های من</a>
  </div>
</div>
