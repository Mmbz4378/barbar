<?php
/**
 * کارت عدد اصلی با مقایسه با دورهٔ قبل.
 *
 * @var string     $label
 * @var string     $value    متن آماده (عدد فارسی یا مبلغ)
 * @var string     $icon
 * @var float|int|null $current
 * @var float|int|null $previous  برای درصد تغییر؛ null یعنی بی‌مقایسه
 * @var string|null $hint
 * @var string|null $unit     «٪» برای نرخ‌ها: تغییر به‌صورت واحد درصد
 * @var bool|null  $accent
 */
$delta = null;
if (isset($previous, $current) && $previous !== null) {
    if (($unit ?? '') === 'pp') {
        $delta = round((float) $current - (float) $previous, 1);
    } elseif ((float) $previous > 0) {
        $delta = round(((float) $current - (float) $previous) / (float) $previous * 100);
    } elseif ((float) $current > 0) {
        $delta = null; // از صفر: درصد بی‌معناست
    } else {
        $delta = 0.0;
    }
}
$deltaText = $delta === null ? null : (($delta > 0 ? '+' : ($delta < 0 ? '−' : '')) . fa_num(abs($delta)) . (($unit ?? '') === 'pp' ? ' واحد درصد' : '٪'));
?>
<div class="stat<?= !empty($accent) ? ' stat--accent' : '' ?>">
  <span class="stat__label"><?= icon($icon ?? 'chart') ?> <?= e($label) ?></span>
  <span class="stat__value num"><?= e($value) ?></span>
  <?php if ($deltaText !== null): ?>
    <span class="stat__delta"><?= icon($delta > 0 ? 'arrow-up' : ($delta < 0 ? 'arrow-down' : 'circle')) ?> <span class="num"><?= e($deltaText) ?></span> <span class="muted">نسبت به دورهٔ قبل</span></span>
  <?php elseif (!empty($hint)): ?>
    <span class="stat__hint"><?= e($hint) ?></span>
  <?php endif; ?>
</div>
