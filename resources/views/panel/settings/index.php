<?php
/**
 * @var array $salon
 * @var array $hours
 * @var array $holidays
 * @var array $timeOffs
 * @var array $staffList
 */
use App\Support\Audience;
use App\Support\JalaliCalendar;
use App\Support\Theme;

$v = static fn (string $key, mixed $fallback = '') => old($key, $fallback);
$logoUrl = salon_logo_url($salon['logo_file'] ?? null);
$theme = Theme::resolve($salon['theme'] ?? null);
$notice = (int) $salon['min_notice_minutes'];
$cancel = (int) $salon['cancel_notice_minutes'];
$durations = [0 => 'بدون محدودیت', 30 => 'نیم ساعت', 60 => '۱ ساعت', 120 => '۲ ساعت', 180 => '۳ ساعت', 360 => '۶ ساعت', 720 => '۱۲ ساعت', 1440 => '۱ روز', 2880 => '۲ روز'];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">تنظیمات سالن</h1>
    <p class="page-head__sub">تغییرات هر بخش جدا ذخیره می‌شود.</p>
  </div>
</div>

<div class="tabs" role="tablist" aria-label="بخش‌های تنظیمات" data-tabs="settings">
  <button class="tab" role="tab" id="tab-profile" aria-controls="panel-profile" data-hash="profile"><?= icon('store') ?> مشخصات</button>
  <button class="tab" role="tab" id="tab-hours" aria-controls="panel-hours" data-hash="hours"><?= icon('clock') ?> ساعت کاری</button>
  <button class="tab" role="tab" id="tab-rules" aria-controls="panel-rules" data-hash="rules"><?= icon('sliders') ?> قوانین رزرو</button>
  <button class="tab" role="tab" id="tab-closures" aria-controls="panel-closures" data-hash="closures"><?= icon('calendar-x') ?> تعطیلی و مرخصی</button>
</div>

