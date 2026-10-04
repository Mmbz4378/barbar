<?php
/**
 * امروز.
 *
 * @var array $snapshot
 * @var bool $desk
 * @var ?int $myStaffId
 * @var array $serviceGroups
 * @var array $staffList
 * @var array $summary
 * @var ?array $myEarnings
 * @var ?array $salonEarnings
 * @var array $awaiting
 * @var int $pendingDeposits
 * @var bool $canPay
 */
use App\Support\JalaliCalendar;
use App\Support\ServiceVisual;

$walkinOpen = (bool) flash('walkin_open');
$oldServices = array_map('intval', (array) old('service_ids', []));

/** دکمه‌های اقدام یک ردیف صف. */
$rowActions = static function (array $row, bool $big = false): void {
    $inChair = $row['status'] === 'in_chair';
    $who = $row['customer_name'] ?: 'مشتری';
    ?>
    <div class="queue-row__actions">
      <?php if ($inChair): ?>
        <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/complete')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn--success btn--grow <?= $big ? 'btn--xl btn--block' : '' ?>"><?= icon('check') ?> تمام شد</button>
        </form>
      <?php else: ?>
        <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/start')) ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn--primary btn--grow <?= $big ? 'btn--xl btn--block' : '' ?>"><?= icon('play') ?> شروع</button>
        </form>
      <?php endif; ?>
      <details class="more-menu">
        <summary class="btn btn--ghost btn--icon <?= $big ? 'btn--lg' : '' ?>" aria-label="اقدام‌های بیشتر برای <?= e($who) ?>"><?= icon('more') ?></summary>
        <div class="more-menu__panel">
          <?php if (!$inChair): ?>
            <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/no-show')) ?>" data-confirm="غیبت «<?= e($who) ?>» ثبت شود؟" data-confirm-ok="ثبت غیبت">
              <?= csrf_field() ?><button type="submit" class="btn btn--ghost"><?= icon('user-x') ?> ثبت غیبت</button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?= e(url('panel/queue/' . $row['id'] . '/cancel')) ?>" data-confirm="نوبت «<?= e($who) ?>» لغو شود؟" data-confirm-ok="لغو نوبت">
            <?= csrf_field() ?><button type="submit" class="btn btn--danger-ghost"><?= icon('x') ?> لغو نوبت</button>
          </form>
          <?php if (!empty($row['customer_phone'])): ?>
            <a class="btn btn--ghost" href="tel:<?= e($row['customer_phone']) ?>"><?= icon('phone') ?> تماس با مشتری</a>
          <?php endif; ?>
        </div>
      </details>
    </div>
    <?php
};

