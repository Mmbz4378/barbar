<?php
/**
 * فیلدهای حساب تازه (نام، موبایل، نام کاربری، رمز) با پیشوند.
 *
 * @var string $prefix          مثل «owner_» یا «member_»
 * @var bool   $mustChangeDefault
 */
$p = $prefix ?? '';
$f = static fn (string $k): string => $p . $k;
?>
<div class="grid-auto" style="--min:240px">
  <div class="field">
    <label class="field__label" for="<?= e($f('name')) ?>">نام و نام خانوادگی</label>
    <input class="input" id="<?= e($f('name')) ?>" name="<?= e($f('name')) ?>" maxlength="120" autocomplete="off" value="<?= e((string) old($f('name'))) ?>" <?= field_error($f('name')) ? 'aria-invalid="true" aria-describedby="' . e($f('name')) . '-error"' : '' ?>>
    <?= partial('field-error', ['key' => $f('name')]) ?>
  </div>
  <div class="field">
    <label class="field__label" for="<?= e($f('phone')) ?>">شمارهٔ موبایل</label>
    <input class="input input--ltr num" id="<?= e($f('phone')) ?>" name="<?= e($f('phone')) ?>" type="tel" inputmode="tel" dir="ltr" placeholder="09123456789" autocomplete="off" value="<?= e((string) old($f('phone'))) ?>" data-numeric <?= field_error($f('phone')) ? 'aria-invalid="true" aria-describedby="' . e($f('phone')) . '-error"' : '' ?>>
    <?= partial('field-error', ['key' => $f('phone')]) ?>
  </div>
  <div class="field">
    <label class="field__label" for="<?= e($f('username')) ?>">نام کاربری <span class="field__optional">(اختیاری)</span></label>
    <input class="input input--ltr" id="<?= e($f('username')) ?>" name="<?= e($f('username')) ?>" maxlength="40" dir="ltr" autocapitalize="none" spellcheck="false" autocomplete="off" value="<?= e((string) old($f('username'))) ?>" aria-describedby="<?= e($f('username')) ?>-hint<?= field_error($f('username')) ? ' ' . e($f('username')) . '-error' : '' ?>" <?= field_error($f('username')) ? 'aria-invalid="true"' : '' ?>>
    <p class="field__hint" id="<?= e($f('username')) ?>-hint">بدون آن، با شمارهٔ موبایل وارد می‌شود.</p>
    <?= partial('field-error', ['key' => $f('username')]) ?>
  </div>
  <div class="field">
    <label class="field__label" for="<?= e($f('password')) ?>">رمز</label>
    <input class="input input--ltr" id="<?= e($f('password')) ?>" name="<?= e($f('password')) ?>" type="password" dir="ltr" autocomplete="new-password" aria-describedby="<?= e($f('password')) ?>-hint<?= field_error($f('password')) ? ' ' . e($f('password')) . '-error' : '' ?>" <?= field_error($f('password')) ? 'aria-invalid="true"' : '' ?>>
    <p class="field__hint" id="<?= e($f('password')) ?>-hint">یا گزینهٔ «رمز تصادفی» را بزنید.</p>
    <?= partial('field-error', ['key' => $f('password')]) ?>
  </div>
</div>
<div class="stack gap-0">
  <label class="check"><input type="checkbox" name="<?= e($f('generate')) ?>" value="1" <?= old($f('generate')) === '1' ? 'checked' : '' ?>><span class="check__text"><span class="strong">رمز تصادفی بساز</span><span class="check__hint">پس از ذخیره یک بار نمایش داده می‌شود تا به کاربر بدهید.</span></span></label>
  <label class="check"><input type="checkbox" name="<?= e($f('must_change')) ?>" value="1" <?= old($f('must_change'), ($mustChangeDefault ?? true) ? '1' : '') === '1' ? 'checked' : '' ?>><span class="check__text"><span class="strong">در اولین ورود رمزش را عوض کند</span><span class="check__hint">توصیه‌شده؛ رمزی که شما گذاشته‌اید فقط برای ورود اول است.</span></span></label>
</div>
