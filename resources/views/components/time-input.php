<?php
/**
 * انتخابگر ساعت — فارسی، ۲۴ساعته (جایگزین input[type=time] که زبانِ
 * مرورگر را می‌گیرد، نه زبان صفحه).
 *
 * @var string $name       پیشوند؛ فیلدهای {name}_h و {name}_m
 * @var ?string $value     «HH:MM»
 * @var string $label      برای صفحه‌خوان
 * @var int|null $minuteStep
 * @var bool|null $allowEmpty
 */
use App\Support\Clock;

$label = $label ?? 'زمان';
$value = $value ?? null;
$minuteStep = $minuteStep ?? 5;
$allowEmpty = $allowEmpty ?? false;
$hasValue = $value !== null && $value !== '';
$curH = $hasValue ? (int) substr((string) $value, 0, 2) : null;
$curM = $hasValue ? (int) substr((string) $value, 3, 2) : null;

// مقدار ذخیره‌شدهٔ غیرمضربِ گام هم در فهرست بماند، وگرنه ذخیرهٔ بعدی بی‌صدا عوضش می‌کند
$minutes = Clock::minuteOptions($minuteStep);
if ($curM !== null && !isset($minutes[$curM])) {
    $minutes[$curM] = fa_num(sprintf('%02d', $curM));
    ksort($minutes);
}
$id = $id ?? $name;
// نام آرایه‌ای (hours[0][opens]) با پسوند _h خراب می‌شود؛ پس نام کامل هر فهرست جدا قابل تعیین است
$nameH = $nameH ?? $name . '_h';
$nameM = $nameM ?? $name . '_m';
?>
<span class="time-control">
  <select class="select select--compact" id="<?= e($id) ?>_h" name="<?= e($nameH) ?>" aria-label="ساعتِ <?= e($label) ?>">
    <?php if ($allowEmpty): ?><option value="" <?= $hasValue ? '' : 'selected' ?>>—</option><?php endif; ?>
    <?php foreach (Clock::hourOptions() as $h => $text): ?>
      <option value="<?= $h ?>" <?= $curH === $h ? 'selected' : '' ?>><?= e($text) ?></option>
    <?php endforeach; ?>
  </select>
  <span class="time-control__sep" aria-hidden="true">:</span>
  <select class="select select--compact" id="<?= e($id) ?>_m" name="<?= e($nameM) ?>" aria-label="دقیقهٔ <?= e($label) ?>">
    <?php if ($allowEmpty): ?><option value="" <?= $hasValue ? '' : 'selected' ?>>—</option><?php endif; ?>
    <?php foreach ($minutes as $m => $text): ?>
      <option value="<?= $m ?>" <?= $curM === $m ? 'selected' : '' ?>><?= e($text) ?></option>
    <?php endforeach; ?>
  </select>
</span>
