<?php
use App\Http\Controllers\PlatformSalonController as PSC;

$ownerMode = (string) old('owner_mode', 'new');
?>
<a class="back-link" href="<?= e(url('platform/salons')) ?>"><?= icon('chevron-start') ?> همهٔ سالن‌ها</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">سالن تازه</h1>
    <p class="page-head__sub">سالن و حساب صاحبش را یک‌جا بسازید. صاحب سالن با نام کاربری (یا موبایل) و رمز وارد پنل خودش می‌شود.</p>
  </div>
</div>

<form method="post" action="<?= e(url('platform/salons')) ?>" class="stack stack-lg container-md">
  <?= csrf_field() ?>

  <section class="card" aria-labelledby="ns-salon"><div class="card__body stack">
    <h2 class="title-sm" id="ns-salon">۱. مشخصات سالن</h2>
    <div class="field">
      <label class="field__label" for="name">نام سالن</label>
      <input class="input" id="name" name="name" required maxlength="150" value="<?= e((string) old('name')) ?>" <?= field_error('name') ? 'aria-invalid="true" aria-describedby="name-error"' : '' ?>>
      <?= partial('field-error', ['key' => 'name']) ?>
    </div>
    <fieldset class="field">
      <legend class="field__label mb-2">نوع سالن</legend>
      <div class="choice-grid" style="--min:170px">
        <?php $current = (string) old('audience', ''); ?>
        <?php foreach ([['men', 'آرایشگاه مردانه', 'scissors', 'کوتاهی، ریش، صورت'], ['women', 'سالن زیبایی بانوان', 'sparkles', 'مو، رنگ، ناخن، مژه، میکاپ'], ['unisex', 'هر دو', 'users', 'خدمات آقایان و بانوان']] as [$key, $label, $symbol, $hint]): ?>
          <label class="choice">
            <input class="choice__input" type="radio" name="audience" value="<?= $key ?>" required <?= $current === $key ? 'checked' : '' ?>>
            <span class="choice__card choice__card--stacked">
              <span class="icon-tile"><?= icon($symbol) ?></span>
              <span class="choice__title"><?= e($label) ?></span>
              <span class="choice__meta"><?= e($hint) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <?= partial('field-error', ['key' => 'audience']) ?>
    </fieldset>
    <div class="grid-auto" style="--min:200px">
      <div class="field"><label class="field__label" for="city">شهر <span class="field__optional">(اختیاری)</span></label><input class="input" id="city" name="city" maxlength="80" value="<?= e((string) old('city')) ?>"></div>
      <div class="field"><label class="field__label" for="phone">تلفن سالن <span class="field__optional">(اختیاری)</span></label><input class="input input--ltr num" id="phone" name="phone" type="tel" dir="ltr" value="<?= e((string) old('phone')) ?>" data-numeric></div>
    </div>
    <div class="field"><label class="field__label" for="address">نشانی <span class="field__optional">(اختیاری)</span></label><input class="input" id="address" name="address" maxlength="255" value="<?= e((string) old('address')) ?>"></div>
  </div></section>

  <section class="card" aria-labelledby="ns-owner"><div class="card__body stack">
    <h2 class="title-sm" id="ns-owner">۲. صاحب سالن</h2>
    <fieldset class="field">
      <legend class="sr-only">حساب صاحب سالن</legend>
      <div class="choice-grid" style="--min:200px">
        <label class="choice"><input class="choice__input" type="radio" name="owner_mode" value="new" <?= $ownerMode !== 'existing' ? 'checked' : '' ?>><span class="choice__card"><span class="choice__body"><span class="choice__title">حساب تازه</span><span class="choice__meta">برای کسی که هنوز در سامانه حساب ندارد</span></span><span class="choice__mark"><?= icon('check') ?></span></span></label>
        <label class="choice"><input class="choice__input" type="radio" name="owner_mode" value="existing" <?= $ownerMode === 'existing' ? 'checked' : '' ?>><span class="choice__card"><span class="choice__body"><span class="choice__title">کاربر موجود</span><span class="choice__meta">مثلاً صاحبِ سالن دیگری در همین سامانه</span></span><span class="choice__mark"><?= icon('check') ?></span></span></label>
      </div>
    </fieldset>

    <details class="disclosure" <?= $ownerMode !== 'existing' ? 'open' : '' ?>>
      <summary>اطلاعات حساب تازه</summary>
      <div class="stack mt-3">
        <?= App\Core\View::render('platform._account-fields', ['prefix' => 'owner_', 'mustChangeDefault' => true]) ?>
      </div>
    </details>
    <details class="disclosure" <?= $ownerMode === 'existing' ? 'open' : '' ?>>
      <summary>کاربر موجود</summary>
      <div class="field mt-3">
        <label class="field__label" for="owner_identifier">موبایل یا نام کاربری</label>
        <input class="input input--ltr" id="owner_identifier" name="owner_identifier" dir="ltr" autocapitalize="none" spellcheck="false" value="<?= e((string) old('owner_identifier')) ?>" <?= field_error('owner_identifier') ? 'aria-invalid="true" aria-describedby="owner_identifier-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'owner_identifier']) ?>
      </div>
    </details>

    <label class="check"><input type="checkbox" name="owner_works" value="1" <?= old('owner_works') === '1' ? 'checked' : '' ?>><span class="check__text"><span class="strong">صاحب سالن خودش هم کار می‌کند</span><span class="check__hint">به فهرست کارکنان اضافه می‌شود تا نوبت بگیرد.</span></span></label>
  </div></section>

  <section class="card" aria-labelledby="ns-plan"><div class="card__body stack">
    <h2 class="title-sm" id="ns-plan">۳. طرح، اعتبار و خدمات</h2>
    <div class="grid-auto" style="--min:180px">
      <div class="field">
        <label class="field__label" for="plan_code">طرح</label>
        <select class="select" id="plan_code" name="plan_code">
          <?php foreach (PSC::PLANS as $key => $label): ?><option value="<?= e($key) ?>" <?= old('plan_code', 'trial') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="field__label" for="trial_days">مدت آزمایشی (روز)</label>
        <input class="input num" id="trial_days" name="trial_days" inputmode="numeric" value="<?= e((string) old('trial_days', '30')) ?>" data-numeric>
      </div>
      <div class="field">
        <label class="field__label" for="sms_credit">اعتبار اولیهٔ پیامک</label>
        <input class="input num" id="sms_credit" name="sms_credit" inputmode="numeric" value="<?= e((string) old('sms_credit', '200')) ?>" data-numeric>
      </div>
    </div>
    <label class="check"><input type="checkbox" name="seed_services" value="1" <?= old('seed_services', '1') === '1' ? 'checked' : '' ?>><span class="check__text"><span class="strong">خدمات پیشنهادی همین نوع سالن اضافه شود</span><span class="check__hint">بی‌قیمت و غیرفعال؛ صاحب سالن قیمت می‌گذارد و فعالشان می‌کند. ساعت کاری پیش‌فرض ۹ تا ۲۱ است.</span></span></label>
  </div></section>

  <div class="btn-row">
    <button class="btn btn--primary btn--lg" type="submit"><?= icon('plus') ?> ساخت سالن</button>
    <a class="btn btn--ghost" href="<?= e(url('platform/salons')) ?>">انصراف</a>
  </div>
</form>
