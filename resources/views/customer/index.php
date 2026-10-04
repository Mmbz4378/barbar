<?php
/**
 * @var string $phone
 * @var array $upcoming
 * @var array $past
 */

use App\Support\Clock;
use App\Support\JalaliCalendar;

$labels = [
    'confirmed' => ['رزرو شده', 'text-ink-700'],
    'queued'    => ['در صف',    'text-amber-700'],
    'in_chair'  => ['روی صندلی', 'text-green-700'],
    'completed' => ['انجام شد', 'text-ink-500'],
    'cancelled' => ['لغو شده',  'text-ink-400'],
    'no_show'   => ['غیبت',     'text-ink-400'],
];

/** یک نوبت، در قالب یک کارت. */
$card = static function (array $a, bool $cancellable) use ($labels): void {
    $when = $a['scheduled_at'] ?: $a['queued_at'];
    [$label, $tone] = $labels[$a['status']] ?? [$a['status'], 'text-ink-500'];
    $date = new DateTimeImmutable($when);
    ?>
    <li class="glass appointment-card rounded-2xl px-4 py-3.5" data-appointment-group="<?= $a['status']==='completed'?'completed':'other' ?>">
      <div class="flex items-baseline gap-2 mb-1.5">
        <span class="text-[14px] font-extrabold text-ink-900 flex-1 min-w-0 truncate">
          <?= e($a['salon_name']) ?>
        </span>
        <span class="text-[12px] font-bold <?= $tone ?> shrink-0"><?= e($label) ?></span>
      </div>

      <p class="appointment-date text-[13px] text-ink-700 tabular-nums"><?= icon('calendar-days') ?>
        <?= e(JalaliCalendar::relativeDate($date)) ?>، ساعت <?= e(Clock::hm($date->format('H:i'))) ?>
      </p>

      <?php if (!empty($a['service_names'])): ?>
        <p class="text-[12px] text-ink-500 mt-1"><?= e($a['service_names']) ?></p>
      <?php endif; ?>

      <div class="flex items-center gap-3 mt-2 text-[12px] text-ink-400">
        <?php if (!empty($a['staff_name'])): ?>
          <span><?= e($a['staff_name']) ?></span>
        <?php endif; ?>
        <?php if ((int) $a['total_price'] > 0): ?>
          <span class="tabular-nums"><?= e(toman((int) $a['total_price'])) ?></span>
        <?php endif; ?>
      </div>

      <div class="flex items-center gap-2 mt-3">
        <a href="<?= e(url('q/' . $a['public_token'])) ?>"
           class="flex-1 h-11 rounded-xl grid place-items-center text-[12px] font-semibold
                  text-ink-700 border border-ink-200 tap">
          کارت نوبت
        </a>

        <?php if ($cancellable): ?>
          <form method="post" action="<?= e(url('me/' . (int) $a['id'] . '/cancel')) ?>" class="flex-1"
                onsubmit="return confirm('این نوبت لغو شود؟')">
            <?= csrf_field() ?>
            <button type="submit"
                    class="w-full h-11 rounded-xl text-[12px] font-semibold text-red-700 border border-red-100 bg-red-50 tap">
              لغو نوبت
            </button>
          </form>
        <?php endif; ?>
      </div>
      <?php if ($a['status']==='completed'): ?>
      <details class="mt-4"><summary>ثبت نظر دربارهٔ این مراجعه</summary><form method="post" action="<?= e(url('me/'.$a['id'].'/review')) ?>" class="space-y-3 mt-3"><?= csrf_field() ?>
      <label class="block">امتیاز<select name="rating" required class="w-full border rounded-xl p-3"><option value="">انتخاب کن</option><?php for($rating=5;$rating>=1;$rating--): ?><option value="<?= $rating ?>"><?= e(fa_num($rating)) ?> از ۵</option><?php endfor; ?></select></label>
      <label class="block">تجربهٔ شما<textarea name="comment" maxlength="500" class="w-full border rounded-xl p-3"></textarea></label><button class="btn-ink">ارسال برای بررسی</button></form></details>
      <?php endif; ?>
    </li>
    <?php
};
?>

<p class="text-[12px] text-ink-400 tabular-nums mb-5" dir="ltr"><?= e(fa_num($phone)) ?></p>

<div class="appointment-filters" data-appointment-filters hidden role="group" aria-label="نمایش نوبت‌ها"><button type="button" data-appointment-filter="upcoming" aria-pressed="true">پیش‌رو <span><?= e(fa_num(count($upcoming))) ?></span></button><button type="button" data-appointment-filter="completed" aria-pressed="false">انجام‌شده</button><button type="button" data-appointment-filter="other" aria-pressed="false">سایر سوابق</button></div>
<p class="sr-only" data-appointment-status role="status" aria-live="polite"></p>
<div class="empty-state glass" data-appointment-empty hidden><?= icon('calendar-days','empty-state__icon') ?><h2 class="card-title">نوبتی در این بخش نیست</h2><a class="text-action" href="<?= e(url('discover')) ?>">پیداکردن سالن و رزرو</a></div>
<section class="mb-7" data-appointment-section="upcoming">
  <h2 class="card-title mb-2.5">نوبت‌های پیش رو</h2>

  <?php if (empty($upcoming)): ?>
    <div class="glass rounded-2xl py-10 px-5 text-center">
      <?= icon('calendar-days', 'w-9 h-9 mx-auto text-ink-300 mb-3') ?>
      <p class="text-sm font-semibold text-ink-600 mb-1">نوبتی در پیش نداری</p>
      <p class="text-[12px] text-ink-400">وقت بعدی‌ات را از میان سالن‌ها انتخاب کن.</p><a class="btn-accent mt-4" href="<?= e(url('discover')) ?>">پیداکردن سالن</a>
    </div>
  <?php else: ?>
    <ul class="space-y-2.5">
      <?php foreach ($upcoming as $a) { $card($a, in_array($a['status'], ['confirmed', 'queued'], true)); } ?>
    </ul>
  <?php endif; ?>
</section>

<?php if (!empty($past)): ?>
  <section data-appointment-section="past">
    <h2 class="card-title mb-2.5">سابقه</h2>
    <ul class="space-y-2.5">
      <?php foreach ($past as $a) { $card($a, false); } ?>
    </ul>
  </section>
<?php endif; ?>
