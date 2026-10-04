<?php
/**
 * گزارش ماهانه.
 *
 * @var int $jy
 * @var int $jm
 * @var string $label
 * @var array $bars
 * @var array $prev
 * @var array $next
 * @var bool $isCurrent
 */
$max = max(1, ...array_column($bars, 'total'));
$best = array_reduce($bars, static fn ($carry, $b) => $carry === null || $b['total'] > $carry['total'] ? $b : $carry);
$activeDays = count(array_filter($bars, static fn ($b) => $b['total'] > 0));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">گزارش ماهانه</h1>
    <p class="page-head__sub"><?= e($label) ?></p>
  </div>
  <div class="page-head__actions">
    <nav class="btn-row" aria-label="جابه‌جایی ماه">
      <a class="btn btn--secondary btn--icon" href="<?= e(url('panel/reports/monthly?jy=' . $prev[0] . '&jm=' . $prev[1])) ?>" aria-label="ماه قبل"><?= icon('chevron-start') ?></a>
      <?php if (!$isCurrent): ?>
        <a class="btn btn--secondary" href="<?= e(url('panel/reports/monthly')) ?>">ماه جاری</a>
        <a class="btn btn--secondary btn--icon" href="<?= e(url('panel/reports/monthly?jy=' . $next[0] . '&jm=' . $next[1])) ?>" aria-label="ماه بعد"><?= icon('chevron-end') ?></a>
      <?php endif; ?>
    </nav>
  </div>
</div>
<div class="stack stack-lg">
  <?php $active = 'monthly'; include __DIR__ . '/_tabs.php'; ?>

  <section class="card" aria-labelledby="r-chart">
    <div class="card__header card__header--divided spread">
      <h2 class="card__title" id="r-chart">فروش روزبه‌روز</h2>
      <?php if ($best !== null && $best['total'] > 0): ?><span class="text-sm muted">بهترین روز: <?= e(fa_num($best['day'])) ?>ام · <?= e(toman($best['total'])) ?></span><?php endif; ?>
    </div>
    <div class="card__body">
      <?php if ($activeDays === 0): ?>
        <?= partial('empty-state', ['icon' => 'chart', 'title' => 'در این ماه پرداختی ثبت نشده']) ?>
      <?php else: ?>
        <div class="bars" role="img" aria-label="نمودار فروش روزانهٔ <?= e($label) ?>؛ <?= e(fa_num($activeDays)) ?> روز با فروش">
          <?php foreach ($bars as $b): ?>
            <a class="bars__bar<?= $b['total'] === 0 ? ' bars__bar--empty' : '' ?>" style="--v:<?= $b['total'] === 0 ? 0 : max(2, (int) round($b['total'] / $max * 100)) ?>" href="<?= e(url('panel/reports?date=' . $b['date'])) ?>" title="<?= e(fa_num($b['day']) . ' — ' . toman($b['total'])) ?>" tabindex="-1"></a>
          <?php endforeach; ?>
        </div>
        <div class="spread text-xs muted num" aria-hidden="true" style="margin-top:6px"><span>۱</span><span><?= e(fa_num(intdiv(count($bars), 2))) ?></span><span><?= e(fa_num(count($bars))) ?></span></div>
      <?php endif; ?>
    </div>
  </section>

  <?php include __DIR__ . '/_body.php'; ?>
</div>
