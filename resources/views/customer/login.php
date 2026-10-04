<?php /** @var ?string $error */ ?>

<?php if ($error): ?>
  <div role="alert" class="flex items-start gap-2 bg-red-50 text-red-800 text-sm rounded-xl px-4 py-3 mb-4 border border-red-100">
    <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<p class="text-[13px] text-ink-500 leading-relaxed mb-5">
  شمارهٔ موبایلت را بزن تا نوبت‌هایت را ببینی. همان شماره‌ای که با آن نوبت گرفته‌ای.
</p>

<form method="post" action="<?= e(url('me/login')) ?>" class="space-y-4">
  <?= csrf_field() ?>

  <div>
    <label for="me-phone" class="block text-sm text-ink-600 mb-1.5">شمارهٔ موبایل</label>
    <input id="me-phone" type="tel" name="phone" dir="ltr" required autofocus
           inputmode="numeric" autocomplete="tel" placeholder="09123456789"
           class="w-full rounded-xl border border-ink-200 px-4 py-3 text-left tabular-nums
                  focus:outline-none focus:ring-2 focus:ring-accent">
  </div>

  <button type="submit" class="btn-accent metal w-full">
    ادامه
    <?= icon('chevron-end', 'w-4 h-4') ?>
  </button>
</form>

<p class="text-[12px] text-ink-400 mt-5 leading-relaxed">
  اگر تازه نوبت گرفته‌ای، لینک کارت نوبتت هم کار می‌کند و برای دیدنش لازم نیست وارد شوی.
</p>