<section id="panel-profile" role="tabpanel" aria-labelledby="tab-profile" tabindex="0">
  <form method="post" action="<?= e(url('panel/settings/profile')) ?>" enctype="multipart/form-data" class="stack stack-lg" novalidate>
    <?= csrf_field() ?>
    <div class="card"><div class="card__body stack">
      <div class="form-grid form-grid--2">
        <div class="field">
          <label class="field__label" for="sp-name">نام سالن</label>
          <input class="input" id="sp-name" name="name" maxlength="150" required value="<?= e((string) $v('name', $salon['name'])) ?>" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
          <?= partial('field-error', ['key' => 'name']) ?>
        </div>
        <div class="field">
          <label class="field__label" for="sp-aud">نوع سالن</label>
          <select class="select" id="sp-aud" name="audience">
            <?php foreach (Audience::options() as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) $v('audience', $salon['audience']) === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <p class="field__hint">واژه‌ها (آرایشگر/متخصص)، تصاویر و فیلتر «کشف» بر همین اساس تنظیم می‌شوند.</p>
        </div>
        <div class="field"><label class="field__label" for="sp-city">شهر</label><input class="input" id="sp-city" name="city" maxlength="80" value="<?= e((string) $v('city', $salon['city'] ?? '')) ?>"></div>
        <div class="field"><label class="field__label" for="sp-phone">تلفن سالن</label><input class="input input--ltr num" id="sp-phone" name="phone" type="tel" dir="ltr" value="<?= e((string) $v('phone', $salon['phone'] ?? '')) ?>" data-numeric></div>
        <div class="field span-2"><label class="field__label" for="sp-address">نشانی</label><input class="input" id="sp-address" name="address" maxlength="255" value="<?= e((string) $v('address', $salon['address'] ?? '')) ?>"></div>
      </div>
      <div class="field">
        <label class="field__label" for="sp-slug">نشانی صفحهٔ رزرو</label>
        <div class="input-group" dir="ltr"><span class="input-group__addon" style="border-inline-start:1px solid var(--border-input);border-inline-end:0;border-radius:0 var(--radius-md) var(--radius-md) 0"><?= e(rtrim(absolute_url('s'), '/')) ?>/</span><input class="input input--ltr" id="sp-slug" name="slug" placeholder="<?= e($salon['slug']) ?>" value="<?= e((string) $v('slug', '')) ?>" style="border-radius:var(--radius-md) 0 0 var(--radius-md)"></div>
        <p class="field__hint">خالی بماند تغییر نمی‌کند. با تغییر، QRهای چاپ‌شدهٔ قبلی دیگر کار نمی‌کنند.</p>
        <?= partial('field-error', ['key' => 'slug']) ?>
      </div>
    </div></div>

    <div class="card"><div class="card__header"><h2 class="card__title">رنگ برند</h2></div><div class="card__body stack">
      <p class="text-sm muted">رنگ دکمه‌ها و نشانه‌ها در صفحهٔ رزرو و پنل. رنگ‌های وضعیت (لغو، تأیید) ثابت می‌مانند. همهٔ رنگ‌ها در حالت روشن و تیره خوانایی استاندارد دارند.</p>
      <fieldset>
        <legend class="sr-only">رنگ برند</legend>
        <div class="swatches">
          <?php foreach (Theme::forAudience($salon['audience'] ?? 'men') as $key => $palette): ?>
            <label class="choice swatch" data-theme="<?= e($key) ?>">
              <input class="choice__input" type="radio" name="theme" value="<?= e($key) ?>" <?= $theme === $key ? 'checked' : '' ?>>
              <span class="choice__card"><span class="swatch__chip" style="--swatch:var(--accent)"></span><span class="choice__title text-sm"><?= e($palette['name']) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    </div></div>

    <div class="card"><div class="card__header"><h2 class="card__title">لوگو</h2></div><div class="card__body row" style="flex-wrap:wrap">
      <span class="brand-mark" style="width:64px;height:64px;border-radius:16px"><?= $logoUrl ? '<img src="' . e($logoUrl) . '" alt="لوگوی فعلی">' : icon(($salon['audience'] ?? 'men') === 'women' ? 'sparkles' : 'scissors') ?></span>
      <div class="stack stack-sm grow">
        <label class="field__label" for="sp-logo">انتخاب تصویر (PNG، JPG یا WebP)</label>
        <input class="input" id="sp-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        <?php if ($logoUrl): ?><label class="check"><input type="checkbox" name="remove_logo" value="1"><span>حذف لوگو</span></label><?php endif; ?>
      </div>
    </div></div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--lg">ذخیرهٔ مشخصات</button></div>
  </form>
</section>

