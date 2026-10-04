<?php /** @var array $salon @var array $owners @var array $auditLogs */ ?>
<div class="flex items-center gap-3 mb-5">
  <a href="<?= url('platform') ?>" class="w-11 h-11 -ms-2 grid place-items-center rounded-xl text-ink-400 tap">←</a>
  <h1 class="page-title"><?= e($salon['name']) ?></h1>
</div>

<div class="grid lg:grid-cols-2 gap-5">
  <div class="glass rounded-2xl p-5">
    <h2 class="card-title mb-3">اطلاعات سالن</h2>
    <div class="text-sm space-y-1.5 text-ink-600">
      <div>شهر: <?= e($salon['city'] ?? '—') ?></div>
      <div>پلن: <?= e($salon['plan_code']) ?> · <?= fa_num($salon['seats']) ?> صندلی</div>
      <div>تاریخ ثبت‌نام: <?= jdate($salon['created_at'], 'Y/m/d') ?></div>
    </div>

    <form method="post" action="<?= url('platform/' . $salon['id'] . '/impersonate') ?>" class="mt-4">
      <?= csrf_field() ?>
      <button class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-xl py-2.5 text-sm">ورود پشتیبانی به پنل این سالن</button>
    </form>

    <div class="mt-5">
      <h3 class="text-[12px] font-bold text-ink-500 mb-2">اعضا</h3>
      <?php foreach ($owners as $o): ?>
      <div class="flex items-center justify-between text-xs py-1">
        <span><?= e($o['name'] ?? '—') ?> <span dir="ltr" class="text-ink-400"><?= e($o['phone']) ?></span></span>
        <span class="text-ink-400"><?= e(['owner'=>'صاحب','manager'=>'مدیر','staff'=>'آرایشگر','reception'=>'پذیرش'][$o['role']] ?? $o['role']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="glass rounded-2xl p-5">
    <h2 class="card-title mb-3">لاگ دسترسی و رویدادها</h2>
    <div class="space-y-2">
      <?php foreach ($auditLogs as $log): ?>
      <div class="text-xs border-b border-ink-50 pb-1.5">
        <span class="font-bold text-ink-700"><?= e($log['action']) ?></span>
        <span class="text-ink-400"> — <?= e($log['actor_phone'] ?? 'سیستم') ?> — <?= jdate($log['created_at'], 'Y/m/d H:i') ?></span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($auditLogs)): ?><p class="text-xs text-ink-400">رویدادی ثبت نشده.</p><?php endif; ?>
    </div>
  </div>
</div>
