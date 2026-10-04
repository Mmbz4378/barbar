<?php
/**
 * بدنهٔ مشترک گزارش روزانه و ماهانه.
 *
 * @var array $totals
 * @var array $methods
 * @var array $byStaff
 * @var array $topServices
 * @var array $flow
 * @var array $mix
 * @var array $reminded
 */
use App\Domain\Payment\PaymentRepository;

$staffWord = term('staff');
$net = $totals['revenue'];
$maxStaff = max(1, ...array_map(static fn ($r) => (int) $r['revenue'], $byStaff ?: [['revenue' => 0]]));
$maxMethod = max(1, ...array_map(static fn ($r) => (int) $r['total'], $methods ?: [['total' => 0]]));
$finished = $flow['completed'] + $flow['no_show'];
$noShowRate = $finished > 0 ? (int) round($flow['no_show'] / $finished * 100) : null;
$cancelled = $flow['cancelled_customer'] + $flow['cancelled_salon'] + $flow['cancelled_system'];
$customers = $mix['new'] + $mix['returning'];
?>
<div class="stats" style="--cols:4">
  <div class="stat stat--accent">
    <span class="stat__label"><?= icon('wallet') ?> فروش</span>
    <span class="stat__value num"><?= e(toman($net)) ?></span>
    <?php if ($totals['deposits'] > 0): ?><span class="stat__hint">شامل <?= e(toman($totals['deposits'])) ?> بیعانه</span><?php endif; ?>
  </div>
  <div class="stat">
    <span class="stat__label"><?= icon('circle-check') ?> مراجعهٔ تسویه‌شده</span>
    <span class="stat__value num"><?= e(fa_num($totals['settlements'])) ?></span>
    <span class="stat__hint">میانگین هر مراجعه <?= e(toman($totals['avg_ticket'])) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label"><?= icon('heart') ?> انعام</span>
    <span class="stat__value stat__value--sm num"><?= e(toman($totals['tips'])) ?></span>
    <span class="stat__hint">جدا از فروش، سهم <?= e($staffWord) ?></span>
  </div>
  <div class="stat">
    <span class="stat__label"><?= icon('tag') ?> تخفیف داده‌شده</span>
    <span class="stat__value stat__value--sm num"><?= e(toman($totals['discounts'])) ?></span>
  </div>
</div>

