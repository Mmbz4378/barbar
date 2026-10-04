<?php /** @var ?string $error */ ?>
<h2 class="text-[15px] font-extrabold text-ink-900 mb-1">ورود / ثبت‌نام</h2>
<p class="text-sm text-ink-500 mb-5">شمارهٔ موبایل‌تان را وارد کنید تا کد یک‌بارمصرف برایتان پیامک شود.</p>

<?php if ($error): ?>
<div class="bg-red-50 text-red-700 text-sm rounded-lg px-3 py-2 mb-4 border border-red-100"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= url('login') ?>" class="space-y-4">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm text-ink-600 mb-1.5" for="phone">شمارهٔ موبایل</label>
    <input id="phone" type="tel" name="phone" inputmode="numeric" autofocus placeholder="۰۹۱۲۳۴۵۶۷۸۹"
      class="w-full rounded-xl border border-ink-200 px-4 py-3 text-left ltr text-lg tracking-wider focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent" dir="ltr">
  </div>
  <button type="submit" class="btn-ink w-full">
    ارسال کد
  </button>
</form>
<p class="text-xs text-ink-400 text-center mt-5">با ورود، شرایط استفاده از رشن را می‌پذیرید.</p>
