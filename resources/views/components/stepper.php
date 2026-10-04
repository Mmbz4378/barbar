<?php
/** @var array<int,array{key:string,label:string,state:string,href:?string}> $steps */
$currentIndex = 0;
foreach ($steps as $i => $s) { if ($s['state'] === 'current') { $currentIndex = $i; } }
?>
<nav class="stepper" aria-label="مراحل رزرو">
  <p class="sr-only">گام <?= e(fa_num($currentIndex + 1)) ?> از <?= e(fa_num(count($steps))) ?>: <?= e($steps[$currentIndex]['label'] ?? '') ?></p>
  <ol class="stepper__list">
    <?php foreach ($steps as $i => $s): ?>
      <li class="stepper__item stepper__item--<?= e($s['state']) ?>" <?= $s['state'] === 'current' ? 'aria-current="step"' : '' ?>>
        <span class="stepper__bar" aria-hidden="true"></span>
        <span class="stepper__label">
          <?php if ($s['state'] === 'done' && $s['href'] !== null): ?>
            <a href="<?= e(url($s['href'])) ?>"><?= icon('check', 'icon') ?> <?= e($s['label']) ?></a>
          <?php else: ?>
            <span aria-hidden="true"><?= e(fa_num($i + 1)) ?>.</span> <?= e($s['label']) ?>
          <?php endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>
