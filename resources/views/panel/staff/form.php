<?php
/**
 * @var ?array $staff
 * @var ?array $account
 * @var array $groups
 * @var array<int,bool> $offered
 * @var array<int,array> $hours
 * @var array<int,array> $salonHours
 * @var bool $isOwner
 * @var array $usedColors
 */
use App\Support\JalaliCalendar;
use App\Support\StaffColor;

$s = $staff ?? [];
$isNew = $staff === null;
$v = static fn (string $key, mixed $fallback = '') => old($key, $fallback);
$color = StaffColor::resolve((string) $v('color', $s['color'] ?? StaffColor::next($usedColors)));
$role = (string) $v('role', $account['role'] ?? 'staff');
?>
<a class="back-link" href="<?= e(url('panel/staff')) ?>"><?= icon('chevron-start') ?> تیم</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title"><?= $isNew ? 'افزودن ' . e(term('staff')) : e($s['name']) ?></h1>
    <?php if (!$isNew && !(bool) $s['is_active']): ?><p class="page-head__sub"><span class="badge badge--warning">غیرفعال</span></p><?php endif; ?>
  </div>
</div>

<form method="post" action="<?= e(url($isNew ? 'panel/staff' : 'panel/staff/' . $s['id'])) ?>" class="stack stack-lg" novalidate>
  <?= csrf_field() ?>
  <section class="card" aria-labelledby="profile-title"><div class="card__header"><h2 class="card__title" id="profile-title">مشخصات</h2></div><div class="card__body stack">
    <div class="form-grid form-grid--2">
      <div class="field">
        <label class="field__label" for="st-name">نامی که مشتری می‌بیند</label>
        <input class="input" id="st-name" name="name" maxlength="120" required value="<?= e((string) $v('name', $s['name'] ?? '')) ?>" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'name']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="st-title">عنوان شغلی <span class="field__optional">(اختیاری)</span></label>
        <input class="input" id="st-title" name="title" maxlength="80" placeholder="<?= e(term('staff_title_placeholder')) ?>" value="<?= e((string) $v('title', $s['title'] ?? '')) ?>">
      </div>
    </div>
    <div class="field">
      <label class="field__label" for="st-bio">معرفی کوتاه <span class="field__optional">(اختیاری)</span></label>
      <input class="input" id="st-bio" name="bio" maxlength="300" value="<?= e((string) $v('bio', $s['bio'] ?? '')) ?>">
    </div>
    <fieldset class="field">
      <legend class="field__label mb-2">رنگ نشانه در پنل</legend>
      <div class="color-dots">
        <?php foreach (StaffColor::PALETTE as $hex => $name): ?>
          <label class="color-dot" style="--swatch:<?= e($hex) ?>" title="<?= e($name) ?>"><input type="radio" name="color" value="<?= e($hex) ?>" <?= $color === $hex ? 'checked' : '' ?> aria-label="<?= e($name) ?>"><span></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <label class="check">
      <input type="checkbox" name="accepts_online" value="1" <?= (string) $v('accepts_online', (string) ($s['accepts_online'] ?? 1)) === '1' ? 'checked' : '' ?>>
      <span class="check__text"><span class="strong">در رزرو آنلاین قابل انتخاب باشد</span><span class="check__hint">خاموش: فقط از پذیرش حضوری و رزرو پنل نوبت می‌گیرد.</span></span>
    </label>
    <div class="field w-md">
      <label class="field__label" for="st-comm">درصد سهم <span class="field__optional">(اختیاری)</span></label>
      <div class="input-group"><input class="input num" id="st-comm" name="commission_percent" inputmode="decimal" value="<?= e((string) $v('commission_percent', $s['commission_percent'] ?? '')) ?>" data-numeric><span class="input-group__addon">٪</span></div>
      <?= partial('field-error', ['key' => 'commission_percent']) ?>
    </div>
  </div></section>

  <section class="card" aria-labelledby="access-title"><div class="card__header"><h2 class="card__title" id="access-title">ورود به پنل</h2></div><div class="card__body stack">
    <p class="text-sm muted">با شمارهٔ موبایل، این فرد می‌تواند وارد پنل شود و صف و نوبت‌های خودش را ببیند. بدون شماره فقط در فهرست نوبت‌ها هست.</p>
    <div class="form-grid form-grid--2">
      <div class="field">
        <label class="field__label" for="st-phone">موبایل</label>
        <input class="input input--ltr num" id="st-phone" name="phone" type="tel" dir="ltr" value="<?= e((string) $v('phone', !empty($s['phone']) ? '0' . substr((string) $s['phone'], 3) : '')) ?>" data-numeric <?= field_error('phone') ? 'aria-invalid="true" aria-describedby="phone-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'phone']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="st-role">نقش در پنل</label>
        <?php if (($account['role'] ?? '') === 'owner'): ?>
          <input type="hidden" name="role" value="staff"><p class="input input--static">صاحب سالن</p>
        <?php else: ?>
          <select class="select" id="st-role" name="role">
            <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>><?= e(term('staff')) ?> — فقط کار خودش</option>
            <option value="reception" <?= $role === 'reception' ? 'selected' : '' ?>>پذیرش — صف، رزرو، تسویه</option>
            <?php if ($isOwner): ?><option value="manager" <?= $role === 'manager' ? 'selected' : '' ?>>مدیر — همه‌چیز</option><?php endif; ?>
          </select>
        <?php endif; ?>
        <?= partial('field-error', ['key' => 'role']) ?>
      </div>
    </div>
    <?php if (!$isNew && $s['user_id'] !== null && ($account['role'] ?? '') !== 'owner'): ?>
      <label class="check"><input type="checkbox" name="unlink" value="1"><span class="check__text"><span>جدا کردن حساب و برداشتن دسترسی</span><span class="check__hint">برای این کار شماره را هم خالی کنید.</span></span></label>
    <?php endif; ?>
  </div></section>

  <section class="card" aria-labelledby="skills-title"><div class="card__header"><h2 class="card__title" id="skills-title">خدماتی که انجام می‌دهد</h2></div><div class="card__body stack">
    <input type="hidden" name="skills_present" value="1">
    <p class="text-sm muted">فقط برای همین خدمات در رزرو پیشنهاد می‌شود. اگر مشتری خدمتی بخواهد که این فرد انجام نمی‌دهد، بخش‌ها بین چند نفر تقسیم می‌شود.</p>
    <?php if ($groups === []): ?><p class="text-sm">هنوز خدمتی تعریف نشده. <a class="link" href="<?= e(url('panel/services/create')) ?>">افزودن خدمت</a></p><?php endif; ?>
    <?php foreach ($groups as $group): ?>
      <fieldset class="stack stack-xs">
        <legend class="text-sm strong"><?= e($group['name']) ?></legend>
        <div class="choice-grid" style="--min:160px">
          <?php foreach ($group['services'] as $svc): $checked = $isNew ? true : ($offered[(int) $svc['id']] ?? true); ?>
            <label class="choice choice--compact choice--check"><input class="choice__input" type="checkbox" name="skills[]" value="<?= (int) $svc['id'] ?>" <?= $checked ? 'checked' : '' ?>><span class="choice__card"><span class="truncate"><?= e($svc['name']) ?></span></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; ?>
  </div></section>

  <section class="card" aria-labelledby="hours-title"><div class="card__header"><h2 class="card__title" id="hours-title">ساعت کاری</h2></div><div class="card__body stack stack-sm">
    <p class="text-sm muted">«مثل سالن» یعنی همان ساعت تنظیمات سالن. استراحت روزانهٔ سالن برای همه اعمال می‌شود.</p>
    <div class="hours">
      <?php foreach (JalaliCalendar::WEEKDAY_NAMES as $w => $dayName):
          $row = $hours[$w] ?? null;
          $mode = $row === null ? 'salon' : ((int) $row['is_closed'] === 1 ? 'off' : 'custom');
          $salonDay = $salonHours[$w] ?? null;
          $salonText = $salonDay === null ? '—' : ((int) $salonDay['is_closed'] === 1 ? 'تعطیل' : fa_time($salonDay['opens_at']) . '–' . fa_time($salonDay['closes_at'])); ?>
        <div class="hours__row">
          <span class="hours__day"><?= e($dayName) ?></span>
          <label class="sr-only" for="hm-<?= $w ?>">حالت <?= e($dayName) ?></label>
          <select class="select select--compact" id="hm-<?= $w ?>" name="hours[<?= $w ?>][mode]" data-hours-mode="<?= $w ?>">
            <option value="salon" <?= $mode === 'salon' ? 'selected' : '' ?>>مثل سالن (<?= e($salonText) ?>)</option>
            <option value="custom" <?= $mode === 'custom' ? 'selected' : '' ?>>ساعت دیگر</option>
            <option value="off" <?= $mode === 'off' ? 'selected' : '' ?>>کار نمی‌کند</option>
          </select>
          <div class="hours__times" id="ht-<?= $w ?>" <?= $mode === 'custom' ? '' : 'hidden' ?>>
            <label>از <?= partial('time-input', ['name' => 'h' . $w . 'o', 'nameH' => 'hours[' . $w . '][opens_h]', 'nameM' => 'hours[' . $w . '][opens_m]', 'id' => 'h' . $w . 'o', 'value' => $row['opens_at'] ?? ($salonDay['opens_at'] ?? '09:00'), 'label' => 'شروع ' . $dayName]) ?></label>
            <label>تا <?= partial('time-input', ['name' => 'h' . $w . 'c', 'nameH' => 'hours[' . $w . '][closes_h]', 'nameM' => 'hours[' . $w . '][closes_m]', 'id' => 'h' . $w . 'c', 'value' => $row['closes_at'] ?? ($salonDay['closes_at'] ?? '21:00'), 'label' => 'پایان ' . $dayName]) ?></label>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div></section>

  <div class="form-actions">
    <button type="submit" class="btn btn--primary btn--lg"><?= $isNew ? 'افزودن به تیم' : 'ذخیرهٔ تغییرات' ?></button>
    <a class="btn btn--ghost btn--lg" href="<?= e(url('panel/staff')) ?>">انصراف</a>
  </div>
</form>
<script nonce="<?= e(csp_nonce()) ?>">
/* ساعت‌ها فقط وقتی «ساعت دیگر» انتخاب شده نمایش داده می‌شوند. */
(function () {
  Array.prototype.forEach.call(document.querySelectorAll('[data-hours-mode]'), function (select) {
    var box = document.getElementById('ht-' + select.getAttribute('data-hours-mode'));
    select.addEventListener('change', function () { box.hidden = select.value !== 'custom'; });
  });
})();
</script>
