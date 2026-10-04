<?php
/**
 * تقویم شمسی ماهانه.
 *
 * @var array $cal        خروجی JalaliCalendar::month()
 * @var ?string $selected تاریخ میلادی انتخاب‌شده
 * @var callable $linkFor
 * @var callable $navFor
 * @var ?array $minMonth
 */
use App\Support\Jalali;
use App\Support\JalaliCalendar;

$minMonth = $minMonth ?? null;
$atMin = $minMonth !== null
    && ($cal['year'] < $minMonth['year'] || ($cal['year'] === $minMonth['year'] && $cal['month'] <= $minMonth['month']));
?>
<div class="calendar" role="group" aria-label="تقویم <?= e($cal['monthName'] . ' ' . fa_num($cal['year'])) ?>">
  <div class="calendar__head">
    <?php if ($atMin): ?>
      <span class="btn btn--icon btn--ghost" aria-hidden="true" style="visibility:hidden"></span>
    <?php else: ?>
      <a class="btn btn--icon btn--ghost" href="<?= e($navFor($cal['prev']['year'], $cal['prev']['month'])) ?>" aria-label="ماه قبل"><?= icon('chevron-start') ?></a>
    <?php endif; ?>
    <span class="calendar__title"><?= e($cal['monthName']) ?> <?= e(fa_num($cal['year'])) ?></span>
    <a class="btn btn--icon btn--ghost" href="<?= e($navFor($cal['next']['year'], $cal['next']['month'])) ?>" aria-label="ماه بعد"><?= icon('chevron-end') ?></a>
  </div>
  <div class="calendar__grid">
    <?php foreach (JalaliCalendar::WEEKDAY_INITIALS as $i => $initial): ?>
      <span class="calendar__weekday<?= $i === 6 ? ' calendar__weekday--weekend' : '' ?>" aria-hidden="true"><?= e($initial) ?></span>
    <?php endforeach; ?>
    <?php foreach ($cal['weeks'] as $week): foreach ($week as $cell): ?>
      <?php if ($cell === null): ?>
        <span aria-hidden="true"></span>
      <?php else:
          $isSelected = $selected !== null && $cell['gregorian'] === $selected;
          $aria = JalaliCalendar::WEEKDAY_NAMES[Jalali::weekday(new DateTimeImmutable($cell['gregorian']))] . ' ' . fa_num($cell['jday']) . ' ' . $cal['monthName']
              . ($cell['isToday'] ? '، امروز' : '') . (!$cell['available'] ? '، ' . ($cell['label'] ?: 'غیرقابل انتخاب') : '');
          $cls = 'calendar__day' . ($cell['isToday'] ? ' calendar__day--today' : '');
      ?>
        <?php if (!$cell['available']): ?>
          <button type="button" class="<?= $cls ?>" disabled aria-label="<?= e($aria) ?>"><?= e(fa_num($cell['jday'])) ?></button>
        <?php else: ?>
          <a class="<?= $cls ?>" href="<?= e($linkFor($cell['gregorian'])) ?>" aria-label="<?= e($aria) ?>" <?= $isSelected ? 'aria-current="date"' : '' ?>><?= e(fa_num($cell['jday'])) ?></a>
        <?php endif; ?>
      <?php endif; ?>
    <?php endforeach; endforeach; ?>
  </div>
</div>
