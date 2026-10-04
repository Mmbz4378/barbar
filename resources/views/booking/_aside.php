<?php
/**
 * ستون کناری مسیر رزرو (دسکتاپ): اطلاعات سالن و قوانین رزرو.
 *
 * @var array $salon
 */
use App\Domain\Booking\SlotFinder;

$settings = (new SlotFinder())->settings((int) $salon['id']);
?>
<aside class="wizard__aside only-desktop" aria-label="اطلاعات سالن">
  <div class="card">
    <div class="card__body stack stack-md">
      <div class="row">
        <span class="icon-tile"><?= icon('store') ?></span>
        <div class="stack stack-xs" style="min-width:0">
          <strong class="truncate"><?= e($salon['name']) ?></strong>
          <span class="text-sm muted"><?= e(term('salon_type')) ?></span>
        </div>
      </div>
      <?php if (!empty($salon['address'])): ?>
        <p class="row row-start text-sm"><?= icon('map-pin', 'icon muted') ?><span><?= e(join_parts([$salon['city'] ?? '', $salon['address']])) ?></span></p>
      <?php endif; ?>
      <?php if (!empty($salon['phone'])): ?>
        <p class="row text-sm"><?= icon('phone', 'icon muted') ?><a class="ltr num" href="tel:<?= e($salon['phone']) ?>"><?= e(fa_num($salon['phone'])) ?></a></p>
      <?php endif; ?>
      <hr class="divider">
      <ul class="stack stack-sm text-sm muted" style="list-style:none">
        <li class="row row-start"><?= icon('circle-check', 'icon success-text') ?><span>پرداخت پس از انجام خدمت در سالن.</span></li>
        <?php if ($settings['cancel_notice'] > 0): ?>
          <li class="row row-start"><?= icon('clock', 'icon') ?><span>لغو آنلاین تا <?= e(duration_text($settings['cancel_notice'])) ?> پیش از نوبت.</span></li>
        <?php else: ?>
          <li class="row row-start"><?= icon('clock', 'icon') ?><span>لغو آنلاین تا پیش از شروع نوبت.</span></li>
        <?php endif; ?>
        <?php if ($settings['min_notice'] > 0): ?>
          <li class="row row-start"><?= icon('hourglass', 'icon') ?><span>رزرو آنلاین دست‌کم <?= e(duration_text($settings['min_notice'])) ?> زودتر.</span></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</aside>
