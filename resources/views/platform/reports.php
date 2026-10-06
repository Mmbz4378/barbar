<?php
/**
 * @var App\Domain\Reports\ReportRange $range
 * @var array $filters
 * @var string $sort
 * @var array $kpis
 * @var array $previous
 * @var array $daily
 * @var array $heatmap
 * @var array $statuses
 * @var array $methods
 * @var array $salons
 * @var array $services
 * @var array $cities
 * @var array $mix
 * @var array $sms
 * @var array $growth
 * @var array $salonOptions
 * @var array $cityOptions
 */
use App\Domain\Reports\ReportRange;
use App\Domain\System\SiteSettings;
use App\Http\Controllers\PlatformReportController as PRC;
use App\Support\Audience;
use App\Support\Jalali;

$baseQuery = array_filter($range->query() + ['salon' => (string) ($filters['salon_id'] ?? ''), 'audience' => (string) ($filters['audience'] ?? ''), 'city' => (string) ($filters['city'] ?? '')], static fn ($v) => $v !== '' && $v !== null);
$link = static fn (string $path, array $extra = []): string => url($path . '?' . http_build_query(array_merge($baseQuery, $extra)));
$kpi = static fn (array $data): string => App\Core\View::render('platform._kpi', $data);
$num = static fn (int $n): string => fa_num($n);
$money = static fn (int $n): string => toman($n);
$weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
$filtered = !empty($filters['salon_id']) || !empty($filters['audience']) || !empty($filters['city']);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">گزارش‌ها</h1>
    <p class="page-head__sub"><?= e($range->label()) ?> (<?= e(fa_num($range->days())) ?> روز)<?= $filtered ? ' · با فیلتر' : ' · همهٔ سالن‌ها' ?>. مقایسه‌ها نسبت به <?= e(fa_num($range->days())) ?> روزِ پیش از آن است.</p>
  </div>
  <div class="page-head__actions">
    <details class="more-menu">
      <summary class="btn btn--secondary"><?= icon('download') ?> خروجی Excel</summary>
      <div class="more-menu__panel">
        <?php foreach (['daily' => 'روزبه‌روز', 'salons' => 'سالن‌ها', 'services' => 'خدمات', 'cities' => 'شهرها', 'sms' => 'پیامک', 'growth' => 'رشد ماهانه'] as $ds => $dl): ?>
          <a class="btn btn--ghost" href="<?= e($link('platform/reports/export', ['dataset' => $ds, 'sort' => $sort])) ?>"><?= e($dl) ?> (CSV)</a>
        <?php endforeach; ?>
      </div>
    </details>
  </div>
</div>

