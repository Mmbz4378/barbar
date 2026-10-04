<?php /** @var string $phone @var ?string $error @var ?string $debugLine */ ?>

<?php if ($error): ?>
  <div role="alert" class="flex items-start gap-2 bg-red-50 text-red-800 text-sm rounded-xl px-4 py-3 mb-4 border border-red-100">
    <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
    <span><?= e($error) ?></span>
  </div>
<?php endif; ?>

<p class="text-[13px] text-ink-500 leading-relaxed mb-1">کد پیامک‌شده به این شماره را بزن:</p>
<p class="text-[13px] font-bold text-ink-900 tabular-nums mb-5" dir="ltr"><?= e(fa_num($phone)) ?></p>

<?php if ($debugLine): ?>
  <p class="text-[12px] text-ink-500 bg-ink-50 rounded-xl px-4 py-3 mb-4 border border-ink-100">
    <?= e($debugLine) ?>
  </p>
<?php endif; ?>

<form method="post" action="<?= e(url('me/verify')) ?>" class="space-y-4">
  <?= csrf_field() ?>

  <div>
    <label for="me-code" class="block text-sm text-ink-600 mb-1.5">کد تأیید</label>
    <input id="me-code" type="text" name="code" dir="ltr" required autofocus
           inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
           class="w-full rounded-xl border border-ink-200 px-4 py-3 text-center text-lg font-bold tabular-nums
                  focus:outline-none focus:ring-2 focus:ring-accent">
  </div>

  <button type="submit" class="btn-accent metal w-full">ورود</button>
</form>

<a href="<?= e(url('me/login')) ?>" class="block text-center text-[12px] text-ink-500 mt-4 tap py-2">
  شماره را اشتباه زدم
</a>
