<?php
/**
 * تقویم شمسی — قابل استفاده در صفحهٔ رزرو مشتری و پنل سالن.
 *
 * ورودی‌ها:
 *   $cal          خروجی JalaliCalendar::month()
 *   $selected     تاریخ انتخاب‌شده (میلادی Y-m-d) یا null
 *   $linkFor      callable(string $gregorian): string  — آدرس هر روز
 *   $navFor       callable(int $y, int $m): string     — آدرس ماه قبل/بعد
 *   $minMonth     ['year'=>int,'month'=>int] یا null — پیش از آن، دکمهٔ «قبل» غیرفعال
 *
 * قاعده‌های رعایت‌شده:
 *   • خانه‌های روز ۴۴×۴۴ پیکسل — حداقل استاندارد لمسی
 *   • فاصلهٔ ≥۸ پیکسل بین خانه‌ها
 *   • روز غیرقابل‌انتخاب <button disabled> است، نه <a> بی‌اثر —
 *     تا صفحه‌خوان و کیبورد هم بفهمند
 *   • «امروز» فقط با رنگ مشخص نمی‌شود؛ حلقهٔ دور خانه هم دارد
 *   • هر خانه aria-label کامل فارسی دارد، چون «۲» به‌تنهایی بی‌معنی است
 */

use App\Support\JalaliCalendar;
use App\Support\Jalali;

/** @var array $cal */
/** @var ?string $selected */
/** @var callable $linkFor */
/** @var callable $navFor */
$minMonth = $minMonth ?? null;

$atMin = $minMonth !== null
    && ($cal['year'] < $minMonth['year']
        || ($cal['year'] === $minMonth['year'] && $cal['month'] <= $minMonth['month']));
?>
<div class="glass rounded-2xl overflow-hidden rise rise-1" role="group"
     aria-label="تقویم — <?= e($cal['monthName']) ?> <?= e(fa_num($cal['year'])) ?>">

  <div class="flex items-center justify-between px-2 py-2 border-b" style="border-color:var(--line)">
    <?php if ($atMin): ?>
      <span class="w-11 h-11 min-w-[44px] min-h-[44px]" aria-hidden="true"></span>
    <?php else: ?>
      <a href="<?= e($navFor($cal['prev']['year'], $cal['prev']['month'])) ?>"
         class="w-11 h-11 min-w-[44px] min-h-[44px] grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100
                focus-visible:outline-2 focus-visible:outline-accent transition-colors cursor-pointer"
         aria-label="ماه قبل">
        <?= icon('chevron-start', 'w-5 h-5') ?>
      </a>
    <?php endif; ?>

    <h2 class="card-title">
      <?= e($cal['monthName']) ?> <?= e(fa_num($cal['year'])) ?>
    </h2>

    <a href="<?= e($navFor($cal['next']['year'], $cal['next']['month'])) ?>"
       class="w-11 h-11 min-w-[44px] min-h-[44px] grid place-items-center rounded-xl text-ink-500 hover:bg-ink-100
              focus-visible:outline-2 focus-visible:outline-accent transition-colors cursor-pointer"
       aria-label="ماه بعد">
      <?= icon('chevron-end', 'w-5 h-5') ?>
    </a>
  </div>

  <div class="p-2">
    <div class="grid grid-cols-7 gap-1 mb-1" aria-hidden="true">
      <?php foreach (JalaliCalendar::WEEKDAY_INITIALS as $i => $initial): ?>
        <div class="h-7 grid place-items-center text-[12px] font-bold
                    <?= $i >= 5 ? 'text-accent' : 'text-ink-400' ?>">
          <?= e($initial) ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-7 gap-1">
      <?php foreach ($cal['weeks'] as $week): ?>
        <?php foreach ($week as $cell): ?>

          <?php if ($cell === null): ?>
            <span class="h-11 min-h-[44px]"></span>

          <?php else:
              $isSelected = $selected !== null && $cell['gregorian'] === $selected;
              $aria = JalaliCalendar::WEEKDAY_NAMES[Jalali::weekday(new DateTimeImmutable($cell['gregorian']))]
                  . ' ' . fa_num($cell['jday']) . ' ' . $cal['monthName']
                  . ($cell['isToday'] ? '، امروز' : '')
                  . ($cell['available'] ? '' : '، بدون وقت آزاد')
                  . ($cell['label'] !== '' ? '، ' . $cell['label'] : '');
          ?>

            <?php if (!$cell['available']): ?>
              <button type="button" disabled aria-label="<?= e($aria) ?>"
                      class="h-11 min-h-[44px] rounded-xl text-sm tabular-nums cursor-not-allowed
                             text-ink-300 <?= $cell['isToday'] ? 'ring-1 ring-ink-200' : '' ?>">
                <?= e(fa_num($cell['jday'])) ?>
              </button>
            <?php else: ?>
              <a href="<?= e($linkFor($cell['gregorian'])) ?>"
                 aria-label="<?= e($aria) ?>"
                 <?= $isSelected ? 'aria-current="date"' : '' ?>
                 class="h-11 min-h-[44px] grid place-items-center rounded-xl text-sm tabular-nums font-semibold
                        transition-colors cursor-pointer
                        focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-accent
                        <?php if ($isSelected): ?>
                          metal
                        <?php elseif ($cell['isToday']): ?>
                          ring-accent text-accent font-extrabold hover:bg-accent-soft
                        <?php elseif ($cell['isWeekend']): ?>
                          text-accent hover:bg-accent-soft
                        <?php else: ?>
                          text-ink-700 hover:bg-ink-100
                        <?php endif; ?>">
                <?= e(fa_num($cell['jday'])) ?>
              </a>
            <?php endif; ?>

          <?php endif; ?>

        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
