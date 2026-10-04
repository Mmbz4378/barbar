<?php
/**
 * @var array $salons  هرکدام با checks
 * @var array $reviews
 */
use App\Support\Audience;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">بررسی انتشار و نظرها</h1>
    <p class="page-head__sub">سالن‌های منتظر انتشار و نظرهای تازه یا گزارش‌شده.</p>
  </div>
</div>

<div class="stack stack-xl">
  <section class="section" aria-labelledby="mod-salons">
    <div class="section__head"><h2 class="section__title" id="mod-salons">درخواست انتشار <span class="badge"><?= e(fa_num(count($salons))) ?></span></h2></div>
    <?php if ($salons === []): ?>
      <?= partial('empty-state', ['icon' => 'store', 'title' => 'درخواستی در صف نیست', 'dashed' => true]) ?>
    <?php else: ?>
      <div class="grid-auto" style="--min:340px">
        <?php foreach ($salons as $s): $ready = !in_array(false, $s['checks'], true); ?>
          <article class="card"><div class="card__body stack">
            <div class="spread">
              <div class="stack" style="gap:0">
                <a class="strong" href="<?= e(url('platform/' . $s['id'])) ?>"><?= e($s['name']) ?></a>
                <span class="text-sm muted"><?= e(Audience::options()[$s['audience']] ?? '') ?> · <?= e(trim(($s['city'] ?? '') . ' ' . ($s['neighborhood'] ?? ''))) ?: 'بدون شهر' ?></span>
              </div>
              <a class="btn btn--ghost btn--icon" href="<?= e(url('s/' . $s['slug'])) ?>" target="_blank" rel="noopener" aria-label="دیدن صفحهٔ رزرو <?= e($s['name']) ?>"><?= icon('external') ?></a>
            </div>
            <?php if (!empty($s['introduction'])): ?><p class="text-sm clamp-2"><?= e($s['introduction']) ?></p><?php endif; ?>
            <p class="text-xs muted"><?= e(fa_num((int) $s['service_count'])) ?> خدمت فعال · <?= e(fa_num((int) $s['staff_count'])) ?> <?= e(term('staff', $s['audience'])) ?> · <?= !empty($s['cover_path']) ? 'عکس دارد' : 'بدون عکس' ?></p>
            <div class="chips chips--wrap">
              <?php foreach ($s['checks'] as $label => $ok): ?>
                <span class="badge <?= $ok ? 'badge--success' : 'badge--danger' ?>"><?= icon($ok ? 'check' : 'x') ?><?= e($label) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="btn-row">
              <form method="post" action="<?= e(url('platform/moderation')) ?>"><?= csrf_field() ?><input type="hidden" name="type" value="salon"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="decision" value="approve">
                <button class="btn btn--success btn--sm" type="submit" <?= $ready ? '' : 'disabled title="پیش‌نیازها کامل نیست"' ?>><?= icon('check') ?> تأیید و انتشار</button></form>
              <form method="post" action="<?= e(url('platform/moderation')) ?>" data-confirm="درخواست انتشار «<?= e($s['name']) ?>» رد شود؟"><?= csrf_field() ?><input type="hidden" name="type" value="salon"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="decision" value="reject">
                <button class="btn btn--danger-ghost btn--sm" type="submit">رد درخواست</button></form>
            </div>
          </div></article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="section" aria-labelledby="mod-reviews">
    <div class="section__head"><h2 class="section__title" id="mod-reviews">نظرها <span class="badge"><?= e(fa_num(count($reviews))) ?></span></h2></div>
    <?php if ($reviews === []): ?>
      <?= partial('empty-state', ['icon' => 'star', 'title' => 'نظری برای بررسی نیست', 'dashed' => true]) ?>
    <?php else: ?>
      <div class="stack stack-sm">
        <?php foreach ($reviews as $r): ?>
          <article class="card"><div class="card__body stack stack-sm">
            <div class="spread">
              <span class="rating"><?php for ($i = 1; $i <= 5; $i++): ?><?= $i <= (int) $r['rating'] ? icon('star-solid') : icon('star', 'icon star-empty') ?><?php endfor; ?><span class="sr-only"><?= e(fa_num((int) $r['rating'])) ?> از ۵</span></span>
              <span class="text-xs muted"><?= e($r['salon_name']) ?> · <?= e(jdate($r['created_at'], 'Y/m/d')) ?></span>
            </div>
            <p><?= $r['comment'] !== null && $r['comment'] !== '' ? nl2br(e($r['comment'])) : '<span class="muted">بدون متن</span>' ?></p>
            <div class="cluster">
              <?php if ($r['moderation_status'] === 'pending'): ?><span class="badge badge--warning">تازه</span><?php endif; ?>
              <?php if ((int) $r['report_count'] > 0): ?><span class="badge badge--danger"><?= icon('alert') ?> <?= e(fa_num((int) $r['report_count'])) ?> گزارش</span><?php endif; ?>
              <span class="ms-auto btn-row">
                <form method="post" action="<?= e(url('platform/moderation')) ?>"><?= csrf_field() ?><input type="hidden" name="type" value="review"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="approve">
                  <button class="btn btn--secondary btn--sm" type="submit"><?= icon('check') ?> <?= $r['moderation_status'] === 'published' ? 'ماندن' : 'انتشار' ?></button></form>
                <form method="post" action="<?= e(url('platform/moderation')) ?>"><?= csrf_field() ?><input type="hidden" name="type" value="review"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="decision" value="reject">
                  <button class="btn btn--danger-ghost btn--sm" type="submit">پنهان کردن</button></form>
              </span>
            </div>
          </div></article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
