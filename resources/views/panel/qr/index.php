<?php
/**
 * لینک عمومی و برگهٔ QR.
 *
 * @var array $salon
 * @var string $link
 * @var ?string $qrDataUri
 * @var bool $pngAvailable
 */
$menuLink = rtrim($link, '/') . '/menu';
?>
<div class="page-head no-print">
  <div class="page-head__text">
    <h1 class="page-head__title">لینک رزرو و کد QR</h1>
    <p class="page-head__sub">برگه را چاپ کنید و کنار آینه یا روی پیشخوان بگذارید؛ لینک را در بیو اینستاگرام و واتساپ قرار دهید.</p>
  </div>
</div>

<div class="grid grid-2 items-start">
  <section class="card" id="qr-sheet" aria-labelledby="qr-title">
    <div class="card__body stack center items-center">
      <span class="eyebrow">رزرو آنلاین نوبت</span>
      <h2 class="title-md" id="qr-title"><?= e($salon['name']) ?></h2>
      <?php if ($qrDataUri !== null): ?>
        <img src="<?= e($qrDataUri) ?>" alt="کد QR صفحهٔ رزرو <?= e($salon['name']) ?>" width="240" height="240" class="qr-frame">
        <p class="strong">دوربین موبایل را روی این کد بگیرید</p>
      <?php else: ?>
        <div class="alert alert--warning"><?= icon('alert') ?><div class="alert__body">ساخت QR روی این سرور در دسترس نیست (کتابخانهٔ لازم نصب نشده). لینک زیر همچنان کار می‌کند.</div></div>
      <?php endif; ?>
      <p class="ltr text-sm muted break-anywhere"><?= e($link) ?></p>
    </div>
  </section>

  <div class="stack no-print">
    <div class="card"><div class="card__body stack stack-md">
      <h2 class="card__title">لینک صفحهٔ رزرو</h2>
      <div class="copy-field"><code><?= e($link) ?></code><button type="button" class="btn btn--secondary btn--sm" data-copy="<?= e($link) ?>"><?= icon('copy') ?> کپی</button></div>
      <h3 class="title-xs mt-2">لینک منوی خدمات و قیمت‌ها</h3>
      <p class="text-sm muted">برای کسی که اول می‌خواهد قیمت‌ها را ببیند.</p>
      <div class="copy-field"><code><?= e($menuLink) ?></code><button type="button" class="btn btn--secondary btn--sm" data-copy="<?= e($menuLink) ?>"><?= icon('copy') ?> کپی</button></div>
      <a class="btn btn--ghost" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= icon('external') ?> باز کردن صفحهٔ رزرو</a>
    </div></div>

    <?php if ($qrDataUri !== null): ?>
      <div class="card"><div class="card__body stack stack-md">
        <h2 class="card__title">فایل QR</h2>
        <div class="btn-row">
          <button type="button" class="btn btn--primary" onclick="window.print()"><?= icon('printer') ?> چاپ برگه</button>
          <a class="btn btn--secondary" href="<?= e(url('panel/qr.svg')) ?>" download><?= icon('download') ?> SVG برای چاپ</a>
          <?php if ($pngAvailable): ?><a class="btn btn--secondary" href="<?= e(url('panel/qr.png')) ?>" download><?= icon('download') ?> PNG برای واتساپ</a><?php endif; ?>
        </div>
        <p class="text-sm muted">SVG در هر اندازه‌ای تیز می‌ماند؛ PNG برای پیام‌رسان‌ها و اینستاگرام است.</p>
      </div></div>
    <?php endif; ?>
  </div>
</div>
