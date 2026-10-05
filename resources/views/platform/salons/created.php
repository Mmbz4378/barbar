<?php
/**
 * @var array       $salon
 * @var array       $owner
 * @var string|null $password  فقط وقتی حساب صاحب تازه ساخته شده؛ یک بار نمایش
 * @var bool        $mustChange
 * @var int         $seeded
 */
$loginId = $owner['username'] ?: phone_local((string) $owner['phone']);
?>
<div class="stack stack-lg container-md">
  <div class="alert alert--success" role="status"><?= icon('circle-check') ?><div class="alert__body"><p class="alert__title">سالن «<?= e($salon['name']) ?>» ساخته شد</p><p>ساعت کاری پیش‌فرض ۹ تا ۲۱ (جمعه تعطیل) گذاشته شد<?= $seeded > 0 ? ' و ' . e(fa_num($seeded)) . ' خدمت پیشنهادی بی‌قیمت اضافه شد' : '' ?>.</p></div></div>

  <section class="card" aria-labelledby="cr-login"><div class="card__body stack">
    <h2 class="title-sm" id="cr-login">اطلاعات ورود صاحب سالن</h2>
    <dl class="kv">
      <div class="kv__row"><dt>نام</dt><dd><?= e($owner['name'] ?: '—') ?></dd></div>
      <div class="kv__row"><dt>نشانی ورود</dt><dd class="ltr"><?= e(rtrim((string) App\Core\Config::get('app.url', ''), '/') . '/login') ?></dd></div>
      <div class="kv__row"><dt>نام کاربری</dt><dd class="ltr num strong"><?= e($loginId) ?></dd></div>
      <?php if ($password !== null): ?>
        <div class="kv__row"><dt>رمز</dt><dd class="ltr strong"><span class="cluster" style="--gap:8px"><code><?= e($password) ?></code><button type="button" class="btn btn--secondary btn--sm" data-copy="<?= e($password) ?>"><?= icon('copy') ?> کپی</button></span></dd></div>
      <?php else: ?>
        <div class="kv__row"><dt>رمز</dt><dd>همان رمزِ حساب موجودش (یا ورود با کد پیامکی)</dd></div>
      <?php endif; ?>
    </dl>
    <?php if ($password !== null): ?>
      <div class="alert alert--warning"><?= icon('lock') ?><div class="alert__body"><p>این رمز فقط همین یک بار نمایش داده می‌شود. همین حالا یادداشت کنید و به صاحب سالن بدهید.<?= $mustChange ? ' در اولین ورود از او خواسته می‌شود رمز خودش را بگذارد.' : '' ?></p></div></div>
    <?php endif; ?>
  </div></section>

  <div class="btn-row">
    <a class="btn btn--primary" href="<?= e(url('platform/salons/' . $salon['id'])) ?>">صفحهٔ سالن</a>
    <form method="post" action="<?= e(url('platform/salons/' . $salon['id'] . '/impersonate')) ?>"><?= csrf_field() ?><button class="btn btn--secondary" type="submit"><?= icon('shield') ?> ورود به پنل سالن برای تنظیم</button></form>
    <a class="btn btn--ghost" href="<?= e(url('platform/salons/new')) ?>"><?= icon('plus') ?> سالن دیگر</a>
  </div>
</div>
