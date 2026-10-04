<?php
/**
 * صفحهٔ عمومی سالن در «کشف».
 *
 * @var array $salon
 * @var array<string,bool> $checklist
 */
$status = (string) $salon['publication_status'];
$statusMeta = [
    'draft' => ['پیش‌نویس — در فهرست عمومی دیده نمی‌شود', 'badge', 'info'],
    'pending' => ['در انتظار بررسی', 'badge badge--warning', 'warning'],
    'published' => ['منتشرشده در «کشف»', 'badge badge--success', 'success'],
    'rejected' => ['نیازمند اصلاح', 'badge badge--danger', 'danger'],
][$status] ?? ['—', 'badge', 'info'];
$ready = !in_array(false, $checklist, true);
$v = static fn (string $key, mixed $fallback = '') => old($key, $fallback);
$preview = array_merge($salon, ['neighborhood' => $salon['neighborhood'] ?? null, 'address' => $salon['address'] ?? '']);
$coord = static fn ($x) => $x === null || $x === '' ? '' : rtrim(rtrim((string) $x, '0'), '.');
$hasCover = preg_match('/^[a-zA-Z0-9-]+\.webp$/', (string) ($salon['cover_path'] ?? '')) === 1;
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">صفحهٔ عمومی سالن</h1>
    <p class="page-head__sub">آنچه مشتری‌های تازه در فهرست «کشف سالن‌ها» می‌بینند.</p>
  </div>
  <div class="page-head__actions">
    <span class="<?= e($statusMeta[1]) ?>"><?= e($statusMeta[0]) ?></span>
  </div>
</div>

<?php if ($status === 'rejected'): ?>
  <div class="alert alert--danger mb-4" role="status"><?= icon('alert') ?><div class="alert__body"><p class="alert__title">درخواست قبلی تأیید نشد</p><p class="text-sm">اطلاعات را کامل و دقیق کنید و دوباره درخواست بدهید.</p></div></div>
<?php elseif ($status === 'pending'): ?>
  <div class="alert alert--warning mb-4" role="status"><?= icon('hourglass') ?><div class="alert__body text-sm">درخواست شما در صف بررسی است. تا آن زمان صفحهٔ رزرو با لینک مستقیم و QR کار می‌کند.</div></div>
<?php endif; ?>

