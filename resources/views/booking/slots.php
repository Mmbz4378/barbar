<?php
/**
 * گام زمان.
 *
 * @var array $salon
 * @var array $stepper
 * @var array $context
 * @var array $days
 * @var array $calendar
 * @var array $minMonth
 * @var string $selectedDate
 * @var ?string $selectedTime
 * @var string $selectedDateLabel
 * @var string[] $slots
 * @var ?array $liveStatus
 * @var string $formAction
 * @var string $baseUrl
 * @var bool $isFirstStep
 */
use App\Support\Clock;

include __DIR__ . '/_next.php';
$groups = ['صبح' => [], 'ظهر' => [], 'عصر' => [], 'شب' => []];
foreach ($slots as $time) {
    $groups[Clock::partOfDay($time)][] = $time;
}
$openPeriod = $selectedTime !== null ? Clock::partOfDay($selectedTime) : array_key_first(array_filter($groups));
$wide = true;
?>
<div class="wizard">
  <div class="wizard__main">
    <?= partial('stepper', ['steps' => $stepper]) ?>
    <?= partial('context-chips', ['chips' => $context]) ?>

    <?php if ($liveStatus !== null): ?>
      <div class="live" role="status">
        <?php if ($liveStatus['open']): ?>
          <span class="dot dot--live" aria-hidden="true"></span>
          <div class="live__text">
            <?php if ($liveStatus['freeNow'] > 0): ?>
              <span class="live__title success-text">الان باز است و <?= e(fa_num($liveStatus['freeNow'])) ?> <?= e(term('station')) ?> خالی دارد</span>
              <span class="live__sub">می‌توانی همین حالا بیایی یا یک ساعت را رزرو کنی.</span>
            <?php elseif ($liveStatus['waiting'] > 0): ?>
              <span class="live__title"><?= e(fa_num($liveStatus['waiting'])) ?> نفر در صف</span>
              <span class="live__sub"><?= e($liveStatus['waitLabel'] ?: 'رزرو آنلاین بدون انتظار در صف است.') ?></span>
            <?php else: ?>
              <span class="live__title">الان باز است</span>
              <span class="live__sub">کسی در صف نیست.</span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <span class="dot" aria-hidden="true"></span>
          <div class="live__text"><span class="live__title">الان بسته است</span><span class="live__sub">یکی از زمان‌های آزاد را رزرو کن.</span></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="step-head">
      <h1 class="step-head__title"><?= $isFirstStep ? 'چه روزی و چه ساعتی؟' : 'زمان نوبت را انتخاب کن' ?></h1>
      <p class="step-head__sub"><?= $isFirstStep ? 'روز و یکی از ساعت‌های آزاد را انتخاب کن؛ خدمت را در گام بعد انتخاب می‌کنی.' : 'فقط ساعت‌هایی نشان داده می‌شوند که برای همهٔ خدمات انتخابی وقت کافی دارند.' ?></p>
    </div>

    <?= partial('day-strip', ['days' => $days, 'linkFor' => static fn (string $g): string => url($baseUrl . '?date=' . $g), 'skeletonFor' => 'slots-area']) ?>

    <details class="mt-2 mb-4">
      <summary class="btn btn--link"><?= icon('calendar') ?> روز دیگری می‌خواهم</summary>
      <div class="mt-3">
        <?= partial('jalali-calendar', [
            'cal' => $calendar,
            'selected' => $selectedDate,
            'minMonth' => $minMonth,
            'linkFor' => static fn (string $g): string => url($baseUrl . '?date=' . $g . '&jy=' . $calendar['year'] . '&jm=' . $calendar['month']),
            'navFor' => static fn (int $y, int $m): string => url($baseUrl . '?date=' . $selectedDate . '&jy=' . $y . '&jm=' . $m),
        ]) ?>
      </div>
    </details>

    <section aria-labelledby="slots-title" id="slots-area" data-skeleton-region>
      <template data-skeleton-tpl><?= partial('skeleton', ['variant' => 'slots']) ?></template>
      <div class="spread mb-2">
        <h2 class="title-xs" id="slots-title">ساعت‌های آزاد <?= e($selectedDateLabel) ?></h2>
        <?php if ($slots !== []): ?><span class="text-sm muted"><?= e(fa_num(count($slots))) ?> ساعت</span><?php endif; ?>
      </div>

      <?php if ($slots === []): ?>
        <div class="card card--flat">
          <?= partial('empty-state', [
              'icon' => 'calendar-x',
              'title' => 'این روز ساعت آزادی ندارد',
              'text' => 'روز دیگری را از نوار بالا یا تقویم انتخاب کن' . (!empty($salon['phone']) ? '، یا برای هماهنگی با سالن تماس بگیر.' : '.'),
          ]) ?>
        </div>
      <?php else: ?>
        <form method="post" action="<?= e(url($formAction)) ?>" data-live-summary>
          <?= csrf_field() ?>
          <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
          <fieldset>
            <legend class="sr-only">انتخاب ساعت برای <?= e($selectedDateLabel) ?></legend>
            <div class="slot-groups">
              <?php foreach ($groups as $label => $times): if ($times === []) { continue; } ?>
                <details class="slot-group" <?= $label === $openPeriod ? 'open' : '' ?>>
                  <summary><?= e($label) ?> <span class="slot-group__count"><?= e(fa_num(count($times))) ?> ساعت</span><?= icon('chevron-down') ?></summary>
                  <div class="slot-grid">
                    <?php foreach ($times as $time): ?>
                      <label class="choice slot">
                        <input class="choice__input" type="radio" name="time" value="<?= e($time) ?>" required
                               data-label="<?= e($selectedDateLabel . '، ساعت ' . fa_time($time)) ?>" <?= $selectedTime === $time ? 'checked' : '' ?>>
                        <span class="choice__card"><?= e(fa_time($time)) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <div class="action-bar">
            <p class="action-bar__summary" role="status" aria-live="polite"><span data-summary data-empty="یک ساعت انتخاب کن">یک ساعت انتخاب کن</span></p>
            <button type="submit" class="btn btn--primary btn--lg btn--block" data-summary-submit><?= e($nextLabel) ?> <?= icon('chevron-end') ?></button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>
  <?php include __DIR__ . '/_aside.php'; ?>
</div>