/** توضیح زمان یک ردیف: رزرو ساعت فلان یا تخمین صف. */
$rowTime = static function (array $row): string {
    if ($row['status'] === 'in_chair') {
        return 'از ' . fa_time(substr((string) $row['actual_start_at'], 11, 5));
    }
    if ($row['kind'] === 'booked' && !empty($row['scheduled_at'])) {
        return 'رزرو ' . fa_time(substr((string) $row['scheduled_at'], 11, 5));
    }

    return (string) ($row['display']['text'] ?? '');
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= $desk ? 'امروز در سالن' : 'امروزِ من' ?></h1>
    <p class="page-head__sub"><?= e(JalaliCalendar::humanDate(new DateTimeImmutable('today'), true)) ?> · <span id="sync-status" role="status">به‌روز</span></p>
  </div>
  <div class="page-head__actions">
    <button type="button" class="btn btn--ghost btn--icon" data-refresh-now aria-label="به‌روزرسانی"><?= icon('refresh') ?></button>
    <?php if ($desk): ?>
      <a class="btn btn--secondary" href="<?= e(url('panel/bookings/new')) ?>"><?= icon('calendar') ?> رزرو برای بعد</a>
      <button type="button" class="btn btn--primary" data-toggle="walkin" aria-expanded="<?= $walkinOpen ? 'true' : 'false' ?>" aria-controls="walkin"><?= icon('user-plus') ?> پذیرش حضوری</button>
    <?php endif; ?>
  </div>
</div>

<div id="live" data-auto-refresh="30" data-refresh-ids="today-stats,today-attention,queue-board" data-refresh-status="sync-status"<?php if (!empty($refreshVersion)): ?> data-refresh-version="<?= e($refreshVersion) ?>"<?php endif; ?>>

<div class="stats mb-6" id="today-stats" style="--cols:<?= $salonEarnings !== null || $myEarnings !== null ? 4 : 3 ?>">
  <div class="stat"><span class="stat__label"><?= icon('hourglass') ?> در انتظار</span><span class="stat__value"><?= e(fa_num($summary['waiting'])) ?></span></div>
  <div class="stat"><span class="stat__label"><?= icon('play') ?> در حال انجام</span><span class="stat__value"><?= e(fa_num($summary['in_chair'])) ?></span></div>
  <div class="stat"><span class="stat__label"><?= icon('circle-check') ?> انجام‌شده</span><span class="stat__value"><?= e(fa_num($summary['completed'])) ?></span><?php if ($summary['no_show'] + $summary['cancelled'] > 0): ?><span class="stat__hint"><?= e(fa_num($summary['no_show'])) ?> غیبت · <?= e(fa_num($summary['cancelled'])) ?> لغو</span><?php endif; ?></div>
  <?php if ($salonEarnings !== null): ?>
    <div class="stat stat--accent"><span class="stat__label"><?= icon('wallet') ?> دریافتی امروز</span><span class="stat__value stat__value--sm"><?= e(toman((int) $salonEarnings['total'])) ?></span></div>
  <?php elseif ($myEarnings !== null): ?>
    <div class="stat stat--accent"><span class="stat__label"><?= icon('wallet') ?> فروش امروز من</span><span class="stat__value stat__value--sm"><?= e(toman((int) $myEarnings['total'])) ?></span></div>
  <?php endif; ?>
</div>

<div id="today-attention">
<?php if ($awaiting !== [] || $pendingDeposits > 0): ?>
  <section class="section mb-6" aria-labelledby="attention-title">
    <h2 class="section__title" id="attention-title">نیاز به اقدام</h2>
    <?php if ($pendingDeposits > 0): ?>
      <a class="alert alert--warning" href="<?= e(url('panel/bookings?view=deposits')) ?>"><?= icon('wallet') ?><div class="alert__body"><strong><?= e(fa_num($pendingDeposits)) ?> رزرو منتظر تأیید بیعانه</strong> — پس از دیدن واریز، تأیید کنید تا نوبت قطعی شود.</div><?= icon('chevron-end', 'icon') ?></a>
    <?php endif; ?>
    <?php if ($awaiting !== []): ?>
      <div class="card">
        <div class="card__header"><h3 class="card__title">منتظر تسویه <span class="badge badge--danger"><?= e(fa_num(count($awaiting))) ?></span></h3></div>
        <ul class="list mt-2">
          <?php foreach ($awaiting as $a): ?>
            <li class="list-row">
              <span class="list-row__body">
                <span class="list-row__title"><?= e($a['customer_name'] ?: 'مشتری') ?></span>
                <span class="list-row__meta"><?= e($a['staff_name'] ?? '') ?> · پایان <?= e(fa_time(substr((string) $a['actual_end_at'], 11, 5))) ?> · <span class="num"><?= e(toman((int) $a['total_price'])) ?></span></span>
              </span>
              <?php if ($canPay): ?><a class="btn btn--primary btn--sm" href="<?= e(url('panel/pay/' . $a['id'])) ?>">تسویه</a><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>
</div>

</div><?php /* #live: فرم پذیرش بیرون از ناحیهٔ تازه‌شونده است تا تایپ کاربر پاک نشود */ ?>

<?php if ($desk): ?>
<section class="card mb-6" id="walkin" <?= $walkinOpen ? '' : 'hidden' ?> aria-labelledby="walkin-title">
  <div class="card__header card__header--divided">
    <h2 class="card__title" id="walkin-title">پذیرش حضوری</h2>
    <button type="button" class="btn btn--ghost btn--sm" data-toggle="walkin">بستن</button>
  </div>
  <form method="post" action="<?= e(url('panel/queue/walkin')) ?>" class="card__body stack">
    <?= csrf_field() ?>
    <div class="form-grid form-grid--2">
      <div class="field"><label class="field__label" for="w-name">نام مشتری <span class="field__optional">(اختیاری)</span></label><input class="input" id="w-name" name="name" autocomplete="off" value="<?= e((string) old('name')) ?>"></div>
      <div class="field"><label class="field__label" for="w-phone">موبایل <span class="field__optional">(برای پیامک نوبت)</span></label><input class="input input--ltr num" id="w-phone" name="phone" type="tel" inputmode="tel" dir="ltr" autocomplete="off" value="<?= e((string) old('phone')) ?>" data-numeric></div>
    </div>
    <fieldset class="stack stack-sm">
      <legend class="field__label">خدمات</legend>
      <?php foreach ($serviceGroups as $group): ?>
        <div class="stack stack-xs">
          <span class="text-xs muted"><?= e($group['name']) ?></span>
          <div class="choice-grid" style="--min:140px">
            <?php foreach ($group['services'] as $s): ?>
              <label class="choice choice--compact choice--check">
                <input class="choice__input" type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $oldServices, true) ? 'checked' : '' ?>>
                <span class="choice__card"><span class="truncate"><?= e($s['name']) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </fieldset>
    <div class="field">
      <label class="field__label" for="w-staff"><?= e(term('staff')) ?></label>
      <select class="select" id="w-staff" name="staff_id">
        <option value="">کم‌صف‌ترین فردی که این خدمات را انجام می‌دهد</option>
        <?php foreach ($staffList as $st): ?><option value="<?= (int) $st['id'] ?>" <?= (string) old('staff_id') === (string) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?><?= $st['title'] ? ' — ' . e($st['title']) : '' ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-actions"><button type="submit" class="btn btn--primary"><?= icon('plus') ?> افزودن به صف</button></div>
  </form>
</section>
<?php endif; ?>

