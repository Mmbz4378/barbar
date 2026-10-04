<?php
/**
 * انتخابگر تاریخ شمسی (سه فهرست؛ تبدیل در سرور).
 *
 * @var string $name   پیشوند؛ {name}_y، {name}_m، {name}_d
 * @var ?string $value تاریخ میلادی Y-m-d یا null برای امروز
 * @var string|null $label
 * @var array|null $years  بازهٔ سال نسبت به امسال
 */
use App\Support\Jalali;
use App\Support\JalaliCalendar;

$label = $label ?? 'تاریخ';
$years = $years ?? [-1, 2];
$base = !empty($value) ? new DateTimeImmutable($value) : new DateTimeImmutable('today');
[$curY, $curM, $curD] = Jalali::fromDateTime($base);
[$thisY] = Jalali::fromDateTime(new DateTimeImmutable('today'));
$id = $id ?? $name;
?>
<span class="date-control">
  <select class="select select--compact" id="<?= e($id) ?>_d" name="<?= e($name) ?>_d" aria-label="روزِ <?= e($label) ?>">
    <?php for ($d = 1; $d <= 31; $d++): ?><option value="<?= $d ?>" <?= $curD === $d ? 'selected' : '' ?>><?= e(fa_num($d)) ?></option><?php endfor; ?>
  </select>
  <select class="select select--compact" id="<?= e($id) ?>_m" name="<?= e($name) ?>_m" aria-label="ماهِ <?= e($label) ?>">
    <?php foreach (JalaliCalendar::MONTHS as $m => $mName): ?><option value="<?= $m ?>" <?= $curM === $m ? 'selected' : '' ?>><?= e($mName) ?></option><?php endforeach; ?>
  </select>
  <select class="select select--compact" id="<?= e($id) ?>_y" name="<?= e($name) ?>_y" aria-label="سالِ <?= e($label) ?>">
    <?php for ($y = $thisY + $years[0]; $y <= $thisY + $years[1]; $y++): ?><option value="<?= $y ?>" <?= $curY === $y ? 'selected' : '' ?>><?= e(fa_num($y)) ?></option><?php endfor; ?>
  </select>
</span>
