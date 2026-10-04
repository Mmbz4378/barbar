<?php
/**
 * کشف سالن.
 *
 * @var array $salons
 * @var bool $hasNext
 * @var array $filters
 * @var string[] $cities
 */
use App\Support\ServiceVisual;

$isFavorites = !empty($filters['favorites']);
$query = static function (array $override) use ($filters): string {
    $q = array_merge($filters, $override);
    unset($q['favorites']);
    $q = array_filter($q, static fn ($v) => $v !== '' && $v !== null);

    return $q === [] ? '' : '?' . http_build_query($q);
};
$categories = ['haircut', 'beard', 'color', 'care', 'nails', 'lashes', 'facial', 'waxing', 'makeup', 'bridal'];
$advanced = $filters['neighborhood'] !== '' || $filters['max_price'] !== '' || $filters['rating'] !== '';
$page = max(1, (int) ($filters['page'] ?: 1));
?>
<?php if (!$isFavorites): ?>
<section class="hero">
  <h1 class="hero__title">نوبت آرایشگاه و سالن زیبایی، بدون تماس و انتظار</h1>
  <p class="hero__sub">خدمات و قیمت‌ها را ببین، ساعت آزاد را انتخاب کن و در چند ثانیه نوبت بگیر.</p>
</section>

<form class="search-panel mb-6" method="get" action="<?= e(url('discover')) ?>" role="search">
  <div class="field">
    <label class="field__label" for="d-q">سالن یا خدمت</label>
    <div class="input-search"><?= icon('search') ?><input class="input" id="d-q" name="q" value="<?= e($filters['q']) ?>" placeholder="مثلاً کوتاهی، کراتین، ناخن…" maxlength="100"></div>
  </div>
  <div class="field">
    <label class="field__label" for="d-city">شهر</label>
    <input class="input" id="d-city" name="city" value="<?= e($filters['city']) ?>" placeholder="همهٔ شهرها" maxlength="80" list="d-cities" autocomplete="address-level2">
    <datalist id="d-cities"><?php foreach ($cities as $city): ?><option value="<?= e($city) ?>"><?php endforeach; ?></datalist>
  </div>
  <button class="btn btn--primary btn--lg" type="submit"><?= icon('search') ?> جست‌وجو</button>
  <?php foreach (['audience', 'cat'] as $keep): if ($filters[$keep] !== ''): ?><input type="hidden" name="<?= $keep ?>" value="<?= e($filters[$keep]) ?>"><?php endif; endforeach; ?>
  <details class="span-full" <?= $advanced ? 'open' : '' ?>>
    <summary class="btn btn--link"><?= icon('sliders') ?> فیلترهای بیشتر</summary>
    <div class="form-grid form-grid--3 mt-3">
      <div class="field"><label class="field__label" for="d-n">محله</label><input class="input" id="d-n" name="neighborhood" value="<?= e($filters['neighborhood']) ?>" maxlength="100" placeholder="همهٔ محله‌ها"></div>
      <div class="field"><label class="field__label" for="d-p">حداکثر قیمت پایه</label><div class="input-group"><input class="input num" id="d-p" name="max_price" value="<?= e($filters['max_price']) ?>" inputmode="numeric" data-numeric placeholder="بدون محدودیت"><span class="input-group__addon">تومان</span></div></div>
      <div class="field"><label class="field__label" for="d-r">حداقل امتیاز</label>
        <select class="select" id="d-r" name="rating"><option value="">همه</option><?php foreach ([4.5, 4, 3] as $r): ?><option value="<?= $r ?>" <?= $filters['rating'] === (string) $r ? 'selected' : '' ?>><?= e(fa_num($r)) ?> و بالاتر</option><?php endforeach; ?></select>
      </div>
    </div>
  </details>
</form>

<nav class="stack stack-sm mb-6" aria-label="نوع سالن و دسته‌ها">
  <div class="segmented" role="group" aria-label="نوع سالن">
    <a href="<?= e(url('discover') . $query(['audience' => '', 'page' => ''])) ?>" <?= $filters['audience'] === '' ? 'aria-current="page"' : '' ?>>همه</a>
    <a href="<?= e(url('discover') . $query(['audience' => 'men', 'page' => ''])) ?>" <?= $filters['audience'] === 'men' ? 'aria-current="page"' : '' ?>>آرایشگاه مردانه</a>
    <a href="<?= e(url('discover') . $query(['audience' => 'women', 'page' => ''])) ?>" <?= $filters['audience'] === 'women' ? 'aria-current="page"' : '' ?>>سالن بانوان</a>
  </div>
  <div class="chips">
    <a class="chip" href="<?= e(url('discover') . $query(['cat' => '', 'page' => ''])) ?>" <?= $filters['cat'] === '' ? 'aria-current="page"' : '' ?>>همهٔ خدمات</a>
    <?php foreach ($categories as $key): ?>
      <a class="chip" href="<?= e(url('discover') . $query(['cat' => $key, 'page' => ''])) ?>" <?= $filters['cat'] === $key ? 'aria-current="page"' : '' ?>><?= icon(ServiceVisual::icon($key)) ?><?= e(ServiceVisual::label($key)) ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<?php else: ?>
  <div class="page-head"><div class="page-head__text"><h1 class="page-head__title">سالن‌های ذخیره‌شده</h1><p class="page-head__sub">سالن‌هایی که با علامت قلب نگه داشته‌ای.</p></div></div>
<?php endif; ?>

<div class="section__head mb-3" id="results">
  <h2 class="section__title"><?= $isFavorites ? 'فهرست تو' : 'سالن‌های قابل رزرو' ?></h2>
  <?php if ($salons !== []): ?>
    <button class="btn btn--secondary btn--sm" type="button" data-locate><?= icon('navigation') ?> فاصله از من</button>
  <?php endif; ?>
</div>
<p class="text-sm muted mb-3" role="status" data-location-status></p>

<?php if ($salons === []): ?>
  <div class="card">
    <?= partial('empty-state', $isFavorites
        ? ['icon' => 'heart', 'title' => 'هنوز سالنی ذخیره نکرده‌ای', 'text' => 'در صفحهٔ هر سالن، «ذخیرهٔ سالن» را بزن تا اینجا بیاید.', 'actionHref' => url('discover'), 'actionLabel' => 'کشف سالن‌ها']
        : ['icon' => 'search', 'title' => 'سالنی با این مشخصات پیدا نشد', 'text' => 'شهر، خدمت یا فیلترها را تغییر بده.', 'actionHref' => url('discover'), 'actionLabel' => 'پاک کردن فیلترها']) ?>
  </div>
<?php else: ?>
  <div class="grid-auto" style="--min:280px">
    <?php foreach ($salons as $salon) { include __DIR__ . '/_salon-card.php'; } ?>
  </div>
  <?php if ($page > 1 || $hasNext): ?>
    <nav class="btn-row mt-6 justify-center" aria-label="صفحه‌های نتایج">
      <?php if ($page > 1): ?><a class="btn btn--secondary" href="<?= e(url($isFavorites ? 'me/favorites' : 'discover') . $query(['page' => $page - 1])) ?>"><?= icon('chevron-start') ?> قبلی</a><?php endif; ?>
      <span class="btn btn--ghost" aria-current="page">صفحهٔ <?= e(fa_num($page)) ?></span>
      <?php if ($hasNext): ?><a class="btn btn--secondary" href="<?= e(url($isFavorites ? 'me/favorites' : 'discover') . $query(['page' => $page + 1])) ?>">بعدی <?= icon('chevron-end') ?></a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
<script src="<?= e(asset('js/discovery.js')) ?>" defer></script>