<form method="get" action="<?= e(url('platform/reports')) ?>" class="card card--flat mb-4"><div class="card__body stack stack-sm">
  <div class="cluster items-end" style="--gap:12px">
    <div class="field">
      <label class="field__label" for="r-range">بازه</label>
      <select class="select select--compact" id="r-range" name="range">
        <?php foreach (ReportRange::PRESETS as $key => $label): ?><option value="<?= e($key) ?>" <?= $range->preset === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="field__label" for="r-salon">سالن</label>
      <select class="select select--compact" id="r-salon" name="salon">
        <option value="">همهٔ سالن‌ها</option>
        <?php foreach ($salonOptions as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) ($filters['salon_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="field__label" for="r-aud">نوع</label>
      <select class="select select--compact" id="r-aud" name="audience">
        <option value="">همه</option>
        <?php foreach (Audience::options() as $k => $l): ?><option value="<?= e($k) ?>" <?= ($filters['audience'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="field__label" for="r-city">شهر</label>
      <select class="select select--compact" id="r-city" name="city">
        <option value="">همه</option>
        <?php foreach ($cityOptions as $c): ?><option value="<?= e($c) ?>" <?= ($filters['city'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn--secondary" type="submit"><?= icon('filter') ?> اعمال</button>
    <?php if ($filtered || $range->preset !== '30d'): ?><a class="btn btn--ghost" href="<?= e(url('platform/reports')) ?>">پاک کردن</a><?php endif; ?>
  </div>
  <details <?= $range->preset === 'custom' ? 'open' : '' ?>>
    <summary class="text-sm">بازهٔ دلخواه (برای «بازهٔ دلخواه»)</summary>
    <div class="cluster mt-2" style="--gap:16px">
      <?= partial('jalali-date-input', ['name' => 'from', 'value' => $range->from->format('Y-m-d'), 'label' => 'از', 'years' => [-3, 0]]) ?>
      <?= partial('jalali-date-input', ['name' => 'to', 'value' => $range->to->format('Y-m-d'), 'label' => 'تا', 'years' => [-3, 0]]) ?>
    </div>
  </details>
</div></form>

<div class="stack stack-lg">
  <div class="stats" style="--cols:4">
    <?= $kpi(['label' => 'نوبت', 'icon' => 'calendar-days', 'value' => fa_num((int) $kpis['bookings']), 'current' => $kpis['bookings'], 'previous' => $previous['bookings'], 'accent' => true]) ?>
    <?= $kpi(['label' => 'انجام‌شده', 'icon' => 'circle-check', 'value' => fa_num((int) $kpis['completed']), 'current' => $kpis['completed'], 'previous' => $previous['completed']]) ?>
    <?= $kpi(['label' => 'درآمد ثبت‌شده', 'icon' => 'wallet', 'value' => toman((int) $kpis['revenue']), 'current' => $kpis['revenue'], 'previous' => $previous['revenue']]) ?>
    <?= $kpi(['label' => 'میانگین هر تسویه', 'icon' => 'receipt', 'value' => toman((int) $kpis['avg_ticket']), 'current' => $kpis['avg_ticket'], 'previous' => $previous['avg_ticket']]) ?>
    <?= $kpi(['label' => 'نرخ لغو', 'icon' => 'x', 'value' => fa_num($kpis['cancel_rate']) . '٪', 'current' => $kpis['cancel_rate'], 'previous' => $previous['cancel_rate'], 'unit' => 'pp']) ?>
    <?= $kpi(['label' => 'نرخ نیامدن', 'icon' => 'user-x', 'value' => fa_num($kpis['no_show_rate']) . '٪', 'current' => $kpis['no_show_rate'], 'previous' => $previous['no_show_rate'], 'unit' => 'pp']) ?>
    <?= $kpi(['label' => 'مشتری تازه', 'icon' => 'user-plus', 'value' => fa_num((int) $kpis['new_customers']), 'current' => $kpis['new_customers'], 'previous' => $previous['new_customers']]) ?>
    <?= $kpi(['label' => 'رزرو آنلاین / حضوری', 'icon' => 'sparkles', 'value' => fa_num((int) $kpis['online']) . ' / ' . fa_num((int) $kpis['walkins']), 'hint' => 'بقیه را پذیرش در پنل ثبت کرده']) ?>
  </div>

  <?= App\Core\View::render('platform._bars', ['id' => 'r-daily', 'title' => 'نوبت‌ها روزبه‌روز', 'points' => array_map(static fn (array $d): array => ['label' => $d['label'], 'value' => $d['bookings']], $daily), 'format' => $num, 'valueLabel' => 'نوبت']) ?>
  <?= App\Core\View::render('platform._bars', ['id' => 'r-revenue', 'title' => 'درآمد روزبه‌روز', 'points' => array_map(static fn (array $d): array => ['label' => $d['label'], 'value' => $d['revenue']], $daily), 'format' => $money, 'valueLabel' => 'درآمد']) ?>

  <section class="card" aria-labelledby="r-heat">
    <div class="card__header card__header--divided spread">
      <h2 class="card__title" id="r-heat">شلوغی هفته (روز × ساعت)</h2>
      <span class="heatmap-legend" aria-hidden="true">کم <span class="heatmap-legend__ramp"></span> زیاد</span>
    </div>
    <div class="card__body">
      <?php if ($heatmap['max'] === 0): ?>
        <?= partial('empty-state', ['icon' => 'calendar', 'title' => 'در این بازه نوبتی نیست']) ?>
      <?php else: ?>
        <p class="text-sm muted mb-2">پررنگ‌تر یعنی نوبت بیشتر؛ عدد دقیق با نگه‌داشتن نشانگر روی هر خانه. نوبت‌های لغوشده حساب نمی‌شوند.</p>
        <div class="table-wrap">
          <table class="heatmap">
            <caption class="sr-only">تعداد نوبت در هر روز هفته و ساعت</caption>
            <thead><tr><th scope="col"><span class="sr-only">روز</span></th><?php foreach ($heatmap['hours'] as $h): ?><th scope="col" class="num"><?= e(fa_num($h)) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($weekdays as $wd => $name): ?>
                <tr>
                  <th scope="row"><?= e($name) ?></th>
                  <?php foreach ($heatmap['hours'] as $h): $n = (int) ($heatmap['grid'][$wd][$h] ?? 0); ?>
                    <td style="--v:<?= $n === 0 ? 0 : max(8, (int) round($n / $heatmap['max'] * 100)) ?>" data-tooltip="<?= e($name . ' ساعت ' . fa_num($h) . ': ' . fa_num($n) . ' نوبت') ?>"><span class="sr-only"><?= e(fa_num($n)) ?></span></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="grid-auto" style="--min:280px">
    <section class="card" aria-labelledby="r-status"><div class="card__body stack stack-sm">
      <h2 class="card__title" id="r-status">وضعیت نوبت‌ها</h2>
      <?php $sTotal = max(1, array_sum($statuses)); if ($statuses === []): ?><p class="text-sm muted">داده‌ای نیست.</p><?php endif; ?>
      <?php foreach ($statuses as $st => $n): ?>
        <div class="meter-row"><span><?= e(PRC::STATUS_LABELS[$st] ?? $st) ?></span><div class="meter" aria-hidden="true"><div class="meter__fill" style="--v:<?= (int) round($n / $sTotal * 100) ?>"></div></div><span class="num"><?= e(fa_num($n)) ?> (<?= e(fa_num((int) round($n / $sTotal * 100))) ?>٪)</span></div>
      <?php endforeach; ?>
    </div></section>

    <section class="card" aria-labelledby="r-methods"><div class="card__body stack stack-sm">
      <h2 class="card__title" id="r-methods">روش پرداخت</h2>
      <?php $mTotal = max(1, array_sum(array_column($methods, 'amount'))); if ($methods === []): ?><p class="text-sm muted">پرداختی ثبت نشده.</p><?php endif; ?>
      <?php foreach ($methods as $m => $row): ?>
        <div class="meter-row"><span><?= e(PRC::METHOD_LABELS[$m] ?? $m) ?></span><div class="meter" aria-hidden="true"><div class="meter__fill" style="--v:<?= (int) round($row['amount'] / $mTotal * 100) ?>"></div></div><span class="num"><?= e(toman($row['amount'])) ?> · <?= e(fa_num($row['count'])) ?> بار</span></div>
      <?php endforeach; ?>
      <dl class="kv mt-2">
        <div class="kv__row"><dt>انعام</dt><dd class="num"><?= e(toman((int) $kpis['tips'])) ?></dd></div>
        <div class="kv__row"><dt>تخفیف داده‌شده</dt><dd class="num"><?= e(toman((int) $kpis['discounts'])) ?></dd></div>
        <div class="kv__row"><dt>بیعانه</dt><dd class="num"><?= e(toman((int) $kpis['deposits'])) ?></dd></div>
      </dl>
    </div></section>

    <section class="card" aria-labelledby="r-mix"><div class="card__body stack stack-sm">
      <h2 class="card__title" id="r-mix">مشتری‌ها</h2>
      <?php $cTotal = max(1, $mix['new'] + $mix['returning']); ?>
      <div class="meter-row"><span>تازه</span><div class="meter" aria-hidden="true"><div class="meter__fill" style="--v:<?= (int) round($mix['new'] / $cTotal * 100) ?>"></div></div><span class="num"><?= e(fa_num($mix['new'])) ?></span></div>
      <div class="meter-row"><span>بازگشتی</span><div class="meter" aria-hidden="true"><div class="meter__fill" style="--v:<?= (int) round($mix['returning'] / $cTotal * 100) ?>"></div></div><span class="num"><?= e(fa_num($mix['returning'])) ?></span></div>
      <p class="text-xs muted">مشتری‌هایی که در این بازه مراجعهٔ انجام‌شده داشته‌اند؛ «بازگشتی» پیش از این بازه هم ثبت شده بود. کل مشتری‌های دارای نوبت: <?= e(fa_num((int) $kpis['customers'])) ?>.</p>
    </div></section>
  </div>

  <section class="card" id="salons" aria-labelledby="r-salons">
    <div class="card__header card__header--divided spread">
      <h2 class="card__title" id="r-salons">رتبه‌بندی سالن‌ها</h2>
      <nav class="chips" aria-label="مرتب‌سازی">
        <?php foreach (PRC::SORTS as $key => $label): ?><a class="chip" href="<?= e($link('platform/reports', ['sort' => $key])) ?>#salons" <?= $sort === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
      </nav>
    </div>
    <div class="table-wrap table-wrap--flush">
      <table class="table table--stack">
        <thead><tr><th scope="col">سالن</th><th scope="col" class="num">نوبت</th><th scope="col" class="num">انجام‌شده</th><th scope="col" class="num">لغو</th><th scope="col" class="num">نیامده</th><th scope="col" class="num">درآمد</th><th scope="col" class="num">مشتری تازه</th><th scope="col" class="num">پیامک</th></tr></thead>
        <tbody>
          <?php foreach ($salons as $s): ?>
            <tr>
              <td data-label="سالن"><a href="<?= e(url('platform/salons/' . $s['id'])) ?>"><?= e($s['name']) ?></a><?php if (!(int) $s['is_active']): ?> <span class="badge badge--danger">غیرفعال</span><?php endif; ?><span class="text-xs muted"><?= $s['city'] ? ' · ' . e($s['city']) : '' ?></span></td>
              <td data-label="نوبت" class="num"><?= e(fa_num((int) $s['bookings'])) ?></td>
              <td data-label="انجام‌شده" class="num"><?= e(fa_num((int) $s['completed'])) ?></td>
              <td data-label="لغو" class="num"><?= e(fa_num((int) $s['cancelled'])) ?><?= (int) $s['bookings'] > 0 ? ' <span class="text-xs muted">(' . e(fa_num((float) $s['cancel_rate'])) . '٪)</span>' : '' ?></td>
              <td data-label="نیامده" class="num"><?= e(fa_num((int) $s['no_show'])) ?></td>
              <td data-label="درآمد" class="num"><?= e(toman((int) $s['revenue'])) ?></td>
              <td data-label="مشتری تازه" class="num"><?= e(fa_num((int) $s['new_customers'])) ?></td>
              <td data-label="پیامک" class="num"><?= e(fa_num((int) $s['sms'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <div class="grid-auto" style="--min:320px">
    <section class="card" aria-labelledby="r-services">
      <div class="card__header card__header--divided"><h2 class="card__title" id="r-services">پرطرفدارترین خدمات</h2></div>
      <?php if ($services === []): ?><div class="card__body"><p class="text-sm muted">داده‌ای نیست.</p></div><?php else: ?>
        <div class="table-wrap table-wrap--flush"><table class="table table--compact">
          <thead><tr><th scope="col">خدمت</th><th scope="col" class="num">تعداد</th><th scope="col" class="num">درآمد</th></tr></thead>
          <tbody><?php foreach ($services as $sv): ?><tr><td><?= e($sv['name']) ?><?= $sv['salons'] > 1 ? ' <span class="text-xs muted">· ' . e(fa_num($sv['salons'])) . ' سالن</span>' : '' ?></td><td class="num"><?= e(fa_num($sv['count'])) ?></td><td class="num"><?= e(toman($sv['revenue'])) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </section>
    <section class="card" aria-labelledby="r-cities">
      <div class="card__header card__header--divided"><h2 class="card__title" id="r-cities">شهرها</h2></div>
      <?php if ($cities === []): ?><div class="card__body"><p class="text-sm muted">داده‌ای نیست.</p></div><?php else: ?>
        <div class="table-wrap table-wrap--flush"><table class="table table--compact">
          <thead><tr><th scope="col">شهر</th><th scope="col" class="num">نوبت</th><th scope="col" class="num">سالن</th></tr></thead>
          <tbody><?php foreach ($cities as $c): ?><tr><td><?= e($c['city']) ?></td><td class="num"><?= e(fa_num($c['bookings'])) ?></td><td class="num"><?= e(fa_num($c['salons'])) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </section>
  </div>

  <section class="card" id="sms" aria-labelledby="r-sms">
    <div class="card__header card__header--divided spread"><h2 class="card__title" id="r-sms">پیامک</h2><span class="text-sm muted"><?= e(fa_num((int) $kpis['sms_sent'])) ?> فرستاده · <?= e(fa_num((int) $kpis['sms_failed'])) ?> ناموفق · <?= e(fa_num((int) $kpis['sms_queued'])) ?> در صف</span></div>
    <?php if ($sms['templates'] === []): ?><div class="card__body"><p class="text-sm muted">پیامکی در این بازه نیست.</p></div><?php else: ?>
      <div class="table-wrap table-wrap--flush"><table class="table table--compact">
        <thead><tr><th scope="col">پیامک</th><th scope="col" class="num">فرستاده</th><th scope="col" class="num">ناموفق</th><th scope="col" class="num">ردشده (سکوت، سقف، اعتبار)</th></tr></thead>
        <tbody><?php foreach ($sms['templates'] as $t): ?><tr><td><?= e(SiteSettings::SMS_PATTERNS[$t['template']] ?? $t['template']) ?></td><td class="num"><?= e(fa_num($t['sent'])) ?></td><td class="num"><?= e(fa_num($t['failed'])) ?></td><td class="num"><?= e(fa_num($t['skipped'])) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="card" aria-labelledby="r-growth">
    <div class="card__header card__header--divided"><h2 class="card__title" id="r-growth">رشد ۱۲ ماه اخیر</h2></div>
    <div class="card__body">
      <div class="small-multiples">
        <?php foreach (['salons' => 'سالن تازه', 'users' => 'کاربر پنل تازه', 'bookings' => 'نوبت'] as $key => $label): $gMax = max(1, ...array_column($growth, $key)); ?>
          <div class="stack stack-xs">
            <h3 class="text-sm strong"><?= e($label) ?> <span class="muted num">· جمع <?= e(fa_num(array_sum(array_column($growth, $key)))) ?></span></h3>
            <div class="bars" aria-hidden="true">
              <?php foreach ($growth as $g): ?><span class="bars__bar<?= $g[$key] === 0 ? ' bars__bar--empty' : '' ?>" style="--v:<?= $g[$key] === 0 ? 0 : max(3, (int) round($g[$key] / $gMax * 100)) ?>" data-tooltip="<?= e($g['label'] . ': ' . fa_num($g[$key])) ?>"></span><?php endforeach; ?>
            </div>
            <div class="spread text-xs muted" aria-hidden="true"><span><?= e($growth[0]['label']) ?></span><span><?= e($growth[count($growth) - 1]['label']) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
      <details class="mt-3">
        <summary class="text-sm">نمایش جدول داده</summary>
        <div class="table-wrap mt-2"><table class="table table--compact">
          <thead><tr><th scope="col">ماه</th><th scope="col" class="num">سالن تازه</th><th scope="col" class="num">کاربر پنل تازه</th><th scope="col" class="num">نوبت</th></tr></thead>
          <tbody><?php foreach ($growth as $g): ?><tr><td><?= e($g['label']) ?></td><td class="num"><?= e(fa_num($g['salons'])) ?></td><td class="num"><?= e(fa_num($g['users'])) ?></td><td class="num"><?= e(fa_num($g['bookings'])) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
      </details>
    </div>
  </section>
</div>
