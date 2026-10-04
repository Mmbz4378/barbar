<?php
/**
 * گام آخر — مرور نوبت، شماره و ثبت.
 *
 * @var array $salon
 * @var array $stepper
 * @var array $plan
 * @var array $wizard
 * @var bool $needsVerification
 * @var bool $depositActive
 * @var int $cancelNotice
 * @var array<string,string> $editLinks
 */
use App\Support\JalaliCalendar;

$phoneError = field_error('phone');
$savedPhone = !empty($wizard['phone']) ? '0' . substr((string) $wizard['phone'], 3) : '';
?>
<div class="wizard">
  <div class="wizard__main">
    <?= partial('stepper', ['steps' => $stepper]) ?>

    <div class="step-head">
      <h1 class="step-head__title">مرور و ثبت نوبت</h1>
      <p class="step-head__sub">انتخاب‌ها را بررسی کن و شمارهٔ موبایلت را برای پیگیری و یادآوری بنویس.</p>
    </div>

    <section class="card summary-card mb-4" aria-labelledby="summary-title">
      <div class="card__header"><h2 class="card__title" id="summary-title">خلاصهٔ نوبت</h2></div>
      <div class="card__body">
        <dl class="kv">
          <div class="kv__row">
            <dt>زمان</dt>
            <dd><?= e(JalaliCalendar::relativeDate($plan['start'])) ?>، <?= e(JalaliCalendar::humanDate($plan['start'])) ?> · ساعت <?= e(fa_time($plan['start']->format('H:i'))) ?> <a class="link text-sm" href="<?= e(url($editLinks['time'])) ?>">تغییر<span class="sr-only"> زمان</span></a></dd>
          </div>
          <?php if (!$plan['multi']): ?>
            <div class="kv__row">
              <dt><?= e(term('staff')) ?></dt>
              <dd><?= e($plan['segments'][0]['staff_name']) ?><?= empty($wizard['staff_id']) ? ' <span class="muted text-sm">(انتخاب خودکار)</span>' : '' ?> <a class="link text-sm" href="<?= e(url($editLinks['staff'])) ?>">تغییر<span class="sr-only"> <?= e(term('staff')) ?></span></a></dd>
            </div>
          <?php endif; ?>
          <div class="kv__row">
            <dt>مدت تقریبی</dt>
            <dd><?= e(duration_text($plan['minutes'])) ?> · تا حدود <?= e(fa_time($plan['end']->format('H:i'))) ?></dd>
          </div>
        </dl>

        <?php if ($plan['multi']): ?>
          <div class="alert alert--accent mt-3"><?= icon('users') ?><div class="alert__body">این خدمات را چند <?= e(term('staff')) ?> پشت سر هم انجام می‌دهند. ترتیب و زمان هر بخش:</div></div>
          <ol class="timeline mt-3">
            <?php foreach ($plan['segments'] as $seg): ?>
              <li class="timeline__item">
                <span class="timeline__time"><?= e(fa_time($seg['start']->format('H:i'))) ?></span>
                <span class="timeline__line" aria-hidden="true"></span>
                <div class="timeline__body">
                  <div class="strong"><?= e(implode('، ', array_column($seg['items'], 'name'))) ?></div>
                  <div class="text-sm muted"><?= e($seg['staff_name']) ?> · <?= e(duration_text($seg['minutes'])) ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>

        <hr class="divider">
        <dl class="kv">
          <?php foreach ($plan['segments'] as $seg): foreach ($seg['items'] as $item): ?>
            <div class="kv__row"><dt><?= e($item['name']) ?></dt><dd class="num"><?= e(price_text((int) $item['price'], $item['price_type'])) ?></dd></div>
          <?php endforeach; endforeach; ?>
          <div class="kv__row kv__row--total"><dt>جمع<?= $plan['price_from'] ? ' (حداقل)' : '' ?></dt><dd class="num"><?= e(price_text($plan['price'], $plan['price_from'] ? 'from' : 'fixed')) ?></dd></div>
        </dl>
        <p class="text-sm muted mt-2"><a class="link" href="<?= e(url($editLinks['services'])) ?>">تغییر خدمات</a></p>
      </div>
    </section>

    <?php if ($depositActive): ?>
      <div class="alert alert--warning mb-4" role="note">
        <?= icon('wallet') ?>
        <div class="alert__body">
          <span class="alert__title">این نوبت بیعانه دارد: <?= e(toman($plan['deposit'])) ?></span>
          پس از ثبت، شمارهٔ کارت سالن نمایش داده می‌شود. نوبت تا تأیید دریافت بیعانه نگه داشته می‌شود و اگر در مهلت پرداخت نشود، خودکار آزاد می‌شود.
        </div>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('s/' . $salon['slug'] . '/phone')) ?>" class="stack" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label class="field__label" for="bk-phone">شمارهٔ موبایل</label>
        <input class="input input--lg input--ltr num" id="bk-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required dir="ltr"
               placeholder="09123456789" value="<?= e((string) old('phone', $savedPhone)) ?>" data-numeric
               aria-describedby="bk-phone-hint<?= $phoneError ? ' phone-error' : '' ?>" <?= $phoneError ? 'aria-invalid="true"' : '' ?>>
        <p class="field__hint" id="bk-phone-hint">برای پیامک تأیید و یادآوری. به هیچ‌کس جز همین سالن داده نمی‌شود.</p>
        <?= partial('field-error', ['key' => 'phone']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="bk-name">نام <span class="field__optional">(اختیاری)</span></label>
        <input class="input" id="bk-name" name="name" type="text" maxlength="120" autocomplete="name" value="<?= e((string) old('name', $wizard['name'] ?? '')) ?>">
      </div>
      <div class="field">
        <label class="field__label" for="bk-note">توضیح برای سالن <span class="field__optional">(اختیاری)</span></label>
        <textarea class="textarea" id="bk-note" name="note" maxlength="300" rows="2" placeholder="مثلاً حساسیت پوستی، مدل موردنظر یا هر نکتهٔ دیگر"><?= e((string) old('note', $wizard['note'] ?? '')) ?></textarea>
      </div>

      <div class="action-bar">
        <p class="action-bar__summary"><span>جمع</span><strong class="num"><?= e(price_text($plan['price'], $plan['price_from'] ? 'from' : 'fixed')) ?></strong></p>
        <button type="submit" class="btn btn--primary btn--lg btn--block"><?= $needsVerification ? 'ارسال کد تأیید' : 'تأیید و ثبت نوبت' ?></button>
        <p class="text-xs muted center">
          با ثبت، نوبت برای شما نگه داشته می‌شود؛ پرداخت پس از انجام خدمت است.
          <?= $cancelNotice > 0 ? 'لغو آنلاین تا ' . e(duration_text($cancelNotice)) . ' پیش از نوبت ممکن است.' : 'لغو آنلاین تا پیش از شروع نوبت ممکن است.' ?>
        </p>
      </div>
    </form>
  </div>
  <?php include __DIR__ . '/_aside.php'; ?>
</div>
