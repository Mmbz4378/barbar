<?php /** @var string $active */ ?>
<nav class="tabs" aria-label="نوع گزارش">
  <a class="tab" href="<?= e(url('panel/reports')) ?>" <?= $active === 'daily' ? 'aria-current="page"' : '' ?>><?= icon('calendar') ?> روزانه</a>
  <a class="tab" href="<?= e(url('panel/reports/monthly')) ?>" <?= $active === 'monthly' ? 'aria-current="page"' : '' ?>><?= icon('chart') ?> ماهانه</a>
</nav>
