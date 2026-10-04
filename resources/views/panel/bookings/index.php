<?php
/**
 * رزروها.
 *
 * @var array $days
 * @var array $counts
 * @var array $deposits
 * @var DateTimeImmutable $from
 * @var DateTimeImmutable $to
 * @var string $prev
 * @var string $next
 * @var bool $isToday
 * @var bool $desk
 * @var ?int $staffFilter
 * @var array $staffList
 */
use App\Support\JalaliCalendar;

$upcoming = ($counts['pending'] ?? 0) + ($counts['confirmed'] ?? 0);
$totalRows = array_sum(array_map(static fn ($d) => count($d['rows']), $days));
$staffQuery = $staffFilter !== null ? '&staff=' . $staffFilter : '';
$bySalon = (int) ($counts['cancelled_by']['salon'] ?? 0);
$byCustomer = (int) ($counts['cancelled_by']['customer'] ?? 0);
$bySystem = (int) ($counts['cancelled_by']['system'] ?? 0);
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= $desk ? 'رزروها' : 'نوبت‌های من' ?></h1>
    <p class="page-head__sub"><?= e(JalaliCalendar::humanDate($from)) ?> تا <?= e(JalaliCalendar::humanDate($to, true)) ?></p>
  </div>
  <div class="page-head__actions">
    <nav class="btn-row" aria-label="جابه‌جایی بازه">
      <a class="btn btn--secondary btn--icon" href="<?= e(url('panel/bookings?from=' . $prev . $staffQuery)) ?>" aria-label="دو هفتهٔ قبل"><?= icon('chevron-start') ?></a>
      <?php if (!$isToday): ?><a class="btn btn--secondary" href="<?= e(url('panel/bookings' . ($staffFilter !== null ? '?staff=' . $staffFilter : ''))) ?>">امروز</a><?php endif; ?>
      <a class="btn btn--secondary btn--icon" href="<?= e(url('panel/bookings?from=' . $next . $staffQuery)) ?>" aria-label="دو هفتهٔ بعد"><?= icon('chevron-end') ?></a>
    </nav>
    <?php if ($desk): ?><a class="btn btn--primary" href="<?= e(url('panel/bookings/new')) ?>"><?= icon('plus') ?> رزرو جدید</a><?php endif; ?>
  </div>
</div>

<?php if ($deposits !== []): ?>
  <section class="section mb-6" id="deposits" aria-labelledby="deposits-title">
    <div class="section__head"><h2 class="section__title" id="deposits-title">منتظر تأیید بیعانه <span class="badge badge--warning"><?= e(fa_num(count($deposits))) ?></span></h2></div>
    <p class="section__sub">پس از دیدن واریز در حساب، تأیید کنید. اگر تا مهلت تأیید نشود، نوبت خودکار لغو و ساعتش آزاد می‌شود.</p>
    <div class="stack stack-sm">
      <?php foreach ($deposits as $d): ?>
        <article class="card"><div class="card__body stack stack-sm">
          <div class="spread">
            <strong><?= e($d['customer_name'] ?: 'مشتری') ?> <span class="ltr num muted text-sm"><?= e(phone_local($d['customer_phone'])) ?></span></strong>
            <span class="badge badge--warning"><?= e(toman((int) $d['deposit_amount'])) ?></span>
          </div>
          <p class="text-sm muted"><?= e(JalaliCalendar::humanDate(new DateTimeImmutable((string) $d['scheduled_at']))) ?> · ساعت <?= e(fa_time(substr((string) $d['scheduled_at'], 11, 5))) ?> · <?= e($d['service_names'] ?? '') ?> · <?= e($d['staff_name'] ?? '') ?></p>
          <?php if (!empty($d['hold_expires_at'])): ?><p class="text-xs warning-text">مهلت: <?= e(jdate($d['hold_expires_at'], 'j M، H:i')) ?></p><?php endif; ?>
          <div class="btn-row">
            <form method="post" action="<?= e(url('panel/bookings/' . $d['id'] . '/confirm')) ?>" class="row" style="--gap:8px">
              <?= csrf_field() ?>
              <label class="sr-only" for="dm-<?= (int) $d['id'] ?>">روش دریافت بیعانه</label>
              <select class="select select--compact" id="dm-<?= (int) $d['id'] ?>" name="method"><option value="card_to_card">کارت‌به‌کارت</option><option value="cash">نقدی</option><option value="pos">کارتخوان</option></select>
              <button type="submit" class="btn btn--primary btn--sm"><?= icon('check') ?> بیعانه دریافت شد</button>
            </form>
            <form method="post" action="<?= e(url('panel/queue/' . $d['id'] . '/cancel')) ?>" data-confirm="رزرو «<?= e($d['customer_name'] ?: 'مشتری') ?>» لغو شود؟ ساعتش آزاد می‌شود." data-confirm-ok="لغو رزرو">
              <?= csrf_field() ?><input type="hidden" name="reason" value="بیعانه دریافت نشد"><input type="hidden" name="back" value="/panel/bookings">
              <button type="submit" class="btn btn--danger-ghost btn--sm">لغو</button>
            </form>
          </div>
        </div></article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<div class="stats mb-4" style="--cols:3">
  <div class="stat"><span class="stat__label">رزرو پیش رو</span><span class="stat__value"><?= e(fa_num($upcoming)) ?></span></div>
  <div class="stat"><span class="stat__label">انجام‌شده</span><span class="stat__value"><?= e(fa_num((int) ($counts['completed'] ?? 0))) ?></span></div>
  <div class="stat"><span class="stat__label">لغو و غیبت</span><span class="stat__value"><?= e(fa_num((int) ($counts['cancelled'] ?? 0) + (int) ($counts['no_show'] ?? 0))) ?></span>
    <?php if ($bySalon + $byCustomer + $bySystem > 0): ?><span class="stat__hint">مشتری <?= e(fa_num($byCustomer)) ?> · سالن <?= e(fa_num($bySalon)) ?><?= $bySystem ? ' · بیعانه ' . e(fa_num($bySystem)) : '' ?></span><?php endif; ?>
  </div>
