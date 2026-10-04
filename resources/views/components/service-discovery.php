<?php
$categories = [
    'haircut' => ['کوتاهی', 'hair'], 'beard' => ['ریش', 'beard'],
    'styling' => ['حالت‌دهی', 'comb'], 'facial' => ['پوست', 'sparkles'],
    'care' => ['مراقبت مو', 'sparkles'], 'color' => ['رنگ مو', 'sparkle'],
];
$available = array_unique(array_map(static fn ($service) => service_visual((string) $service['name'])['category'], $services));
?>
<div class="service-discovery" data-service-discovery hidden>
  <label class="service-search">
    <?= icon('search', 'w-5 h-5') ?>
    <span class="sr-only">جست‌وجوی خدمات</span>
    <input type="search" placeholder="چه خدمتی نیاز داری؟" autocomplete="off">
    <span role="status" aria-live="polite"></span>
  </label>
  <div class="service-categories" role="group" aria-label="دسته‌بندی خدمات">
    <button type="button" data-category="all" aria-pressed="true">همهٔ خدمات</button>
    <?php foreach ($categories as $key => [$label, $symbol]): if (!in_array($key, $available, true)) continue; ?>
      <button type="button" data-category="<?= e($key) ?>" aria-pressed="false"><?= icon($symbol, 'w-5 h-5') ?><?= e($label) ?></button>
    <?php endforeach; ?>
  </div>
  <div class="service-empty" data-no-results hidden>
    <p>خدمتی با این مشخصات پیدا نشد.</p>
    <button type="button" class="btn-ink" data-reset-search>نمایش همهٔ خدمات</button>
  </div>
</div>
<script src="<?= e(asset('js/service-discovery.js')) ?>" defer></script>
