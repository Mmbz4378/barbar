<?php use App\Domain\System\SiteSettings as S; $mode = S::registrationMode(); ?>
<form method="post" action="<?= e(url('platform/settings/auth')) ?>" class="stack stack-lg">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">روش‌های ورود به پنل</h2>
    <?= partial('field-error', ['key' => 'methods']) ?>
    <label class="check"><input type="checkbox" name="password_login" value="1" <?= S::passwordLoginEnabled() ? 'checked' : '' ?>><span class="check__text"><span class="strong">نام کاربری (یا موبایل) و رمز</span><span class="check__hint">بدون نیاز به پیامک؛ برای کسی که هنوز رمز ندارد کار نمی‌کند.</span></span></label>
    <label class="check"><input type="checkbox" name="otp_login" value="1" <?= S::bool('auth.otp', true) ? 'checked' : '' ?>><span class="check__text"><span class="strong">کد پیامکی</span><span class="check__hint">بازیابی رمز هم با همین است. خاموش‌کردنش یعنی رمز فراموش‌شده را فقط مدیر بازنشانی می‌کند.</span></span></label>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">ثبت‌نام سالن</h2>
    <fieldset class="field">
      <legend class="sr-only">حالت ثبت‌نام</legend>
      <div class="choice-list">
        <?php foreach ([S::REG_CLOSED => ['بسته (پیش‌فرض)', 'فقط مدیر کل سالن و صاحبش را می‌سازد. کسی که حساب پنل ندارد کد ورود هم نمی‌گیرد.'], S::REG_APPROVAL => ['باز، با تأیید مدیر', 'هر کسی با شماره‌اش وارد می‌شود و سالن می‌سازد؛ سالن تا تأیید شما (فعال‌کردن در «سالن‌ها») بسته است.'], S::REG_OPEN => ['کاملاً باز', 'هر کسی سالن می‌سازد و بی‌درنگ از آن استفاده می‌کند.']] as $key => [$label, $hint]): ?>
          <label class="choice"><input class="choice__input" type="radio" name="registration" value="<?= e($key) ?>" <?= $mode === $key ? 'checked' : '' ?>><span class="choice__card"><span class="choice__body"><span class="choice__title"><?= e($label) ?></span><span class="choice__meta"><?= e($hint) ?></span></span><span class="choice__mark"><?= icon('check') ?></span></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">امنیت رمز</h2>
    <div class="grid-auto" style="--min:180px">
      <div class="field"><label class="field__label" for="min_password">حداقل طول رمز</label><input class="input num" id="min_password" name="min_password" inputmode="numeric" value="<?= e((string) S::minPasswordLength()) ?>" data-numeric></div>
      <div class="field"><label class="field__label" for="max_attempts">تلاش ناموفق تا قفل</label><input class="input num" id="max_attempts" name="max_attempts" inputmode="numeric" value="<?= e((string) S::maxLoginAttempts()) ?>" data-numeric></div>
      <div class="field"><label class="field__label" for="lock_minutes">مدت قفل (دقیقه)</label><input class="input num" id="lock_minutes" name="lock_minutes" inputmode="numeric" value="<?= e((string) S::lockMinutes()) ?>" data-numeric></div>
    </div>
    <p class="text-sm muted">قفل فقط جلوی ورود با رمز را می‌گیرد؛ صاحب حساب با کد پیامکی همچنان وارد می‌شود. تلاش‌های ناموفق از یک نشانی IP هم سقف دارند.</p>
  </div></section>

  <div><button class="btn btn--primary btn--lg" type="submit">ذخیره</button></div>
</form>
