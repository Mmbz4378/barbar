<?php /** @var array $memberships */ ?>
<h2 class="text-[15px] font-extrabold text-ink-900 mb-1">انتخاب سالن</h2>
<p class="text-sm text-ink-500 mb-5">در چند سالن عضو هستید. کدام را باز کنیم؟</p>

<div class="space-y-2">
<?php foreach ($memberships as $m): ?>
  <a href="<?= url('salons/' . $m['salon_id'] . '/switch') ?>"
     class="flex items-center justify-between rounded-xl border border-ink-200 hover:border-brand-300 hover:bg-gold-50 px-4 py-3 transition">
    <span class="font-medium text-ink-800"><?= e($m['salon_name']) ?></span>
    <span class="text-xs text-ink-400"><?= e(['owner'=>'صاحب سالن','manager'=>'مدیر','staff'=>'آرایشگر','reception'=>'پذیرش'][$m['role']] ?? $m['role']) ?></span>
  </a>
<?php endforeach; ?>
</div>
<div class="text-center mt-5">
  <a href="<?= url('logout') ?>" class="text-xs text-ink-400 hover:text-ink-600">خروج از حساب</a>
</div>
