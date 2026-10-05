<?php
/**
 * @var string     $reason       no_salon | salon_inactive
 * @var array|null $salon
 * @var bool       $otherSalons
 */
?>
<div class="stack">
  <?php if ($reason === 'salon_inactive'): ?>
    <div class="stack stack-xs">
      <h1 class="title-md">پنل «<?= e((string) ($salon['name'] ?? 'سالن')) ?>» فعال نیست</h1>
      <p class="text-sm muted">این سالن هنوز توسط مدیر سامانه فعال نشده یا موقتاً غیرفعال شده است. رزرو آنلاین هم تا فعال‌شدن بسته است. برای پیگیری با مدیر سامانه تماس بگیرید.</p>
    </div>
  <?php else: ?>
    <div class="stack stack-xs">
      <h1 class="title-md">حساب شما به سالنی وصل نیست</h1>
      <p class="text-sm muted">وارد شده‌اید، ولی هنوز به پنل هیچ سالنی دسترسی ندارید. سالن‌ها را مدیر سامانه می‌سازد؛ اگر صاحب یا کارمند سالن هستید، از مدیر سامانه یا صاحب سالن بخواهید شما را اضافه کند.</p>
    </div>
  <?php endif; ?>
  <div class="btn-row justify-between">
    <?php if (!empty($otherSalons)): ?><a class="btn btn--secondary" href="<?= e(url('salons')) ?>">سالن‌های دیگر من</a><?php endif; ?>
    <a class="btn btn--ghost" href="<?= e(url('account')) ?>"><?= icon('user') ?> حساب من</a>
    <a class="btn btn--ghost" href="<?= e(url('logout')) ?>">خروج</a>
  </div>
</div>
