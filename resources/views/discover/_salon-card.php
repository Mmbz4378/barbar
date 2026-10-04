<?php /** @var array $salon */ ?>
<article class="card card--interactive salon-card" data-theme="<?= e(App\Support\Theme::resolve($salon['theme'] ?? null)) ?>" data-salon-location data-lat="<?= e($salon['map_lat'] === null ? '' : (string) $salon['map_lat']) ?>" data-lng="<?= e($salon['map_lng'] === null ? '' : (string) $salon['map_lng']) ?>">
  <a class="salon-card__media" href="<?= e(url('salons/view/' . $salon['slug'])) ?>" tabindex="-1" aria-hidden="true">
    <?= salon_cover($salon) ?>
  </a>
  <div class="salon-card__body">
    <div class="spread">
      <h2 class="salon-card__name"><a href="<?= e(url('salons/view/' . $salon['slug'])) ?>" style="color:inherit"><?= e($salon['name']) ?></a></h2>
      <span class="badge"><?= e(term('audience_label', $salon['audience'] ?? null)) ?></span>
    </div>
    <p class="text-sm muted truncate"><?= icon('map-pin', 'icon') ?> <?= e(join_parts([$salon['city'] ?? '', ($salon['neighborhood'] ?? '') ?: ($salon['address'] ?? '')], ' · ')) ?></p>
    <div class="spread text-sm">
      <span class="rating">
        <?php if ((int) $salon['rating_count'] > 0): ?>
          <?= icon('star-solid') ?><?= e(fa_num(number_format((float) $salon['rating_avg'], 1))) ?> <span class="muted">(<?= e(fa_num($salon['rating_count'])) ?> نظر)</span>
        <?php else: ?>
          <span class="muted">هنوز نظری ثبت نشده</span>
        <?php endif; ?>
      </span>
      <span class="muted" data-distance></span>
    </div>
    <div class="spread mt-2">
      <span class="text-sm"><?= $salon['min_price'] ? 'از <strong class="num">' . e(toman((int) $salon['min_price'])) . '</strong>' : '<span class="muted">قیمت در صفحهٔ سالن</span>' ?></span>
      <a class="btn btn--primary btn--sm" href="<?= e(url('s/' . $salon['slug'])) ?>">رزرو نوبت</a>
    </div>
  </div>
</article>
