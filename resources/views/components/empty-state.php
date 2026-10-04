<?php
/**
 * @var string $icon
 * @var string $title
 * @var string|null $text
 * @var string|null $actionHref
 * @var string|null $actionLabel
 * @var bool|null $dashed
 */
?>
<div class="empty<?= !empty($dashed) ? ' empty--dashed' : '' ?>">
  <span class="icon-tile icon-tile--lg icon-tile--neutral"><?= icon($icon ?? 'info') ?></span>
  <p class="empty__title"><?= e($title) ?></p>
  <?php if (!empty($text)): ?><p class="empty__text"><?= e($text) ?></p><?php endif; ?>
  <?php if (!empty($actionHref)): ?><a class="btn btn--secondary" href="<?= e($actionHref) ?>"><?= e($actionLabel ?? 'ادامه') ?></a><?php endif; ?>
</div>
