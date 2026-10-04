<?php
/**
 * گالری زندهٔ سیستم طراحی.
 *
 * هر بخش: نام کلاس‌ها + نمونهٔ زنده با همهٔ حالت‌ها. دادهٔ نمونه ثابت
 * است؛ هیچ تاریخ یا ساعت جاری در این صفحه نیست (آزمون تصویری CI).
 *
 * @var array<string,array{name:string,swatch:string,audience:string}> $themes
 * @var string $version
 */
$code = static fn (string $s): string => '<pre class="code-block text-xs" dir="ltr">' . e(trim($s)) . '</pre>';
$sections = [
    'tokens' => 'رنگ‌ها', 'type' => 'تایپوگرافی', 'space' => 'فاصله و گوشه', 'buttons' => 'دکمه', 'fields' => 'فیلد و انتخاب',
    'status' => 'نشان و وضعیت', 'alerts' => 'هشدار', 'cards' => 'کارت و فهرست', 'tabs' => 'زبانه', 'data' => 'آمار و نمودار',
    'table' => 'جدول', 'empty' => 'حالت خالی و بارگذاری', 'overlays' => 'پنجره، شیت، tooltip', 'booking' => 'مسیر رزرو',
    'identity' => 'سالن، آواتار، نشان', 'pagination' => 'صفحه‌بندی', 'utilities' => 'ابزارها',
];
?>
<div class="page-head">
  <div class="page-head__text">
    <h1 class="page-head__title">سیستم طراحی</h1>
    <p class="page-head__sub">همهٔ اجزای <span dir="ltr">reshen.css</span> با حالت‌هایشان · نسخهٔ <span dir="ltr"><?= e($version) ?></span> · راهنما: <span dir="ltr">docs/design-system.md</span></p>
  </div>
  <div class="page-head__actions">
    <div class="field">
      <label class="field__label" for="ds-theme">پیش‌نمایش رنگ برند</label>
      <select class="select select--compact" id="ds-theme" data-theme-preview="#ds-canvas">
        <?php foreach ($themes as $key => $t): ?><option value="<?= e($key) ?>" <?= $key === 'forest' ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<nav class="ds-toc" aria-label="بخش‌های گالری">
  <div class="chips">
    <?php foreach ($sections as $id => $label): ?><a class="chip" href="#ds-<?= e($id) ?>"><?= e($label) ?></a><?php endforeach; ?>
  </div>
</nav>

