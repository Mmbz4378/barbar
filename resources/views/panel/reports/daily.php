<?php
/**
 * گزارش روزانه.
 *
 * @var string $date
 * @var string $label
 */
use App\Support\Now;

$d = new DateTimeImmutable($date);
$prev = $d->modify('-1 day')->format('Y-m-d');
$next = $d->modify('+1 day')->format('Y-m-d');
$isToday = $date === Now::today()->format('Y-m-d');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">گزارش روزانه</h1>
    <p class="page-head__sub"><?= e($label) ?></p>
  </div>
  <div class="page-head__actions">
    <nav class="btn-row" aria-label="جابه‌جایی روز">
      <a class="btn btn--secondary btn--icon" href="<?= e(url('panel/reports?date=' . $prev)) ?>" aria-label="روز قبل"><?= icon('chevron-start') ?></a>
      <?php if (!$isToday): ?><a class="btn btn--secondary" href="<?= e(url('panel/reports')) ?>">امروز</a><?php endif; ?>
      <?php if ($next <= Now::today()->format('Y-m-d')): ?><a class="btn btn--secondary btn--icon" href="<?= e(url('panel/reports?date=' . $next)) ?>" aria-label="روز بعد"><?= icon('chevron-end') ?></a><?php endif; ?>
    </nav>
  </div>
</div>
<div class="stack stack-lg">
  <?php $active = 'daily'; include __DIR__ . '/_tabs.php'; ?>
  <form method="get" action="<?= e(url('panel/reports')) ?>" class="cluster" aria-label="انتخاب روز">
    <?= partial('jalali-date-input', ['name' => 'date', 'value' => $date, 'label' => 'گزارش', 'years' => [-2, 0]]) ?>
    <button type="submit" class="btn btn--secondary">نمایش</button>
  </form>
  <?php include __DIR__ . '/_body.php'; ?>
</div>
