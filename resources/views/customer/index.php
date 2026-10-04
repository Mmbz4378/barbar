<?php
/**
 * نوبت‌های من.
 *
 * @var string $phone
 * @var array $upcoming
 * @var array $past
 */
use App\Support\JalaliCalendar;

$card = static function (array $a, bool $upcoming): void {
    $when = $a['scheduled_at'] ?: $a['queued_at'];
    $date = new DateTimeImmutable((string) $when);
    [$label, $tone] = status_meta((string) $a['status'], $a['salon_audience'] ?? null);
    $staff = array_values(array_unique($a['staff_names'] ?? []));
    ?>
    <li class="card" data-filter-item="<?= e($a['salon_name'] . ' ' . $a['service_names']) ?>" data-filter-cat="<?= $a['status'] === 'completed' ? 'completed' : ($upcoming ? 'upcoming' : 'other') ?>">
      <div class="card__body stack stack-sm">
        <div class="spread">
          <a class="title-xs truncate" href="<?= e(url('q/' . $a['public_token'])) ?>" style="color:inherit"><?= e($a['salon_name']) ?></a>
          <span class="badge badge--<?= e($tone) ?>"><?= e($label) ?></span>
        </div>
        <p class="row text-sm"><?= icon('calendar-days', 'icon muted') ?><span class="strong"><?= e(JalaliCalendar::relativeDate($date)) ?></span><span class="muted"><?= e(JalaliCalendar::humanDate($date)) ?> · ساعت <?= e(fa_time($date->format('H:i'))) ?></span></p>
        <?php if (!empty($a['service_names'])): ?><p class="text-sm"><?= e($a['service_names']) ?></p><?php endif; ?>
        <p class="text-sm muted">
          <?= e(implode('، ', $staff)) ?>
          <?php if ((int) $a['total_price'] > 0): ?><?= $staff ? ' · ' : '' ?><span class="num"><?= e(toman((int) $a['total_price'])) ?></span><?php endif; ?>
          <?php if ((int) ($a['part_count'] ?? 1) > 1): ?> · <?= e(fa_num($a['part_count'])) ?> بخش<?php endif; ?>
        </p>
        <div class="btn-row mt-2">
          <a class="btn btn--secondary btn--sm" href="<?= e(url('q/' . $a['public_token'])) ?>">کارت نوبت</a>
          <?php if ($upcoming && !empty($a['cancel']['ok'])): ?>
            <form method="post" action="<?= e(url('me/' . (int) $a['id'] . '/cancel')) ?>" data-confirm="این نوبت در «<?= e($a['salon_name']) ?>» لغو شود؟" data-confirm-ok="بله، لغو شود">
              <?= csrf_field() ?><button type="submit" class="btn btn--danger-ghost btn--sm">لغو نوبت</button>
            </form>
          <?php endif; ?>
          <?php if (!$upcoming && in_array($a['status'], ['completed', 'cancelled', 'no_show'], true)): ?>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('s/' . $a['salon_slug'])) ?>"><?= icon('refresh') ?> رزرو دوباره</a>
          <?php endif; ?>
        </div>
        <?php if ($upcoming && empty($a['cancel']['ok']) && !empty($a['cancel']['reason'])): ?>
          <p class="text-xs muted"><?= e($a['cancel']['reason']) ?></p>
        <?php endif; ?>
        <?php if ($a['status'] === 'completed' && empty($a['review_id'])): ?>
          <details class="mt-2">
            <summary class="btn btn--tonal btn--sm" style="list-style:none"><?= icon('star') ?> ثبت نظر دربارهٔ این مراجعه</summary>
            <form method="post" action="<?= e(url('me/' . (int) $a['id'] . '/review')) ?>" class="stack stack-sm mt-3">
              <?= csrf_field() ?>
              <fieldset>
                <legend class="field__label">امتیاز شما</legend>
                <div class="star-input">
                  <?php for ($r = 5; $r >= 1; $r--): ?>
                    <input type="radio" id="r<?= (int) $a['id'] ?>-<?= $r ?>" name="rating" value="<?= $r ?>" required>
                    <label for="r<?= (int) $a['id'] ?>-<?= $r ?>" title="<?= e(fa_num($r)) ?> از ۵"><svg class="icon" aria-hidden="true"><use href="#i-star-solid"></use></svg><span class="sr-only"><?= e(fa_num($r)) ?> از ۵</span></label>
                  <?php endfor; ?>
                </div>
              </fieldset>
              <div class="field">
                <label class="field__label" for="c<?= (int) $a['id'] ?>">تجربهٔ شما <span class="field__optional">(اختیاری)</span></label>
                <textarea class="textarea" id="c<?= (int) $a['id'] ?>" name="comment" maxlength="500" rows="3"></textarea>
              </div>
              <button class="btn btn--primary btn--sm" type="submit">ارسال برای بررسی</button>
            </form>
          </details>
        <?php elseif ($a['status'] === 'completed'): ?>
          <p class="text-xs muted"><?= icon('circle-check', 'icon') ?> نظرت ثبت شده است.</p>
        <?php endif; ?>
      </div>
    </li>
    <?php
};
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">نوبت‌های من</h1>
    <p class="page-head__sub">با شمارهٔ <span class="ltr num"><?= e(phone_local($phone)) ?></span></p>
  </div>
  <a class="btn btn--primary" href="<?= e(url('discover')) ?>"><?= icon('plus') ?> نوبت تازه</a>
</div>

<section class="section" aria-labelledby="up-title">
  <h2 class="section__title" id="up-title">پیشِ رو <?php if ($upcoming): ?><span class="badge badge--accent"><?= e(fa_num(count($upcoming))) ?></span><?php endif; ?></h2>
  <?php if ($upcoming === []): ?>
    <div class="card"><?= partial('empty-state', ['icon' => 'calendar-days', 'title' => 'نوبتی در پیش نداری', 'text' => 'سالن مناسب را پیدا کن و وقت بعدی‌ات را بگیر.', 'actionHref' => url('discover'), 'actionLabel' => 'کشف سالن‌ها']) ?></div>
  <?php else: ?>
    <ul class="stack stack-md" style="list-style:none"><?php foreach ($upcoming as $a) { $card($a, true); } ?></ul>
  <?php endif; ?>
</section>

<?php if ($past !== []): ?>
  <section class="section" aria-labelledby="past-title">
    <div class="section__head">
      <h2 class="section__title" id="past-title">سابقه</h2>
    </div>
    <div class="chips" data-filter="past-list" hidden role="group" aria-label="فیلتر سابقه">
      <button type="button" class="chip" data-filter-chip="all" aria-pressed="true">همه</button>
      <button type="button" class="chip" data-filter-chip="completed" aria-pressed="false">انجام‌شده</button>
      <button type="button" class="chip" data-filter-chip="other" aria-pressed="false">لغو و غیبت</button>
      <span class="sr-only" role="status" aria-live="polite" data-filter-status></span>
    </div>
    <ul class="stack stack-md" style="list-style:none" id="past-list"><?php foreach ($past as $a) { $card($a, false); } ?></ul>
    <div id="past-list-empty" hidden><p class="muted center">موردی در این بخش نیست.</p></div>
  </section>
<?php endif; ?>
