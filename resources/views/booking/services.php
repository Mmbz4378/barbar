<?php
/**
 * گام خدمت.
 *
 * @var array $salon
 * @var array $stepper
 * @var array $context
 * @var array $groups  خدمات به تفکیک دسته
 * @var int[] $selected
 */
use App\Support\ServiceVisual;

include __DIR__ . '/_next.php';
$all = array_merge(...array_map(static fn ($g) => $g['services'], $groups ?: [['services' => []]]));
$online = array_values(array_filter($all, static fn ($s) => (int) $s['online_booking'] === 1));
$phoneOnly = array_values(array_filter($all, static fn ($s) => (int) $s['online_booking'] !== 1));
$visuals = [];
foreach ($groups as $g) {
    if ($g['visual'] !== null) { $visuals[$g['id']] = $g['name']; }
}
?>
<div class="wizard">
  <div class="wizard__main">
    <?= partial('stepper', ['steps' => $stepper]) ?>
    <?= partial('context-chips', ['chips' => $context]) ?>

    <div class="step-head">
      <h1 class="step-head__title">چه خدمتی می‌خواهی؟</h1>
      <p class="step-head__sub">می‌توانی چند خدمت را با هم انتخاب کنی. قیمت پایه نمایش داده می‌شود؛ قیمت قطعی در خلاصهٔ نوبت می‌آید.</p>
    </div>

    <?php if ($online === []): ?>
      <div class="card card--flat">
        <?= partial('empty-state', [
            'icon' => 'tag',
            'title' => 'فعلاً خدمتی برای رزرو آنلاین نیست',
            'text' => !empty($salon['phone']) ? 'برای رزرو با سالن تماس بگیر: ' . fa_num($salon['phone']) : 'لطفاً بعداً دوباره سر بزن.',
        ]) ?>
      </div>
    <?php else: ?>
      <?php if (count($online) > 6): ?>
        <div class="search-tools" data-filter="service-list" hidden>
          <label class="input-search">
            <?= icon('search') ?>
            <span class="sr-only">جست‌وجوی خدمت</span>
            <input class="input" type="search" placeholder="جست‌وجوی خدمت…" autocomplete="off">
          </label>
          <?php if (count($visuals) > 1): ?>
            <div class="chips" role="group" aria-label="دسته‌بندی خدمات">
              <button type="button" class="chip" data-filter-chip="all" aria-pressed="true">همه</button>
              <?php foreach ($visuals as $id => $name): ?>
                <button type="button" class="chip" data-filter-chip="<?= (int) $id ?>" aria-pressed="false"><?= e($name) ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <p class="sr-only" role="status" aria-live="polite" data-filter-status></p>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('s/' . $salon['slug'] . '/services')) ?>" data-live-summary>
        <?= csrf_field() ?>
        <div id="service-list">
          <?php foreach ($groups as $group):
              $items = array_values(array_filter($group['services'], static fn ($s) => (int) $s['online_booking'] === 1));
              if ($items === []) { continue; } ?>
            <fieldset class="service-group" data-filter-group>
              <legend class="service-group__title"><?= icon(ServiceVisual::icon($group['visual'] ?? 'haircut')) ?><?= e($group['name']) ?></legend>
              <div class="choice-list">
                <?php foreach ($items as $i => $s): ?>
                  <label class="choice choice--check service-choice" data-filter-item="<?= e($s['name'] . ' ' . ($s['description'] ?? '') . ' ' . $group['name']) ?>" data-filter-cat="<?= (int) ($group['id'] ?? 0) ?>">
                    <input class="choice__input" type="checkbox" name="service_ids[]" value="<?= (int) $s['id'] ?>"
                           data-price="<?= (int) $s['price'] ?>" data-minutes="<?= (int) $s['duration_minutes'] ?>" <?= $s['price_type'] === 'from' ? 'data-from' : '' ?>
                           <?= in_array((int) $s['id'], $selected, true) ? 'checked' : '' ?>>
                    <span class="choice__card">
                      <?= service_media($s, 'service-thumb', $i < 3) ?>
                      <span class="choice__body">
                        <span class="choice__title"><?= e($s['name']) ?></span>
                        <?php if (!empty($s['description'])): ?><span class="choice__meta clamp-2"><?= e($s['description']) ?></span><?php endif; ?>
                        <span class="service-meta">
                          <span><?= icon('clock') ?><?= e(duration_text((int) $s['duration_minutes'])) ?></span>
                          <?php if (!empty($s['deposit_amount'])): ?><span><?= icon('wallet') ?>بیعانه <?= e(toman((int) $s['deposit_amount'])) ?></span><?php endif; ?>
                        </span>
                        <span class="service-price"><?= e(price_text((int) $s['price'], (string) $s['price_type'])) ?></span>
                      </span>
                      <span class="choice__mark" aria-hidden="true"><?= icon('check') ?></span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>
          <?php endforeach; ?>
          <div id="service-list-empty" hidden>
            <?= partial('empty-state', ['icon' => 'search', 'title' => 'خدمتی با این عنوان پیدا نشد', 'text' => 'عبارت دیگری را امتحان کن.']) ?>
          </div>
        </div>

        <div class="action-bar">
          <p class="action-bar__summary" role="status" aria-live="polite"><span data-summary data-empty="دست‌کم یک خدمت انتخاب کن">دست‌کم یک خدمت انتخاب کن</span></p>
          <button type="submit" class="btn btn--primary btn--lg btn--block" data-summary-submit><?= e($nextLabel) ?> <?= icon('chevron-end') ?></button>
        </div>
      </form>
    <?php endif; ?>

    <?php if ($phoneOnly !== []): ?>
      <section class="section mt-8" aria-labelledby="phone-only-title">
        <h2 class="section__title" id="phone-only-title">با هماهنگی تلفنی</h2>
        <p class="section__sub">این خدمات به مشاوره یا هماهنگی پیش از رزرو نیاز دارند.</p>
        <ul class="card list">
          <?php foreach ($phoneOnly as $s): ?>
            <li class="list-row">
              <?= service_media($s, 'service-thumb service-thumb--sm') ?>
              <span class="list-row__body"><span class="list-row__title"><?= e($s['name']) ?></span><span class="list-row__meta"><?= e(duration_text((int) $s['duration_minutes'])) ?> · <?= e(price_text((int) $s['price'], (string) $s['price_type'])) ?></span></span>
              <?php if (!empty($salon['phone'])): ?><a class="btn btn--secondary btn--sm" href="tel:<?= e($salon['phone']) ?>"><?= icon('phone') ?> تماس</a><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
  <?php include __DIR__ . '/_aside.php'; ?>
</div>