<div id="ds-canvas" data-theme="forest" class="stack stack-xl mt-4">

  <section class="section stack" id="ds-tokens" aria-labelledby="h-tokens">
    <h2 class="section__title" id="h-tokens">رنگ‌ها</h2>
    <p class="section__sub">فقط نقش‌های معنایی در اجزا استفاده می‌شوند؛ حالت تیره و رنگ برند فقط همین‌ها را عوض می‌کنند. رنگ وضعیت (خطر، موفقیت، هشدار) با رنگ برند عوض نمی‌شود.</p>
    <div class="ds-swatches">
      <?php foreach (['--bg' => 'زمینه', '--surface' => 'سطح', '--surface-sunken' => 'سطح فرورفته', '--text' => 'متن', '--text-muted' => 'متن کم‌رنگ', '--border' => 'مرز', '--border-input' => 'مرز ورودی', '--accent' => 'تأکید (برند)', '--accent-soft' => 'تأکید ملایم', '--success-solid' => 'موفقیت', '--warning-solid' => 'هشدار', '--danger-solid' => 'خطر'] as $var => $label): ?>
        <div class="ds-swatch"><span class="ds-swatch__chip" style="--sw:var(<?= e($var) ?>)"></span><strong><?= e($label) ?></strong><span class="muted" dir="ltr"><?= e($var) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?= $code('html.dark            حالت تیره (دکمهٔ بالای صفحه)
[data-theme="rose"]  رنگ برند برای همان زیردرخت — ۱۳ پالت، کنتراست همه در CI سنجیده می‌شود') ?>
  </section>

  <section class="section stack" id="ds-type" aria-labelledby="h-type">
    <h2 class="section__title" id="h-type">تایپوگرافی</h2>
    <div class="ds-demo ds-demo--stack">
      <p class="title-xl">عنوان بزرگ — title-xl</p>
      <p class="title-lg">عنوان صفحه — title-lg</p>
      <p class="title-md">عنوان بخش — title-md</p>
      <p class="title-sm">عنوان کارت — title-sm</p>
      <p class="title-xs">عنوان کوچک — title-xs</p>
      <p>متن پایه ۱۶ پیکسل: نوبت شما برای کوتاهی مو ثبت شد. <span class="num">۱۲۳٬۴۵۶</span> تومان</p>
      <p class="text-sm">text-sm — توضیح فرم و متن ثانویه</p>
      <p class="text-xs muted">text-xs muted — برچسب و زمان</p>
      <p class="subtle">subtle — کم‌اهمیت‌ترین متن</p>
    </div>
    <?= $code('.title-xl .title-lg .title-md .title-sm .title-xs .text-md .text-sm .text-xs .muted .subtle .strong .bold .num .ltr .truncate .clamp-2') ?>
  </section>

  <section class="section stack" id="ds-space" aria-labelledby="h-space">
    <h2 class="section__title" id="h-space">فاصله و گوشه</h2>
    <div class="ds-demo ds-demo--stack">
      <?php foreach ([1, 2, 3, 4, 5, 6, 8] as $n): ?><div class="row"><span class="text-xs muted w-xs" dir="ltr">--space-<?= $n ?></span><span class="ds-space" style="--sw:var(--space-<?= $n ?>)"></span></div><?php endforeach; ?>
    </div>
    <div class="ds-demo">
      <?php foreach (['xs', 'sm', 'md', 'lg', 'xl', 'full'] as $r): ?><div class="stack stack-xs items-center"><span class="ds-radius" style="--sw:var(--radius-<?= $r ?>)"></span><span class="text-xs muted" dir="ltr"><?= $r ?></span></div><?php endforeach; ?>
    </div>
    <?= $code('.stack (.stack-xs/-sm/-md/-lg/-xl)  .row  .cluster  .spread  .grid .grid-2/-3/-4  .grid-auto (--min)  .grid-main-aside  .form-grid--2') ?>
  </section>

  <section class="section stack" id="ds-buttons" aria-labelledby="h-buttons">
    <h2 class="section__title" id="h-buttons">دکمه</h2>
    <div class="ds-demo">
      <button class="btn btn--primary" type="button">اصلی</button>
      <button class="btn btn--secondary" type="button">ثانویه</button>
      <button class="btn btn--tonal" type="button">ملایم</button>
      <button class="btn btn--ghost" type="button">بی‌قاب</button>
      <button class="btn btn--success" type="button"><?= icon('check') ?> موفقیت</button>
      <button class="btn btn--danger" type="button">حذف</button>
      <button class="btn btn--danger-soft" type="button">لغو نوبت</button>
      <button class="btn btn--danger-ghost" type="button">غیرفعال کن</button>
      <button class="btn btn--link" type="button">پیوند</button>
    </div>
    <div class="ds-demo">
      <button class="btn btn--primary btn--sm" type="button">کوچک</button>
      <button class="btn btn--primary" type="button">معمولی</button>
      <button class="btn btn--primary btn--lg" type="button">بزرگ</button>
      <button class="btn btn--primary btn--xl" type="button"><?= icon('check') ?> تمام شد</button>
      <button class="btn btn--secondary btn--icon" type="button" aria-label="ویرایش"><?= icon('edit') ?></button>
      <button class="btn btn--primary" type="button" disabled>غیرفعال</button>
      <button class="btn btn--primary" type="button" aria-busy="true">در حال ارسال</button>
    </div>
    <div class="ds-demo ds-demo--stack"><button class="btn btn--primary btn--block" type="button">تمام‌عرض (btn--block)</button></div>
    <?= $code('.btn + .btn--primary|secondary|tonal|ghost|success|danger|danger-soft|danger-ghost|link
      + .btn--sm|lg|xl  .btn--icon (همیشه با aria-label)  .btn--block  [disabled]  [aria-busy=true]
.btn-row  .btn-row--end') ?>
  </section>

  <section class="section stack" id="ds-fields" aria-labelledby="h-fields">
    <h2 class="section__title" id="h-fields">فیلد و انتخاب</h2>
    <div class="ds-demo ds-demo--stack">
      <div class="form-grid form-grid--2">
        <div class="field">
          <label class="field__label" for="ds-name">نام مشتری</label>
          <input class="input" id="ds-name" value="ترانه احمدی">
          <p class="field__hint">همان‌طور که در پیامک دیده می‌شود.</p>
        </div>
        <div class="field">
          <label class="field__label" for="ds-phone">موبایل <span class="field__optional">(اختیاری)</span></label>
          <input class="input input--ltr num" id="ds-phone" dir="ltr" value="0912abc" aria-invalid="true" aria-describedby="ds-phone-error">
          <p class="field__error" id="ds-phone-error"><?= icon('alert') ?><span>شمارهٔ موبایل معتبر نیست.</span></p>
        </div>
        <div class="field">
          <label class="field__label" for="ds-price">قیمت</label>
          <div class="input-group"><input class="input num" id="ds-price" value="350000"><span class="input-group__addon">تومان</span></div>
        </div>
        <div class="field">
          <label class="field__label" for="ds-slug">نشانی صفحه</label>
          <div class="input-group" dir="ltr"><span class="input-group__addon">reshen.ir/s/</span><input class="input input--ltr" id="ds-slug" value="araishgah-parsa"></div>
        </div>
        <div class="field">
          <label class="field__label" for="ds-select">دسته</label>
          <select class="select" id="ds-select"><option>رنگ و لایت</option><option>ناخن</option></select>
        </div>
        <div class="field">
          <span class="field__label">ساعت شروع</span>
          <?= partial('time-input', ['name' => 'ds_time', 'value' => '09:30', 'label' => 'ساعت شروع']) ?>
        </div>
        <div class="field span-full">
          <label class="field__label" for="ds-note">یادداشت</label>
          <textarea class="textarea" id="ds-note" rows="3">رنگ ریشه با فرمول قبلی.</textarea>
        </div>
      </div>
      <div class="cluster">
        <label class="switch"><input type="checkbox" checked aria-label="رزرو آنلاین"><span class="switch__track"></span></label><span class="text-sm">switch</span>
        <label class="check"><input type="checkbox" checked><span class="check__text"><span class="strong">خودم هم کار می‌کنم</span><span class="check__hint">در فهرست افراد قابل رزرو</span></span></label>
      </div>
      <div class="choice-grid">
        <label class="choice"><input class="choice__input" type="radio" name="ds-choice" checked><span class="choice__card"><span class="choice__body"><span class="choice__title">نصب خودکار</span><span class="choice__meta">choice — انتخاب‌شده</span></span></span></label>
        <label class="choice"><input class="choice__input" type="radio" name="ds-choice"><span class="choice__card"><span class="choice__body"><span class="choice__title">فقط اعلام</span><span class="choice__meta">choice</span></span></span></label>
      </div>
      <div class="cluster">
        <div class="segmented" role="group" aria-label="نوع سالن"><a href="#ds-fields" aria-current="page">همه</a><a href="#ds-fields">مردانه</a><a href="#ds-fields">بانوان</a></div>
        <div class="chips"><a class="chip" href="#ds-fields" aria-current="page">همه</a><a class="chip" href="#ds-fields"><span class="dot" style="--c:#0f766e"></span>علی</a><a class="chip" href="#ds-fields"><span class="dot" style="--c:#be185d"></span>نگار</a></div>
      </div>
    </div>
    <?= $code('.field > .field__label + .input|select|textarea + .field__hint | .field__error (aria-invalid + aria-describedby)
.input-group (پیشوند یا پسوند)  .input--ltr  .input--static  .select--compact  .switch  .check  .check--compact
.choice > .choice__input + .choice__card  .choice--check  .choice-grid  .choice-list  .segmented  .chips > .chip
partial: time-input  jalali-date-input  field-error') ?>
  </section>

  <section class="section stack" id="ds-status" aria-labelledby="h-status">
    <h2 class="section__title" id="h-status">نشان و وضعیت</h2>
    <div class="ds-demo">
      <span class="badge">پیش‌فرض</span><span class="badge badge--accent">تأکید</span><span class="badge badge--success">موفق</span><span class="badge badge--warning">در انتظار</span><span class="badge badge--danger">ناموفق</span><span class="badge badge--info">اطلاع</span><span class="badge badge--outline">طرح آزمایشی</span>
    </div>
    <div class="ds-demo">
      <?php foreach (['pending', 'confirmed', 'queued', 'in_chair', 'completed', 'cancelled', 'no_show'] as $st): ?><?= status_badge($st) ?><?php endforeach; ?>
    </div>
    <div class="ds-demo">
      <span class="row text-sm"><span class="dot dot--success"></span>باز</span><span class="row text-sm"><span class="dot dot--warning"></span>شلوغ</span><span class="row text-sm"><span class="dot dot--danger"></span>بسته</span><span class="row text-sm"><span class="dot dot--live"></span>زنده</span><span class="row text-sm"><span class="dot dot--md" style="--c:#6d28d9"></span>رنگ کارکنان</span>
    </div>
    <?= $code('.badge--accent|success|warning|danger|info|outline   status_badge($status)   .dot--success|warning|danger|accent|live  .dot (--c)') ?>
  </section>

  <section class="section stack" id="ds-alerts" aria-labelledby="h-alerts">
    <h2 class="section__title" id="h-alerts">هشدار</h2>
    <div class="stack stack-sm">
      <div class="alert alert--info"><?= icon('info') ?><div class="alert__body"><p class="alert__title">اطلاع</p><p class="text-sm">در این روزها نوبت‌دهی آنلاین بسته است.</p></div></div>
      <div class="alert alert--success"><?= icon('circle-check') ?><div class="alert__body"><p class="alert__title">نوبت ثبت شد</p></div></div>
      <div class="alert alert--warning"><?= icon('hourglass') ?><div class="alert__body text-sm">منتظر تأیید بیعانه تا ساعت ۱۸:۰۰.</div></div>
      <div class="alert alert--danger"><?= icon('alert') ?><div class="alert__body"><p class="alert__title">پرداخت ناموفق بود</p><p class="text-sm">دوباره تلاش کنید.</p></div><button class="alert__close btn btn--ghost btn--icon" type="button" data-dismiss aria-label="بستن"><?= icon('x') ?></button></div>
      <div class="alert alert--accent"><?= icon('sparkles') ?><div class="alert__body text-sm">نسخهٔ تازه آماده است.</div></div>
    </div>
    <?= $code('.alert.alert--info|success|warning|danger|accent > svg + .alert__body (.alert__title)  [data-dismiss]   flash: components/flash.php') ?>
  </section>

  <section class="section stack" id="ds-cards" aria-labelledby="h-cards">
    <h2 class="section__title" id="h-cards">کارت و فهرست</h2>
    <div class="grid grid-2">
      <article class="card"><div class="card__header card__header--divided"><h3 class="card__title">کارت با سربرگ</h3></div><div class="card__body"><dl class="kv">
        <div class="kv__row"><dt>خدمت</dt><dd>رنگ ریشه</dd></div>
        <div class="kv__row"><dt>متخصص</dt><dd>نگار صادقی</dd></div>
        <div class="kv__row kv__row--total"><dt>جمع</dt><dd class="num">۱٬۵۰۰٬۰۰۰ تومان</dd></div>
      </dl></div><div class="card__footer"><button class="btn btn--primary btn--sm" type="button">تأیید</button></div></article>
      <div class="stack stack-sm">
        <div class="card card--flat"><div class="card__body text-sm">card--flat</div></div>
        <div class="card card--sunken"><div class="card__body text-sm">card--sunken</div></div>
        <div class="card card--accent"><div class="card__body text-sm">card--accent</div></div>
        <a class="card card--interactive link-plain" href="#ds-cards"><div class="card__body text-sm">card--interactive (لمس‌پذیر)</div></a>
      </div>
    </div>
    <ul class="card list">
      <li><a class="list-row link-plain" href="#ds-cards"><span class="avatar" style="--avatar-bg:#0f766e" aria-hidden="true">ع</span><span class="list-row__body"><span class="list-row__title">علی محمدی</span><span class="list-row__meta">استادکار · ۲۱ مراجعه</span></span><span class="list-row__end"><span class="badge badge--success">فعال</span><?= icon('chevron-end', 'list-row__chevron') ?></span></a></li>
      <li class="list-row is-inactive"><span class="avatar avatar--any" aria-hidden="true">ر</span><span class="list-row__body"><span class="list-row__title">رضا حسینی</span><span class="list-row__meta">is-inactive</span></span></li>
    </ul>
    <?= $code('.card (.card--flat|sunken|accent|interactive) > .card__header(--divided) .card__body .card__footer
.list > .list-row > .list-row__body(.list-row__title .list-row__meta) .list-row__end .list-row__chevron   .kv > .kv__row(--total)') ?>
  </section>

  <section class="section stack" id="ds-tabs" aria-labelledby="h-tabs">
    <h2 class="section__title" id="h-tabs">زبانه</h2>
    <div class="tabs" role="tablist" aria-label="نمونهٔ زبانه" data-tabs="ds-tabs">
      <button class="tab" role="tab" id="ds-tab-a" aria-controls="ds-panel-a"><?= icon('store') ?> مشخصات</button>
      <button class="tab" role="tab" id="ds-tab-b" aria-controls="ds-panel-b"><?= icon('clock') ?> ساعت کاری</button>
      <button class="tab" role="tab" id="ds-tab-c" aria-controls="ds-panel-c"><?= icon('sliders') ?> قوانین</button>
    </div>
    <div id="ds-panel-a" role="tabpanel" aria-labelledby="ds-tab-a" tabindex="0" class="ds-demo">پنل اول — با کلیدهای جهت (راست‌به‌چپ) بین زبانه‌ها جابه‌جا شوید.</div>
    <div id="ds-panel-b" role="tabpanel" aria-labelledby="ds-tab-b" tabindex="0" class="ds-demo">پنل دوم</div>
    <div id="ds-panel-c" role="tabpanel" aria-labelledby="ds-tab-c" tabindex="0" class="ds-demo">پنل سوم</div>
    <?= $code('.tabs[role=tablist][data-tabs] > .tab[role=tab][aria-controls]   [data-hash] زبانه را در نشانی نگه می‌دارد') ?>
  </section>

  <section class="section stack" id="ds-data" aria-labelledby="h-data">
    <h2 class="section__title" id="h-data">آمار و نمودار</h2>
    <div class="stats" style="--cols:4">
      <div class="stat stat--accent"><span class="stat__label"><?= icon('wallet') ?> فروش</span><span class="stat__value num">۵۹٬۲۷۸٬۰۰۰</span><span class="stat__hint">تومان</span></div>
      <div class="stat"><span class="stat__label"><?= icon('circle-check') ?> مراجعه</span><span class="stat__value num">۶۱</span></div>
      <div class="stat"><span class="stat__label"><?= icon('heart') ?> انعام</span><span class="stat__value stat__value--sm num">۱۵۰٬۰۰۰ تومان</span></div>
      <div class="stat"><span class="stat__label"><?= icon('user-x') ?> نیامده</span><span class="stat__value num">۲</span><span class="stat__hint">۳٪</span></div>
    </div>
    <div class="card"><div class="card__body stack">
      <div class="bars" role="img" aria-label="نمودار نمونه">
        <?php foreach ([20, 35, 0, 50, 80, 65, 100, 45, 0, 30, 70, 55] as $v): ?><span class="bars__bar<?= $v === 0 ? ' bars__bar--empty' : '' ?>" style="--v:<?= $v ?>"></span><?php endforeach; ?>
      </div>
      <div class="stack stack-xs"><div class="spread text-sm"><span>کارت‌به‌کارت</span><strong class="num">۶۰٪</strong></div><div class="meter"><div class="meter__fill" style="--v:60"></div></div></div>
      <div class="stack stack-xs"><div class="spread text-sm"><span>رنگ کارکنان</span><strong class="num">۸۵٪</strong></div><div class="meter"><div class="meter__fill" style="--v:85;--c:#be185d"></div></div></div>
    </div></div>
    <?= $code('.stats (--cols) > .stat(.stat--accent) > .stat__label .stat__value(--sm) .stat__hint
.bars > .bars__bar (--v: 0..100) .bars__bar--empty     .meter > .meter__fill (--v, --c)') ?>
  </section>

  <section class="section stack" id="ds-table" aria-labelledby="h-table">
    <h2 class="section__title" id="h-table">جدول</h2>
    <div class="table-wrap">
      <table class="table table--stack">
        <thead><tr><th scope="col">خدمت</th><th scope="col" class="num">دفعات</th><th scope="col" class="num">ارزش</th></tr></thead>
        <tbody>
          <tr><td data-label="خدمت">رنگ ریشه</td><td data-label="دفعات" class="num">۱۰</td><td data-label="ارزش" class="num">۳٬۰۰۰٬۰۰۰ تومان</td></tr>
          <tr><td data-label="خدمت">مانیکور</td><td data-label="دفعات" class="num">۹</td><td data-label="ارزش" class="num">۴٬۰۵۰٬۰۰۰ تومان</td></tr>
        </tbody>
      </table>
    </div>
    <?= $code('.table-wrap(.table-wrap--flush) > .table(.table--stack: زیر ۷۶۸ پیکسل هر ردیف کارت می‌شود؛ برچسب از data-label)') ?>
  </section>

  <section class="section stack" id="ds-empty" aria-labelledby="h-empty">
    <h2 class="section__title" id="h-empty">حالت خالی و بارگذاری</h2>
    <div class="grid grid-2">
      <?= partial('empty-state', ['icon' => 'calendar-x', 'title' => 'این روز ساعت آزادی ندارد', 'text' => 'روز دیگری را انتخاب کن.', 'actionHref' => '#ds-empty', 'actionLabel' => 'روز بعد']) ?>
      <?= partial('empty-state', ['icon' => 'store', 'title' => 'درخواستی در صف نیست', 'dashed' => true]) ?>
    </div>
    <div class="grid grid-2">
      <div class="stack stack-sm"><p class="text-sm muted" dir="ltr">skeleton list</p><?= partial('skeleton', ['variant' => 'list', 'count' => 3]) ?></div>
      <div class="stack stack-sm"><p class="text-sm muted" dir="ltr">skeleton slots / text / cards</p><?= partial('skeleton', ['variant' => 'slots', 'count' => 6]) ?><?= partial('skeleton', ['variant' => 'text']) ?></div>
    </div>
    <?= $code('partial: empty-state (icon, title, text, actionHref, dashed)   skeleton (variant: list|slots|cards|text, count)
<div id="x" data-skeleton-region><template data-skeleton-tpl>…skeleton…</template> … </div>
<a href="…" data-skeleton-for="x">   ← تا رسیدن صفحهٔ تازه، ناحیه skeleton و aria-busy می‌گیرد') ?>
  </section>

  <section class="section stack" id="ds-overlays" aria-labelledby="h-overlays">
    <h2 class="section__title" id="h-overlays">پنجره، شیت، tooltip</h2>
    <div class="ds-demo">
      <button class="btn btn--secondary" type="button" data-open="ds-dialog">باز کردن پنجره</button>
      <button class="btn btn--secondary" type="button" data-open="ds-sheet">باز کردن شیت</button>
      <form method="get" action="#ds-overlays" data-confirm="این یک تأیید نمونه است؛ چیزی تغییر نمی‌کند." data-confirm-tone="neutral"><button class="btn btn--secondary" type="submit">تأیید پیش از اقدام</button></form>
      <button class="btn btn--secondary btn--icon" type="button" aria-label="دو هفتهٔ قبل"><?= icon('chevron-start') ?></button>
      <button class="btn btn--ghost" type="button" data-tooltip="بیعانه پس از دیدن واریز تأیید می‌شود"><?= icon('info') ?> tooltip با توضیح</button>
    </div>
    <dialog class="dialog" id="ds-dialog" aria-labelledby="ds-dialog-title">
      <div class="dialog__body"><h2 class="dialog__title" id="ds-dialog-title">پنجرهٔ نمونه</h2><p class="dialog__text">dialog بومی؛ Escape و کلیک بیرون می‌بندد.</p></div>
      <div class="dialog__actions"><button class="btn btn--primary" type="button" data-close>باشد</button></div>
    </dialog>
    <dialog class="sheet" id="ds-sheet" aria-labelledby="ds-sheet-title">
      <div class="sheet__handle" aria-hidden="true"></div>
      <div class="sheet__head"><h2 class="sheet__title" id="ds-sheet-title">شیت نمونه</h2><button type="button" class="btn btn--ghost btn--icon" data-close aria-label="بستن"><?= icon('x') ?></button></div>
      <div class="sheet__body"><p class="text-sm">روی موبایل از پایین بالا می‌آید.</p></div>
    </dialog>
    <?= $code('<dialog class="dialog|sheet">  [data-open="id"]  [data-close]   form[data-confirm][data-confirm-tone][data-confirm-ok]
tooltip: .btn--icon[aria-label] خودکار؛ هر عنصر دیگر با [data-tooltip]') ?>
  </section>

  <section class="section stack" id="ds-booking" aria-labelledby="h-booking">
    <h2 class="section__title" id="h-booking">مسیر رزرو</h2>
    <div class="ds-demo ds-demo--stack">
      <?= partial('stepper', ['steps' => [
          ['key' => 'time', 'label' => 'زمان', 'state' => 'done', 'href' => '#ds-booking'],
          ['key' => 'services', 'label' => 'خدمت', 'state' => 'current', 'href' => null],
          ['key' => 'staff', 'label' => 'متخصص', 'state' => 'upcoming', 'href' => null],
          ['key' => 'phone', 'label' => 'اطلاعات', 'state' => 'upcoming', 'href' => null],
      ]]) ?>
      <?= partial('context-chips', ['chips' => [['label' => 'زمان', 'value' => 'فردا، ساعت ۱۰:۰۰', 'href' => 'system/design#ds-booking'], ['label' => 'متخصص', 'value' => 'فرقی نمی‌کند', 'href' => 'system/design#ds-booking']]]) ?>
      <?= partial('day-strip', ['days' => [
          ['date' => '2026-01-01', 'label' => 'امروز', 'day' => '۱۲', 'month' => 'مهر', 'available' => false, 'selected' => false],
          ['date' => '2026-01-02', 'label' => 'فردا', 'day' => '۱۳', 'month' => 'مهر', 'available' => true, 'selected' => true],
          ['date' => '2026-01-03', 'label' => 'سه‌شنبه', 'day' => '۱۴', 'month' => 'مهر', 'available' => true, 'selected' => false],
          ['date' => '2026-01-04', 'label' => 'چهارشنبه', 'day' => '۱۵', 'month' => 'مهر', 'available' => true, 'selected' => false],
          ['date' => '2026-01-05', 'label' => 'پنج‌شنبه', 'day' => '۱۶', 'month' => 'مهر', 'available' => true, 'selected' => false],
      ], 'linkFor' => static fn (string $d): string => '#ds-booking']) ?>
      <div class="slot-grid">
        <?php foreach (['۰۹:۰۰', '۰۹:۳۰', '۱۰:۰۰', '۱۰:۳۰', '۱۱:۰۰', '۱۱:۳۰'] as $i => $t): ?><label class="choice slot"><input class="choice__input" type="radio" name="ds-slot" <?= $i === 2 ? 'checked' : '' ?>><span class="choice__card"><?= e($t) ?></span></label><?php endforeach; ?>
      </div>
      <div class="choice-list">
        <label class="choice choice--check service-choice">
          <input class="choice__input" type="checkbox" checked>
          <span class="choice__card">
            <?= service_media(['name' => 'رنگ ریشه'], 'service-thumb', false, 'women') ?>
            <span class="choice__body">
              <span class="choice__title">رنگ ریشه</span>
              <span class="choice__meta clamp-2">شامل شست‌وشو و سشوار.</span>
              <span class="service-meta"><span><?= icon('clock') ?><?= e(duration_text(90)) ?></span><span><?= icon('wallet') ?>بیعانه ۱۰۰٬۰۰۰ تومان</span></span>
              <span class="service-price"><?= e(price_text(15000000, 'from')) ?></span>
            </span>
            <span class="choice__mark" aria-hidden="true"><?= icon('check') ?></span>
          </span>
        </label>
      </div>
      <ol class="timeline">
        <li class="timeline__item"><span class="timeline__time">۱۰:۰۰</span><span class="timeline__line" aria-hidden="true"></span><div class="timeline__body"><div class="strong">رنگ ریشه</div><div class="text-sm muted">نگار صادقی</div></div></li>
        <li class="timeline__item"><span class="timeline__time">۱۱:۳۰</span><span class="timeline__line" aria-hidden="true"></span><div class="timeline__body"><div class="strong">مانیکور</div><div class="text-sm muted">الهام نوری</div></div></li>
      </ol>
    </div>
    <?= $code('partial: stepper  context-chips  day-strip (skeletonFor)  jalali-calendar
.slot-grid > .choice.slot   .choice.choice--check.service-choice   service_media()   .timeline') ?>
  </section>

  <section class="section stack" id="ds-identity" aria-labelledby="h-identity">
    <h2 class="section__title" id="h-identity">سالن، آواتار، نشان</h2>
    <div class="grid-auto" style="--min:260px">
      <?= App\Core\View::render('discover._salon-card', ['salon' => ['name' => 'سالن زیبایی نمونه', 'slug' => 'nemoone', 'audience' => 'women', 'theme' => 'rose', 'city' => 'تهران', 'neighborhood' => 'سعادت‌آباد', 'address' => '', 'map_lat' => null, 'map_lng' => null, 'rating_count' => 12, 'rating_avg' => 4.6, 'min_price' => 2000000, 'cover_path' => null]]) ?>
      <?= App\Core\View::render('discover._salon-card', ['salon' => ['name' => 'آرایشگاه نمونه', 'slug' => 'nemoone-2', 'audience' => 'unisex', 'theme' => 'teal', 'city' => 'شیراز', 'neighborhood' => '', 'address' => 'خیابان زند', 'map_lat' => null, 'map_lng' => null, 'rating_count' => 0, 'rating_avg' => null, 'min_price' => null, 'cover_path' => null]]) ?>
    </div>
    <div class="ds-demo">
      <span class="avatar avatar--sm" style="--avatar-bg:#0f766e" aria-hidden="true">ع</span>
      <span class="avatar" style="--avatar-bg:#be185d" aria-hidden="true">ن</span>
      <span class="avatar avatar--lg" style="--avatar-bg:#6d28d9" aria-hidden="true">م</span>
      <span class="avatar avatar--square" aria-hidden="true">س</span>
      <span class="avatar avatar--any" aria-hidden="true"><?= icon('users') ?></span>
      <span class="brand-mark brand-mark--sm"><?= icon('scissors') ?></span>
      <span class="brand-mark"><?= icon('sparkles') ?></span>
      <span class="brand-mark brand-mark--lg"><?= icon('scissors') ?></span>
      <span class="brand-mark brand-mark--xl"><?= icon('sparkles') ?></span>
    </div>
    <div class="ds-demo">
      <span class="icon-tile icon-tile--sm icon-tile--success"><?= icon('check') ?></span>
      <span class="icon-tile icon-tile--md"><?= icon('palette') ?></span>
      <span class="icon-tile"><?= icon('scissors') ?></span>
      <span class="icon-tile icon-tile--lg icon-tile--neutral"><?= icon('calendar-x') ?></span>
      <span class="icon-tile icon-tile--warning"><?= icon('hourglass') ?></span>
      <span class="icon-tile icon-tile--danger"><?= icon('alert') ?></span>
    </div>
    <?= $code('discover/_salon-card   salon_cover()   .avatar(--sm|lg|square|any, --avatar-bg)   .brand-mark(--sm|lg|xl)
.icon-tile(--sm|md|lg, --neutral|success|warning|danger)   icon("name")') ?>
  </section>

  <section class="section stack" id="ds-pagination" aria-labelledby="h-pagination">
    <h2 class="section__title" id="h-pagination">صفحه‌بندی</h2>
    <div class="ds-demo ds-demo--stack">
      <?= partial('pagination', ['page' => 5, 'pages' => 12, 'url' => static fn (int $p): string => '#ds-pagination', 'label' => 'نمونهٔ صفحه‌بندی با شماره']) ?>
      <?= partial('pagination', ['page' => 2, 'hasNext' => true, 'url' => static fn (int $p): string => '#ds-pagination', 'label' => 'نمونهٔ صفحه‌بندی بدون شمارش کل']) ?>
    </div>
    <?= $code("partial('pagination', ['page' => \$page, 'pages' => \$pages | 'hasNext' => bool, 'url' => fn(int \$p) => …, 'skeletonFor' => 'id'])") ?>
  </section>

  <section class="section stack" id="ds-utilities" aria-labelledby="h-utilities">
    <h2 class="section__title" id="h-utilities">ابزارها</h2>
    <p class="section__sub">در نما فقط پارامتر <span dir="ltr">--x</span> درون‌خطی مجاز است (<span dir="ltr">tools/check-inline-styles.php</span>)؛ بقیه با این کلاس‌ها.</p>
    <?= $code('چیدمان    .wrap .justify-center .justify-between .items-start .items-center .items-end .gap-0 .grow .grow-200 .basis-200 .shrink-0 .span-full .min-w-0
اندازه     .w-full .w-xs .w-sm .w-md .w-lg .container-xs .container-sm .container-md
فاصله      .mt-0..8 .mb-0..6 .ms-auto
متن        .link-plain .break-anywhere .truncate .clamp-2 .nowrap .center
حالت       .is-inactive .is-struck .is-placeholder .sr-only .only-mobile .only-desktop
بلوک       .list-reset .list-bulleted .code-block .scroll-box .divider') ?>
  </section>
</div>
