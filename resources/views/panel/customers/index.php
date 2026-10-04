<?php /** @var array $customers @var string $q */ ?>
<h1 class="page-title mb-4">مشتریان</h1>

<form method="get" action="<?= url('panel/customers') ?>" class="mb-4">
  <label for="customer-search" class="sr-only">جستجوی مشتری با نام یا شماره</label>
  <input type="search" id="customer-search" name="q" value="<?= e($q) ?>"
    placeholder="جستجو با نام یا شماره..."
    class="w-full rounded-xl border border-ink-200 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
</form>

<?php if (empty($customers)): ?>
  <div class="glass rounded-2xl p-10 text-center text-ink-400">مشتری‌ای یافت نشد.</div>
<?php else: ?>
<div class="glass rounded-2xl divide-y divide-ink-100">
  <?php foreach ($customers as $c): ?>
  <a href="<?= url('panel/customers/' . $c['id']) ?>" class="flex items-center justify-between px-5 py-3.5 hover:bg-ink-50">
    <div>
      <div class="font-bold text-ink-800"><?= e($c['name'] ?: 'بدون نام') ?></div>
      <div class="text-xs text-ink-400" dir="ltr"><?= $c['phone'] ? e(\App\Support\IranMobile::parse($c['phone'])->local()) : '—' ?></div>
    </div>
    <div class="text-left text-xs text-ink-400">
      <?= fa_num($c['visit_count']) ?> مراجعه
      <?php if ($c['last_visit_at']): ?><div><?= jdate($c['last_visit_at'], 'Y/m/d') ?></div><?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
