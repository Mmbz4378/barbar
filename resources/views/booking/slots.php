<?php
/**
 * @var array $salon
 * @var array $calendar
 * @var array $days
 * @var array $minMonth
 * @var string $selectedDate
 * @var string $selectedDateLabel
 * @var array $slots
 * @var array $liveStatus
 */

$base = 's/' . $salon['slug'];
?>

<?php if ($error = flash('error')): ?>
  <div role="alert"
       class="flex items-start gap-2 bg-red-50 text-red-800 text-sm rounded-xl px-4 py-3 mb-4 border border-red-100">
    <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<!--
  وضعیت زندهٔ صف — تمایز اصلی محصول.

  این اولین چیزی است که مشتری می‌بیند، چون جوابِ سؤالی است که واقعاً
  در ذهنش دارد: «الان برم یا شلوغه؟» رقبا این را ندارند چون دادهٔ
  لحظه‌ای صف را ندارند.
-->
<?php if ($liveStatus['open']): ?>
  <div class="rise glass rounded-2xl overflow-hidden mb-5 hairline-accent">
    <div class="px-4 py-3.5 flex items-center gap-3">
      <span class="relative flex w-2.5 h-2.5 shrink-0" aria-hidden="true">
        <span class="absolute inline-flex w-full h-full rounded-full bg-green-500 opacity-60 animate-ping"></span>
        <span class="relative inline-flex w-2.5 h-2.5 rounded-full bg-green-600"></span>
      </span>

      <div class="flex-1 min-w-0">
        <?php if ($liveStatus['freeNow'] > 0): ?>
          <p class="text-sm font-bold text-green-700">همین حالا آزاد است</p>
          <p class="text-[12px] text-ink-500 mt-0.5">
            <?= e(fa_num($liveStatus['freeNow'])) ?> صندلی خالی — می‌توانی همین الان بیایی
          </p>
        <?php elseif ($liveStatus['waiting'] === 0): ?>
          <p class="text-sm font-bold text-ink-900">باز است</p>
          <p class="text-[12px] text-ink-500 mt-0.5">کسی در صف نیست</p>
        <?php else: ?>
          <p class="text-sm font-bold text-ink-900">
            <?= e(fa_num($liveStatus['waiting'])) ?> نفر در صف
          </p>
          <p class="text-[12px] text-ink-500 mt-0.5">
            <?= e($liveStatus['waitLabel']) ?>
          </p>
        <?php endif; ?>
      </div>

      <span class="text-[12px] font-semibold text-ink-400 shrink-0">زنده</span>
    </div>
  </div>
<?php else: ?>
  <div class="rise glass rounded-2xl px-4 py-3.5 mb-5 flex items-center gap-3">
    <span class="w-2.5 h-2.5 rounded-full bg-ink-300 shrink-0" aria-hidden="true"></span>
    <div>
      <p class="text-sm font-bold text-ink-700">الان بسته است</p>
      <p class="text-[12px] text-ink-500 mt-0.5">می‌توانی یکی از زمان‌های آزاد را رزرو کنی</p>
    </div>
  </div>
<?php endif; ?>

<div class="rise rise-1 mb-4">
  <h1 class="text-[15px] font-extrabold text-ink-900 mb-1">انتخاب روز و ساعت</h1>
  <p class="text-[13px] text-ink-500">روز و یکی از ساعت‌های آزاد را انتخاب کن.</p>
</div>

<?php
/*
 * گام ۱ — روز.
 *
 * نوار افقی روزهای نزدیک جلوی چشم است، و تقویم کامل پشت یک دکمه.
 * تقریباً همهٔ رزروها برای همین چند روزند؛ تقویم ماهانه فقط وقتی لازم
 * می‌شود که کسی واقعاً ماه بعد را بخواهد.
 */
echo App\Core\View::render('components.day-strip', [
    'days' => $days,
    'linkFor' => static fn (string $g): string => url($base . '?date=' . $g),
]);
?>

<details class="mt-3 group">
  <summary class="tap inline-flex items-center gap-1.5 h-11 text-[13px] font-semibold
                  text-ink-600 cursor-pointer select-none list-none
                  focus-visible:outline-2 focus-visible:outline-accent rounded-lg">
    <?= icon('calendar', 'w-4 h-4') ?>
    روز دیگری می‌خواهم
  </summary>

  <div class="mt-3">
