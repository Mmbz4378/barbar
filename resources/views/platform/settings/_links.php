<?php
use App\Domain\System\SiteSettings as S;

$social = S::json('links.social') ?? [];
$footer = S::footerLinks();
$rows = max(3, count($footer) + 2);
$enamad = S::enamad();
?>
<form method="post" action="<?= e(url('platform/settings/links')) ?>" class="stack stack-lg">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">شبکه‌های اجتماعی</h2>
    <p class="text-sm muted">نشانی کامل یا فقط نام کاربری (مثل <span class="ltr">@mysalon</span>). خالی یعنی نمایش داده نشود.</p>
    <div class="grid-auto" style="--min:240px">
      <?php foreach (S::SOCIAL as $key => [$label, $prefix]): ?>
        <div class="field">
          <label class="field__label" for="social_<?= e($key) ?>"><?= e($label) ?></label>
          <input class="input input--ltr" id="social_<?= e($key) ?>" name="social_<?= e($key) ?>" dir="ltr" maxlength="300" value="<?= e((string) old('social_' . $key, (string) ($social[$key] ?? ''))) ?>" placeholder="<?= e($prefix) ?>…" <?= field_error('social_' . $key) ? 'aria-invalid="true" aria-describedby="social_' . e($key) . '-error"' : '' ?>>
          <?= partial('field-error', ['key' => 'social_' . $key]) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">پیوندهای پانویس</h2>
    <p class="text-sm muted">صفحه‌های محتوایی (درباره، قوانین…) خودکار در پانویس می‌آیند؛ اینجا پیوندهای دیگر را اضافه کنید. نشانی کامل (https://…) یا مسیر داخلی (/discover).</p>
    <?= partial('field-error', ['key' => 'footer_links']) ?>
    <?php for ($i = 0; $i < $rows; $i++): $row = $footer[$i] ?? ['label' => '', 'url' => '']; ?>
      <div class="grid-auto" style="--min:200px">
        <div class="field"><label class="field__label" for="fl-<?= $i ?>">عنوان <?= e(fa_num($i + 1)) ?></label><input class="input" id="fl-<?= $i ?>" name="footer_label[]" maxlength="60" value="<?= e($row['label']) ?>"></div>
        <div class="field"><label class="field__label" for="fu-<?= $i ?>">نشانی <?= e(fa_num($i + 1)) ?></label><input class="input input--ltr" id="fu-<?= $i ?>" name="footer_url[]" dir="ltr" maxlength="300" value="<?= e($row['url']) ?>"></div>
      </div>
    <?php endfor; ?>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">نماد اعتماد الکترونیکی (اینماد)</h2>
    <?php if ($enamad !== null): ?><p class="text-sm"><span class="badge badge--success">فعال</span> شناسهٔ <span class="ltr num"><?= e($enamad['id']) ?></span> در پانویس همهٔ صفحه‌های عمومی نشان داده می‌شود.</p><?php endif; ?>
    <div class="field">
      <label class="field__label" for="enamad">کد HTML نماد</label>
      <textarea class="textarea input--ltr" id="enamad" name="enamad" rows="3" dir="ltr" aria-describedby="enamad-hint<?= field_error('enamad') ? ' enamad-error' : '' ?>" <?= field_error('enamad') ? 'aria-invalid="true"' : '' ?>><?= e((string) old('enamad', '')) ?></textarea>
      <p class="field__hint" id="enamad-hint">کدی که پنل اینماد می‌دهد را کامل بچسبانید. فقط شناسه و کد آن برداشته می‌شود و نماد به شکل امن ساخته می‌شود؛ HTML دلخواه در سایت نمی‌نشیند.<?= $enamad !== null ? ' برای نگه‌داشتن نماد فعلی خالی بگذارید.' : '' ?></p>
      <?= partial('field-error', ['key' => 'enamad']) ?>
    </div>
    <?php if ($enamad !== null): ?><label class="check"><input type="checkbox" name="remove_enamad" value="1"><span>برداشتن نماد</span></label><?php endif; ?>
  </div></section>

  <div><button class="btn btn--primary btn--lg" type="submit">ذخیره</button></div>
</form>
