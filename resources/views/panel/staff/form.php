<?php /** @var ?array $staff */ ?>
<div class="max-w-lg">
<div class="flex items-center gap-3 mb-5">
  <a href="<?= url('panel/staff') ?>" class="w-11 h-11 -ms-2 grid place-items-center rounded-xl text-ink-400 tap">←</a>
  <h1 class="page-title"><?= $staff ? 'ویرایش آرایشگر' : 'آرایشگر جدید' ?></h1>
</div>

<form method="post" action="<?= url($staff ? 'panel/staff/' . $staff['id'] : 'panel/staff') ?>" class="glass rounded-2xl p-5 space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="name">نام</label>
    <input id="name" type="text" name="name" required value="<?= e($staff['name'] ?? '') ?>"
      class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
  </div>
  <?php if (!$staff): ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="phone">شمارهٔ موبایل (برای ورود آرایشگر به پنل — اختیاری)</label>
    <input id="phone" inputmode="numeric" type="tel" name="phone" dir="ltr" placeholder="09123456789"
      class="w-full rounded-xl border border-ink-200 px-4 py-3 text-left focus:outline-none focus:ring-2 focus:ring-accent">
  </div>
  <?php endif; ?>
  <div class="space-y-4">
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="commission_percent">درصد پیش‌فرض تسویه</label>
      <input id="commission_percent" inputmode="numeric" type="number" step="0.1" name="commission_percent" value="<?= e((string)($staff['commission_percent'] ?? '')) ?>" placeholder="۵۰"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5">رنگ نمایشی</label>
      <?php
        /*
         * انتخابگر رنگِ مرورگر (input type=color) پنجرهٔ سیستم‌عامل را باز
         * می‌کرد — انگلیسی، و هر سیستمی یک شکل. اینجا فقط هشت رنگ لازم است
         * تا آرایشگرها در تقویم از هم جدا شوند، پس خودِ رنگ‌ها را نشان
         * می‌دهیم. هر هشت رنگ با متن سفید نسبت ۴.۵:۱ را رد می‌کنند، چون
         * حرف اول نام آرایشگر روی همین رنگ می‌نشیند.
         */
        $palette = [
          '#1D4ED8' => 'سرمه‌ای', '#0F766E' => 'فیروزه‌ای', '#15803D' => 'سبز',
          '#C2410C' => 'نارنجی', '#B91C1C' => 'آجری', '#9F1239' => 'زرشکی',
          '#6D28D9' => 'بنفش', '#44403C' => 'خاکستری',
        ];
        $current = strtoupper((string) ($staff['color'] ?? '#1D4ED8'));
        /*
         * رنگِ آرایشگرهای قدیمی ممکن است در این هشت‌تا نباشد. اگر
         * بی‌سروصدا اولین رنگ را انتخاب می‌کردیم، باز کردن و ذخیرهٔ
         * همان صفحه رنگ آن آرایشگر را عوض می‌کرد؛ پس رنگ فعلی را
         * به‌عنوان نهمین گزینه نگه می‌داریم.
         */
        if (!isset($palette[$current])) {
            if (preg_match('/^#[0-9A-F]{6}$/', $current) === 1) {
                $palette = [$current => 'رنگ فعلی'] + $palette;
            } else {
                $current = array_key_first($palette);
            }
        }
      ?>
      <div class="flex flex-wrap gap-2 pt-1.5" role="radiogroup" aria-label="رنگ نمایشی آرایشگر">
        <?php foreach ($palette as $hex => $label): ?>
        <label class="cursor-pointer" title="<?= e($label) ?>">
          <input type="radio" name="color" value="<?= e($hex) ?>" class="sr-only peer"
            <?= $hex === $current ? 'checked' : '' ?>>
          <span class="block w-9 h-9 rounded-full ring-2 ring-transparent ring-offset-2 ring-offset-transparent
                       peer-checked:ring-ink-700 peer-focus-visible:ring-accent transition"
                style="background:<?= e($hex) ?>"></span>
          <span class="sr-only"><?= e($label) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <button type="submit" class="btn-ink w-full">ذخیره</button>
</form>

<?php if ($staff): ?>
<form method="post" action="<?= url('panel/staff/' . $staff['id'] . '/toggle') ?>" class="mt-3">
  <?= csrf_field() ?>
  <button type="submit" class="w-full text-sm text-ink-400 hover:text-ink-600 py-2">
    <?= $staff['is_active'] ? 'غیرفعال کردن' : 'فعال کردن' ?>
  </button>
</form>
<?php endif; ?>
</div>
