<?php /** @var string $message */ ?>
<div class="card__body stack items-center center">
  <span class="icon-tile icon-tile--lg"><?= icon('cog') ?></span>
  <h1 class="title-md"><?= e(brand()) ?> در دست تعمیر است</h1>
  <p class="muted"><?= nl2br(e($message)) ?></p>
  <p class="text-xs muted">نوبت‌های ثبت‌شده محفوظ‌اند.</p>
</div>
