<?php /** @var ?string $error */ ?>
<h2 class="text-[15px] font-extrabold text-ink-900 mb-1">اولین سالن‌تان را بسازید</h2>
<p class="text-sm text-ink-500 mb-5">کمتر از ۲ دقیقه. جزئیات را بعداً هم می‌توانید کامل کنید.</p>

<?php if ($error): ?>
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4 border border-red-100"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('onboarding') ?>" class="space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="name">نام سالن</label>
    <input id="name" type="text" name="name" required autofocus placeholder="آرایشگاه شهاب"
      class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent">
  </div>
  <div class="grid grid-cols-2 gap-3">
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="city">شهر</label>
      <input id="city" type="text" name="city" placeholder="شیراز"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent">
    </div>
    <div>
      <label class="block text-sm text-ink-600 mb-1.5" for="seats">تعداد صندلی</label>
      <input id="seats" inputmode="numeric" type="number" name="seats" value="1" min="1" max="20"
        class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent">
    </div>
  </div>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="address">آدرس (اختیاری)</label>
    <input id="address" type="text" name="address" placeholder="خیابان..."
      class="w-full rounded-xl border border-ink-200 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent">
  </div>
  <button type="submit" class="btn-ink w-full">
    ساخت سالن و شروع
  </button>
</form>
