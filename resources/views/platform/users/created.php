<?php
/**
 * نمایش یک‌بارهٔ رمز (حساب تازه یا رمز تازه).
 *
 * @var array       $user
 * @var string      $password
 * @var bool        $mustChange
 * @var string|null $context
 * @var bool|null   $reset
 * @var string      $backUrl
 */
$loginId = $user['username'] ?: phone_local((string) $user['phone']);
?>
<div class="stack stack-lg container-md">
  <div class="alert alert--success" role="status"><?= icon('circle-check') ?><div class="alert__body"><p class="alert__title"><?= !empty($reset) ? 'رمز تازه ذخیره شد' : 'حساب ساخته شد' ?></p><p><?= e($user['name'] ?: phone_local((string) $user['phone'])) ?><?= !empty($context) ? ' — ' . e($context) : '' ?><?= !empty($reset) ? '. نشست‌های باز این کاربر بسته شد.' : '' ?></p></div></div>

  <section class="card" aria-labelledby="uc-login"><div class="card__body stack">
    <h2 class="title-sm" id="uc-login">اطلاعات ورود</h2>
    <dl class="kv">
      <div class="kv__row"><dt>نشانی ورود</dt><dd class="ltr"><?= e(rtrim((string) App\Core\Config::get('app.url', ''), '/') . '/login') ?></dd></div>
      <div class="kv__row"><dt>نام کاربری</dt><dd class="ltr num strong"><?= e($loginId) ?></dd></div>
      <div class="kv__row"><dt>رمز</dt><dd class="ltr strong"><span class="cluster" style="--gap:8px"><code><?= e($password) ?></code><button type="button" class="btn btn--secondary btn--sm" data-copy="<?= e($password) ?>"><?= icon('copy') ?> کپی</button></span></dd></div>
    </dl>
    <div class="alert alert--warning"><?= icon('lock') ?><div class="alert__body"><p>این رمز فقط همین یک بار نمایش داده می‌شود. همین حالا یادداشت کنید و به کاربر بدهید.<?= $mustChange ? ' در اولین ورود از او خواسته می‌شود رمز خودش را بگذارد.' : '' ?></p></div></div>
  </div></section>

  <div class="btn-row">
    <a class="btn btn--primary" href="<?= e(url(ltrim($backUrl, '/'))) ?>">ادامه</a>
    <a class="btn btn--ghost" href="<?= e(url('platform/users')) ?>">همهٔ کاربران</a>
  </div>
</div>
