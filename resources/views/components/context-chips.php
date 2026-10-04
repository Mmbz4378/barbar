<?php /** @var array<int,array{label:string,value:string,href:string}> $chips */ ?>
<?php if (!empty($chips)): ?>
<div class="context-chips">
  <?php foreach ($chips as $chip): ?>
    <a class="context-chip" href="<?= e(url($chip['href'])) ?>">
      <span class="context-chip__label"><?= e($chip['label']) ?></span>
      <span class="context-chip__value"><?= e($chip['value']) ?></span>
      <span class="context-chip__edit">تغییر<span class="sr-only"> <?= e($chip['label']) ?></span></span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
