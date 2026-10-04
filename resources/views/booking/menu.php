<?php
/**
 * منوی خدمات — خواندنی، برای کسی که اول می‌خواهد بداند «چی دارید و چند؟»
 *
 * @var array $salon
 * @var array $groups
 */
use App\Support\ServiceVisual;

$count = array_sum(array_map(static fn ($g) => count($g['services']), $groups));
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">خدمات و قیمت‌ها</h1>
    <p class="page-head__sub"><?= $count > 0 ? e(fa_num($count)) . ' خدمت در ' . e(fa_num(count($groups))) . ' دسته' : 'هنوز خدمتی ثبت نشده.' ?></p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('s/' . $salon['slug'])) ?>"><?= icon('calendar') ?> رزرو نوبت</a>
</div>

<?php if ($count === 0): ?>
  <div class="card"><?= partial('empty-state', ['icon' => 'tag', 'title' => 'هنوز خدمتی ثبت نشده', 'text' => !empty($salon['phone']) ? 'برای اطلاع از خدمات با سالن تماس بگیر.' : null]) ?></div>
<?php else: ?>
  <?php foreach ($groups as $group): ?>
    <section class="service-group" aria-labelledby="g-<?= (int) ($group['id'] ?? 0) ?>">
      <h2 class="service-group__title" id="g-<?= (int) ($group['id'] ?? 0) ?>"><?= icon(ServiceVisual::icon($group['visual'] ?? 'haircut')) ?><?= e($group['name']) ?></h2>
      <div class="stack stack-sm">
        <?php foreach ($group['services'] as $i => $s): ?>
          <article class="menu-item">
            <?= service_media($s, 'service-thumb', $i < 2) ?>
            <div class="menu-item__body">
              <div class="menu-item__row">
                <h3 class="title-xs"><?= e($s['name']) ?></h3>
                <span class="service-price"><?= e(price_text((int) $s['price'], (string) $s['price_type'])) ?></span>
              </div>
              <?php if (!empty($s['description'])): ?><p class="text-sm muted"><?= e($s['description']) ?></p><?php endif; ?>
              <div class="service-meta">
                <span><?= icon('clock') ?><?= e(duration_text((int) $s['duration_minutes'])) ?></span>
                <?php if ((int) $s['online_booking'] !== 1): ?><span><?= icon('phone') ?>رزرو تلفنی</span><?php endif; ?>
                <?php if (!empty($s['deposit_amount'])): ?><span><?= icon('wallet') ?>بیعانه <?= e(toman((int) $s['deposit_amount'])) ?></span><?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <p class="text-sm muted center mt-6">قیمت‌های «از …» به طول و حجم مو یا جزئیات کار بستگی دارند و پیش از شروع خدمت اعلام می‌شوند.</p>
  <div class="action-bar">
    <a class="btn btn--primary btn--lg btn--block" href="<?= e(url('s/' . $salon['slug'])) ?>"><?= icon('calendar') ?> رزرو نوبت</a>
  </div>
<?php endif; ?>
