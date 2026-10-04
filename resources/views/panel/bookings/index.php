<?php
/**
 * فهرست رزروهای زمان‌دار، روز به روز.
 *
 * @var array $days   هر عضو: date, label, rows
 * @var array $counts شمارش به تفکیک وضعیت
 * @var DateTimeImmutable $from
 * @var DateTimeImmutable $to
 * @var string $prev
 * @var string $next
 * @var bool $isToday
 * @var bool $onlyMine
 * @var array $staffList
 */

use App\Support\JalaliCalendar;

/** برچسب و رنگ هر وضعیت. کلیدها با enum ستون status یکی‌اند. */
$statusMeta = [
    'pending'   => ['در انتظار تأیید', 'text-amber-700', 'bg-amber-50'],
    'confirmed' => ['تأییدشده',        'text-accent',    'bg-gold-50'],
    'queued'    => ['در صف',           'text-ink-700',   'bg-ink-100'],
    'in_chair'  => ['روی صندلی',       'text-ink-900',   'bg-ink-100'],
    'completed' => ['انجام‌شده',        'text-green-700', 'bg-green-50'],
    'cancelled' => ['لغوشده',          'text-red-700',   'bg-red-50'],
    'no_show'   => ['غیبت',            'text-ink-500',   'bg-ink-50'],
];

$upcoming = ($counts['pending'] ?? 0) + ($counts['confirmed'] ?? 0);
$totalRows = array_sum(array_map(static fn ($d) => count($d['rows']), $days));
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
  <div>
    <h1 class="page-title">رزروها</h1>
    <p class="text-[12px] text-ink-400 mt-0.5">
      <?= e(JalaliCalendar::humanDate($from)) ?> تا <?= e(JalaliCalendar::humanDate($to, true)) ?>
      <?php if ($onlyMine): ?> — فقط نوبت‌های خودت<?php endif; ?>
    </p>
  </div>

  <a href="<?= e(url('panel/bookings/new')) ?>" class="btn-accent metal h-11 text-[13px] px-4 order-last sm:order-none">
    <?= icon('plus', 'w-4 h-4') ?>
    رزرو جدید
  </a>

  <nav class="flex items-center gap-1.5" aria-label="جابه‌جایی بازه">
    <a href="<?= e(url('panel/bookings?from=' . $prev)) ?>"
       class="glass w-11 h-11 grid place-items-center rounded-xl text-ink-600 tap"
       aria-label="دو هفتهٔ قبل"><?= icon('chevron-start', 'w-4 h-4') ?></a>
    <?php if (!$isToday): ?>
      <a href="<?= e(url('panel/bookings')) ?>"
         class="glass h-11 grid place-items-center rounded-xl px-4 text-[12px] font-bold text-ink-700 tap">امروز</a>
    <?php endif; ?>
    <a href="<?= e(url('panel/bookings?from=' . $next)) ?>"
       class="glass w-11 h-11 grid place-items-center rounded-xl text-ink-600 tap"
       aria-label="دو هفتهٔ بعد"><?= icon('chevron-end', 'w-4 h-4') ?></a>
  </nav>
</div>

<dl class="grid grid-cols-3 gap-2 mb-5">
  <?php foreach ([
    ['رزرو پیشِ رو', $upcoming,                 'text-accent',   'bg-gold-50'],
    ['انجام‌شده',     $counts['completed'] ?? 0, 'text-green-700','bg-green-50'],
    ['لغو و غیبت',   ($counts['cancelled'] ?? 0) + ($counts['no_show'] ?? 0), 'text-ink-500', 'bg-ink-50'],
  ] as [$label, $value, $fg, $bg]): ?>
    <div class="<?= $bg ?> rounded-xl py-2.5 px-1 text-center">
      <dd class="text-xl font-extrabold <?= $fg ?> tabular-nums"><?= e(fa_num((int) $value)) ?></dd>
      <dt class="text-[12px] text-ink-500 mt-0.5"><?= e($label) ?></dt>
    </div>
  <?php endforeach; ?>
</dl>

<?php
/*
 * لغوهای خودِ آرایشگاه جدا نوشته می‌شود.
 *
 * وقتی با لغو مشتری در یک عدد جمع شود، بدترین خبر — «ما داریم به
 * مشتری بدقولی می‌کنیم» — زیر همان عدد پنهان می‌ماند.
 *
 * فقط وقتی نشان داده می‌شود که چیزی برای نشان دادن باشد؛ آرایشگاهی که
 * لغو ندارد نباید یک خط خالی ببیند.
 */
