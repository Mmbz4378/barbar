<?php
/**
 * انتخابگر تاریخ شمسی.
 *
 * جایگزین <input type="date"> است. آن ورودی تقویم **میلادی** باز می‌کند
 * و روی مرورگر انگلیسی «mm/dd/yyyy» می‌نویسد — در برنامه‌ای که همه‌جایش
 * شمسی است، کاربر باید در ذهنش تبدیل کند.
 *
 * سه <select>: روز، ماه، سال. تبدیل به میلادی سمت سرور انجام می‌شود
 * (jalali_date_from_request) تا بدون جاوااسکریپت هم کار کند.
 *
 * ورودی‌ها:
 *   $name   پیشوند؛ سه فیلد $name_y و $name_m و $name_d ساخته می‌شود
 *   $value  تاریخ میلادی «Y-m-d» یا null برای امروز
 *   $label  برچسب برای صفحه‌خوان
 *   $years  بازهٔ سال‌ها نسبت به امسال، پیش‌فرض [-1, +2]
 */

use App\Support\Jalali;
use App\Support\JalaliCalendar;

/** @var string $name */
/** @var string $label */
$label = $label ?? 'تاریخ';
$value = $value ?? null;
$years = $years ?? [-1, 2];

$base = $value !== null && $value !== ''
    ? new DateTimeImmutable($value)
    : new DateTimeImmutable('today');
[$curY, $curM, $curD] = Jalali::fromDateTime($base);

[$thisY] = Jalali::fromDateTime(new DateTimeImmutable('today'));

$cls = 'h-11 px-2 rounded-lg border border-ink-200 bg-transparent text-[13px] tabular-nums
        appearance-none cursor-pointer focus:outline-none focus:ring-2 focus:ring-accent';
?>
<span class="date-control inline-flex items-center gap-1.5">
  <select id="<?= e($name) ?>_d" name="<?= e($name) ?>_d" aria-label="روزِ <?= e($label) ?>" class="<?= $cls ?> text-center">
    <?php for ($d = 1; $d <= 31; $d++): ?>
      <option value="<?= $d ?>" <?= $curD === $d ? 'selected' : '' ?>><?= e(fa_num($d)) ?></option>
    <?php endfor; ?>
  </select>

  <select id="<?= e($name) ?>_m" name="<?= e($name) ?>_m" aria-label="ماهِ <?= e($label) ?>" class="<?= $cls ?>">
    <?php foreach (JalaliCalendar::MONTHS as $m => $mName): ?>
      <option value="<?= $m ?>" <?= $curM === $m ? 'selected' : '' ?>><?= e($mName) ?></option>
    <?php endforeach; ?>
  </select>

  <select id="<?= e($name) ?>_y" name="<?= e($name) ?>_y" aria-label="سالِ <?= e($label) ?>" class="<?= $cls ?> text-center">
    <?php for ($y = $thisY + $years[0]; $y <= $thisY + $years[1]; $y++): ?>
      <option value="<?= $y ?>" <?= $curY === $y ? 'selected' : '' ?>><?= e(fa_num($y)) ?></option>
    <?php endfor; ?>
  </select>
</span>