<div class="grid grid-2">
  <section class="card" aria-labelledby="r-staff">
    <div class="card__header card__header--divided"><h2 class="card__title" id="r-staff">فروش به تفکیک <?= e($staffWord) ?></h2></div>
    <div class="card__body">
      <?php if (array_sum(array_map(static fn ($r) => (int) $r['revenue'], $byStaff)) === 0): ?>
        <?= partial('empty-state', ['icon' => 'users', 'title' => 'فروشی ثبت نشده', 'text' => 'پس از ثبت پرداخت، سهم هر نفر اینجا دیده می‌شود.']) ?>
      <?php else: ?>
        <ul class="stack stack-md" role="list">
          <?php foreach ($byStaff as $row): if ((int) $row['revenue'] === 0 && (int) $row['visits'] === 0) { continue; } ?>
            <?php $share = (int) round(((int) $row['revenue'] - (int) $row['tips']) * ((float) ($row['commission_percent'] ?? 0)) / 100); ?>
            <li class="stack stack-xs">
              <div class="spread">
                <span class="row" style="--gap:8px"><span class="dot" style="background:<?= e(staff_color($row['color'] ?? null)) ?>;width:10px;height:10px" aria-hidden="true"></span><strong><?= e($row['name']) ?></strong> <span class="text-xs muted"><?= e(fa_num((int) $row['visits'])) ?> مراجعه</span></span>
                <span class="num bold"><?= e(toman((int) $row['revenue'])) ?></span>
              </div>
              <div class="meter" role="presentation"><div class="meter__fill" style="--v:<?= (int) round((int) $row['revenue'] / $maxStaff * 100) ?>;background:<?= e(staff_color($row['color'] ?? null)) ?>"></div></div>
              <?php if ((float) ($row['commission_percent'] ?? 0) > 0): ?>
                <span class="text-xs muted">سهم تخمینی (<?= e(fa_num((float) $row['commission_percent'])) ?>٪ بدون انعام): <?= e(toman($share)) ?><?= (int) $row['tips'] > 0 ? ' · انعام ' . e(toman((int) $row['tips'])) : '' ?></span>
              <?php elseif ((int) $row['tips'] > 0): ?>
                <span class="text-xs muted">انعام <?= e(toman((int) $row['tips'])) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" aria-labelledby="r-methods">
    <div class="card__header card__header--divided"><h2 class="card__title" id="r-methods">روش پرداخت</h2></div>
    <div class="card__body">
      <?php if ($methods === []): ?>
        <?= partial('empty-state', ['icon' => 'card', 'title' => 'پرداختی ثبت نشده']) ?>
      <?php else: ?>
        <ul class="stack stack-md" role="list">
          <?php foreach ($methods as $m): ?>
            <li class="stack stack-xs">
              <div class="spread"><span><?= e(PaymentRepository::METHODS[$m['method']] ?? $m['method']) ?> <span class="text-xs muted"><?= e(fa_num((int) $m['count'])) ?> پرداخت</span></span><span class="num bold"><?= e(toman((int) $m['total'])) ?></span></div>
              <div class="meter" role="presentation"><div class="meter__fill" style="--v:<?= (int) round((int) $m['total'] / $maxMethod * 100) ?>"></div></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" aria-labelledby="r-flow">
    <div class="card__header card__header--divided"><h2 class="card__title" id="r-flow">نوبت‌ها</h2></div>
    <div class="card__body">
      <dl class="kv">
        <div class="kv__row"><dt>انجام‌شده</dt><dd class="num"><?= e(fa_num($flow['completed'])) ?></dd></div>
        <div class="kv__row"><dt>نیامده</dt><dd class="num <?= $noShowRate !== null && $noShowRate >= 15 ? 'danger-text' : '' ?>"><?= e(fa_num($flow['no_show'])) ?><?= $noShowRate !== null ? ' <span class="text-xs muted">(' . e(fa_num($noShowRate)) . '٪)</span>' : '' ?></dd></div>
        <div class="kv__row"><dt>لغو</dt><dd class="num"><?= e(fa_num($cancelled)) ?><?php if ($cancelled > 0): ?> <span class="text-xs muted">مشتری <?= e(fa_num($flow['cancelled_customer'])) ?> · سالن <?= e(fa_num($flow['cancelled_salon'])) ?> · خودکار <?= e(fa_num($flow['cancelled_system'])) ?></span><?php endif; ?></dd></div>
        <div class="kv__row"><dt>رزرو آنلاین</dt><dd class="num"><?= e(fa_num($flow['online'])) ?></dd></div>
        <div class="kv__row"><dt>رزرو از پنل</dt><dd class="num"><?= e(fa_num($flow['panel'])) ?></dd></div>
        <div class="kv__row"><dt>حضوری (بدون نوبت)</dt><dd class="num"><?= e(fa_num($flow['walkins'])) ?></dd></div>
      </dl>
    </div>
  </section>

  <section class="card" aria-labelledby="r-customers">
    <div class="card__header card__header--divided"><h2 class="card__title" id="r-customers">مشتری‌ها</h2></div>
    <div class="card__body stack">
      <?php if ($customers === 0): ?>
        <p class="muted text-sm">هنوز مراجعهٔ انجام‌شده‌ای در این بازه نیست.</p>
      <?php else: ?>
        <div class="spread"><span>مشتری تازه</span><strong class="num"><?= e(fa_num($mix['new'])) ?></strong></div>
        <div class="meter" role="presentation"><div class="meter__fill" style="--v:<?= (int) round($mix['new'] / $customers * 100) ?>"></div></div>
        <div class="spread"><span>مشتری برگشتی</span><strong class="num"><?= e(fa_num($mix['returning'])) ?></strong></div>
        <div class="meter" role="presentation"><div class="meter__fill" style="--v:<?= (int) round($mix['returning'] / $customers * 100) ?>"></div></div>
      <?php endif; ?>
      <?php if ($reminded['count'] > 0): ?>
        <div class="alert alert--info"><?= icon('message') ?><div class="alert__body text-sm"><?= e(fa_num($reminded['count'])) ?> نوبت پس از پیامک یادآوری انجام شد (ارزش خدمات: <?= e(toman($reminded['value'])) ?>).</div></div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php if ($topServices !== []): ?>
  <section class="section" aria-labelledby="r-top">
    <div class="section__head"><h2 class="section__title" id="r-top">پرطرفدارترین خدمات</h2></div>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">خدمت</th><th scope="col" class="num">دفعات</th><th scope="col" class="num">ارزش (قیمت فهرست)</th></tr></thead>
        <tbody>
          <?php foreach ($topServices as $s): ?>
            <tr><td data-label="خدمت"><?= e($s['name']) ?></td><td data-label="دفعات" class="num"><?= e(fa_num((int) $s['times'])) ?></td><td data-label="ارزش" class="num"><?= e(toman((int) $s['value'])) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
