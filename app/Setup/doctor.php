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
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>">
<head>
<?php $title = 'سلامت سیستم'; include BASE_PATH . '/resources/views/components/head.php'; ?>
</head>
<body>
<?php include BASE_PATH . '/resources/views/components/body-start.php'; ?>
<main id="main" class="public__main" style="max-width:760px;margin-inline:auto;padding:24px 16px 48px">
  <div class="page-head">
    <div class="page-head__text">
      <h1 class="page-head__title">سلامت سیستم</h1>
      <p class="page-head__sub"><span class="ltr"><?= e($_SERVER['HTTP_HOST'] ?? '') ?></span> · <?= e(jdate(date('Y-m-d H:i:s'))) ?></p>
    </div>
  </div>

  <?php $tone = $counts['fail'] > 0 ? 'danger' : ($counts['warn'] > 0 ? 'warning' : 'success'); ?>
  <div class="alert alert--<?= $tone ?> mb-6" role="status">
    <?= icon($tone === 'success' ? 'circle-check' : 'alert') ?>
    <div class="alert__body"><p class="alert__title">
      <?php if ($counts['fail'] > 0): ?>
        <?= e(fa_num($counts['fail'])) ?> مورد نیاز به رسیدگی دارد.
      <?php elseif ($counts['warn'] > 0): ?>
        همه‌چیز کار می‌کند، ولی <?= e(fa_num($counts['warn'])) ?> هشدار هست.
      <?php else: ?>
        همه‌چیز سالم است.
      <?php endif; ?>
    </p></div>
  </div>

  <div class="stack stack-lg">
  <?php foreach ($groups as $group => $rows): ?>
    <section class="stack stack-sm">
      <h2 class="title-xs muted"><?= e((string) $group) ?></h2>
      <div class="card"><ul class="list" role="list">
        <?php foreach ($rows as $row): $st = $row['status']; ?>
          <li class="list-row" style="align-items:flex-start">
            <span class="icon-tile icon-tile--<?= $st === 'ok' ? 'success' : ($st === 'warn' ? 'warning' : 'danger') ?>" style="width:32px;height:32px"><?= icon($st === 'ok' ? 'check' : ($st === 'warn' ? 'alert' : 'x'), 'icon', $st === 'ok' ? 'سالم' : ($st === 'warn' ? 'هشدار' : 'خطا')) ?></span>
            <span class="list-row__body">
              <span class="list-row__title"><?= e($row['label']) ?></span>
              <?php if ($row['hint'] !== ''): ?><span class="list-row__meta"><?= e($row['hint']) ?></span><?php endif; ?>
            </span>
            <span class="list-row__end text-xs muted ltr" style="max-width:45%;overflow-wrap:anywhere"><?= e($row['value']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul></div>
    </section>
  <?php endforeach; ?>
  </div>

  <p class="center mt-8"><a class="btn btn--secondary" href="<?= e(url('/panel')) ?>"><?= icon('chevron-start') ?> بازگشت به پنل</a></p>
</main>
<?php $withInstall = false; include BASE_PATH . '/resources/views/components/body-end.php'; ?>
</body>
</html>
