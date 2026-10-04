<?php
/**
 * گام انتخاب فرد.
 *
 * @var array $salon
 * @var array $stepper
 * @var array $context
 * @var array $staff
 * @var array<int,array{price:int,minutes:int}> $prices
 * @var ?int $selectedStaffId
 * @var bool $timeKnown
 */
include __DIR__ . '/_next.php';
?>
<div class="wizard">
  <div class="wizard__main">
    <?= partial('stepper', ['steps' => $stepper]) ?>
    <?= partial('context-chips', ['chips' => $context]) ?>

    <div class="step-head">
      <h1 class="step-head__title"><?= e(term('staff_question')) ?></h1>
      <p class="step-head__sub">
        <?= $timeKnown
            ? e(fa_num(count($staff))) . ' نفر در زمان انتخابی آزادند.'
            : 'کسانی که همهٔ خدمات انتخابی را انجام می‌دهند.' ?>
        قیمت و مدت ممکن است برای هر نفر کمی فرق کند.
      </p>
    </div>

    <form method="post" action="<?= e(url('s/' . $salon['slug'] . '/staff')) ?>" data-live-summary>
      <?= csrf_field() ?>
      <fieldset class="choice-list">
        <legend class="sr-only"><?= e(term('staff_question')) ?></legend>
        <label class="choice">
          <input class="choice__input" type="radio" name="staff_id" value="" data-label="<?= e(term('staff_any')) ?>" <?= $selectedStaffId === null ? 'checked' : '' ?>>
          <span class="choice__card">
            <span class="avatar avatar--any"><?= icon('users') ?></span>
            <span class="choice__body">
              <span class="choice__title"><?= e(term('staff_any')) ?></span>
              <span class="choice__meta"><?= e(term('staff_any_hint')) ?></span>
            </span>
            <span class="choice__mark" aria-hidden="true"><?= icon('check') ?></span>
          </span>
        </label>
        <?php foreach ($staff as $member): $p = $prices[(int) $member['id']] ?? null; ?>
          <label class="choice">
            <input class="choice__input" type="radio" name="staff_id" value="<?= (int) $member['id'] ?>" data-label="<?= e($member['name']) ?>" <?= $selectedStaffId === (int) $member['id'] ? 'checked' : '' ?>>
            <span class="choice__card">
              <span class="avatar" style="--avatar-bg:<?= e(staff_color($member['color'] ?? null)) ?>" aria-hidden="true"><?= e(initial($member['name'])) ?></span>
              <span class="choice__body">
                <span class="choice__title"><?= e($member['name']) ?></span>
                <?php if (!empty($member['title'])): ?><span class="choice__meta"><?= e($member['title']) ?></span><?php endif; ?>
                <?php if ($p !== null): ?><span class="service-meta"><span><?= icon('clock') ?><?= e(duration_text($p['minutes'])) ?></span><span class="service-price"><?= e(toman($p['price'])) ?></span></span><?php endif; ?>
              </span>
              <span class="choice__mark" aria-hidden="true"><?= icon('check') ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <div class="action-bar">
        <p class="action-bar__summary" role="status" aria-live="polite"><span data-summary data-empty="یک گزینه انتخاب کن">یک گزینه انتخاب کن</span></p>
        <button type="submit" class="btn btn--primary btn--lg btn--block"><?= e($nextLabel) ?> <?= icon('chevron-end') ?></button>
      </div>
    </form>
  </div>
  <?php include __DIR__ . '/_aside.php'; ?>
</div>
