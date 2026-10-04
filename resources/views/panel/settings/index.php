<?php
/** @var array $salon
 * @var array $hours
 * @var array $weekdayNames
 * @var array $holidays
 * @var int $currentJalaliYear
 */
?>
<h1 class="page-title mb-2">تنظیمات سالن</h1><p class="text-ink-500 mb-5">بخش مورد نظر را انتخاب کن؛ تغییرات هر بخش جدا ذخیره می‌شود.</p><div data-settings-tabs></div>

<div class="grid lg:grid-cols-2 gap-5">
  <div class="glass rounded-2xl p-4 sm:p-5">
    <h2 class="card-title mb-4">مشخصات سالن</h2>
    <!-- enctype لازم است، وگرنه فایل اصلاً به سرور نمی‌رسد و
         $_FILES خالی می‌ماند بدون هیچ خطایی. -->
    <form method="post" action="<?= e(url('panel/settings/profile')) ?>"
          enctype="multipart/form-data" class="space-y-3">
      <?= csrf_field() ?>

      <?php $logoUrl = salon_logo_url($salon['logo_file'] ?? null); ?>
      <div>
        <span class="block text-[12px] font-bold text-ink-600 mb-2">لوگو</span>
        <div class="flex items-center gap-3">
          <span class="w-16 h-16 shrink-0 rounded-2xl grid place-items-center overflow-hidden
                       <?= $logoUrl === null ? 'metal' : '' ?>"
                style="<?= $logoUrl === null ? '' : 'background:var(--accent-soft)' ?>">
            <?php if ($logoUrl !== null): ?>
              <img src="<?= e($logoUrl) ?>" alt="لوگوی <?= e($salon['name']) ?>"
                   class="w-full h-full object-contain" width="64" height="64">
            <?php else: ?>
              <span class="text-2xl font-extrabold" style="color:var(--on-accent)" aria-hidden="true">ر</span>
            <?php endif; ?>
          </span>

          <div class="flex-1 min-w-0">
            <!--
              دکمهٔ انتخاب فایلِ خود مرورگر «Choose File» و «No file chosen»
              را به انگلیسی می‌نویسد و هیچ CSSای آن متن را عوض نمی‌کند.
              پس ورودی پنهان می‌شود و یک برچسب فارسی جایش می‌نشیند؛
              برچسب، کلیکش را به همان ورودی می‌دهد، پس بدون جاوااسکریپت
              هم کار می‌کند و فقط نمایشِ نام فایل به JS نیاز دارد.
            -->
            <input type="file" name="logo" id="logo-input" class="sr-only"
                   accept="image/png,image/jpeg,image/webp">
            <label for="logo-input"
                   class="inline-flex items-center gap-1.5 h-10 px-3.5 rounded-xl cursor-pointer
                          text-[12px] font-bold text-ink-700 bg-ink-100 tap">
              <?= icon('plus', 'w-4 h-4') ?>
              انتخاب تصویر
            </label>
            <span id="logo-name" class="block text-[12px] text-ink-500 mt-1.5">فایلی انتخاب نشده</span>
            <script>
            (function () {
              var input = document.getElementById('logo-input');
              var name = document.getElementById('logo-name');
              if (!input || !name) return;
              input.addEventListener('change', function () {
                name.textContent = input.files && input.files.length
                  ? input.files[0].name
                  : 'فایلی انتخاب نشده';
              });
            })();
            </script>
            <p class="text-[12px] text-ink-400 mt-1.5 leading-relaxed">
              PNG یا JPG، تا ۳ مگابایت. به‌طور خودکار کوچک می‌شود.
            </p>
            <?php if ($logoUrl !== null): ?>
              <label class="inline-flex items-center gap-1.5 text-[12px] text-ink-500 mt-1.5 cursor-pointer" for="name">
                <input type="checkbox" name="remove_logo" value="1" class="w-4 h-4 accent-current">
                حذف لوگوی فعلی
              </label>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div>
        <label for="salon-name" class="block text-xs text-ink-500 mb-1">نام سالن</label>
        <input id="salon-name" type="text" name="name" value="<?= e($salon['name']) ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs text-ink-500 mb-1" for="city">شهر</label>
          <input id="city" type="text" name="city" value="<?= e($salon['city'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
        </div>
        <div>
          <label class="block text-xs text-ink-500 mb-1" for="phone">تلفن</label>
          <input id="phone" type="tel" inputmode="numeric" autocomplete="tel" dir="ltr" name="phone" value="<?= e($salon['phone'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm text-left focus:outline-none focus:ring-2 focus:ring-accent">
        </div>
      </div>
      <div>
        <label class="block text-xs text-ink-500 mb-1" for="address">آدرس</label>
        <input id="address" type="text" name="address" value="<?= e($salon['address'] ?? '') ?>" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
      </div>

      <div>
        <label class="block text-xs font-bold text-ink-600 mb-2">رنگ صفحهٔ سالن</label>
        <p class="text-[12px] text-ink-400 mb-3">
          صفحه‌ای که مشتری می‌بیند با این رنگ نمایش داده می‌شود.
        </p>

        <fieldset class="grid grid-cols-3 gap-2">
          <legend class="sr-only">انتخاب پالت رنگی</legend>
          <?php $current = App\Support\Theme::resolve($salon['theme'] ?? null); ?>
          <?php foreach (App\Support\Theme::all() as $key => $palette): ?>
            <label class="pick relative block tap">
              <input type="radio" name="theme" value="<?= e($key) ?>" class="sr-only"
                     <?= $key === $current ? 'checked' : '' ?>>
              <span class="pick-card glass flex items-center gap-2 rounded-xl px-3 py-2.5
                           transition-all duration-200 ease-out-soft cursor-pointer">
                <span class="w-5 h-5 rounded-full shrink-0 ring-1 ring-black/10"
                      style="background:<?= e($palette['swatch']) ?>" aria-hidden="true"></span>
                <span class="text-[12px] font-semibold text-ink-800"><?= e($palette['name']) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </fieldset>
      </div>
      <div class="space-y-1.5">
        <label for="salon-slug" class="block text-[13px] font-semibold text-ink-800">
          نشانی عمومی رزرو
        </label>
        <div class="flex items-stretch rounded-xl overflow-hidden ring-1 ring-ink-200 bg-white
                    focus-within:ring-2 focus-within:ring-accent" dir="ltr">
          <span class="flex items-center px-2.5 text-[12px] text-ink-400 bg-ink-50 shrink-0 code">
            <?= e(rtrim(url('s'), '/')) ?>/
          </span>
          <input type="text" id="salon-slug" name="slug" dir="ltr"
                 value="<?= e($salon['slug']) ?>"
                 class="flex-1 min-w-0 px-2.5 py-2.5 text-[13px] code bg-transparent
                        border-0 outline-none"
                 autocomplete="off" spellcheck="false">
        </div>
        <p class="text-[12px] text-ink-400 leading-relaxed">
          همین نشانی روی QR چاپ می‌شود. اگر عوضش کنید، QRها و لینک‌هایی که
          قبلاً پخش کرده‌اید دیگر کار نمی‌کنند — پس بهتر است همین اول کار
          درستش کنید.
        </p>
      </div>
      <button type="submit" class="btn-accent metal w-full">ذخیره</button>
    </form>
  </div>

  <div class="glass rounded-2xl p-4 sm:p-5">
    <div class="flex items-baseline justify-between mb-1">
      <h2 class="card-title">ساعت کاری و سانس‌بندی</h2>
    </div>
    <p class="text-[12px] text-ink-400 mb-4">
      سانس‌های قابل رزرو از همین ساعت‌ها ساخته می‌شوند. استراحت، آن بازه را از رزرو درمی‌آورد.
    </p>

    <form method="post" action="<?= e(url('panel/settings/hours')) ?>" class="space-y-3">
      <?= csrf_field() ?>

      <div>
        <label for="slot-step" class="block text-xs font-bold text-ink-600 mb-1.5">طول هر سانس</label>
        <select name="slot_step_minutes" id="slot-step"
                class="w-full h-11 rounded-xl border border-ink-200 bg-transparent px-3 text-sm
                       focus:outline-none focus:ring-2 focus:ring-accent">
          <?php $step = (int) ($salon['slot_step_minutes'] ?? 15); ?>
          <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
            <option value="<?= $m ?>" <?= $m === $step ? 'selected' : '' ?>>
              هر <?= e(fa_num($m)) ?> دقیقه
            </option>
          <?php endforeach; ?>
        </select>
        <p class="text-[12px] text-ink-400 mt-1.5">
          فاصلهٔ بین زمان‌هایی که مشتری می‌بیند. کوتاه‌تر یعنی گزینهٔ بیشتر، ولی فهرست شلوغ‌تر.
        </p>
      </div>

      <div class="h-px my-1" style="background:var(--line)" aria-hidden="true"></div>

      <?php foreach ($weekdayNames as $i => $dayName):
          $h = $hours[$i] ?? null;
          $closed = $h ? (bool) $h['is_closed'] : false;

          /*
           * مؤلفهٔ انتخابگر ساعت با include می‌آید، پس متغیرهایش در همین
           * دامنه‌اند. تابعِ کوچکِ زیر آن‌ها را هر بار تازه ست می‌کند تا
           * مقدارِ جامانده از دور قبل به دور بعد نشت نکند.
           */
          $timeField = function (string $field, ?string $val, string $lbl, bool $small, bool $empty) {
              $name = $field;
              $value = $val;
              $label = $lbl;
              $compact = $small;
              $allowEmpty = $empty;
              $minuteStep = 15;
              include BASE_PATH . '/resources/views/components/time-input.php';
          };
      ?>
        <fieldset data-working-day class="rounded-xl p-3 <?= $closed ? 'opacity-55' : '' ?>"
                  style="background:var(--accent-soft)">
          <legend class="sr-only"><?= e($dayName) ?></legend>

          <!--
            روی موبایل سه ردیف، روی صفحهٔ بزرگ‌تر فشرده‌تر.
            نام روز و «تعطیل» همیشه کنار هم‌اند: تصمیمِ باز یا بسته بودن،
            پیش از ساعت‌ها گرفته می‌شود.
          -->
          <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-[13px] font-bold text-ink-800"><?= e($dayName) ?></span>
            <label class="flex items-center gap-1.5 text-[12px] text-ink-600 cursor-pointer
                          py-1 px-1.5 -me-1.5 rounded-lg" for="closed_<?= $i ?>">
              <input id="closed_<?= $i ?>" data-day-closed type="checkbox" name="closed_<?= $i ?>" <?= $closed ? 'checked' : '' ?>
                     class="w-4 h-4 accent-current">
              تعطیل
            </label>
          </div>

          <div data-day-times <?= $closed ? 'hidden' : '' ?> class="flex flex-wrap items-center gap-x-2 gap-y-2 mb-2">
            <?php $timeField("opens_{$i}", substr((string) ($h['opens_at'] ?? '09:00:00'), 0, 5),
                             'باز شدن ' . $dayName, false, false); ?>
            <span class="text-ink-400 text-xs shrink-0">تا</span>
            <?php $timeField("closes_{$i}", substr((string) ($h['closes_at'] ?? '21:00:00'), 0, 5),
                             'بسته شدن ' . $dayName, false, false); ?>
          </div>

          <div data-day-times <?= $closed ? 'hidden' : '' ?> class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
            <span class="text-[12px] text-ink-500 shrink-0">استراحت</span>
            <?php $timeField("break_start_{$i}", $h['break_start'] ?? null,
                             'شروع استراحت ' . $dayName, true, true); ?>
            <span class="text-ink-400 text-[12px] shrink-0">تا</span>
            <?php $timeField("break_end_{$i}", $h['break_end'] ?? null,
                             'پایان استراحت ' . $dayName, true, true); ?>
          </div>
        </fieldset>
      <?php endforeach; ?>

      <button type="submit" class="btn-accent metal w-full mt-2">ذخیره ساعت کاری</button>
    </form>
  </div>

  <div class="glass rounded-2xl p-4 sm:p-5 lg:col-span-2">
    <div class="flex items-center justify-between mb-4">
      <h2 class="card-title">تعطیلات</h2>
      <form method="post" action="<?= e(url('panel/settings/holidays/seed')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="jalali_year" value="<?= (int) $currentJalaliYear ?>">
        <button class="glass h-11 rounded-xl px-3.5 text-[12px] font-bold text-ink-700 tap">
          افزودن تعطیلات ثابت <?= e(fa_num($currentJalaliYear)) ?>
        </button>
      </form>
    </div>
    <div class="space-y-1.5 mb-4">
      <?php foreach ($holidays as $h): ?>
      <div class="flex items-center gap-2 bg-ink-50 rounded-xl ps-3 pe-1 py-1">
        <span class="flex-1 min-w-0 text-[13px] text-ink-700">
          <span class="tabular-nums"><?= e(jdate($h['gregorian_date'], 'Y/m/d')) ?></span>
          <span class="text-ink-400">—</span>
          <?= e($h['jalali_label']) ?>
        </span>
        <form method="post" action="<?= e(url('panel/settings/holidays/' . $h['id'] . '/remove')) ?>">
          <?= csrf_field() ?>
          <button class="w-11 h-11 grid place-items-center rounded-lg text-ink-400
                         hover:text-red-500 hover:bg-red-50 transition-colors cursor-pointer
                         focus-visible:outline-2 focus-visible:outline-accent"
                  aria-label="حذف تعطیلی <?= e($h['jalali_label']) ?>">
            <?= icon('x', 'w-4 h-4') ?>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
      <?php if (empty($holidays)): ?><p class="text-xs text-ink-400">تعطیلی ثبت نشده.</p><?php endif; ?>
    </div>
    <!--
      روی موبایل عمودی می‌چیند. افقی در ۳۲۰ پیکسل جا نمی‌شد و
      سه انتخابگر تاریخ کنار فیلد عنوان، صفحه را ۳۹ پیکسل سرریز می‌کرد.
    -->
    <form method="post" action="<?= e(url('panel/settings/holidays')) ?>" class="space-y-2">
      <?= csrf_field() ?>
      <?php $name = 'date'; $value = null; $label = 'تعطیلی';
            include BASE_PATH . '/resources/views/components/jalali-date-input.php'; ?>
      <div class="flex items-center gap-2">
        <input type="text" name="label" placeholder="عنوان (مثلاً تاسوعا)"
               aria-label="عنوان تعطیلی"
               class="flex-1 min-w-0 h-11 rounded-lg border border-ink-200 bg-transparent px-3 text-[13px]
                      focus:outline-none focus:ring-2 focus:ring-accent">
        <button class="btn-ink h-11 text-[13px] px-4 shrink-0">افزودن</button>
      </div>
    </form>
    <p class="text-[12px] text-ink-400 mt-2">تعطیلات قمری (مثل عید فطر، تاسوعا و عاشورا) هر سال جابه‌جا می‌شوند و باید دستی اضافه شوند.</p>
  </div>

