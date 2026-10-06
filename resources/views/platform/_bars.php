<?php
/**
 * نمودار ستونیِ یک‌سری (روزانه یا ماهانه) + جدول همان داده.
 *
 * یک سری، یک رنگ (رنگ تأکید تم)؛ نام سری عنوان کارت است، پس راهنما لازم
 * نیست. هر ستون راهنمای شناور دارد و همان داده در جدولِ زیرش هست — نمودار
 * برای صفحه‌خوان پنهان است و جدول جایش را می‌گیرد.
 *
 * @var string $id
 * @var string $title
 * @var array<int,array{label:string,value:int}> $points
 * @var callable(int):string $format  نمایش مقدار (عدد یا مبلغ)
 * @var string $valueLabel
 */
$max = max(1, ...array_column($points, 'value'));
$sum = array_sum(array_column($points, 'value'));
$peak = $points !== [] ? array_reduce($points, static fn ($c, $p) => $c === null || $p['value'] > $c['value'] ? $p : $c) : null;
$n = count($points);
?>
<section class="card" aria-labelledby="<?= e($id) ?>-title">
  <div class="card__header card__header--divided spread">
    <h2 class="card__title" id="<?= e($id) ?>-title"><?= e($title) ?></h2>
    <span class="text-sm muted">جمع <?= e($format($sum)) ?><?= $peak !== null && $peak['value'] > 0 ? ' · بیشترین ' . e($peak['label']) : '' ?></span>
  </div>
  <div class="card__body">
    <?php if ($sum === 0): ?>
      <?= partial('empty-state', ['icon' => 'chart', 'title' => 'در این بازه داده‌ای نیست']) ?>
    <?php else: ?>
      <div class="chart-bars" aria-hidden="true">
        <div class="bars">
          <?php foreach ($points as $p): ?>
            <span class="bars__bar<?= $p['value'] === 0 ? ' bars__bar--empty' : '' ?>" style="--v:<?= $p['value'] === 0 ? 0 : max(2, (int) round($p['value'] / $max * 100)) ?>" data-tooltip="<?= e($p['label'] . ' — ' . $format($p['value'])) ?>"></span>
          <?php endforeach; ?>
        </div>
        <div class="spread text-xs muted num mt-2"><span><?= e($points[0]['label']) ?></span><?php if ($n > 2): ?><span><?= e($points[intdiv($n, 2)]['label']) ?></span><?php endif; ?><span><?= e($points[$n - 1]['label']) ?></span></div>
      </div>
      <details class="mt-3">
        <summary class="text-sm">نمایش جدول داده</summary>
        <div class="table-wrap mt-2">
          <table class="table table--compact">
            <thead><tr><th scope="col">تاریخ</th><th scope="col" class="num"><?= e($valueLabel) ?></th></tr></thead>
            <tbody><?php foreach ($points as $p): ?><tr><td><?= e($p['label']) ?></td><td class="num"><?= e($format($p['value'])) ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
      </details>
    <?php endif; ?>
  </div>
</section>