</div>

<?php if ($desk && count($staffList) > 1): ?>
  <nav class="chips mb-4" aria-label="فیلتر بر اساس فرد">
    <a class="chip" href="<?= e(url('panel/bookings?from=' . $from->format('Y-m-d'))) ?>" <?= $staffFilter === null ? 'aria-current="page"' : '' ?>>همه</a>
    <?php foreach ($staffList as $st): ?>
      <a class="chip" href="<?= e(url('panel/bookings?from=' . $from->format('Y-m-d') . '&staff=' . $st['id'])) ?>" <?= $staffFilter === (int) $st['id'] ? 'aria-current="page"' : '' ?>><span class="dot" style="background:<?= e(staff_color($st['color'])) ?>"></span><?= e($st['name']) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if ($totalRows === 0): ?>
  <div class="card"><?= partial('empty-state', [
      'icon' => 'calendar-x',
      'title' => 'در این بازه رزروی نیست',
      'text' => $desk ? 'لینک رزرو سالن را برای مشتری‌ها بفرستید یا رزرو تلفنی را دستی ثبت کنید.' : 'نوبت‌های رزروشدهٔ شما اینجا نمایش داده می‌شوند.',
      'actionHref' => $desk ? url('panel/qr') : null,
      'actionLabel' => 'لینک رزرو سالن',
  ]) ?></div>
<?php else: ?>
  <div class="stack">
    <?php foreach ($days as $day): if ($day['rows'] === []) { continue; } $full = JalaliCalendar::humanDate($day['date']); ?>
      <section class="card" aria-label="<?= e($full) ?>">
        <div class="card__header card__header--divided">
          <h2 class="card__title"><?= e($day['label']) ?><?php if ($day['label'] !== $full): ?> <span class="muted text-sm"><?= e($full) ?></span><?php endif; ?></h2>
          <span class="badge"><?= e(fa_num(count($day['rows']))) ?> نوبت</span>
        </div>
        <ul class="list">
          <?php foreach ($day['rows'] as $r): $off = in_array($r['status'], ['cancelled', 'no_show'], true); [$label, $tone] = status_meta((string) $r['status']); ?>
            <li class="list-row" style="<?= $off ? 'opacity:.62' : '' ?>">
              <time class="num strong" datetime="<?= e((string) $r['scheduled_at']) ?>" style="min-width:48px;<?= $off ? 'text-decoration:line-through' : '' ?>"><?= e(fa_time(substr((string) $r['scheduled_at'], 11, 5))) ?></time>
              <span aria-hidden="true" style="width:4px;align-self:stretch;border-radius:4px;background:<?= e(staff_color($r['staff_color'] ?? null)) ?>"></span>
              <span class="list-row__body">
                <span class="list-row__title truncate"><?= e($r['customer_name'] ?: 'مشتری') ?><?= $r['group_token'] ? ' <span class="badge badge--outline">چندبخشی</span>' : '' ?></span>
                <span class="list-row__meta truncate"><?= e($r['service_names'] ?? '') ?> · <?= e($r['staff_name'] ?? '') ?><?php if ($desk && !empty($r['customer_phone'])): ?> · <a class="ltr num" href="tel:<?= e($r['customer_phone']) ?>"><?= e(phone_local($r['customer_phone'])) ?></a><?php endif; ?></span>
              </span>
              <span class="list-row__end">
                <span class="badge badge--<?= e($tone) ?>"><?= e($label) ?></span>
                <?php if ($desk && in_array($r['status'], ['confirmed', 'pending'], true)): ?>
                  <details class="more-menu">
                    <summary class="btn btn--ghost btn--icon btn--sm" aria-label="اقدام‌ها"><?= icon('more') ?></summary>
                    <div class="more-menu__panel">
                      <?php if (!empty($r['customer_phone'])): ?><a class="btn btn--ghost" href="tel:<?= e($r['customer_phone']) ?>"><?= icon('phone') ?> تماس</a><?php endif; ?>
                      <a class="btn btn--ghost" href="<?= e(url('q/' . $r['public_token'])) ?>" target="_blank" rel="noopener"><?= icon('external') ?> کارت نوبت</a>
                      <form method="post" action="<?= e(url('panel/queue/' . $r['id'] . '/cancel')) ?>" data-confirm="نوبت «<?= e($r['customer_name'] ?: 'مشتری') ?>» لغو شود؟ به مشتری پیامک لغو فرستاده می‌شود." data-confirm-ok="لغو نوبت">
                        <?= csrf_field() ?><input type="hidden" name="back" value="/panel/bookings?from=<?= e($from->format('Y-m-d')) ?>">
                        <button class="btn btn--danger-ghost" type="submit"><?= icon('x') ?> لغو نوبت</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
