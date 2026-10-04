<?php

declare(strict_types=1);

/**
 * صفحهٔ سلامت سیستم.
 *
 * چرا از وب: صاحب سالن SSH ندارد. وقتی پیامکی نمی‌رود یا چیزی کار
 * نمی‌کند، پشتیبانی باید بتواند بگوید «این آدرس را باز کن و عکسش را
 * بفرست» — به‌جای رفت‌وبرگشت‌های تلفنی.
 *
 * امنیت: چون این صفحه پیکربندی سرور را نشان می‌دهد، فقط برای کاربرِ
 * واردشده با نقش صاحب/مدیر باز می‌شود. اگر هنوز نصب نشده، آزاد است
 * چون هنوز چیزی برای محافظت وجود ندارد.
 */

if (!defined('RESHEN_DOCTOR')) {
    // مستقیم صدا زده شده، نه از راه دروازه. دروازه است که پیش از لمس
    // کردن autoload نسخهٔ PHP را می‌سنجد؛ دور زدنش یعنی همان خطای
    // مرگبارِ بی‌پیام که این صفحه قرار بود توضیحش بدهد.
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Domain\Diagnostics\HealthCheck;

$installed = is_file(BASE_PATH . '/storage/installed.lock');

if ($installed && !in_array(Auth::role(), ['owner', 'manager'], true) && !Auth::isPlatformAdmin()) {
    http_response_code(403);
    echo '<html lang="fa" dir="rtl"><meta charset="utf-8">'
       . '<body style="font:16px Tahoma;padding:2rem">'
       . 'برای دیدن این صفحه باید به‌عنوان صاحب یا مدیر سالن وارد شوید.'
       . '</body></html>';
    exit;
}

$groups = (new HealthCheck())->run();

$counts = ['ok' => 0, 'warn' => 0, 'fail' => 0];
foreach ($groups as $rows) {
    foreach ($rows as $row) {
        $counts[$row['status']]++;
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>سلامت سیستم — رشن</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="bg-slate-50 text-slate-900 p-4">
<div class="max-w-3xl mx-auto py-6">

  <h1 class="text-2xl font-bold text-brand-700 mb-1">سلامت سیستم</h1>
  <p class="text-sm text-slate-500 mb-5">
    <?= e($_SERVER['HTTP_HOST'] ?? '') ?> · <?= e(jdate(date('Y-m-d H:i:s'))) ?>
  </p>

  <div class="rounded-xl p-4 mb-6 font-bold <?= $counts['fail'] > 0
        ? 'bg-red-50 text-red-700' : ($counts['warn'] > 0 ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700') ?>">
    <?php if ($counts['fail'] > 0): ?>
      <?= e(fa_num($counts['fail'])) ?> مورد نیاز به رسیدگی دارد.
    <?php elseif ($counts['warn'] > 0): ?>
      همه‌چیز کار می‌کند، ولی <?= e(fa_num($counts['warn'])) ?> هشدار هست.
    <?php else: ?>
      همه‌چیز سالم است.
    <?php endif; ?>
  </div>

  <?php foreach ($groups as $group => $rows): ?>
    <h2 class="text-xs font-bold text-slate-500 mt-5 mb-2"><?= e((string) $group) ?></h2>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
      <?php foreach ($rows as $row): ?>
        <div class="flex flex-wrap gap-2 items-start px-4 py-3 border-b border-slate-100 last:border-b-0">
          <span class="w-5 font-bold <?= $row['status'] === 'ok' ? 'text-green-600'
                : ($row['status'] === 'warn' ? 'text-amber-600' : 'text-red-600') ?>">
            <?= $row['status'] === 'ok' ? '✓' : ($row['status'] === 'warn' ? '!' : '✗') ?>
          </span>
          <span class="flex-1 min-w-0 font-semibold text-sm"><?= e($row['label']) ?></span>
          <span class="text-xs text-slate-500 font-mono ltr"><?= e($row['value']) ?></span>
          <?php if ($row['hint'] !== ''): ?>
            <p class="w-full text-xs text-slate-500 ps-7 mt-1"><?= e($row['hint']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <p class="text-xs text-slate-400 mt-6 text-center">
    <a class="text-brand-600" href="<?= e(url('/panel')) ?>">بازگشت به پنل</a>
  </p>
</div>
</body>
</html>