$bySalon = (int) ($counts['cancelled_by']['salon'] ?? 0);
$byCustomer = (int) ($counts['cancelled_by']['customer'] ?? 0);
?>
<?php if ($bySalon > 0 || $byCustomer > 0): ?>
  <p class="text-[12px] text-ink-500 -mt-3 mb-5 leading-relaxed">
    از لغوها،
    <span class="font-bold text-ink-700"><?= e(fa_num($bySalon)) ?></span> مورد را آرایشگاه لغو کرده
    و <span class="font-bold text-ink-700"><?= e(fa_num($byCustomer)) ?></span> مورد را مشتری.
  </p>
<?php endif; ?>

<?php if ($totalRows === 0): ?>
  <div class="glass rounded-2xl p-10 text-center">
    <span class="inline-grid place-items-center w-14 h-14 rounded-2xl mb-3"
          style="background:var(--accent-soft);color:var(--accent)">
      <?= icon('calendar-x', 'w-7 h-7') ?>
    </span>
    <p class="text-sm font-bold text-ink-700">در این بازه رزروی ثبت نشده.</p>
    <p class="text-[12px] text-ink-400 mt-1.5 leading-relaxed">
      لینک صفحهٔ سالن را برای مشتری‌ها بفرست تا بتوانند خودشان سانس بگیرند.<br>
      ساعت کاری و طول سانس را در <a class="text-accent font-semibold" href="<?= e(url('panel/settings')) ?>">تنظیمات</a> مشخص کن.
    </p>
    <a href="<?= e(url('panel/bookings/new')) ?>" class="btn-ink mt-4 inline-flex">
      <?= icon('plus', 'w-4 h-4') ?>
      رزرو دستی ثبت کن
    </a>
  </div>
<?php else: ?>
  <div class="space-y-4">
    <?php foreach ($days as $day): ?>
      <?php if (empty($day['rows'])) { continue; } ?>
      <section class="glass rounded-2xl overflow-hidden">
        <h2 class="flex items-baseline gap-2 px-4 py-3 border-b" style="border-color:var(--line)">
          <?php $full = JalaliCalendar::humanDate($day['date']); ?>
          <span class="text-[13px] font-extrabold text-ink-900"><?= e($day['label']) ?></span>
          <?php /* دورتر از پس‌فردا، برچسب نسبی خودش همان تاریخ است — دوبار ننویسیم */ ?>
          <?php if ($day['label'] !== $full): ?>
            <span class="text-[12px] text-ink-400"><?= e($full) ?></span>
          <?php endif; ?>
          <span class="ms-auto text-[12px] font-bold text-ink-500 tabular-nums">
            <?= e(fa_num(count($day['rows']))) ?> نوبت
          </span>
        </h2>

        <ul class="divide-y divide-ink-100">
          <?php foreach ($day['rows'] as $r): ?>
            <?php
              [$label, $fg, $bg] = $statusMeta[$r['status']] ?? ['—', 'text-ink-500', 'bg-ink-50'];
              $off = in_array($r['status'], ['cancelled', 'no_show'], true);
            ?>
            <li class="flex items-center gap-3 px-4 py-3 <?= $off ? 'opacity-60' : '' ?>">
              <time class="shrink-0 text-[15px] font-extrabold text-ink-900 tabular-nums <?= $off ? 'line-through' : '' ?>"
                    datetime="<?= e((string) $r['scheduled_at']) ?>">
                <?= e(fa_num(substr((string) $r['scheduled_at'], 11, 5))) ?>
              </time>

              <span class="w-1 self-stretch rounded-full shrink-0"
                    style="background:<?= e($r['staff_color'] ?: 'var(--line)') ?>" aria-hidden="true"></span>

              <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-ink-800 truncate"><?= e($r['customer_name']) ?></p>
                <p class="text-[12px] text-ink-400 truncate">
                  <?= e($r['staff_name'] ?? 'بدون آرایشگر مشخص') ?>
                  <?php if (!empty($r['customer_phone'])): ?>
                    · <span class="ltr tabular-nums"><?= e(fa_num($r['customer_phone'])) ?></span>
                  <?php endif; ?>
                </p>
              </div>

              <span class="shrink-0 text-[12px] font-bold rounded-full px-2.5 py-1 <?= $fg ?> <?= $bg ?>">
                <?= e($label) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
