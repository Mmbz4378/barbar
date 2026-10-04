<?php
/**
 * @var int $jy
 * @var int $thisYear
 * @var array $holidays
 * @var int $observing
 */
use App\Support\Jalali;
use App\Support\Now;

$today = Now::today()->format('Y-m-d');
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">تعطیلات رسمی <?= e(fa_num($jy)) ?></h1>
    <p class="page-head__sub">در این روزها نوبت‌دهی آنلاین <?= e(fa_num($observing)) ?> سالنی که «رعایت تعطیلات رسمی» را روشن کرده‌اند بسته است.</p>
  </div>
  <div class="page-head__actions">
    <nav class="btn-row" aria-label="سال">
      <?php if ($jy > $thisYear - 2): ?><a class="btn btn--secondary btn--icon" href="<?= e(url('platform/holidays?jy=' . ($jy - 1))) ?>" aria-label="سال قبل"><?= icon('chevron-start') ?></a><?php endif; ?>
      <?php if ($jy !== $thisYear): ?><a class="btn btn--secondary" href="<?= e(url('platform/holidays')) ?>">امسال</a><?php endif; ?>
      <?php if ($jy < $thisYear + 3): ?><a class="btn btn--secondary btn--icon" href="<?= e(url('platform/holidays?jy=' . ($jy + 1))) ?>" aria-label="سال بعد"><?= icon('chevron-end') ?></a><?php endif; ?>
    </nav>
  </div>
</div>

<div class="grid grid-main-aside" style="--gap:24px">
  <section aria-label="فهرست تعطیلات">
    <?php if ($holidays === []): ?>
      <?= partial('empty-state', ['icon' => 'calendar-x', 'title' => 'برای این سال تعطیلی ثبت نشده', 'text' => 'تعطیلات ثابت شمسی را با یک دکمه اضافه کنید؛ تعطیلات قمری هر سال جابه‌جا می‌شوند و باید دستی وارد شوند.']) ?>
    <?php else: ?>
      <div class="card">
        <ul class="list" role="list">
          <?php foreach ($holidays as $h): $d = new DateTimeImmutable((string) $h['gregorian_date']); $past = $h['gregorian_date'] < $today; ?>
            <li class="list-row" style="<?= $past ? 'opacity:.6' : '' ?>">
              <span class="icon-tile <?= (int) $h['is_official'] ? '' : 'icon-tile--neutral' ?>" aria-hidden="true"><?= icon('calendar-x') ?></span>
              <span class="list-row__body">
                <span class="list-row__title"><?= e($h['jalali_label']) ?></span>
                <span class="list-row__meta"><?= e(Jalali::weekdayName($d)) ?> <?= e(Jalali::format($d, 'j M Y')) ?><?= (int) $h['is_official'] ? ' · ثابت' : '' ?></span>
              </span>
              <span class="list-row__end">
                <form method="post" action="<?= e(url('platform/holidays/' . $h['id'] . '/remove')) ?>" data-confirm="تعطیلی «<?= e($h['jalali_label']) ?>» حذف شود؟">
                  <?= csrf_field() ?><button class="btn btn--ghost btn--icon" type="submit" aria-label="حذف <?= e($h['jalali_label']) ?>"><?= icon('trash') ?></button>
                </form>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>

  <aside class="stack">
    <form method="post" action="<?= e(url('platform/holidays')) ?>" class="card" novalidate><div class="card__body stack">
      <?= csrf_field() ?>
      <h2 class="card__title">افزودن تعطیلی</h2>
      <fieldset class="field">
        <legend class="field__label">تاریخ</legend>
        <?= partial('jalali-date-input', ['name' => 'date', 'value' => $jy === $thisYear ? null : Jalali::toDateTime($jy, 1, 1)->format('Y-m-d'), 'label' => 'تعطیلی', 'years' => [-1, 3]]) ?>
        <?= partial('field-error', ['key' => 'date']) ?>
      </fieldset>
      <div class="field">
        <label class="field__label" for="hl-label">عنوان</label>
        <input class="input" id="hl-label" name="label" maxlength="100" placeholder="مثلاً عید فطر" value="<?= e((string) old('label', '')) ?>" <?= field_error('label') ? 'aria-invalid="true" aria-describedby="label-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'label']) ?>
      </div>
      <button class="btn btn--primary" type="submit"><?= icon('plus') ?> افزودن</button>
    </div></form>

    <form method="post" action="<?= e(url('platform/holidays/seed')) ?>" class="card"><div class="card__body stack stack-sm">
      <?= csrf_field() ?><input type="hidden" name="jy" value="<?= (int) $jy ?>">
      <h2 class="card__title">تعطیلات ثابت شمسی</h2>
      <p class="text-sm muted">نوروز، سیزده‌به‌در، ۱۲ فروردین، ۱۴ و ۱۵ خرداد، ۲۲ بهمن و ۲۹ اسفند برای سال <?= e(fa_num($jy)) ?>. موارد تکراری دوباره اضافه نمی‌شوند.</p>
      <button class="btn btn--secondary" type="submit"><?= icon('calendar') ?> افزودن تعطیلات ثابت</button>
    </div></form>
  </aside>
</div>
