<?php use App\Domain\System\SiteSettings as S; $logo = S::logoPath(); ?>
<form method="post" action="<?= e(url('platform/settings/brand')) ?>" enctype="multipart/form-data" class="stack stack-lg">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">نام و شعار</h2>
    <div class="field">
      <label class="field__label" for="name">نام سامانه</label>
      <input class="input" id="name" name="name" required maxlength="60" value="<?= e((string) old('name', S::brandName())) ?>" aria-describedby="name-hint<?= field_error('name') ? ' name-error' : '' ?>" <?= field_error('name') ? 'aria-invalid="true"' : '' ?>>
      <p class="field__hint" id="name-hint">در عنوان صفحه‌ها، پیامک کد ورود، پانویس و اپ نصب‌شده.</p>
      <?= partial('field-error', ['key' => 'name']) ?>
    </div>
    <div class="field">
      <label class="field__label" for="tagline">شعار <span class="field__optional">(اختیاری)</span></label>
      <input class="input" id="tagline" name="tagline" maxlength="120" value="<?= e((string) old('tagline', S::str('brand.tagline'))) ?>" placeholder="نوبت‌دهی آرایشگاه‌ها و سالن‌های زیبایی">
    </div>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">لوگو و آیکون</h2>
    <div class="cluster" style="--gap:16px">
      <span class="brand-mark brand-mark--lg"><?php if ($logo): ?><img src="<?= e(url($logo)) ?>" alt="لوگوی فعلی"><?php else: ?><?= icon('scissors') ?><?php endif; ?></span>
      <p class="text-sm muted"><?= $logo ? 'لوگوی فعلی. با انتخاب تصویر تازه جایگزین می‌شود.' : 'هنوز لوگویی نگذاشته‌اید؛ آیکون پیش‌فرض نشان داده می‌شود.' ?></p>
    </div>
    <div class="field">
      <label class="field__label" for="logo">تصویر لوگو</label>
      <input class="input" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" aria-describedby="logo-hint<?= field_error('logo') ? ' logo-error' : '' ?>" <?= field_error('logo') ? 'aria-invalid="true"' : '' ?>>
      <p class="field__hint" id="logo-hint">PNG (ترجیحاً با پس‌زمینهٔ شفاف)، JPG یا WebP، حداکثر ۳ مگابایت. آیکون مرورگر، صفحهٔ خانهٔ گوشی و اپ خودکار از همین ساخته می‌شوند.</p>
      <?= partial('field-error', ['key' => 'logo']) ?>
    </div>
    <?php if ($logo): ?><label class="check"><input type="checkbox" name="remove_logo" value="1"><span>برداشتن لوگو و برگشت به آیکون پیش‌فرض</span></label><?php endif; ?>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">تماس و پانویس</h2>
    <div class="grid-auto" style="--min:220px">
      <div class="field"><label class="field__label" for="contact_phone">تلفن پشتیبانی</label><input class="input input--ltr num" id="contact_phone" name="contact_phone" dir="ltr" maxlength="30" value="<?= e((string) old('contact_phone', S::str('brand.contact_phone'))) ?>"></div>
      <div class="field"><label class="field__label" for="contact_email">ایمیل</label><input class="input input--ltr" id="contact_email" name="contact_email" type="email" dir="ltr" maxlength="120" value="<?= e((string) old('contact_email', S::str('brand.contact_email'))) ?>" <?= field_error('contact_email') ? 'aria-invalid="true" aria-describedby="contact_email-error"' : '' ?>><?= partial('field-error', ['key' => 'contact_email']) ?></div>
    </div>
    <div class="field"><label class="field__label" for="address">نشانی</label><input class="input" id="address" name="address" maxlength="255" value="<?= e((string) old('address', S::str('brand.address'))) ?>"></div>
    <div class="field"><label class="field__label" for="footer_text">متن پانویس <span class="field__optional">(اختیاری؛ به‌جای شعار)</span></label><textarea class="textarea" id="footer_text" name="footer_text" rows="2" maxlength="300"><?= e((string) old('footer_text', S::str('brand.footer_text'))) ?></textarea></div>
  </div></section>

  <div><button class="btn btn--primary btn--lg" type="submit">ذخیره</button></div>
</form>
