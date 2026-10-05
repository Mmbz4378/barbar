<?php
/** @var array $env */
use App\Domain\System\SiteSettings as S;

$driver = S::str('sms.driver');
$secretState = static function (string $key, string $envKey): string {
    if (S::has($key)) {
        return S::secret($key) !== '' ? 'ذخیره‌شده در پنل' : 'ذخیره‌شده ولی با APP_KEY فعلی باز نمی‌شود؛ دوباره وارد کنید';
    }

    return (string) App\Core\Env::get($envKey, '') !== '' ? 'از فایل .env' : 'تنظیم نشده';
};
$drivers = ['log' => 'آزمایشی (log) — پیامک فرستاده نمی‌شود، در storage/logs/sms.log نوشته می‌شود', 'melipayamak' => 'ملی‌پیامک (با پشتیبان کاوه‌نگار)', 'kavenegar' => 'کاوه‌نگار'];
?>
<form method="post" action="<?= e(url('platform/settings/sms')) ?>" class="stack stack-lg">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">درگاه پیامک</h2>
    <div class="field">
      <label class="field__label" for="driver">درگاه</label>
      <select class="select" id="driver" name="driver">
        <option value="">از فایل .env (اکنون: <?= e($drivers[$env['sms_driver']] ?? $env['sms_driver']) ?>)</option>
        <?php foreach ($drivers as $key => $label): ?><option value="<?= e($key) ?>" <?= $driver === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <?= partial('field-error', ['key' => 'driver']) ?>
    </div>
    <label class="check"><input type="checkbox" name="dedicated_line" value="1" <?= S::bool('sms.dedicated_line', false) ? 'checked' : '' ?>><span class="check__text"><span class="strong">خط اختصاصی دارم</span><span class="check__hint">خط اختصاصی متن آزاد را تحویل می‌دهد؛ خط خدماتی (۳۰۰۰، ۲۰۰۰، ۹۸۲۱…) فقط «الگو». اشتباهِ روشن‌کردن یعنی پیامک‌ها بی‌صدا نمی‌رسند.</span></span></label>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">ملی‌پیامک</h2>
    <div class="grid-auto" style="--min:200px">
      <div class="field"><label class="field__label" for="mp-user">نام کاربری</label><input class="input input--ltr" id="mp-user" name="melipayamak_username" dir="ltr" autocomplete="off" value="<?= e(S::str('sms.melipayamak.username')) ?>" placeholder="<?= e(App\Core\Env::get('SMS_MELIPAYAMAK_USERNAME', '') !== '' ? 'از .env' : '') ?>"></div>
      <div class="field"><label class="field__label" for="mp-sender">شمارهٔ خط</label><input class="input input--ltr num" id="mp-sender" name="melipayamak_sender" dir="ltr" value="<?= e(S::str('sms.melipayamak.sender')) ?>"></div>
    </div>
    <div class="field">
      <label class="field__label" for="mp-pass">رمز یا کلید API</label>
      <input class="input input--ltr" id="mp-pass" name="melipayamak_password" type="password" dir="ltr" autocomplete="new-password" aria-describedby="mp-pass-hint">
      <p class="field__hint" id="mp-pass-hint">وضعیت: <?= e($secretState('sms.melipayamak.password', 'SMS_MELIPAYAMAK_PASSWORD')) ?>. برای تغییر، مقدار تازه را بنویسید؛ خالی یعنی بدون تغییر.</p>
    </div>
    <?php if (S::has('sms.melipayamak.password')): ?><label class="check check--compact"><input type="checkbox" name="clear_melipayamak_password" value="1"><span>پاک‌کردن از پنل (برگشت به .env)</span></label><?php endif; ?>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">کاوه‌نگار</h2>
    <div class="field">
      <label class="field__label" for="kn-key">کلید API</label>
      <input class="input input--ltr" id="kn-key" name="kavenegar_api_key" type="password" dir="ltr" autocomplete="new-password" aria-describedby="kn-key-hint">
      <p class="field__hint" id="kn-key-hint">وضعیت: <?= e($secretState('sms.kavenegar.api_key', 'SMS_KAVENEGAR_API_KEY')) ?>. خالی یعنی بدون تغییر.</p>
    </div>
    <?php if (S::has('sms.kavenegar.api_key')): ?><label class="check check--compact"><input type="checkbox" name="clear_kavenegar_api_key" value="1"><span>پاک‌کردن از پنل (برگشت به .env)</span></label><?php endif; ?>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">کد الگوها</h2>
    <p class="text-sm muted">روی خط خدماتی، هر پیامک باید «الگوی» تأییدشده داشته باشد. متن هر الگو را از صفحهٔ «الگوی پیامک» پنل سالن بردارید، در پنل اپراتور ثبت کنید و کدش را اینجا بگذارید. خالی یعنی مقدار .env.</p>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">پیامک</th><th scope="col">ملی‌پیامک</th><th scope="col">کاوه‌نگار</th></tr></thead>
        <tbody>
          <?php foreach (S::SMS_PATTERNS as $key => $label): ?>
            <tr>
              <td data-label="پیامک"><?= e($label) ?></td>
              <?php foreach (['melipayamak', 'kavenegar'] as $provider): ?>
                <td data-label="<?= $provider === 'melipayamak' ? 'ملی‌پیامک' : 'کاوه‌نگار' ?>"><label class="sr-only" for="p-<?= e($provider . '-' . $key) ?>"><?= e($label) ?> — <?= $provider === 'melipayamak' ? 'ملی‌پیامک' : 'کاوه‌نگار' ?></label><input class="input input--ltr num w-md" id="p-<?= e($provider . '-' . $key) ?>" name="pattern_<?= e($provider . '_' . $key) ?>" dir="ltr" maxlength="60" value="<?= e(S::str('sms.patterns.' . $provider . '.' . $key)) ?>"></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div></section>

  <div><button class="btn btn--primary btn--lg" type="submit">ذخیره</button></div>
</form>

<form method="post" action="<?= e(url('platform/settings/sms')) ?>" class="card mt-6"><div class="card__body stack stack-sm">
  <?= csrf_field() ?><input type="hidden" name="action" value="test">
  <h2 class="title-sm">پیامک آزمایشی</h2>
  <p class="text-sm muted">با تنظیمات ذخیره‌شده، یک پیامک متنی می‌فرستد.</p>
  <div class="cluster items-end" style="--gap:12px">
    <div class="field grow-200"><label class="field__label" for="test_phone">شمارهٔ موبایل</label><input class="input input--ltr num" id="test_phone" name="test_phone" type="tel" dir="ltr" value="<?= e(phone_local((string) (App\Core\Auth::user()['phone'] ?? ''))) ?>" data-numeric <?= field_error('test_phone') ? 'aria-invalid="true" aria-describedby="test_phone-error"' : '' ?>><?= partial('field-error', ['key' => 'test_phone']) ?></div>
    <button class="btn btn--secondary" type="submit"><?= icon('message') ?> ارسال</button>
  </div>
</div></form>
