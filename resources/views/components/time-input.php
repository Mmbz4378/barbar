<?php
/**
 * انتخابگر ساعت — فارسی، ۲۴ساعته.
 *
 * جایگزین <input type="time"> است. آن ورودی را مرورگر بر اساس زبانِ
 * خودش رندر می‌کند، نه زبان صفحه، و روی یک دستگاه انگلیسی «09:00 AM»
 * نشان می‌داد.
 *
 * دو <select> است نه یک فیلد متنی: روی موبایل، چرخ انتخاب بومیِ سیستم
 * بالا می‌آید که از تایپ کردن ساعت راحت‌تر است — همان چیزی که ورودی
 * بومی خوب انجام می‌داد و نمی‌خواهیم از دستش بدهیم.
 *
 * ورودی‌ها:
 *   $name      پیشوند نام فیلدها؛ دو فیلد $name_h و $name_m ساخته می‌شود
 *   $value     «HH:MM» یا «HH:MM:SS» یا null
 *   $label     متن برچسب برای صفحه‌خوان
 *   $minuteStep گام دقیقه (پیش‌فرض ۵)
 *   $allowEmpty اگر true، گزینهٔ «—» هم می‌آید (برای استراحتِ اختیاری)
 *   $compact   نسخهٔ کوچک‌تر
 */

use App\Support\Clock;

/** @var string $name */
/** @var ?string $value */
/** @var string $label */
$label = $label ?? 'زمان';
$value = $value ?? null;
$minuteStep = $minuteStep ?? 5;
$allowEmpty = $allowEmpty ?? false;
$compact = $compact ?? false;

$hasValue = $value !== null && $value !== '';
$curH = $hasValue ? (int) substr((string) $value, 0, 2) : null;
$curM = $hasValue ? (int) substr((string) $value, 3, 2) : null;

/*
 * اگر دقیقهٔ ذخیره‌شده مضرب گام نباشد (مثلاً ۰۹:۰۷ با گام ۵)، در فهرست
 * نیست و مرورگر بی‌صدا گزینهٔ اول را انتخاب می‌کند — یعنی ذخیرهٔ بعدی،
 * داده را عوض می‌کند بی‌آنکه کسی دست زده باشد. پس همان مقدار را هم به
 * فهرست اضافه می‌کنیم.
 */
$minutes = Clock::minuteOptions($minuteStep);
if ($curM !== null && !isset($minutes[$curM])) {
    $minutes[$curM] = App\Support\Jalali::toPersianDigits(sprintf('%02d', $curM));
    ksort($minutes);
}

/*
 * ارتفاع در هر دو حالت ۴۴ پیکسل است، نه فقط در حالت عادی.
 *
 * نسخهٔ فشرده اول ۳۶ پیکسل بود و روی موبایل ۳۰×۳۶ درمی‌آمد — کمتر از
 * حداقل استاندارد لمسی. صاحب سالن همین‌ها را با انگشت شست تنظیم
 * می‌کند؛ چند پیکسل صرفه‌جویی در ارتفاع، ارزش یک کنترلِ لغزنده را
 * ندارد. فشرده بودن حالا فقط در اندازهٔ قلم و فاصله‌هاست.
 */
$box = $compact
    ? 'h-11 text-[12px] px-1 min-w-[2.5rem]'
    : 'h-11 text-[13px] px-1.5 min-w-[2.75rem]';
$cls = "{$box} rounded-lg border border-ink-200 bg-transparent tabular-nums
        text-center appearance-none cursor-pointer
        focus:outline-none focus:ring-2 focus:ring-accent";
?>
<span class="time-control inline-flex items-center gap-1" dir="ltr">
  <select id="<?= e($name) ?>_h" name="<?= e($name) ?>_h" aria-label="ساعتِ <?= e($label) ?>" class="<?= $cls ?>">
    <?php if ($allowEmpty): ?>
      <option value="" <?= $hasValue ? '' : 'selected' ?>>—</option>
    <?php endif; ?>
    <?php foreach (Clock::hourOptions() as $h => $text): ?>
      <option value="<?= $h ?>" <?= $curH === $h ? 'selected' : '' ?>><?= e($text) ?></option>
    <?php endforeach; ?>
  </select>

  <span class="text-ink-400 text-[12px] select-none" aria-hidden="true">:</span>

  <select id="<?= e($name) ?>_m" name="<?= e($name) ?>_m" aria-label="دقیقهٔ <?= e($label) ?>" class="<?= $cls ?>">
    <?php if ($allowEmpty): ?>
      <option value="" <?= $hasValue ? '' : 'selected' ?>>—</option>
    <?php endif; ?>
    <?php foreach ($minutes as $m => $text): ?>
      <option value="<?= $m ?>" <?= $curM === $m ? 'selected' : '' ?>><?= e($text) ?></option>
    <?php endforeach; ?>
  </select>
</span>