<div id="queue-board">
<?php if (!$desk): ?>
  <?php $mine = $snapshot[0]['queue'] ?? []; ?>
  <?php if ($myStaffId === null): ?>
    <div class="card"><?= partial('empty-state', ['icon' => 'user-x', 'title' => 'حساب شما به هیچ ' . term('staff') . 'ی وصل نیست', 'text' => 'از صاحب سالن بخواهید شمارهٔ شما را در «تیم و دسترسی‌ها» برای پروفایلتان ثبت کند.']) ?></div>
  <?php elseif ($mine === []): ?>
    <div class="card"><?= partial('empty-state', ['icon' => 'clock', 'title' => 'فعلاً کسی در صف شما نیست', 'text' => 'نوبت‌های رزروشدهٔ امروز و مراجعه‌های حضوری اینجا نمایش داده می‌شوند.', 'actionHref' => url('panel/bookings'), 'actionLabel' => 'نوبت‌های پیش رو']) ?></div>
  <?php else: $current = $mine[0]; ?>
    <section class="card focus-card mb-4" aria-label="مشتری فعلی">
      <p class="focus-card__label"><?= $current['status'] === 'in_chair' ? e(term('in_service')) : 'نفر بعدی' ?> · <?= e($rowTime($current)) ?></p>
      <p class="focus-card__name"><?= e($current['customer_name'] ?: 'مشتری') ?></p>
      <p class="muted mb-4"><?= e(implode('، ', array_column($current['items'], 'service_name'))) ?></p>
      <?php if (!empty($current['customer_note'])): ?><p class="alert alert--info mb-4"><?= icon('message') ?><span class="alert__body"><?= e($current['customer_note']) ?></span></p><?php endif; ?>
      <?php $rowActions($current, true); ?>
    </section>
    <?php if (count($mine) > 1): ?>
      <section class="card" aria-labelledby="later-title">
        <div class="card__header"><h2 class="card__title" id="later-title">بعدی‌ها</h2></div>
        <ul class="list mt-2">
          <?php foreach (array_slice($mine, 1) as $row): ?>
            <li class="list-row"><span class="list-row__body"><span class="list-row__title"><?= e($row['customer_name'] ?: 'مشتری') ?></span><span class="list-row__meta"><?= e(implode('، ', array_column($row['items'], 'service_name'))) ?></span></span><span class="list-row__end text-sm muted"><?= e($rowTime($row)) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  <?php endif; ?>
<?php else: ?>
  <?php if ($snapshot === []): ?>
    <div class="card"><?= partial('empty-state', ['icon' => 'users', 'title' => 'هنوز کسی در تیم نیست', 'text' => 'برای گرفتن نوبت، دست‌کم یک ' . term('staff') . ' فعال لازم است.', 'actionHref' => url('panel/staff'), 'actionLabel' => 'افزودن به تیم']) ?></div>
  <?php else: ?>
    <div class="queue-board">
      <?php foreach ($snapshot as $group): $n = count($group['queue']); ?>
        <section class="card" aria-label="صف <?= e($group['staff']['name']) ?>">
          <header class="queue-col__head">
            <span class="avatar avatar--sm" style="--avatar-bg:<?= e(staff_color($group['staff']['color'])) ?>" aria-hidden="true"><?= e(initial($group['staff']['name'])) ?></span>
            <span class="grow stack stack-xs"><strong class="truncate"><?= e($group['staff']['name']) ?></strong><?php if (!empty($group['staff']['title'])): ?><span class="text-xs muted truncate"><?= e($group['staff']['title']) ?></span><?php endif; ?></span>
            <span class="badge <?= $n ? 'badge--accent' : '' ?>"><?= $n ? e(fa_num($n)) . ' نفر' : 'آزاد' ?></span>
          </header>
          <?php if ($n === 0): ?>
            <p class="text-sm muted queue-empty">کسی در صف نیست</p>
          <?php endif; ?>
          <?php foreach ($group['queue'] as $row): $inChair = $row['status'] === 'in_chair'; ?>
            <div class="queue-row <?= $inChair ? 'queue-row--active' : '' ?>">
              <div class="queue-row__top">
                <div class="stack stack-xs min-w-0">
                  <span class="row" style="--gap:6px">
                    <?php if ($inChair): ?><span class="dot dot--live" aria-hidden="true"></span><?php endif; ?>
                    <strong class="truncate"><?= e($row['customer_name'] ?: 'مشتری') ?></strong>
                    <?php if ((int) ($row['customer_no_shows'] ?? 0) > 0): ?><span class="badge badge--warning" title="سابقهٔ غیبت"><?= e(fa_num($row['customer_no_shows'])) ?> غیبت</span><?php endif; ?>
                  </span>
                  <span class="text-sm muted truncate"><?= e(implode('، ', array_column($row['items'], 'service_name'))) ?></span>
                  <?php if (!empty($row['customer_note'])): ?><span class="text-xs muted clamp-2"><?= icon('message', 'icon') ?> <?= e($row['customer_note']) ?></span><?php endif; ?>
                </div>
                <span class="badge <?= $row['kind'] === 'booked' ? 'badge--info' : '' ?> shrink-0"><?= e($rowTime($row)) ?></span>
              </div>
              <?php $rowActions($row); ?>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
</div>
