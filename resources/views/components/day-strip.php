<?php
/**
 * نوار افقی روزهای نزدیک.
 *
 * جایگزین تقویم ماهانه به‌عنوان انتخاب پیش‌فرض. تقویم ماه سی خانه
 * نشان می‌داد که بیشترشان گذشته و خاکستری بودند — روی موبایل یک صفحهٔ
 * کامل اسکرول تا رسیدن به ساعت‌ها.
 *
 * شمارِ سانس آزاد روی هر روز می‌آید چون تصمیم را عوض می‌کند: «۱ سانس»
 * یعنی عجله کن، «۱۲ سانس» یعنی خیالت راحت است. روزِ پر هم نشان داده
 * می‌شود ولی خاموش — مشتری باید بداند سالن آن روز پر است، نه اینکه
 * روز اصلاً وجود نداشته باشد.
 *
 * @var array<int,array{date:string,label:string,day:string,free:int,available:bool,selected:bool}> $days
 * @var callable(string):string $linkFor
 */
?>
<div class="-mx-5 px-5 overflow-x-auto no-scrollbar" role="group" aria-label="انتخاب روز">
  <div class="flex gap-2 pb-1 w-max">
    <?php foreach ($days as $d): ?>
      <?php $box = 'shrink-0 w-[4.5rem] rounded-2xl px-2 py-2.5 text-center'; ?>

      <?php if ($d['available'] || $d['selected']): ?>
        <a href="<?= e($linkFor($d['date'])) ?>"
           class="tap day-chip <?= $d['selected'] ? 'day-chip-on' : '' ?> <?= $box ?>
                  transition-all duration-200 ease-out-soft
                  focus-visible:outline-2 focus-visible:outline-accent"
           <?= $d['selected'] ? 'aria-current="date"' : '' ?>>
          <span class="block text-[13px] font-bold leading-tight"><?= e($d['label']) ?></span>
          <span class="block text-lg font-extrabold tabular-nums leading-tight mt-0.5"><?= e($d['day']) ?></span>
          <span class="day-chip-note block text-[12px] mt-1 tabular-nums
                       <?= $d['selected'] ? '' : 'text-ink-400' ?>">
            <?= e(fa_num((string) $d['free'])) ?> سانس
          </span>
        </a>
      <?php else: ?>
        <div class="day-chip-off <?= $box ?>"
             aria-label="<?= e($d['label']) ?> — بدون سانس آزاد">
          <span class="block text-[13px] font-bold leading-tight"><?= e($d['label']) ?></span>
          <span class="block text-lg font-extrabold tabular-nums leading-tight mt-0.5"><?= e($d['day']) ?></span>
          <span class="block text-[12px] mt-1">پر</span>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
