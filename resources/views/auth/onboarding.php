<?php /** @var array $draft */ use App\Support\Audience; ?>
<div class="stack">
  <div class="stack stack-xs">
    <span class="eyebrow">گام ۱ از ۲</span>
    <h1 class="title-md">سالن‌تان را بسازید</h1>
    <p class="text-sm muted">کمتر از دو دقیقه. همهٔ این‌ها را بعداً هم در تنظیمات می‌توانید تغییر دهید.</p>
  </div>
  <form method="post" action="<?= e(url('onboarding')) ?>" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label class="field__label" for="name">نام سالن</label>
      <input class="input" id="name" name="name" required autofocus maxlength="150" placeholder="مثلاً آرایشگاه پارسا یا سالن زیبایی گلاره" value="<?= e((string) old('name', $draft['name'] ?? '')) ?>" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
      <?= partial('field-error', ['key' => 'name']) ?>
    </div>

    <fieldset class="field">
      <legend class="field__label mb-2">نوع سالن</legend>
      <div class="choice-grid" style="--min:170px">
        <?php $current = (string) old('audience', $draft['audience'] ?? ''); ?>
        <?php foreach ([['men', 'آرایشگاه مردانه', 'scissors', 'کوتاهی، ریش، صورت'], ['women', 'سالن زیبایی بانوان', 'sparkles', 'مو، رنگ، ناخن، مژه، میکاپ'], ['unisex', 'هر دو', 'users', 'خدمات آقایان و بانوان']] as [$key, $label, $symbol, $hint]): ?>
          <label class="choice">
            <input class="choice__input" type="radio" name="audience" value="<?= $key ?>" required <?= $current === $key ? 'checked' : '' ?>>
            <span class="choice__card" style="flex-direction:column;align-items:flex-start;gap:8px">
              <span class="icon-tile"><?= icon($symbol) ?></span>
              <span class="choice__title"><?= e($label) ?></span>
              <span class="choice__meta"><?= e($hint) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= partial('field-error', ['key' => 'audience']) ?>
    </fieldset>

    <div class="form-grid form-grid--2">
      <div class="field">
        <label class="field__label" for="city">شهر</label>
        <input class="input" id="city" name="city" maxlength="80" autocomplete="address-level2" value="<?= e((string) old('city', $draft['city'] ?? '')) ?>">
      </div>
      <div class="field">
        <label class="field__label" for="phone">تلفن سالن <span class="field__optional">(اختیاری)</span></label>
        <input class="input input--ltr num" id="phone" name="phone" type="tel" inputmode="tel" maxlength="15" dir="ltr" value="<?= e((string) old('phone', $draft['phone'] ?? '')) ?>" data-numeric>
      </div>
    </div>
    <div class="field">
      <label class="field__label" for="address">نشانی <span class="field__optional">(اختیاری)</span></label>
      <input class="input" id="address" name="address" maxlength="255" autocomplete="street-address" value="<?= e((string) old('address', $draft['address'] ?? '')) ?>">
    </div>

    <div class="field">
      <label class="field__label" for="owner_name">نام شما</label>
      <input class="input" id="owner_name" name="owner_name" maxlength="120" autocomplete="name" value="<?= e((string) old('owner_name', $draft['owner_name'] ?? (App\Core\Auth::user()['name'] ?? ''))) ?>" <?= field_error('owner_name') ? 'aria-invalid="true" aria-describedby="owner_name-error"' : '' ?>>
      <p class="field__hint">در پنل نمایش داده می‌شود؛ اگر خودتان هم کار می‌کنید، مشتری همین نام را هنگام رزرو می‌بیند.</p>
      <?= partial('field-error', ['key' => 'owner_name']) ?>
    </div>

    <label class="check">
      <input type="checkbox" name="owner_works" value="1" <?= old('owner_works', $draft['owner_works'] ?? '') ? 'checked' : '' ?>>
      <span class="check__text"><span class="strong">خودم هم خدمت ارائه می‌دهم</span><span class="check__hint">در فهرست افراد قابل رزرو قرار می‌گیرید.</span></span>
    </label>

    <button type="submit" class="btn btn--primary btn--lg btn--block">ادامه: انتخاب خدمات <?= icon('chevron-end') ?></button>
  </form>
</div>
