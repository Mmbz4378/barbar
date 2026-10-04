<?php $isFavorites=!empty($filters['favorites']); ?>
<section class="discovery-hero discovery-hero--editorial">
 <div class="discovery-hero__copy"><span class="discovery-eyebrow"><?= $isFavorites?'انتخاب‌های خودت':'خوب دیده شو. خوب احساس کن.' ?></span><h1><?= $isFavorites?'سالن‌های دلخواه تو':'وقت یک تغییر خوبه.' ?></h1><p><?= $isFavorites?'سالن‌های ذخیره‌شده را مقایسه کن و وقت بعدی‌ات را بگیر.':'خدمات را ببین، سالن مناسب را پیدا کن و وقتت را رزرو کن.' ?></p><a class="text-action" href="#salon-results">دیدن سالن‌ها <?= icon('chevron-end') ?></a></div>
 <div class="discovery-hero__photos" aria-label="تصاویر نمونهٔ خدمات"><img src="<?= e(asset('images/services/haircut-640.webp')) ?>" alt="نمونهٔ خدمت کوتاهی مو" width="640" height="640" fetchpriority="high"><img src="<?= e(asset('images/services/beard-240.webp')) ?>" alt="نمونهٔ اصلاح ریش" width="240" height="240"><span class="photo-caption">تصاویر نمونه</span></div>
</section>
<form class="discovery-filters glass" method="get"><div class="search-essential">
<label>سالن یا خدمت<input name="q" value="<?= e($filters['q']) ?>" placeholder="کوتاهی، ریش، نام سالن…" maxlength="100"></label>
<label>شهر<input name="city" value="<?= e($filters['city']) ?>" placeholder="مثلاً تهران" maxlength="80"></label>
<button class="btn-accent metal" type="submit"><?= icon('search') ?> جست‌وجوی سالن</button></div>
<details class="search-advanced" <?= ($filters['neighborhood']!==''||$filters['max_price']!==''||$filters['rating']!=='')?'open':'' ?>><summary>فیلترهای بیشتر <span>محله، قیمت و امتیاز</span></summary><div class="search-advanced__fields"><label>محله<input name="neighborhood" value="<?= e($filters['neighborhood']) ?>" placeholder="همهٔ محله‌ها" maxlength="100"></label>
<label>تا قیمت (تومان)<input type="number" min="0" max="1000000000" name="max_price" value="<?= e($filters['max_price']) ?>" placeholder="بدون محدودیت"></label>
<label>حداقل امتیاز<select name="rating"><option value="">همه</option><option value="4" <?= $filters['rating']==='4'?'selected':'' ?>>۴ از ۵</option><option value="3" <?= $filters['rating']==='3'?'selected':'' ?>>۳ از ۵</option></select></label>
<button class="btn-ink" type="submit">اعمال فیلترها</button></div></details>
</form>
<section class="discovery-categories" aria-labelledby="category-heading"><div class="section-heading"><h2 class="card-title" id="category-heading">برای چه خدمتی وقت می‌خواهی؟</h2><span class="text-ink-500 text-sm">انتخاب سریع</span></div><div class="category-gallery">
<?php foreach(['کوتاهی'=>'کوتاهی مو','ریش'=>'اصلاح ریش','حالت'=>'حالت‌دهی','پوست'=>'خدمات پوست','مراقبت'=>'مراقبت مو','رنگ'=>'رنگ مو'] as $query=>$label): $categoryQuery=$filters;unset($categoryQuery['favorites'],$categoryQuery['page']);$categoryQuery['q']=$query; ?>
<a href="?<?= e(http_build_query($categoryQuery)) ?>" <?= $filters['q']===$query?'aria-current="true"':'' ?>><?= service_photo($label,'category-gallery__photo') ?><span><?= e($label) ?></span></a>
<?php endforeach; ?></div></section>
<div class="discovery-results-heading" id="salon-results"><div><span class="eyebrow">انتخاب بعدی تو</span><h2 class="card-title">سالن‌های قابل رزرو</h2></div><button class="btn-ink" type="button" data-locate><?= icon('map-pin') ?> فاصله از من</button></div><p role="status" data-location-status></p>
<?php if(!$salons): ?><div class="glass p-8 text-center empty-state"><?= icon('search','empty-state__icon') ?><h2 class="card-title">سالنی با این مشخصات پیدا نشد</h2><p class="text-ink-500 mt-2">شهر یا فیلترها را تغییر بده.</p><a class="btn-ink mt-4" href="<?= e(url('discover')) ?>">پاک‌کردن فیلترها</a></div><?php endif; ?>
<div class="salon-grid">
<?php foreach($salons as $salon): ?>
<article class="salon-card glass" data-salon-location data-lat="<?= e($salon['map_lat'] === null?'':(string)$salon['map_lat']) ?>" data-lng="<?= e($salon['map_lng'] === null?'':(string)$salon['map_lng']) ?>">
<a class="salon-card__photo" aria-label="معرفی <?= e($salon['name']) ?>" href="<?= e(url('salons/view/'.$salon['slug'])) ?>"><img src="<?= e(salon_cover_url($salon)) ?>" alt="" width="640" height="480" loading="lazy"><?php if(empty($salon['cover_path'])): ?><span class="photo-caption">تصویر نمونه</span><?php endif; ?></a>
<div class="salon-card__body"><h2 class="card-title"><a href="<?= e(url('salons/view/'.$salon['slug'])) ?>"><?= e($salon['name']) ?></a></h2>
<p class="text-ink-500 text-sm"><?= e($salon['city'].' · '.($salon['neighborhood'] ?: $salon['address'])) ?></p>
<div class="salon-card__meta"><span class="rating-label"><?= icon('star') ?> <?= $salon['review_count']?e(fa_num(number_format((float)$salon['rating'],1))).' از ۵ · '.e(fa_num($salon['review_count'])).' نظر':'هنوز نظری ثبت نشده' ?></span><span data-distance></span></div>
<div class="salon-card__bottom"><div><span class="eyebrow">قیمت پایهٔ خدمات</span><strong class="salon-starting-price">از <?= e(toman((int)$salon['min_price'])) ?></strong></div><a class="btn-accent metal" href="<?= e(url('s/'.$salon['slug'])) ?>">رزرو نوبت</a></div>
<?php if(App\Core\Config::get('reshen.discovery.maps_enabled',true)&&$salon['map_lat']!==null&&$salon['map_lng']!==null): ?><button type="button" class="map-trigger" data-map>نمایش روی نقشه</button><?php endif; ?>
</div></article><?php endforeach; ?></div>
<nav class="discovery-pagination" aria-label="صفحه‌های نتایج"><?php $page=max(1,(int)($filters['page']?:1)); foreach([-1=>'قبلی',1=>'بعدی'] as $delta=>$label): if(($delta<0&&$page===1)||($delta>0&&!$hasNext))continue; $query=$filters;unset($query['favorites']);$query['page']=$page+$delta; ?><a class="btn-ink" href="?<?= e(http_build_query($query)) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<section class="map-panel glass" data-map-panel hidden><h2 class="card-title">موقعیت سالن انتخاب‌شده</h2><p>نقشه توسط OpenStreetMap نمایش داده می‌شود. اگر بارگیری نشد، از آدرس سالن استفاده کن.</p><iframe title="نقشهٔ موقعیت سالن" loading="lazy" referrerpolicy="no-referrer"></iframe><a target="_blank" rel="noopener noreferrer" data-map-link>بازکردن نقشه</a></section>
