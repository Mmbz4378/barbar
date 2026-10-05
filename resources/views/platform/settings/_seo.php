<?php use App\Domain\System\SiteSettings as S; $base = rtrim((string) App\Core\Config::get('app.url', ''), '/'); ?>
<form method="post" action="<?= e(url('platform/settings/seo')) ?>" class="stack stack-lg">
  <?= csrf_field() ?>
  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">عنوان و توضیح</h2>
    <div class="field">
      <label class="field__label" for="home_title">عنوان صفحهٔ اصلی در گوگل <span class="field__optional">(اختیاری)</span></label>
      <input class="input" id="home_title" name="home_title" maxlength="70" value="<?= e((string) old('home_title', S::str('seo.title'))) ?>" placeholder="<?= e(S::brandName()) ?> — رزرو آنلاین آرایشگاه و سالن زیبایی">
    </div>
    <div class="field">
      <label class="field__label" for="description">توضیح سایت</label>
      <textarea class="textarea" id="description" name="description" rows="3" maxlength="300" aria-describedby="description-hint"><?= e((string) old('description', S::str('seo.description'))) ?></textarea>
      <p class="field__hint" id="description-hint">زیر عنوان در نتایج جست‌وجو؛ ۱۲۰ تا ۱۶۰ نویسه بهترین است.</p>
    </div>
    <label class="check"><input type="checkbox" name="indexing" value="1" <?= S::indexingAllowed() ? 'checked' : '' ?>><span class="check__text"><span class="strong">موتورهای جست‌وجو سایت را ایندکس کنند</span><span class="check__hint">خاموش: robots.txt همه‌چیز را می‌بندد و همهٔ صفحه‌ها noindex می‌شوند (برای سایت آزمایشی). پنل‌ها همیشه noindex‌اند.</span></span></label>
  </div></section>

  <section class="card"><div class="card__body stack">
    <h2 class="title-sm">تأیید مالکیت و آمار</h2>
    <div class="field">
      <label class="field__label" for="google_verification">کد تأیید Google Search Console</label>
      <input class="input input--ltr" id="google_verification" name="google_verification" dir="ltr" value="<?= e((string) old('google_verification', S::str('seo.google_verification'))) ?>" placeholder='<meta name="google-site-verification" content="…">' aria-describedby="gv-hint<?= field_error('google_verification') ? ' google_verification-error' : '' ?>" <?= field_error('google_verification') ? 'aria-invalid="true"' : '' ?>>
      <p class="field__hint" id="gv-hint">در Search Console روش «HTML tag» را انتخاب کنید و کل تگ را اینجا بچسبانید. پس از ذخیره، در Search Console «Verify» را بزنید.</p>
      <?= partial('field-error', ['key' => 'google_verification']) ?>
    </div>
    <div class="field">
      <label class="field__label" for="bing_verification">کد تأیید Bing Webmaster <span class="field__optional">(اختیاری)</span></label>
      <input class="input input--ltr" id="bing_verification" name="bing_verification" dir="ltr" value="<?= e((string) old('bing_verification', S::str('seo.bing_verification'))) ?>" <?= field_error('bing_verification') ? 'aria-invalid="true" aria-describedby="bing_verification-error"' : '' ?>>
      <?= partial('field-error', ['key' => 'bing_verification']) ?>
    </div>
    <div class="field">
      <label class="field__label" for="ga_id">شناسهٔ Google Analytics 4 <span class="field__optional">(اختیاری)</span></label>
      <input class="input input--ltr" id="ga_id" name="ga_id" dir="ltr" maxlength="24" value="<?= e((string) old('ga_id', S::str('seo.ga_id'))) ?>" placeholder="G-XXXXXXXXXX" aria-describedby="ga-hint<?= field_error('ga_id') ? ' ga_id-error' : '' ?>" <?= field_error('ga_id') ? 'aria-invalid="true"' : '' ?>>
      <p class="field__hint" id="ga-hint">فقط روی صفحه‌های عمومی (کشف، صفحهٔ سالن و رزرو) بار می‌شود؛ پنل‌ها رهگیری نمی‌شوند. IP ناشناس می‌شود.</p>
      <?= partial('field-error', ['key' => 'ga_id']) ?>
    </div>
  </div></section>

  <section class="card"><div class="card__body stack stack-sm">
    <h2 class="title-sm">نقشهٔ سایت</h2>
    <p class="text-sm muted">خودکار ساخته و به‌روز می‌شود: کشف، صفحهٔ رزرو همهٔ سالن‌های فعال، معرفی سالن‌های منتشرشده و صفحه‌های محتوایی. در Search Console بخش «Sitemaps» این نشانی را ثبت کنید:</p>
    <p class="cluster" style="--gap:8px"><code class="ltr"><?= e($base . '/sitemap.xml') ?></code> <a class="btn btn--ghost btn--sm" href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener"><?= icon('external') ?> نمایش</a> <a class="btn btn--ghost btn--sm" href="<?= e(url('robots.txt')) ?>" target="_blank" rel="noopener">robots.txt</a></p>
  </div></section>

  <div><button class="btn btn--primary btn--lg" type="submit">ذخیره</button></div>
</form>
