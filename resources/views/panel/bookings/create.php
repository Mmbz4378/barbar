<?php
/**
 * رزرو دستی — یک فرم، از تاریخ تا ثبت.
 *
 * @var DateTimeImmutable $date
 * @var ?int $staffId
 * @var int[] $serviceIds
 * @var array $services
 * @var array $staffList
 * @var array $slots   "H:i" => staff_ids
 * @var int $duration
 * @var bool $isClosed
 */

use App\Support\Clock;
use App\Support\JalaliCalendar;

$groups = ['صبح' => [], 'ظهر' => [], 'عصر' => [], 'شب' => []];
foreach (array_keys($slots) as $time) {
    $groups[Clock::partOfDay($time)][] = $time;
}
?>

<div class="flex items-center gap-2 mb-5">
  <a href="<?= e(url('panel/bookings')) ?>"
     class="w-11 h-11 grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100 tap shrink-0"
     aria-label="بازگشت به رزروها"><?= icon('chevron-start', 'w-4 h-4') ?></a>
  <div class="min-w-0">
    <h1 class="page-title">رزرو جدید</h1>
    <p class="text-[12px] text-ink-400 mt-0.5">برای مشتری‌ای که زنگ زده یا سر پیشخوان است.</p>
  </div>
</div>

<div class="grid lg:grid-cols-[1fr_1.2fr] gap-4 items-start">

  <!-- گام ۱: تاریخ، آرایشگر، خدمت — با GET تا بدون جاوااسکریپت هم کار کند -->
  <form method="get" action="<?= e(url('panel/bookings/new')) ?>" id="pick-form"
        class="glass rounded-2xl p-4 sm:p-5 space-y-4">

    <div>
      <span class="block text-[12px] font-bold text-ink-600 mb-2">۱. تاریخ</span>
      <?php $name = 'date'; $value = $date->format('Y-m-d'); $label = 'نوبت'; $years = [0, 1];
            include BASE_PATH . '/resources/views/components/jalali-date-input.php'; ?>
      <p class="text-[12px] text-ink-400 mt-1.5"><?= e(JalaliCalendar::relativeDate($date)) ?></p>
    </div>

    <div>
      <label for="staff-pick" class="block text-[12px] font-bold text-ink-600 mb-2">۲. آرایشگر</label>
      <select name="staff_id" id="staff-pick"
              class="w-full h-11 rounded-xl border border-ink-200 bg-transparent px-3 text-[13px]
                     focus:outline-none focus:ring-2 focus:ring-accent">
        <option value="">هر آرایشگری که آزاد باشد</option>
        <?php foreach ($staffList as $st): ?>
          <option value="<?= (int) $st['id'] ?>" <?= $staffId === (int) $st['id'] ? 'selected' : '' ?>>
            <?= e($st['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <span class="block text-[12px] font-bold text-ink-600 mb-2">۳. خدمت</span>
      <?php if ($services === []): ?>
        <p class="text-[12px] text-ink-400">
          هنوز خدمتی ثبت نشده.
          <a href="<?= e(url('panel/services/create')) ?>" class="text-accent font-semibold">یکی اضافه کن</a>.
        </p>
      <?php else: ?>
        <fieldset class="space-y-1.5">
          <legend class="sr-only">انتخاب خدمت</legend>
          <?php foreach ($services as $s): ?>
            <label class="pick block relative tap">
              <input type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>" class="sr-only"
                     <?= in_array((int) $s['id'], $serviceIds, true) ? 'checked' : '' ?>>
              <span class="pick-card flex items-center gap-2.5 rounded-xl px-3 py-2.5
                           transition-all duration-200 ease-out-soft cursor-pointer"
                    >
                <span class="pick-box w-5 h-5 shrink-0 rounded-md border-2 border-ink-300 grid place-items-center"
                      aria-hidden="true">
                  <?= icon('check', 'pick-tick w-3 h-3 opacity-0 transition-opacity duration-200') ?>
                </span>
                <span class="flex-1 min-w-0 text-[13px] font-bold text-ink-800 truncate"><?= e($s['name']) ?></span>
                <span class="text-[12px] text-ink-500 tabular-nums shrink-0">
                  <?= e(fa_num((int) $s['duration_minutes'])) ?> دقیقه
                </span>
              </span>
            </label>
          <?php endforeach; ?>
        </fieldset>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn-ink w-full">نمایش سانس‌های آزاد</button>
    <p class="text-[12px] text-ink-400 text-center">
      مدت محاسبه‌شده: <span class="tabular-nums"><?= e(fa_num($duration)) ?> دقیقه</span>
    </p>
  </form>

  <!-- گام ۲: سانس و مشتری -->
  <form method="post" action="<?= e(url('panel/bookings')) ?>" class="glass rounded-2xl p-4 sm:p-5 space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="date_y" value="<?= e((string) App\Support\Jalali::fromDateTime($date)[0]) ?>">
    <input type="hidden" name="date_m" value="<?= e((string) App\Support\Jalali::fromDateTime($date)[1]) ?>">
    <input type="hidden" name="date_d" value="<?= e((string) App\Support\Jalali::fromDateTime($date)[2]) ?>">
    <input type="hidden" name="staff_id" value="<?= $staffId !== null ? (int) $staffId : '' ?>">
    <?php foreach ($serviceIds as $sid): ?>
      <input type="hidden" name="service_ids[]" value="<?= (int) $sid ?>">
    <?php endforeach; ?>

    <div>
      <span class="block text-[12px] font-bold text-ink-600 mb-2">۴. سانس</span>

      <?php if ($slots === []): ?>
        <div class="rounded-xl py-8 px-4 text-center" style="background:var(--accent-soft)">
          <?= icon('calendar-x', 'w-7 h-7 mx-auto text-ink-400 mb-2') ?>
          <p class="text-[13px] font-bold text-ink-700">
            <?= $isClosed ? 'این روز سانس آزادی ندارد.' : 'اول خدمت را انتخاب کن.' ?>
          </p>
          <?php if ($isClosed): ?>
            <p class="text-[12px] text-ink-400 mt-1.5 leading-relaxed">
              یا سالن تعطیل است، یا همهٔ سانس‌ها پر شده‌اند.<br>
              تاریخ یا آرایشگر دیگری را امتحان کن.
            </p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <fieldset class="space-y-3">
          <legend class="sr-only">انتخاب سانس</legend>
          <?php foreach ($groups as $part => $times): ?>
            <?php if ($times === []) { continue; } ?>
            <div>
              <div class="flex items-center gap-2 mb-1.5">
                <span class="text-[12px] font-bold text-ink-500"><?= e($part) ?></span>
                <span class="flex-1 h-px" style="background:var(--line)" aria-hidden="true"></span>
                <span class="text-[12px] text-ink-400 tabular-nums"><?= e(fa_num(count($times))) ?></span>
              </div>
              <div class="grid grid-cols-3 sm:grid-cols-4 gap-1.5">
                <?php foreach ($times as $time): ?>
                  <label class="pick relative block tap">
                    <input type="radio" name="time" value="<?= e($time) ?>" required class="sr-only">
                    <span class="slot h-11 grid place-items-center rounded-xl text-[13px] font-bold
                                 text-ink-800 tabular-nums cursor-pointer transition-all duration-200"
                          style="background:var(--accent-soft)"><?= e(fa_time($time)) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </fieldset>
      <?php endif; ?>
    </div>

    <div class="h-px" style="background:var(--line)" aria-hidden="true"></div>

    <div class="space-y-2.5">
      <span class="block text-[12px] font-bold text-ink-600">۵. مشتری</span>
      <input type="tel" name="phone" inputmode="numeric" dir="ltr" required
             placeholder="۰۹۱۲۳۴۵۶۷۸۹" aria-label="شمارهٔ موبایل مشتری"
             class="w-full h-11 rounded-xl border border-ink-200 bg-transparent px-3 text-left text-[14px]
                    tabular-nums focus:outline-none focus:ring-2 focus:ring-accent">
      <input type="text" name="name" placeholder="نام (اختیاری)" aria-label="نام مشتری"
             class="w-full h-11 rounded-xl border border-ink-200 bg-transparent px-3 text-[13px]
                    focus:outline-none focus:ring-2 focus:ring-accent">
      <p class="text-[12px] text-ink-400 leading-relaxed">
        شماره لازم است تا پیامک تأیید برایش برود و بتواند نوبتش را پیگیری کند.
      </p>
    </div>

    <?php
    /*
     * همان نوار چسبیدهٔ صفحهٔ رزرو مشتری. اینجا هم لازم است: فرم بلند
     * است و آرایشگر پشت پیشخوان، با مشتری روبه‌رویش، نباید دنبال دکمه
     * بگردد.
     */
    echo App\Core\View::render('components.sticky-action', [
        'label' => 'ثبت نوبت',
        'disabled' => $slots === [],
        'hint' => $slots === [] ? 'این روز سانس آزادی ندارد' : null,
    ]);
    ?>
  </form>
</div>

<script>
// عوض شدن تاریخ، آرایشگر یا خدمت → سانس‌ها دوباره حساب شوند.
// بدون این هم دکمهٔ «نمایش سانس‌های آزاد» کار می‌کند.
(function () {
  var form = document.getElementById('pick-form');
  if (!form) return;
  form.addEventListener('change', function () { form.submit(); });
})();
</script>
