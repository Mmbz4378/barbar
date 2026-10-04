<?php
$customerNavPath = (new App\Core\Request())->path;
$customerNavItems = [
    ['discover', 'کشف سالن', 'search', !str_starts_with($customerNavPath, '/me')],
    ['me', 'نوبت‌های من', 'calendar-days', str_starts_with($customerNavPath, '/me') && $customerNavPath !== '/me/favorites'],
    ['me/favorites', 'ذخیره‌شده‌ها', 'heart', $customerNavPath === '/me/favorites'],
];
?>
<nav class="customer-navigation" aria-label="منوی مشتری">
<?php foreach ($customerNavItems as [$path, $label, $symbol, $active]): ?>
  <a href="<?= e(url($path)) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= icon($symbol, 'customer-navigation__icon') ?><span><?= e($label) ?></span></a>
<?php endforeach; ?>
</nav>
