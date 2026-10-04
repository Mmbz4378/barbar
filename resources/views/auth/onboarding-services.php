<?php
/**
 * @var array $draft
 * @var array $templates  CatalogTemplates::for(audience)
 */
use App\Support\ServiceVisual;
?>
<div class="stack">
  <div class="stack stack-xs">
    <span class="eyebrow">گام ۲ از ۲ · <?= e(term('salon_type', $draft['audience'])) ?></span>
    <h1 class="title-md">خدمات و قیمت‌ها</h1>
    <p class="text-sm muted">خدمت‌هایی را که ارائه می‌دهید علامت بزنید و قیمت را بنویسید. مدت پیشنهادی را بعداً می‌توانید تغییر دهید. خدمتِ بدون قیمت غیرفعال ساخته می‌شود تا با «۰ تومان» به مشتری نشان داده نشود.</p>
  </div>
  <form method="post" action="<?= e(url('onboarding/services')) ?>" class="stack">
    <?= csrf_field() ?>
    <?php foreach ($templates as $ci => $category): ?>
      <fieldset class="stack stack-sm">
        <legend class="service-group__title"><?= icon(ServiceVisual::icon($category['visual'])) ?><?= e($category['name']) ?></legend>
        <?php foreach ($category['services'] as $si => $service): $key = $ci . ':' . $si; ?>
          <div class="card card--flat"><div class="card__body row" style="flex-wrap:wrap">
            <label class="check grow" style="min-width:180px">
              <input type="checkbox" name="pick[]" value="<?= e($key) ?>" checked>
              <span class="check__text"><span class="strong"><?= e($service['name']) ?></span><span class="check__hint"><?= e(duration_text($service['minutes'])) ?><?= $service['price_type'] === 'from' ? ' · قیمت «از»' : '' ?></span></span>
            </label>
            <div class="input-group" style="width:200px">
              <label class="sr-only" for="p-<?= e(str_replace(':', '-', $key)) ?>">قیمت <?= e($service['name']) ?> به تومان</label>
              <input class="input num" id="p-<?= e(str_replace(':', '-', $key)) ?>" name="price[<?= e($key) ?>]" inputmode="numeric" placeholder="قیمت" data-numeric>
              <span class="input-group__addon">تومان</span>
            </div>
          </div></div>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
    <div class="action-bar">
      <button type="submit" class="btn btn--primary btn--lg btn--block">ساخت سالن</button>
      <div class="btn-row" style="justify-content:space-between">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('onboarding')) ?>"><?= icon('chevron-start') ?> بازگشت</a>
        <button type="submit" name="skip" value="1" class="btn btn--ghost btn--sm">بدون خدمات پیشنهادی ادامه بده</button>
      </div>
    </div>
  </form>
</div>
