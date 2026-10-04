<?php /** @var array $staff */ ?>
<div class="flex items-center justify-between mb-5">
  <h1 class="page-title">آرایشگرها</h1>
  <a href="<?= url('panel/staff/create') ?>" class="btn-ink h-11 text-sm px-4">+ افزودن</a>
</div>

<?php if (empty($staff)): ?>
  <div class="bg-white rounded-2xl border border-dashed border-ink-200 p-10 text-center text-ink-400">
    هنوز آرایشگری اضافه نکرده‌اید.
  </div>
<?php else: ?>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
  <?php foreach ($staff as $s): ?>
  <a href="<?= url('panel/staff/' . $s['id'] . '/edit') ?>" class="glass rounded-2xl p-4 flex items-center gap-3 hover:shadow-sm transition <?= !$s['is_active'] ? 'opacity-50' : '' ?>">
    <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold shrink-0" style="background:<?= e($s['color']) ?>"><?= e(mb_substr($s['name'],0,1)) ?></div>
    <div class="min-w-0">
      <div class="font-bold text-ink-800 truncate"><?= e($s['name']) ?></div>
      <div class="text-xs text-ink-400"><?= $s['commission_percent'] !== null ? fa_num($s['commission_percent']) . '٪ درصد' : 'بدون درصد پیش‌فرض' ?></div>
    </div>
    <?php if (!$s['is_active']): ?><span class="mr-auto text-[12px] bg-ink-100 text-ink-500 rounded-full px-2 py-0.5">غیرفعال</span><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
