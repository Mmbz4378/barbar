<?php
/**
 * نوار روزهای نزدیک. روزِ بدون وقت آزاد هم دیده می‌شود ولی خاموش —
 * مشتری باید بداند آن روز پر است، نه اینکه روز غیب شود.
 *
 * @var array<int,array{date:string,label:string,day:string,month:string,available:bool,selected:bool}> $days
 * @var callable(string):string $linkFor
 */
?>
<div class="day-strip" role="list" aria-label="انتخاب روز">
  <?php foreach ($days as $d): ?>
    <?php if ($d['available'] || $d['selected']): ?>
      <a role="listitem" class="day" href="<?= e($linkFor($d['date'])) ?>" <?= $d['selected'] ? 'aria-current="date"' : '' ?>>
        <span class="day__label"><?= e($d['label']) ?></span>
        <span class="day__num"><?= e($d['day']) ?></span>
        <span class="day__note"><?= e($d['available'] ? $d['month'] : 'پر') ?></span>
      </a>
    <?php else: ?>
      <span role="listitem" class="day day--off" aria-label="<?= e($d['label'] . ' ' . $d['day'] . ' ' . $d['month'] . '، بدون وقت آزاد') ?>">
        <span class="day__label"><?= e($d['label']) ?></span>
        <span class="day__num"><?= e($d['day']) ?></span>
        <span class="day__note">پر</span>
      </span>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
