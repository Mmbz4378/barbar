<?php
/**
 * صفحه‌بندی.
 *
 * @var int                    $page     صفحهٔ فعلی (از ۱)
 * @var int|null               $pages    تعداد کل صفحه‌ها؛ اگر نامعلوم است null و hasNext را بدهید
 * @var bool|null              $hasNext  برای فهرست‌هایی که شمارش کل گران است
 * @var callable(int):string   $url      نشانی هر صفحه
 * @var string|null            $label    برچسب ناوبری برای صفحه‌خوان
 * @var string|null            $skeletonFor شناسهٔ ناحیه‌ای که هنگام رفتن به صفحهٔ دیگر skeleton نشان دهد
 *
 * روی موبایل فقط «قبلی/بعدی» و «صفحهٔ x از y»؛ شماره‌ها از ۴۸۰ پیکسل به بالا.
 */
$page = max(1, (int) $page);
$pages = isset($pages) && $pages !== null ? max(1, (int) $pages) : null;
$hasNext = $pages !== null ? $page < $pages : (bool) ($hasNext ?? false);
if ($page === 1 && !$hasNext) {
    return;
}
$skeletonAttr = !empty($skeletonFor) ? ' data-skeleton-for="' . e($skeletonFor) . '"' : '';
$window = [];
if ($pages !== null) {
    foreach ([1, $page - 1, $page, $page + 1, $pages] as $p) {
        if ($p >= 1 && $p <= $pages) {
            $window[$p] = true;
        }
    }
    ksort($window);
}
?>
<nav class="pagination" aria-label="<?= e($label ?? 'صفحه‌ها') ?>">
  <?php if ($page > 1): ?>
    <a class="btn btn--secondary" href="<?= e($url($page - 1)) ?>" rel="prev"<?= $skeletonAttr ?>><?= icon('chevron-start') ?> قبلی</a>
  <?php endif; ?>
  <?php if ($window !== []): ?>
    <ol class="pagination__pages">
      <?php $last = 0; foreach (array_keys($window) as $p): ?>
        <?php if ($p - $last > 1): ?><li class="pagination__gap" aria-hidden="true">…</li><?php endif; ?>
        <li><a class="pagination__page" href="<?= e($url($p)) ?>" <?= $p === $page ? 'aria-current="page"' : '' ?> aria-label="صفحهٔ <?= e(fa_num($p)) ?>"<?= $skeletonAttr ?>><?= e(fa_num($p)) ?></a></li>
      <?php $last = $p; endforeach; ?>
    </ol>
  <?php endif; ?>
  <span class="pagination__status"><?= $pages !== null ? 'صفحهٔ ' . e(fa_num($page)) . ' از ' . e(fa_num($pages)) : 'صفحهٔ ' . e(fa_num($page)) ?></span>
  <?php if ($hasNext): ?>
    <a class="btn btn--secondary" href="<?= e($url($page + 1)) ?>" rel="next"<?= $skeletonAttr ?>>بعدی <?= icon('chevron-end') ?></a>
  <?php endif; ?>
</nav>
