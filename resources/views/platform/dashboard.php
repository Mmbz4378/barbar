<?php
/**
 * @var App\Domain\Reports\ReportRange $range
 * @var array $kpis
 * @var array $previous
 * @var array $todayKpis
 * @var array $daily
 * @var array $topSalons
 * @var array $totals
 * @var array $alerts
 * @var array $events
 */
use App\Support\AuditLabels;

$kpi = static fn (array $data): string => App\Core\View::render('platform._kpi', $data);
$num = static fn (int $n): string => fa_num($n);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">داشبورد</h1>
    <p class="page-head__sub"><?= e(fa_num((int) ($totals['active'] ?? 0))) ?> سالن فعال از <?= e(fa_num((int) ($totals['salons'] ?? 0))) ?> · <?= e(fa_num((int) ($totals['panel_users'] ?? 0))) ?> کاربر پنل · ۳۰ روز اخیر</p>
  </div>
  <div class="page-head__actions">
    <a class="btn btn--primary" href="<?= e(url('platform/salons/new')) ?>"><?= icon('plus') ?> سالن تازه</a>
    <a class="btn btn--secondary" href="<?= e(url('platform/reports')) ?>"><?= icon('trending') ?> گزارش کامل</a>
  </div>
</div>

<div class="stack stack-lg">
  <?php if ($alerts !== []): ?>
    <section class="card" aria-labelledby="dash-alerts"><div class="card__body stack stack-sm">
      <h2 class="card__title" id="dash-alerts">نیاز به رسیدگی</h2>
      <ul class="list" role="list">
        <?php foreach ($alerts as $a): ?>
          <li><a class="list-row" href="<?= e(url(ltrim($a['href'], '/'))) ?>">
            <span class="icon-tile icon-tile--<?= e($a['tone'] === 'danger' ? 'danger' : ($a['tone'] === 'warning' ? 'warning' : 'neutral')) ?>"><?= icon($a['icon']) ?></span>
            <span class="list-row__body"><span class="list-row__title"><?= e($a['text']) ?></span></span>
            <?= icon('chevron-end', 'list-row__chevron') ?>
          </a></li>
        <?php endforeach; ?>
      </ul>
    </div></section>
  <?php endif; ?>

  <div class="stats" style="--cols:4">
    <?= $kpi(['label' => 'نوبت امروز', 'icon' => 'calendar', 'value' => fa_num((int) $todayKpis['bookings']), 'hint' => fa_num((int) $todayKpis['completed']) . ' انجام‌شده · ' . fa_num((int) $todayKpis['walkins']) . ' حضوری', 'accent' => true]) ?>
    <?= $kpi(['label' => 'نوبت (۳۰ روز)', 'icon' => 'calendar-days', 'value' => fa_num((int) $kpis['bookings']), 'current' => $kpis['bookings'], 'previous' => $previous['bookings']]) ?>
    <?= $kpi(['label' => 'درآمد ثبت‌شده', 'icon' => 'wallet', 'value' => toman((int) $kpis['revenue']), 'current' => $kpis['revenue'], 'previous' => $previous['revenue']]) ?>
    <?= $kpi(['label' => 'لغو و نیامده', 'icon' => 'user-x', 'value' => fa_num($kpis['cancel_rate'] + $kpis['no_show_rate']) . '٪', 'current' => $kpis['cancel_rate'] + $kpis['no_show_rate'], 'previous' => $previous['cancel_rate'] + $previous['no_show_rate'], 'unit' => 'pp']) ?>
    <?= $kpi(['label' => 'سالن با نوبت', 'icon' => 'store', 'value' => fa_num((int) $kpis['active_salons']), 'current' => $kpis['active_salons'], 'previous' => $previous['active_salons']]) ?>
    <?= $kpi(['label' => 'مشتری تازه', 'icon' => 'user-plus', 'value' => fa_num((int) $kpis['new_customers']), 'current' => $kpis['new_customers'], 'previous' => $previous['new_customers']]) ?>
    <?= $kpi(['label' => 'رزرو آنلاین', 'icon' => 'sparkles', 'value' => fa_num((int) $kpis['online']), 'current' => $kpis['online'], 'previous' => $previous['online']]) ?>
    <?= $kpi(['label' => 'پیامک فرستاده', 'icon' => 'message', 'value' => fa_num((int) $kpis['sms_sent']), 'hint' => (int) $kpis['sms_failed'] > 0 ? fa_num((int) $kpis['sms_failed']) . ' ناموفق' : 'بدون خطا']) ?>
  </div>

  <?= App\Core\View::render('platform._bars', [
      'id' => 'dash-daily',
      'title' => 'نوبت‌های روزانه (۳۰ روز اخیر)',
      'points' => array_map(static fn (array $d): array => ['label' => $d['label'], 'value' => $d['bookings']], $daily),
      'format' => $num,
      'valueLabel' => 'نوبت',
  ]) ?>

  <div class="grid grid-main-aside" style="--gap:24px">
    <section class="card" aria-labelledby="dash-top">
      <div class="card__header card__header--divided spread"><h2 class="card__title" id="dash-top">سالن‌های پرکار (۳۰ روز)</h2><a class="text-sm" href="<?= e(url('platform/reports')) ?>#salons">همه</a></div>
      <?php if ($topSalons === []): ?>
        <div class="card__body"><p class="text-sm muted">در ۳۰ روز اخیر نوبتی ثبت نشده.</p></div>
      <?php else: $topMax = max(1, ...array_map(static fn ($s) => (int) $s['bookings'], $topSalons)); ?>
        <div class="card__body">
          <?php foreach ($topSalons as $s): ?>
            <div class="meter-row">
              <a href="<?= e(url('platform/salons/' . $s['id'])) ?>"><?= e($s['name']) ?></a>
              <div class="meter" aria-hidden="true"><div class="meter__fill" style="--v:<?= (int) round((int) $s['bookings'] / $topMax * 100) ?>"></div></div>
              <span class="num"><?= e(fa_num((int) $s['bookings'])) ?> نوبت · <?= e(toman((int) $s['revenue'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card" aria-labelledby="dash-events">
      <div class="card__header card__header--divided spread"><h2 class="card__title" id="dash-events">رویدادهای اخیر</h2><a class="text-sm" href="<?= e(url('platform/audit')) ?>">همه</a></div>
      <?php if ($events === []): ?>
        <div class="card__body"><p class="text-sm muted">رویدادی نیست.</p></div>
      <?php else: ?>
        <ul class="list" role="list">
          <?php foreach ($events as $ev): ?>
            <li class="list-row"><span class="list-row__body"><span class="list-row__title text-sm"><?= e(AuditLabels::action((string) $ev['action'])) ?><?= $ev['salon_name'] ? ' · ' . e($ev['salon_name']) : '' ?></span><span class="list-row__meta"><?= e($ev['actor_name'] ?? 'سامانه') ?> · <?= e(jdate((string) $ev['created_at'], 'Y/m/d H:i')) ?></span></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
</div>