<!--
  مرخصی و بستنِ موردیِ بازه.

  با ساعت کاری فرق دارد: آن قاعدهٔ هر هفته است، این استثنای یک روز.
  «پنجشنبه بعدازظهر عروسی دعوتم» را نباید با عوض کردن ساعت کاریِ همهٔ
  پنجشنبه‌ها حل کرد.
-->
<div class="glass rounded-2xl p-5 mt-5">
  <div class="flex items-baseline justify-between mb-1">
    <h2 class="card-title">مرخصی و بستن سانس</h2>
    <?php if (!empty($timeOffs)): ?>
      <span class="text-[12px] text-ink-400 tabular-nums"><?= e(fa_num(count($timeOffs))) ?> بازه</span>
    <?php endif; ?>
  </div>
  <p class="text-[12px] text-ink-500 mb-4 leading-relaxed">
    یک روز یا چند ساعت را ببند بدون اینکه ساعت کاری همیشگی عوض شود.
    ساعت را خالی بگذار تا کل روز بسته شود.
  </p>

  <?php if (!empty($timeOffs)): ?>
    <ul class="space-y-2 mb-4">
      <?php foreach ($timeOffs as $off):
        $start = new DateTimeImmutable($off['starts_at']);
        $end = new DateTimeImmutable($off['ends_at']);
        $wholeDay = $start->format('H:i') === '00:00' && $end > $start->modify('+23 hours');
      ?>
        <li class="flex items-center gap-3 bg-ink-50 rounded-xl px-3 py-2.5">
          <div class="flex-1 min-w-0">
            <p class="text-[13px] font-bold text-ink-800">
              <?= e(App\Support\JalaliCalendar::relativeDate($start)) ?>
              <?php if (!$wholeDay): ?>
                <span class="font-normal text-ink-600">— <?= e(fa_time($start->format('H:i'))) ?> تا <?= e(fa_time($end->format('H:i'))) ?></span>
              <?php else: ?>
                <span class="font-normal text-ink-600">— تمام روز</span>
              <?php endif; ?>
            </p>
            <p class="text-[12px] text-ink-500 mt-0.5">
              <?= $off['staff_name'] !== null ? e($off['staff_name']) : 'کل آرایشگاه' ?>
              <?php if (!empty($off['reason'])): ?> · <?= e($off['reason']) ?><?php endif; ?>
            </p>
          </div>
          <form method="post" action="<?= e(url('panel/settings/timeoff/' . (int) $off['id'] . '/remove')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="w-11 h-11 grid place-items-center rounded-xl text-ink-400 hover:text-red-700 tap"
                    aria-label="باز کردن این بازه">
              <?= icon('x', 'w-4 h-4') ?>
            </button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" action="<?= e(url('panel/settings/timeoff')) ?>" data-timeoff-form class="space-y-3">
    <?= csrf_field() ?>

    <div>
      <label for="off_date_d" class="block text-xs text-ink-500 mb-1">روز</label>
      <?= App\Core\View::render('components.jalali-date-input', ['name' => 'off_date', 'value' => null, 'label' => 'مرخصی']) ?>
    </div>

    <label data-timeoff-all-day-label hidden class="flex items-center gap-2"><input type="checkbox" data-timeoff-all-day checked> تمام روز</label>
    <div data-timeoff-times class="grid grid-cols-2 gap-3">
      <div>
        <label for="off_from_h" class="block text-xs text-ink-500 mb-1">از ساعت</label>
        <?= App\Core\View::render('components.time-input', ['name' => 'off_from', 'value' => null, 'minuteStep' => 15, 'allowEmpty' => true, 'label' => 'شروع مرخصی']) ?>
      </div>
      <div>
        <label for="off_to_h" class="block text-xs text-ink-500 mb-1">تا ساعت</label>
        <?= App\Core\View::render('components.time-input', ['name' => 'off_to', 'value' => null, 'minuteStep' => 15, 'allowEmpty' => true, 'label' => 'پایان مرخصی']) ?>
      </div>
    </div>

    <div>
      <label for="off_staff_id" class="block text-xs text-ink-500 mb-1">برای چه کسی</label>
      <select id="off_staff_id" name="off_staff_id" class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
        <option value="">کل آرایشگاه</option>
        <?php foreach ($staffList as $st): ?>
          <option value="<?= (int) $st['id'] ?>"><?= e($st['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-xs text-ink-500 mb-1" for="off_reason">دلیل (اختیاری)</label>
      <input id="off_reason" type="text" name="off_reason" maxlength="150" placeholder="مثلاً: عروسی"
             class="w-full rounded-xl border border-ink-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
    </div>

    <button type="submit" class="btn-ink w-full">بستن این بازه</button>
  </form>
</div>

</div>
