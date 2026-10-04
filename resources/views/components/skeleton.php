<?php
/**
 * جای‌نگهدار «در حال بارگذاری».
 *
 * @var string|null $variant slots | list | cards | text
 * @var int|null    $count
 *
 * داخل <template data-skeleton-tpl> در یک ناحیهٔ [data-skeleton-region]
 * گذاشته می‌شود؛ app.js وقتی لینکی با data-skeleton-for به آن ناحیه
 * زده شود، همین را تا رسیدن صفحهٔ تازه نشان می‌دهد.
 */
$variant = $variant ?? 'list';
$count = max(1, (int) ($count ?? ['slots' => 9, 'list' => 4, 'cards' => 3, 'text' => 3][$variant] ?? 3));
$widths = ['92%', '68%', '80%', '56%', '74%'];
?>
<div class="skeleton-group" role="status">
  <span class="sr-only">در حال بارگذاری…</span>
  <?php if ($variant === 'slots'): ?>
    <span class="skeleton skeleton--text" style="--w:30%" aria-hidden="true"></span>
    <div class="slot-grid" aria-hidden="true">
      <?php for ($i = 0; $i < $count; $i++): ?><span class="skeleton skeleton--pill"></span><?php endfor; ?>
    </div>
  <?php elseif ($variant === 'cards'): ?>
    <?php for ($i = 0; $i < $count; $i++): ?><span class="skeleton skeleton--block" aria-hidden="true"></span><?php endfor; ?>
  <?php elseif ($variant === 'text'): ?>
    <?php for ($i = 0; $i < $count; $i++): ?><span class="skeleton skeleton--text" style="--w:<?= $widths[$i % 5] ?>" aria-hidden="true"></span><?php endfor; ?>
  <?php else: ?>
    <div class="card" aria-hidden="true">
      <?php for ($i = 0; $i < $count; $i++): ?>
        <div class="skeleton-row">
          <span class="skeleton skeleton--circle"></span>
          <span class="grow"><span class="skeleton skeleton--text" style="--w:<?= $widths[$i % 5] ?>"></span><span class="skeleton skeleton--text" style="--w:40%"></span></span>
        </div>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
