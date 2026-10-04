<?php /** @var bool|null $schemaOutdated */ ?>
<div class="empty">
  <span class="icon-tile icon-tile--lg icon-tile--danger"><?= icon('alert') ?></span>
  <?php if (!empty($schemaOutdated)): ?>
    <h1 class="empty__title">به‌روزرسانی دیتابیس لازم است</h1>
    <p class="empty__text">فایل‌های نسخهٔ تازه روی هاست قرار گرفته‌اند ولی ساختار دیتابیس هنوز به‌روز نشده است. مدیر سامانه صفحهٔ «سلامت سیستم» را باز کند و «اجرای مهاجرت‌ها» را بزند.</p>
    <a class="btn btn--primary" href="<?= e(url('doctor.php')) ?>"><?= icon('refresh') ?> سلامت سیستم</a>
  <?php else: ?>
    <h1 class="empty__title">خطایی رخ داد</h1>
    <p class="empty__text">درخواست شما انجام نشد. چند لحظه بعد دوباره تلاش کنید؛ اگر مشکل ادامه داشت با سالن تماس بگیرید.</p>
    <button type="button" class="btn btn--secondary" data-back>بازگشت</button>
  <?php endif; ?>
</div>
