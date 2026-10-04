<?php
/**
 * پیام‌های یک‌باره (پس از ثبت فرم).
 *
 * خطا با role=alert خوانده می‌شود، موفقیت با role=status — صفحه‌خوان
 * بدون جابه‌جایی فوکوس خبردار می‌شود.
 */
$messages = [];
foreach (['error' => ['danger', 'alert', 'alert'], 'warning' => ['warning', 'alert', 'alert'], 'success' => ['success', 'status', 'circle-check'], 'info' => ['info', 'status', 'info']] as $key => [$tone, $role, $symbol]) {
    $text = flash($key);
    if (is_string($text) && $text !== '') {
        $messages[] = [$tone, $role, $symbol, $text];
    }
}
?>
<?php if ($messages !== []): ?>
<div class="flash-stack">
  <?php foreach ($messages as [$tone, $role, $symbol, $text]): ?>
    <div class="alert alert--<?= e($tone) ?>" role="<?= e($role) ?>">
      <?= icon($symbol) ?>
      <div class="alert__body"><?= e($text) ?></div>
      <button type="button" class="btn btn--ghost btn--icon btn--sm alert__close" data-dismiss aria-label="بستن پیام"><?= icon('x') ?></button>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
