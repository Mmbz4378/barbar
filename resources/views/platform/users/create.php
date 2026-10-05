<?php
/** @var array $salons */
use App\Http\Controllers\PlatformSalonController as PSC;
?>
<a class="back-link" href="<?= e(url('platform/users')) ?>"><?= icon('chevron-start') ?> همهٔ کاربران</a>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">کاربر تازه</h1>
    <p class="page-head__sub">برای مدیر کل دیگر، یا عضو پنل یک سالن. صاحب سالن تازه را از «سالن تازه» بسازید تا سالنش هم‌زمان ساخته شود.</p>
  </div>
</div>
<form method="post" action="<?= e(url('platform/users')) ?>" class="stack stack-lg container-md">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">حساب</h2>
    <?= App\Core\View::render('platform._account-fields', ['prefix' => '', 'mustChangeDefault' => true]) ?>
  </div></section>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">دسترسی</h2>
    <label class="check"><input type="checkbox" name="is_platform_admin" value="1" <?= old('is_platform_admin') === '1' ? 'checked' : '' ?>><span class="check__text"><span class="strong">مدیر کل سامانه</span><span class="check__hint">به همهٔ سالن‌ها، کاربران، تنظیمات و گزارش‌ها دسترسی کامل دارد.</span></span></label>
    <div class="grid-auto" style="--min:200px">
      <div class="field">
        <label class="field__label" for="salon_id">عضو پنل سالن <span class="field__optional">(اختیاری)</span></label>
        <select class="select" id="salon_id" name="salon_id">
          <option value="">— هیچ‌کدام —</option>
          <?php foreach ($salons as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (string) old('salon_id') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
        <?= partial('field-error', ['key' => 'salon_id']) ?>
      </div>
      <div class="field">
        <label class="field__label" for="role">نقش در سالن</label>
        <select class="select" id="role" name="role"><?php foreach (PSC::ROLES as $rk => $rl): ?><option value="<?= e($rk) ?>" <?= old('role', 'reception') === $rk ? 'selected' : '' ?>><?= e($rl) ?></option><?php endforeach; ?></select>
        <?= partial('field-error', ['key' => 'role']) ?>
      </div>
    </div>
  </div></section>
  <div class="btn-row">
    <button class="btn btn--primary btn--lg" type="submit">ساخت حساب</button>
    <a class="btn btn--ghost" href="<?= e(url('platform/users')) ?>">انصراف</a>
  </div>
</form>