<?php
echo App\Core\View::render('components.jalali-calendar', [
    'cal' => $calendar,
    'selected' => $selectedDate,
    'minMonth' => $minMonth,
    'linkFor' => static fn (string $g): string => url(
        $base . '?date=' . $g . '&jy=' . $calendar['year'] . '&jm=' . $calendar['month']
    ),
    'navFor' => static fn (int $y, int $m): string => url(
        $base . '?date=' . $selectedDate . '&jy=' . $y . '&jm=' . $m
    ),
]);
?>
  </div>
</details>

<div class="mt-5 rise rise-2">
  <div class="flex items-baseline justify-between mb-2">
    <h2 class="card-title">
      سانس‌های آزاد — <?= e($selectedDateLabel) ?>
    </h2>
    <?php if (!empty($slots)): ?>
      <span class="text-[12px] text-ink-400 tabular-nums"><?= e(fa_num(count($slots))) ?> سانس</span>
    <?php endif; ?>
  </div>

  <?php if (empty($slots)): ?>
    <div class="glass rounded-2xl py-12 px-5 text-center">
      <?= icon('calendar-x', 'w-10 h-10 mx-auto text-ink-300 mb-3') ?>
      <p class="text-sm font-semibold text-ink-600 mb-1">این روز سانس آزادی ندارد</p>
      <p class="text-[12px] text-ink-400">روز دیگری را از نوار بالا انتخاب کنید.</p>
    </div>
  <?php else: ?>
    <form method="post" action="<?= e(url($base)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="date" value="<?= e($selectedDate) ?>">

      <fieldset>
        <legend class="sr-only">انتخاب ساعت برای <?= e($selectedDateLabel) ?></legend>

        <?php
        /*
         * ساعت‌ها را به صبح/ظهر/عصر گروه می‌کنیم.
         *
         * چرا: یک سالن ۱۲ ساعته با بازهٔ ۱۵ دقیقه‌ای، بیش از ۴۰ دکمه
         * می‌سازد. دیوارِ دکمه، انتخاب را سخت‌تر می‌کند نه آسان‌تر —
         * مشتری معمولاً اول می‌داند «عصر می‌آیم»، بعد ساعت دقیق را
         * انتخاب می‌کند.
         */
        /*
         * برش‌ها از App\Support\Clock می‌آید، نه از عددهای محلی. پیش‌تر
         * اینجا مرزِ «عصر» ساعت ۱۶ بود و هر چیزی بعد از آن — حتی ۲۱:۰۰ —
         * عصر شمرده می‌شد؛ در فارسی آن ساعت شب است.
         */
        $groups = ['صبح' => [], 'ظهر' => [], 'عصر' => [], 'شب' => []];
        foreach ($slots as $time) {
            $groups[App\Support\Clock::partOfDay($time)][] = $time;
        }
        $index = 0;
        $openPeriod = isset($selectedTime) && in_array($selectedTime,$slots,true) ? App\Support\Clock::partOfDay($selectedTime) : array_key_first(array_filter($groups));
        ?>

        <div class="space-y-4 mb-5">
          <?php foreach ($groups as $label => $times): ?>
            <?php if ($times === []) { continue; } ?>
            <details class="time-period" <?= $label === $openPeriod ? 'open' : '' ?>>
              <summary class="flex items-center gap-2 mb-2">
                <span class="font-semibold"><?= e($label) ?></span>
                <span class="flex-1 h-px" style="background:var(--line)" aria-hidden="true"></span>
                <span class="text-[12px] text-ink-400 tabular-nums"><?= e(fa_num(count($times))) ?> زمان آزاد</span>
              </summary>

              <div class="grid grid-cols-3 gap-2">
                <?php foreach ($times as $time): $index++; ?>
                  <label class="pick relative block tap rise rise-<?= min((int) ceil($index / 6), 5) ?>">
                    <input type="radio" name="time" value="<?= e($time) ?>" <?= ($selectedTime ?? null) === $time ? 'checked' : '' ?> required class="sr-only">
                    <span class="slot glass h-12 grid place-items-center rounded-xl
                                 text-sm font-bold text-ink-800 tabular-nums cursor-pointer
                                 transition-all duration-200 ease-out-soft hover:shadow-lift">
                      <?= e(fa_time($time)) ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <?php
      echo App\Core\View::render('components.sticky-action', [
          'label' => 'انتخاب خدمات',
          'hint' => 'یک ساعت را انتخاب کن',
      ]);
      ?>
    </form>
  <?php endif; ?>
</div>