<section id="panel-hours" role="tabpanel" aria-labelledby="tab-hours" tabindex="0" hidden>
  <form method="post" action="<?= e(url('panel/settings/hours')) ?>" class="stack stack-lg">
    <?= csrf_field() ?>
    <div class="card"><div class="card__body">
      <div class="hours">
        <?php foreach (JalaliCalendar::WEEKDAY_NAMES as $w => $dayName): $h = $hours[$w] ?? null; $closed = $h ? (bool) $h['is_closed'] : false; ?>
          <div class="hours__row">
            <span class="hours__day"><?= e($dayName) ?></span>
            <label class="check" style="min-height:auto"><input type="checkbox" name="closed_<?= $w ?>" id="closed_<?= $w ?>" value="1" <?= $closed ? 'checked' : '' ?> data-hides="#times-<?= $w ?>"><span>تعطیل</span></label>
            <div class="hours__times" id="times-<?= $w ?>">
              <label>از <?= partial('time-input', ['name' => 'opens_' . $w, 'value' => $h['opens_at'] ?? '09:00', 'label' => 'شروع ' . $dayName]) ?></label>
              <label>تا <?= partial('time-input', ['name' => 'closes_' . $w, 'value' => $h['closes_at'] ?? '21:00', 'label' => 'پایان ' . $dayName]) ?></label>
              <label>استراحت <?= partial('time-input', ['name' => 'break_start_' . $w, 'value' => $h['break_start'] ?? null, 'label' => 'شروع استراحت ' . $dayName, 'allowEmpty' => true]) ?></label>
              <label>تا <?= partial('time-input', ['name' => 'break_end_' . $w, 'value' => $h['break_end'] ?? null, 'label' => 'پایان استراحت ' . $dayName, 'allowEmpty' => true]) ?></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div></div>
    <div class="card"><div class="card__body field" style="max-width:360px">
      <label class="field__label" for="slot-step">فاصلهٔ ساعت‌های شروع نوبت</label>
      <select class="select" id="slot-step" name="slot_step_minutes">
        <?php foreach ([5, 10, 15, 20, 30, 45, 60] as $m): ?><option value="<?= $m ?>" <?= (int) $salon['slot_step_minutes'] === $m ? 'selected' : '' ?>>هر <?= e(fa_num($m)) ?> دقیقه</option><?php endforeach; ?>
      </select>
      <p class="field__hint">مثلاً با ۱۵ دقیقه، مشتری ۱۰:۰۰، ۱۰:۱۵، ۱۰:۳۰ … را می‌بیند. مدت واقعی هر نوبت از خود خدمت می‌آید.</p>
    </div></div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--lg">ذخیرهٔ ساعت کاری</button></div>
  </form>
  <p class="text-sm muted mt-4">ساعت اختصاصی هر نفر را از <a class="link" href="<?= e(url('panel/staff')) ?>">پروفایل همان نفر در «تیم»</a> تنظیم کنید.</p>
</section>

