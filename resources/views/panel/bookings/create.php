<?php
/**
 * رزرو دستی (تلفنی یا حضوری برای بعد).
 *
 * @var DateTimeImmutable $date
 * @var ?int $staffId
 * @var int[] $serviceIds
 * @var array $groups
 * @var array $staffList
 * @var int[] $capable
 * @var string[] $slots
 * @var ?string $pickError
 * @var ?array $estimate
 * @var bool $multi
 */
use App\Support\Clock;
use App\Support\JalaliCalendar;

$parts = ['صبح' => [], 'ظهر' => [], 'عصر' => [], 'شب' => []];
foreach ($slots as $t) { $parts[Clock::partOfDay($t)][] = $t; }
$oldTime = (string) old('time');
?>
<a class="back-link" href="<?= e(url('panel/bookings')) ?>"><?= icon('chevron-start') ?> رزروها</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">رزرو جدید</h1>
    <p class="page-head__sub">برای مشتری‌ای که تماس گرفته یا برای نوبت بعدی‌اش برنامه می‌ریزد.</p>
  </div>
</div>

<div class="grid grid-2" style="align-items:start">
  <form method="get" action="<?= e(url('panel/bookings/new')) ?>" class="card" id="pick-form">
    <div class="card__header"><h2 class="card__title">۱. خدمت، فرد و روز</h2></div>
    <div class="card__body stack">
      <fieldset class="stack stack-sm">
        <legend class="field__label">خدمات</legend>
        <?php foreach ($groups as $group): ?>
          <span class="text-xs muted"><?= e($group['name']) ?></span>
          <div class="choice-grid" style="--min:140px">
            <?php foreach ($group['services'] as $s): ?>
              <label class="choice choice--compact choice--check">
                <input class="choice__input" type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $serviceIds, true) ? 'checked' : '' ?>>
                <span class="choice__card"><span class="truncate"><?= e($s['name']) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($groups === []): ?><p class="text-sm muted">هنوز خدمتی فعال نیست. <a class="link" href="<?= e(url('panel/services')) ?>">افزودن خدمت</a></p><?php endif; ?>
      </fieldset>
      <div class="field">
        <label class="field__label" for="staff-pick"><?= e(term('staff')) ?></label>
        <select class="select" id="staff-pick" name="staff_id">
          <option value=""><?= $multi ? 'چند نفر به ترتیب (خودکار)' : 'هر کسی که آزاد و توانا باشد' ?></option>
          <?php foreach ($staffList as $st): $able = $serviceIds === [] || in_array((int) $st['id'], $capable, true); ?>
            <option value="<?= (int) $st['id'] ?>" <?= $staffId === (int) $st['id'] ? 'selected' : '' ?> <?= $able ? '' : 'disabled' ?>><?= e($st['name']) ?><?= $able ? '' : ' (این خدمات را انجام نمی‌دهد)' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <span class="field__label" id="date-label">روز</span>
        <?= partial('jalali-date-input', ['name' => 'date', 'value' => $date->format('Y-m-d'), 'label' => 'نوبت', 'years' => [0, 1]]) ?>
        <span class="field__hint"><?= e(JalaliCalendar::relativeDate($date)) ?></span>
      </div>
      <button type="submit" class="btn btn--secondary btn--block"><?= icon('refresh') ?> نمایش ساعت‌های آزاد</button>
      <?php if ($estimate !== null): ?>
        <p class="text-sm muted">مدت: <?= e(duration_text($estimate['minutes_min'])) ?><?= $estimate['minutes_max'] !== $estimate['minutes_min'] ? ' تا ' . e(duration_text($estimate['minutes_max'])) : '' ?> · قیمت: <?= e(price_range_text($estimate['min'], $estimate['max'], $estimate['from'])) ?></p>
      <?php endif; ?>
    </div>
  </form>

  <form method="post" action="<?= e(url('panel/bookings')) ?>" class="card" novalidate>
    <div class="card__header"><h2 class="card__title">۲. ساعت و مشتری</h2></div>
    <div class="card__body stack">
      <?= csrf_field() ?>
      <input type="hidden" name="date" value="<?= e($date->format('Y-m-d')) ?>">
      <input type="hidden" name="staff_id" value="<?= $staffId !== null ? (int) $staffId : '' ?>">
      <?php foreach ($serviceIds as $sid): ?><input type="hidden" name="service_ids[]" value="<?= (int) $sid ?>"><?php endforeach; ?>

      <?php if ($pickError !== null): ?>
        <div class="alert alert--warning"><?= icon('alert') ?><div class="alert__body"><?= e($pickError) ?></div></div>
      <?php elseif ($serviceIds === []): ?>
        <div class="alert alert--info"><?= icon('info') ?><div class="alert__body">اول خدمت را انتخاب کنید؛ ساعت‌ها بر اساس مدت واقعی و توانایی افراد حساب می‌شوند.</div></div>
      <?php elseif ($slots === []): ?>
        <div class="alert alert--warning"><?= icon('calendar-x') ?><div class="alert__body">این روز برای این خدمات ساعت آزادی ندارد. روز یا فرد دیگری را امتحان کنید.</div></div>
      <?php else: ?>
        <?php if ($multi): ?><div class="alert alert--accent"><?= icon('users') ?><div class="alert__body">هیچ‌کس به‌تنهایی همهٔ این خدمات را انجام نمی‌دهد؛ نوبت بین چند نفر پشت سر هم چیده می‌شود.</div></div><?php endif; ?>
        <fieldset>
          <legend class="field__label mb-2">ساعت</legend>
          <?php foreach ($parts as $label => $times): if ($times === []) { continue; } ?>
            <p class="text-xs muted mt-2"><?= e($label) ?></p>
            <div class="slot-grid">
              <?php foreach ($times as $t): ?>
                <label class="choice slot"><input class="choice__input" type="radio" name="time" value="<?= e($t) ?>" required <?= $oldTime === $t ? 'checked' : '' ?>><span class="choice__card"><?= e(fa_time($t)) ?></span></label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
          <?= partial('field-error', ['key' => 'time']) ?>
        </fieldset>
      <?php endif; ?>

      <hr class="divider">
      <div class="field">
        <label class="field__label" for="b-phone">موبایل مشتری</label>
        <input class="input input--ltr num" id="b-phone" name="phone" type="tel" inputmode="tel" dir="ltr" required value="<?= e((string) old('phone')) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'phone']) ?>
      </div>
      <div class="field"><label class="field__label" for="b-name">نام <span class="field__optional">(اختیاری)</span></label><input class="input" id="b-name" name="name" value="<?= e((string) old('name')) ?>"></div>
      <div class="field"><label class="field__label" for="b-note">یادداشت <span class="field__optional">(اختیاری)</span></label><input class="input" id="b-note" name="note" maxlength="300" value="<?= e((string) old('note')) ?>"></div>
      <button type="submit" class="btn btn--primary btn--lg btn--block" <?= $slots === [] ? 'disabled' : '' ?>>ثبت نوبت</button>
    </div>
  </form>
</div>
<script>
/* تغییر انتخاب‌ها ساعت‌ها را دوباره حساب کند (بدون این هم دکمه کار می‌کند). */
(function () { var f = document.getElementById('pick-form'); if (f) f.addEventListener('change', function () { f.submit(); }); })();
</script>
