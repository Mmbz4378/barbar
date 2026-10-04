<?php $reviewTotal=(int)($reviewSummary['total']??0); $startingPrice=$services?min(array_column($services,'price')):null; ?>
<a class="text-action profile-back" href="<?= e(url('discover')) ?>"><?= icon('chevron-start') ?> بازگشت به سالن‌ها</a>
<section class="salon-profile glass">
 <div class="salon-profile__cover"><img src="<?= e(salon_cover_url($salon)) ?>" alt="<?= !empty($salon['cover_path'])?'تصویر سالن '.e($salon['name']):'تصویر نمونهٔ خدمت کوتاهی مو' ?>" width="640" height="480" fetchpriority="high"><?php if(empty($salon['cover_path'])): ?><span class="photo-caption">تصویر نمونهٔ خدمات</span><?php endif; ?></div>
 <div class="salon-profile__intro">
  <span class="discovery-eyebrow">یک انتخاب برای حال خوبت</span><h1 class="page-title"><?= e($salon['name']) ?></h1>
  <p class="profile-location"><?= icon('map-pin') ?> <?= e($salon['city'].' · '.$salon['address']) ?></p>
  <a class="rating-label text-action" href="#salon-reviews"><?= icon('star') ?> <?= $reviewTotal?e(fa_num(number_format((float)$reviewSummary['average'],1))).' از ۵ · '.e(fa_num($reviewTotal)).' نظر':'اولین تجربه‌ات را ثبت کن' ?></a>
  <?php if(!empty($salon['introduction'])): ?><p class="profile-description"><?= e($salon['introduction']) ?></p><?php endif; ?>
  <div class="profile-booking-summary"><div><span class="eyebrow">قیمت پایهٔ خدمات</span><strong><?= $startingPrice!==null?'از '.e(toman((int)$startingPrice)):'قیمت اعلام نشده' ?></strong></div><a class="btn-accent" href="<?= e(url('s/'.$salon['slug'])) ?>">انتخاب وقت و رزرو <?= icon('chevron-end') ?></a></div>
  <div class="profile-actions profile-actions--secondary">
   <a class="btn-ink" href="tel:<?= e($salon['phone']) ?>"><?= icon('phone') ?> تماس</a>
   <form method="post" action="<?= e(url('salons/view/'.$salon['slug'].'/favorite')) ?>"><?= csrf_field() ?><input type="hidden" name="saved" value="<?= $favorite?'0':'1' ?>"><button class="btn-ink" type="submit" aria-pressed="<?= $favorite?'true':'false' ?>"><?= icon('heart') ?> <?= $favorite?'ذخیره شده':'ذخیرهٔ سالن' ?></button></form>
   <?php if(App\Core\Config::get('reshen.discovery.maps_enabled',true)&&$salon['map_lat']!==null&&$salon['map_lng']!==null): ?><a class="btn-ink" rel="noopener noreferrer" target="_blank" href="https://www.openstreetmap.org/?mlat=<?= e((string)$salon['map_lat']) ?>&amp;mlon=<?= e((string)$salon['map_lng']) ?>#map=16/<?= e((string)$salon['map_lat']) ?>/<?= e((string)$salon['map_lng']) ?>"><?= icon('map-pin') ?> مسیریابی</a><?php endif; ?>
  </div>
 </div>
</section>
<nav class="profile-section-nav" aria-label="بخش‌های معرفی سالن"><a href="#salon-services">خدمات و قیمت‌ها</a><a href="#salon-team">تیم و ساعت کاری</a><a href="#salon-reviews">نظرها</a></nav>
<section class="profile-section" id="salon-services"><div class="section-heading"><div><span class="eyebrow">متناسب با سلیقهٔ تو</span><h2 class="page-title">خدمات سالن</h2></div><span class="text-ink-500 text-sm"><?= e(fa_num(count($services))) ?> خدمت</span></div>
<p class="text-ink-500 text-sm mb-4">قیمت نهایی با انتخاب آرایشگر مشخص می‌شود. برای رزرو، ابتدا زمان را انتخاب کن.</p>
<?php include BASE_PATH.'/resources/views/components/service-discovery.php'; ?>
<div class="salon-grid profile-services">
<?php foreach($services as $i=>$s): ?><article class="glass service-menu-card" data-service-card="<?= e($s['name']) ?>" data-category="<?= e(service_visual($s['name'])['category']) ?>">
 <div class="service-menu-card__media"><?= service_photo($s['name'],'service-photo service-menu-card__photo',false,$s['image_file']??null) ?><span class="service-menu-card__badge" aria-hidden="true"><?= icon(service_icon($s['name'])) ?></span></div>
 <div class="service-menu-card__body"><h3 class="card-title"><?= e($s['name']) ?></h3><?php if(!empty($s['description'])): ?><p class="text-ink-500 text-sm"><?= e($s['description']) ?></p><?php endif; ?><span class="service-meta"><?= icon('clock') ?> <?= e(fa_num($s['duration_minutes'])) ?> دقیقه</span><strong class="service-menu-card__price"><?= e(toman((int)$s['price'])) ?></strong></div>
