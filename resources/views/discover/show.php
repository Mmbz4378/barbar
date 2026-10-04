<?php
/**
 * معرفی عمومی سالن.
 *
 * @var array $salon
 * @var array $groups
 * @var array $staff
 * @var array $hours
 * @var array $reviews
 * @var bool $favorite
 */
use App\Support\JalaliCalendar;
use App\Support\ServiceVisual;

$mapsEnabled = (bool) App\Core\Config::get('reshen.discovery.maps_enabled', true);
$hasMap = $mapsEnabled && $salon['map_lat'] !== null && $salon['map_lng'] !== null;
$serviceCount = array_sum(array_map(static fn ($g) => count($g['services']), $groups));
?>
<a class="back-link" href="<?= e(url('discover')) ?>"><?= icon('chevron-start') ?> همهٔ سالن‌ها</a>

<div class="salon-cover mb-4">
  <?= salon_cover($salon, true) ?>
</div>

<div class="grid grid-main-aside" style="--gap:24px">
  <div class="stack stack-lg min-w-0">
    <header class="stack stack-sm">
      <div class="cluster"><span class="badge badge--accent"><?= e(term('salon_type')) ?></span><?php if (!empty($salon['neighborhood'])): ?><span class="badge"><?= e($salon['neighborhood']) ?></span><?php endif; ?></div>
      <h1 class="title-lg"><?= e($salon['name']) ?></h1>
      <p class="muted"><?= icon('map-pin', 'icon') ?> <?= e(join_parts([$salon['city'] ?? '', $salon['address'] ?? ''])) ?></p>
      <a class="rating" href="#reviews"><?= icon('star-solid') ?>
        <?php if ((int) $salon['rating_count'] > 0): ?>
          <?= e(fa_num(number_format((float) $salon['rating_avg'], 1))) ?> از ۵ <span class="muted">· <?= e(fa_num($salon['rating_count'])) ?> نظر</span>
        <?php else: ?><span class="muted">هنوز نظری منتشر نشده</span><?php endif; ?>
      </a>
      <?php if (!empty($salon['introduction'])): ?><p><?= nl2br(e($salon['introduction'])) ?></p><?php endif; ?>
      <div class="btn-row mt-2">
        <a class="btn btn--primary btn--lg" href="<?= e(url('s/' . $salon['slug'])) ?>"><?= icon('calendar') ?> رزرو نوبت</a>
        <a class="btn btn--secondary btn--lg" href="tel:<?= e($salon['phone']) ?>"><?= icon('phone') ?> تماس</a>
        <form method="post" action="<?= e(url('salons/view/' . $salon['slug'] . '/favorite')) ?>">
          <?= csrf_field() ?><input type="hidden" name="saved" value="<?= $favorite ? '0' : '1' ?>">
          <button class="btn btn--ghost btn--lg" type="submit" aria-pressed="<?= $favorite ? 'true' : 'false' ?>"><?= icon('heart') ?> <?= $favorite ? 'ذخیره شده' : 'ذخیرهٔ سالن' ?></button>
        </form>
        <?php if ($hasMap): ?>
          <a class="btn btn--ghost btn--lg" target="_blank" rel="noopener noreferrer" href="https://www.openstreetmap.org/?mlat=<?= e((string) $salon['map_lat']) ?>&amp;mlon=<?= e((string) $salon['map_lng']) ?>#map=17/<?= e((string) $salon['map_lat']) ?>/<?= e((string) $salon['map_lng']) ?>"><?= icon('navigation') ?> مسیریابی</a>
        <?php endif; ?>
      </div>
    </header>

    <nav class="tabs" aria-label="بخش‌های صفحه">
      <a class="tab" href="#services">خدمات <span class="badge"><?= e(fa_num($serviceCount)) ?></span></a>
      <a class="tab" href="#team">تیم و ساعت کاری</a>
      <a class="tab" href="#reviews">نظرها</a>
    </nav>

    <section class="section" id="services" aria-labelledby="services-title">
      <div class="section__head"><h2 class="section__title" id="services-title">خدمات و قیمت‌ها</h2></div>
      <?php foreach ($groups as $group): ?>
        <div class="service-group">
          <h3 class="service-group__title"><?= icon(ServiceVisual::icon($group['visual'] ?? 'haircut')) ?><?= e($group['name']) ?></h3>
          <div class="stack stack-sm">
            <?php foreach ($group['services'] as $s): ?>
              <div class="menu-item">
                <?= service_media($s) ?>
                <div class="menu-item__body">
                  <div class="menu-item__row"><strong><?= e($s['name']) ?></strong><span class="service-price"><?= e(price_text((int) $s['price'], (string) $s['price_type'])) ?></span></div>
                  <?php if (!empty($s['description'])): ?><p class="text-sm muted"><?= e($s['description']) ?></p><?php endif; ?>
                  <div class="service-meta"><span><?= icon('clock') ?><?= e(duration_text((int) $s['duration_minutes'])) ?></span><?php if ((int) $s['online_booking'] !== 1): ?><span><?= icon('phone') ?>رزرو تلفنی</span><?php endif; ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <p class="text-sm muted">قیمت ممکن است برای هر <?= e(term('staff')) ?> کمی فرق کند؛ قیمت قطعی در خلاصهٔ رزرو نمایش داده می‌شود.</p>
    </section>

    <section class="section" id="team" aria-labelledby="team-title">
      <h2 class="section__title" id="team-title">تیم سالن</h2>
      <div class="grid-auto" style="--min:200px;--gap:12px">
        <?php foreach ($staff as $member): ?>
          <div class="card card--flat"><div class="card__body row">
            <span class="avatar" style="--avatar-bg:<?= e(staff_color($member['color'])) ?>" aria-hidden="true"><?= e(initial($member['name'])) ?></span>
            <div class="stack stack-xs min-w-0"><strong class="truncate"><?= e($member['name']) ?></strong><span class="text-sm muted truncate"><?= e($member['title'] ?: term('staff')) ?></span></div>
          </div></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="reviews" aria-labelledby="reviews-title">
      <div class="section__head">
        <h2 class="section__title" id="reviews-title">نظر مراجعان</h2>
        <?php if ((int) $salon['rating_count'] > 0): ?><span class="rating"><?= icon('star-solid') ?><?= e(fa_num(number_format((float) $salon['rating_avg'], 1))) ?> از ۵</span><?php endif; ?>
      </div>
      <p class="section__sub">فقط کسی که نوبتش انجام شده می‌تواند نظر بدهد؛ نظرها پیش از انتشار بررسی می‌شوند.</p>
      <?php if ($reviews === []): ?>
        <div class="card card--flat"><?= partial('empty-state', ['icon' => 'message', 'title' => 'هنوز نظری منتشر نشده', 'text' => 'پس از مراجعه، از «نوبت‌های من» می‌توانی تجربه‌ات را ثبت کنی.']) ?></div>
      <?php else: ?>
        <div class="stack stack-sm">
          <?php foreach ($reviews as $review): ?>
            <article class="card card--flat"><div class="card__body stack stack-sm">
              <div class="spread">
                <span class="rating"><?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= (int) $review['rating'] ? icon('star-solid') : icon('star', 'icon star-empty') ?><?php endfor; ?><span class="sr-only"><?= e(fa_num($review['rating'])) ?> از ۵</span></span>
                <span class="text-xs muted"><?= e(trim(explode(' ', (string) ($review['customer_name'] ?? ''))[0]) ?: 'مراجع') ?> · <?= e(jdate($review['created_at'], 'j M Y')) ?></span>
              </div>
              <?php if (!empty($review['comment'])): ?><p><?= e($review['comment']) ?></p><?php endif; ?>
              <details>
                <summary class="btn btn--link btn--sm">گزارش این نظر</summary>
                <form method="post" action="<?= e(url('reviews/' . $review['id'] . '/report')) ?>" class="stack stack-sm mt-2">
                  <?= csrf_field() ?>
                  <div class="field"><label class="field__label" for="rr-<?= (int) $review['id'] ?>">دلیل گزارش</label><input class="input" id="rr-<?= (int) $review['id'] ?>" name="reason" maxlength="300" required></div>
                  <button class="btn btn--secondary btn--sm" type="submit">ثبت گزارش</button>
                </form>
              </details>
            </div></article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="stack" aria-label="ساعت کاری و رزرو">
    <div class="card">
      <div class="card__header"><h2 class="card__title">ساعت کاری</h2></div>
      <div class="card__body">
        <dl class="kv">
          <?php foreach (JalaliCalendar::WEEKDAY_NAMES as $day => $label): $h = $hours[$day] ?? null; ?>
            <div class="kv__row"><dt><?= e($label) ?></dt><dd class="num <?= ($h['is_closed'] ?? 1) ? 'muted' : '' ?>">
              <?php if (!$h): ?>اعلام نشده<?php elseif ($h['is_closed']): ?>تعطیل<?php else: ?>
                <?= e(fa_time($h['opens_at'])) ?> تا <?= e(fa_time($h['closes_at'])) ?>
                <?php if (!empty($h['break_start'])): ?><br><span class="text-xs muted">استراحت <?= e(fa_time($h['break_start'])) ?>–<?= e(fa_time($h['break_end'])) ?></span><?php endif; ?>
              <?php endif; ?>
            </dd></div>
          <?php endforeach; ?>
        </dl>
      </div>
    </div>
    <div class="card card--accent only-desktop"><div class="card__body stack stack-sm">
      <strong>آمادهٔ رزرو؟</strong>
      <p class="text-sm">ساعت‌های آزاد همین حالا را ببین؛ پرداخت پس از انجام خدمت است.</p>
      <a class="btn btn--primary btn--block" href="<?= e(url('s/' . $salon['slug'])) ?>">انتخاب زمان و رزرو</a>
    </div></div>
  </aside>
</div>

<div class="action-bar only-mobile">
  <a class="btn btn--primary btn--lg btn--block" href="<?= e(url('s/' . $salon['slug'])) ?>"><?= icon('calendar') ?> رزرو نوبت در <?= e($salon['name']) ?></a>
</div>
