<?php
/** @var array $system */
use App\Domain\System\SiteSettings as S;
?>
<div class="stack stack-lg">
  <form method="post" action="<?= e(url('platform/settings/maintenance')) ?>" class="card"><div class="card__body stack">
    <?= csrf_field() ?>
    <h2 class="title-sm">حالت تعمیر <?= S::maintenanceOn() ? '<span class="badge badge--warning">روشن</span>' : '' ?></h2>
    <label class="check"><input type="checkbox" name="maintenance" value="1" <?= S::maintenanceOn() ? 'checked' : '' ?>><span class="check__text"><span class="strong">سایت را برای همه به‌جز مدیران کل ببند</span><span class="check__hint">بازدیدکننده‌ها و پنل سالن‌ها صفحهٔ «در دست تعمیر» می‌بینند. صفحهٔ ورود و پنل مدیریت باز می‌ماند تا بتوانید دوباره بازش کنید.</span></span></label>
    <div class="field"><label class="field__label" for="m-message">پیام</label><textarea class="textarea" id="m-message" name="message" rows="2" maxlength="500" placeholder="<?= e(S::maintenanceMessage()) ?>"><?= e(S::str('maintenance.message')) ?></textarea></div>
    <div><button class="btn btn--primary" type="submit">ذخیره</button></div>
  </div></form>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">وضعیت سامانه</h2>
    <dl class="kv">
      <?php foreach ($system as $label => $value): ?><div class="kv__row"><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?>
    </dl>
    <div class="btn-row">
      <a class="btn btn--secondary" href="<?= e(url('doctor.php')) ?>"><?= icon('shield') ?> سلامت سیستم و مهاجرت‌ها</a>
      <a class="btn btn--secondary" href="<?= e(url('system/updates')) ?>"><?= icon('refresh') ?> به‌روزرسانی</a>
      <form method="post" action="<?= e(url('platform/settings/maintenance')) ?>" data-confirm="کش سامانه پاک شود؟ صفحه‌ها چند لحظه کندتر بار می‌شوند تا کش دوباره پر شود." data-confirm-tone="neutral" data-confirm-ok="پاک کن">
        <?= csrf_field() ?><input type="hidden" name="action" value="clear_cache"><button class="btn btn--ghost" type="submit"><?= icon('refresh') ?> پاک‌کردن کش</button>
      </form>
    </div>
  </div></section>
</div>