</article><?php endforeach; ?></div>
</section>
<div class="profile-columns" id="salon-team">
 <section class="glass p-5"><div class="section-heading"><h2 class="card-title">تیم سالن</h2><?= icon('scissors') ?></div><p class="text-ink-500 text-sm mb-4">در مرحلهٔ رزرو، افراد آزاد در زمان انتخابی را می‌بینی.</p><div class="profile-team-grid">
 <?php foreach($staff as $member): ?><div class="profile-team-member"><span class="profile-team-avatar" aria-hidden="true"><?= icon('barber-mark') ?></span><strong><?= e($member['name']) ?></strong><span class="text-ink-500 text-sm">آرایشگر سالن</span></div><?php endforeach; ?></div></section>
 <section class="glass p-5"><div class="section-heading"><h2 class="card-title">ساعت کاری</h2><?= icon('clock') ?></div><?php $names=['شنبه','یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه']; foreach($names as $day=>$label): $h=$hours[$day]??null; ?><p class="hours-row"><span><?= $label ?></span><span><?= !$h?'اعلام نشده':($h['is_closed']?'تعطیل':e(fa_num(substr($h['opens_at'],0,5).' تا '.substr($h['closes_at'],0,5)))) ?></span></p><?php endforeach; ?></section>
</div>
<section class="profile-section" id="salon-reviews"><div class="section-heading"><h2 class="page-title">نظر مراجعه‌کنندگان</h2><?php if($reviewTotal): ?><span class="rating-label"><?= icon('star') ?> <?= e(fa_num(number_format((float)$reviewSummary['average'],1))) ?> از ۵</span><?php endif; ?></div><p class="text-ink-500 mb-4">نظرها مربوط به مراجعهٔ انجام‌شده‌اند. ثبت نظر از بخش نوبت‌های من امکان‌پذیر است.</p>
 <?php if(!$reviews): ?><div class="glass empty-state"><?= icon('message','empty-state__icon') ?><h3 class="card-title">هنوز نظری منتشر نشده</h3><p>پس از مراجعه، تجربه‌ات را با دیگران به اشتراک بگذار.</p></div><?php endif; ?>
 <div class="review-grid"><?php foreach($reviews as $review): ?><article class="glass salon-review-card"><div class="section-heading"><strong class="rating-label"><?= icon('star') ?> <?= e(fa_num($review['rating'])) ?> از ۵</strong><span class="eyebrow"><?= e(jdate($review['created_at'],'Y/m/d')) ?></span></div><p><?= e($review['comment']) ?></p><details class="mt-3"><summary>گزارش این نظر</summary><form method="post" action="<?= e(url('reviews/'.$review['id'].'/report')) ?>" class="space-y-3 mt-3"><?= csrf_field() ?><label>دلیل گزارش<input class="w-full rounded-xl border p-3" name="reason" maxlength="300" required></label><button class="btn-ink">ثبت گزارش</button></form></details></article><?php endforeach; ?></div>
</section>
<aside class="profile-reserve-bar" aria-label="رزرو از این سالن"><div><strong><?= e($salon['name']) ?></strong><span><?= $startingPrice!==null?'از '.e(toman((int)$startingPrice)):'انتخاب خدمات و زمان' ?></span></div><a class="btn-accent" href="<?= e(url('s/'.$salon['slug'])) ?>">رزرو نوبت <?= icon('chevron-end') ?></a></aside>