<section id="panel-rules" role="tabpanel" aria-labelledby="tab-rules" tabindex="0" hidden>
  <form method="post" action="<?= e(url('panel/settings/rules')) ?>" class="stack stack-lg" novalidate>
    <?= csrf_field() ?>
    <div class="card"><div class="card__header"><h2 class="card__title">ترتیب رزرو آنلاین</h2></div><div class="card__body">
      <div class="choice-list">
        <?php foreach ([['time_first', 'اول زمان، بعد خدمت', 'مناسب آرایشگاهی که بیشتر یک خدمت اصلی دارد؛ مشتری اول ساعت را انتخاب می‌کند.'], ['service_first', 'اول خدمت، بعد زمان', 'مناسب سالنی با خدمات کوتاه و بلند (رنگ، کراتین، ناخن)؛ فقط ساعت‌هایی که برای همان خدمت جا دارد نمایش داده می‌شود.']] as [$key, $title, $hint]): ?>
          <label class="choice"><input class="choice__input" type="radio" name="booking_flow" value="<?= $key ?>" <?= $salon['booking_flow'] === $key ? 'checked' : '' ?>><span class="choice__card"><span class="choice__body"><span class="choice__title"><?= e($title) ?></span><span class="choice__meta"><?= e($hint) ?></span></span><span class="choice__mark"><?= icon('check') ?></span></span></label>
        <?php endforeach; ?>
      </div>
    </div></div>

    <div class="card"><div class="card__header"><h2 class="card__title">محدودیت‌های زمان</h2></div><div class="card__body form-grid form-grid--3">
      <div class="field">
        <label class="field__label" for="r-horizon">تا چند روز آینده رزرو شود</label>
        <div class="input-group"><input class="input num" id="r-horizon" name="booking_horizon_days" inputmode="numeric" value="<?= e((string) $salon['booking_horizon_days']) ?>" data-numeric><span class="input-group__addon">روز</span></div>
      </div>
      <div class="field">
        <label class="field__label" for="r-notice">حداقل فاصله تا نوبت</label>
        <select class="select" id="r-notice" name="min_notice_minutes"><?php foreach ($durations as $m => $label): ?><option value="<?= $m ?>" <?= $notice === $m ? 'selected' : '' ?>><?= e($m === 0 ? 'بدون محدودیت' : $label) ?></option><?php endforeach; ?><?php if (!isset($durations[$notice])): ?><option value="<?= $notice ?>" selected><?= e(duration_text($notice)) ?></option><?php endif; ?></select>
        <p class="field__hint">مثلاً «۲ ساعت»: کسی نمی‌تواند برای یک ساعت بعد آنلاین رزرو کند.</p>
      </div>
      <div class="field">
        <label class="field__label" for="r-cancel">مهلت لغو آنلاین</label>
        <select class="select" id="r-cancel" name="cancel_notice_minutes"><?php foreach ($durations as $m => $label): ?><option value="<?= $m ?>" <?= $cancel === $m ? 'selected' : '' ?>><?= e($m === 0 ? 'تا پیش از شروع' : $label . ' قبل') ?></option><?php endforeach; ?><?php if (!isset($durations[$cancel])): ?><option value="<?= $cancel ?>" selected><?= e(duration_text($cancel)) ?> قبل</option><?php endif; ?></select>
        <p class="field__hint">پس از آن، مشتری برای لغو باید تماس بگیرد.</p>
      </div>
    </div></div>

    <div class="card"><div class="card__header"><h2 class="card__title">بیعانه</h2></div><div class="card__body stack">
      <p class="text-sm muted">برای خدماتی که در صفحهٔ خدمت «بیعانه» دارند. مشتری پس از رزرو، شمارهٔ کارت را می‌بیند؛ نوبت تا تأیید شما نگه داشته و پس از مهلت خودکار آزاد می‌شود. بدون شمارهٔ کارت، بیعانه گرفته نمی‌شود.</p>
      <div class="form-grid form-grid--3">
        <div class="field"><label class="field__label" for="r-card">شمارهٔ کارت</label><input class="input input--ltr num" id="r-card" name="deposit_card_number" inputmode="numeric" dir="ltr" maxlength="24" value="<?= e((string) $v('deposit_card_number', $salon['deposit_card_number'] ?? '')) ?>" data-numeric <?= field_error('deposit_card_number') ? 'aria-invalid="true" aria-describedby="deposit_card_number-error"' : '' ?>><?= partial('field-error', ['key' => 'deposit_card_number']) ?></div>
        <div class="field"><label class="field__label" for="r-holder">به نام</label><input class="input" id="r-holder" name="deposit_card_holder" maxlength="120" value="<?= e((string) $v('deposit_card_holder', $salon['deposit_card_holder'] ?? '')) ?>"></div>
        <div class="field"><label class="field__label" for="r-hold">مهلت پرداخت</label><div class="input-group"><input class="input num" id="r-hold" name="deposit_hold_minutes" inputmode="numeric" value="<?= e((string) $salon['deposit_hold_minutes']) ?>" data-numeric><span class="input-group__addon">دقیقه</span></div></div>
      </div>
    </div></div>

    <div class="card"><div class="card__body">
      <label class="check">
        <input type="checkbox" name="observe_official_holidays" value="1" <?= (int) $salon['observe_official_holidays'] === 1 ? 'checked' : '' ?>>
        <span class="check__text"><span class="strong">در تعطیلات رسمی بسته‌ایم</span><span class="check__hint">اگر در تعطیلات رسمی (مثلاً روزهای پیش از نوروز) کار می‌کنید، خاموش کنید.</span></span>
      </label>
    </div></div>
    <div class="form-actions"><button type="submit" class="btn btn--primary btn--lg">ذخیرهٔ قوانین</button></div>
  </form>
</section>

