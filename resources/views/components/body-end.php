<?php
/** پنجرهٔ تأیید مشترک + دعوت به نصب + اسکریپت‌ها. */
$withInstall = $withInstall ?? true;
?>
<dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-dialog-title">
  <div class="dialog__body">
    <h2 class="dialog__title" id="confirm-dialog-title">مطمئن هستید؟</h2>
    <p class="dialog__text" data-confirm-text></p>
  </div>
  <div class="dialog__actions">
    <button type="button" class="btn btn--danger" data-confirm-ok>بله، انجام شود</button>
    <button type="button" class="btn btn--secondary" data-confirm-cancel>انصراف</button>
  </div>
</dialog>
<?php if ($withInstall): ?>
<div class="install" id="install-card" hidden role="region" aria-label="نصب روی گوشی">
  <img src="<?= e(asset('icons/icon-192.png')) ?>" alt="" width="44" height="44" style="border-radius:12px">
  <div class="grow stack stack-xs">
    <strong>نصب روی گوشی</strong>
    <p class="text-sm muted" id="install-text-android" hidden>مثل یک برنامه باز می‌شود، بدون نوار مرورگر.</p>
    <p class="text-sm muted" id="install-text-ios" hidden>دکمهٔ «اشتراک‌گذاری» را بزن، بعد «افزودن به صفحهٔ اصلی».</p>
    <div class="btn-row mt-2">
      <button type="button" class="btn btn--primary btn--sm" id="install-go" hidden>نصب</button>
      <button type="button" class="btn btn--ghost btn--sm" id="install-close">بعداً</button>
    </div>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
