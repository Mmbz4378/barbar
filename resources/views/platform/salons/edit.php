<?php
/** @var array $salon */
use App\Http\Controllers\PlatformSalonController as PSC;
use App\Support\Audience;

$v = static fn (string $key) => old($key, (string) ($salon[$key] ?? ''));
?>
<a class="back-link" href="<?= e(url('platform/salons/' . $salon['id'])) ?>"><?= icon('chevron-start') ?> <?= e($salon['name']) ?></a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">ویرایش سالن</h1>
    <p class="page-head__sub">مشخصات اصلی، طرح و وضعیت. خدمات، کارکنان و ساعت کاری را صاحب سالن از پنل خودش تنظیم می‌کند (یا شما با «ورود به پنل سالن»).</p>
  </div>
</div>

<form method="post" action="<?= e(url('platform/salons/' . $salon['id'])) ?>" class="stack stack-lg container-md">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <div class="field">
      <label class="field__label" for="name">نام سالن</label>
      <input class="input" id="name" name="name" required maxlength="150" value="<?= e((string) $v('name')) ?>" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
      <?= partial('field-error', ['key' => 'name']) ?>
    </div>
    <div class="field">
      <label class="field__label" for="slug">نشانی صفحهٔ رزرو</label>
      <div class="input-group" dir="ltr"><span class="input-group__addon">/s/</span><input class="input" id="slug" name="slug" required maxlength="60" dir="ltr" autocapitalize="none" spellcheck="false" value="<?= e((string) $v('slug')) ?>" aria-describedby="slug-hint<?= field_error('slug') ? ' slug-error' : '' ?>" <?= field_error('slug') ? 'aria-invalid="true"' : '' ?>></div>
      <p class="field__hint" id="slug-hint">با تغییرش، لینک‌ها و QR قبلی کار نمی‌کنند.</p>
      <?= partial('field-error', ['key' => 'slug']) ?>
    </div>
    <div class="grid-auto" style="--min:200px">
      <div class="field">
        <label class="field__label" for="audience">نوع سالن</label>
        <select class="select" id="audience" name="audience"><?php foreach (Audience::options() as $key => $label): ?><option value="<?= e($key) ?>" <?= $v('audience') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </div>
      <div class="field"><label class="field__label" for="city">شهر</label><input class="input" id="city" name="city" maxlength="80" value="<?= e((string) $v('city')) ?>"></div>
      <div class="field"><label class="field__label" for="phone">تلفن</label><input class="input input--ltr num" id="phone" name="phone" type="tel" dir="ltr" value="<?= e((string) $v('phone')) ?>" data-numeric></div>
      <div class="field"><label class="field__label" for="seats">تعداد صندلی</label><input class="input num" id="seats" name="seats" inputmode="numeric" value="<?= e((string) $v('seats')) ?>" data-numeric></div>
    </div>
    <div class="field"><label class="field__label" for="address">نشانی</label><input class="input" id="address" name="address" maxlength="255" value="<?= e((string) $v('address')) ?>"></div>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">طرح و وضعیت</h2>
    <div class="grid-auto" style="--min:200px">
      <div class="field">
        <label class="field__label" for="plan_code">طرح</label>
        <select class="select" id="plan_code" name="plan_code"><?php foreach (PSC::PLANS as $key => $label): ?><option value="<?= e($key) ?>" <?= $v('plan_code') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </div>
      <div class="field">
        <label class="field__label" for="publication_status">انتشار در صفحهٔ کشف</label>
        <select class="select" id="publication_status" name="publication_status"><?php foreach (PSC::PUBLICATION as $key => $label): ?><option value="<?= e($key) ?>" <?= $v('publication_status') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <?= partial('jalali-date-input', ['name' => 'trial_ends', 'value' => !empty($salon['trial_ends_at']) ? substr((string) $salon['trial_ends_at'], 0, 10) : null, 'label' => 'پایان دورهٔ آزمایشی (فقط برای طرح آزمایشی)', 'years' => [-1, 3]]) ?>
    <label class="check"><input type="checkbox" name="is_active" value="1" <?= (int) $salon['is_active'] === 1 ? 'checked' : '' ?>><span class="check__text"><span class="strong">سالن فعال است</span><span class="check__hint">خاموش: صفحهٔ رزرو و پنل سالن بسته می‌شود؛ داده‌ها می‌مانند.</span></span></label>
  </div></section>

  <div class="btn-row">
    <button class="btn btn--primary btn--lg" type="submit">ذخیره</button>
    <a class="btn btn--ghost" href="<?= e(url('platform/salons/' . $salon['id'])) ?>">انصراف</a>
  </div>
</form>