<section id="panel-closures" role="tabpanel" aria-labelledby="tab-closures" tabindex="0" hidden>
  <div class="grid grid-2" style="align-items:start">
    <form method="post" action="<?= e(url('panel/settings/timeoff')) ?>" class="card"><div class="card__header"><h2 class="card__title">بستن یک بازه</h2></div><div class="card__body stack">
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="off_staff_id">برای</label>
        <select class="select" id="off_staff_id" name="off_staff_id">
          <option value="">کل سالن (تعطیلی)</option>
          <?php foreach ($staffList as $st): ?><option value="<?= (int) $st['id'] ?>">مرخصی <?= e($st['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><span class="field__label">از تاریخ</span><?= partial('jalali-date-input', ['name' => 'off_date', 'label' => 'شروع', 'years' => [0, 1]]) ?></div>
      <div class="field"><span class="field__label">تا تاریخ <span class="field__optional">(برای چند روز)</span></span><?= partial('jalali-date-input', ['name' => 'off_until', 'label' => 'پایان', 'years' => [0, 1]]) ?></div>
      <label class="check"><input type="checkbox" name="off_all_day" value="1" checked data-hides="#off-times"><span>تمام روز</span></label>
      <div class="hours__times" id="off-times">
        <label>از ساعت <?= partial('time-input', ['name' => 'off_from', 'value' => '14:00', 'label' => 'شروع مرخصی']) ?></label>
        <label>تا <?= partial('time-input', ['name' => 'off_to', 'value' => '18:00', 'label' => 'پایان مرخصی']) ?></label>
      </div>
      <div class="field"><label class="field__label" for="off_reason">دلیل <span class="field__optional">(فقط برای خودتان)</span></label><input class="input" id="off_reason" name="off_reason" maxlength="150" placeholder="مثلاً تعطیلی تاسوعا، مرخصی"></div>
      <button type="submit" class="btn btn--primary">ثبت</button>
    </div></form>

    <div class="stack">
      <section class="card" aria-labelledby="offs-title">
        <div class="card__header"><h2 class="card__title" id="offs-title">بازه‌های بسته</h2></div>
        <?php if ($timeOffs === []): ?>
          <?= partial('empty-state', ['icon' => 'calendar', 'title' => 'بازهٔ بسته‌ای ثبت نشده']) ?>
        <?php else: ?>
          <ul class="list mt-2">
            <?php foreach ($timeOffs as $t): $fullDay = substr((string) $t['starts_at'], 11) === '00:00:00' && substr((string) $t['ends_at'], 11) === '00:00:00'; ?>
              <li class="list-row">
                <span class="icon-tile <?= $t['staff_id'] ? 'icon-tile--neutral' : 'icon-tile--warning' ?>"><?= icon($t['staff_id'] ? 'user-x' : 'store') ?></span>
                <span class="list-row__body">
                  <span class="list-row__title"><?= e($t['staff_name'] ?? 'کل سالن') ?></span>
                  <span class="list-row__meta"><?= e(jdate($t['starts_at'], $fullDay ? 'D j M' : 'D j M، H:i')) ?> تا <?= e(jdate($fullDay ? date('Y-m-d H:i:s', strtotime((string) $t['ends_at']) - 60) : $t['ends_at'], $fullDay ? 'D j M' : 'H:i')) ?><?= $t['reason'] ? ' · ' . e($t['reason']) : '' ?></span>
                </span>
                <form method="post" action="<?= e(url('panel/settings/timeoff/' . $t['id'] . '/remove')) ?>" data-confirm="این بازه دوباره باز شود؟" data-confirm-tone="neutral" data-confirm-ok="باز شود"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit">باز کن</button></form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
      <section class="card" aria-labelledby="hol-title">
        <div class="card__header"><h2 class="card__title" id="hol-title">تعطیلات رسمی پیش رو</h2></div>
        <div class="card__body stack stack-sm">
          <p class="text-sm muted"><?= (int) $salon['observe_official_holidays'] === 1 ? 'در این روزها رزرو آنلاین بسته است.' : 'سالن در تعطیلات رسمی باز است (قوانین رزرو).' ?> فهرست را مدیر پلتفرم به‌روز می‌کند؛ تعطیلی خاص سالن را از فرم کناری ثبت کنید.</p>
          <?php if ($holidays === []): ?><p class="text-sm muted">موردی ثبت نشده.</p><?php else: ?>
            <dl class="kv"><?php foreach ($holidays as $h): ?><div class="kv__row"><dt><?= e(jdate($h['gregorian_date'], 'D j M Y')) ?></dt><dd><?= e($h['jalali_label']) ?></dd></div><?php endforeach; ?></dl>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </div>
</section>