<div class="grid grid-main-aside" style="--gap:24px">
  <form method="post" action="<?= e(url('panel/publication')) ?>" enctype="multipart/form-data" class="stack stack-lg" novalidate>
    <?= csrf_field() ?>
    <section class="card" aria-labelledby="pub-intro"><div class="card__header"><h2 class="card__title" id="pub-intro">معرفی</h2></div><div class="card__body stack">
      <div class="field">
        <label class="field__label" for="pub-text">متن معرفی</label>
        <textarea class="textarea" id="pub-text" name="introduction" rows="5" maxlength="1000" data-counter <?= field_error('introduction') ? 'aria-invalid="true"' : '' ?>><?= e((string) $v('introduction', $salon['introduction'] ?? '')) ?></textarea>
        <p class="field__hint">دست‌کم ۲۰ نویسه. تخصص‌ها، سابقه و آنچه سالن شما را متمایز می‌کند.</p>
      </div>
      <div class="field">
        <label class="field__label" for="pub-nb">محله <span class="field__optional">(اختیاری)</span></label>
        <input class="input" id="pub-nb" name="neighborhood" maxlength="100" value="<?= e((string) $v('neighborhood', $salon['neighborhood'] ?? '')) ?>" placeholder="مثلاً ونک">
        <p class="field__hint">در کارت سالن به‌جای نشانی کامل نشان داده می‌شود.</p>
      </div>
    </div></section>

    <section class="card" aria-labelledby="pub-cover"><div class="card__header"><h2 class="card__title" id="pub-cover">عکس سالن</h2></div><div class="card__body stack">
      <div class="salon-cover salon-cover--card" data-theme="<?= e(App\Support\Theme::resolve($salon['theme'] ?? null)) ?>"><?= salon_cover($salon) ?></div>
      <?php if (!$hasCover): ?><p class="text-sm muted">هنوز عکسی بارگذاری نشده. عکس واقعی از فضای سالن، بیشترین اثر را روی انتخاب مشتری دارد.</p><?php endif; ?>
      <div class="field">
        <label class="field__label" for="pub-file"><?= $hasCover ? 'جایگزینی عکس' : 'انتخاب عکس' ?> <span class="field__optional">(JPG، PNG یا WebP؛ حداکثر ۳ مگابایت؛ افقی)</span></label>
        <input class="input" id="pub-file" type="file" name="cover" accept="image/jpeg,image/png,image/webp" <?= field_error('cover') ? 'aria-invalid="true" aria-describedby="cover-error"' : '' ?>>
        <?= partial('field-error', ['key' => 'cover']) ?>
      </div>
      <?php if ($hasCover): ?>
        <label class="check"><input type="checkbox" name="remove_cover" value="1"><span class="check__text">حذف عکس فعلی</span></label>
      <?php endif; ?>
    </div></section>

    <section class="card" aria-labelledby="pub-map"><div class="card__header"><h2 class="card__title" id="pub-map">موقعیت روی نقشه <span class="field__optional">(اختیاری)</span></h2></div><div class="card__body stack">
      <p class="text-sm muted">با موقعیت، مشتری فاصلهٔ سالن را می‌بیند و فهرست بر اساس نزدیکی مرتب می‌شود. مختصات را از نقشهٔ گوگل یا نشان با نگه‌داشتن انگشت روی محل سالن کپی کنید.</p>
      <div class="form-grid form-grid--2">
        <div class="field">
          <label class="field__label" for="pub-lat">عرض جغرافیایی</label>
          <input class="input input--ltr num" id="pub-lat" name="map_lat" inputmode="decimal" dir="ltr" placeholder="35.7575" value="<?= e((string) $v('map_lat', $coord($salon['map_lat'] ?? null))) ?>" <?= field_error('map_lat') ? 'aria-invalid="true" aria-describedby="map_lat-error"' : '' ?>>
          <?= partial('field-error', ['key' => 'map_lat']) ?>
        </div>
        <div class="field">
          <label class="field__label" for="pub-lng">طول جغرافیایی</label>
          <input class="input input--ltr num" id="pub-lng" name="map_lng" inputmode="decimal" dir="ltr" placeholder="51.4100" value="<?= e((string) $v('map_lng', $coord($salon['map_lng'] ?? null))) ?>" <?= field_error('map_lng') ? 'aria-invalid="true" aria-describedby="map_lng-error"' : '' ?>>
          <?= partial('field-error', ['key' => 'map_lng']) ?>
        </div>
      </div>
      <?php if ($salon['map_lat'] !== null && $salon['map_lng'] !== null): ?>
        <a class="btn btn--link" href="https://www.google.com/maps?q=<?= e($coord($salon['map_lat'])) ?>,<?= e($coord($salon['map_lng'])) ?>" target="_blank" rel="noopener"><?= icon('map-pin') ?> بررسی روی نقشه</a>
      <?php endif; ?>
    </div></section>

    <div class="form-actions">
      <?php if (in_array($status, ['draft', 'rejected'], true)): ?>
        <button type="submit" name="request_publication" value="1" class="btn btn--primary btn--lg" <?= $ready ? '' : 'aria-describedby="pub-not-ready"' ?>><?= icon('share') ?> ذخیره و درخواست انتشار</button>
        <button type="submit" class="btn btn--secondary btn--lg">فقط ذخیره</button>
      <?php else: ?>
        <button type="submit" class="btn btn--primary btn--lg">ذخیرهٔ تغییرات</button>
        <button type="submit" name="withdraw" value="1" class="btn btn--danger-ghost btn--lg" data-confirm="سالن از فهرست عمومی خارج شود؟ برای بازگشت، دوباره بررسی لازم است.">خروج از فهرست</button>
      <?php endif; ?>
    </div>
  </form>

  <aside class="stack">
    <section class="card" aria-labelledby="pub-check"><div class="card__header"><h2 class="card__title" id="pub-check">پیش‌نیازهای انتشار</h2></div><div class="card__body">
      <ul class="stack stack-sm" role="list">
        <?php foreach ($checklist as $label => $ok): ?>
          <li class="row" style="--gap:8px">
            <span class="icon-tile icon-tile--sm <?= $ok ? 'icon-tile--success' : 'icon-tile--neutral' ?>"><?= icon($ok ? 'check' : 'x', 'icon', $ok ? 'کامل' : 'ناقص') ?></span>
            <span class="<?= $ok ? '' : 'muted' ?>"><?= e($label) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$ready): ?>
        <p class="text-sm muted mt-3" id="pub-not-ready">شهر، نشانی و تلفن در <a class="link" href="<?= e(url('panel/settings')) ?>">تنظیمات سالن</a>؛ خدمات و <?= e(term('staff_plural')) ?> از منوی مدیریت.</p>
      <?php endif; ?>
    </div></section>

    <section class="stack stack-sm" aria-labelledby="pub-preview">
      <h2 class="title-xs muted" id="pub-preview">پیش‌نمایش کارت در «کشف»</h2>
      <?= App\Core\View::render('discover._salon-card', ['salon' => $preview]) ?>
    </section>
  </aside>
</div>
