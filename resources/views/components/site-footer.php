<?php
/**
 * پانویس سایت: برند، صفحه‌های محتوایی، لینک‌ها، شبکه‌های اجتماعی، تماس و
 * نماد اعتماد — همه از «تنظیمات» پنل مدیر کل.
 */
use App\Domain\Content\PageRepository;
use App\Domain\System\SiteSettings;

$pages = PageRepository::footer();
$links = SiteSettings::footerLinks();
$social = SiteSettings::socialLinks();
$phone = trim(SiteSettings::str('brand.contact_phone'));
$email = trim(SiteSettings::str('brand.contact_email'));
$address = trim(SiteSettings::str('brand.address'));
$footerText = trim(SiteSettings::str('brand.footer_text'));
$enamad = SiteSettings::enamad();
?>
<footer class="site-footer">
  <div class="site-footer__inner">
    <div class="site-footer__brand stack stack-xs">
      <strong><?= e(brand()) ?></strong>
      <span class="text-sm muted"><?= e($footerText !== '' ? $footerText : SiteSettings::tagline()) ?></span>
      <?php if ($phone !== '' || $email !== '' || $address !== ''): ?>
        <span class="text-sm muted">
          <?php if ($phone !== ''): ?><a class="ltr num" href="tel:<?= e(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= e($phone) ?></a><?php endif; ?>
          <?php if ($email !== ''): ?><?= $phone !== '' ? ' · ' : '' ?><a class="ltr" href="mailto:<?= e($email) ?>"><?= e($email) ?></a><?php endif; ?>
          <?php if ($address !== ''): ?><br><?= e($address) ?><?php endif; ?>
        </span>
      <?php endif; ?>
    </div>
    <?php if ($pages !== [] || $links !== []): ?>
      <nav class="site-footer__links" aria-label="پیوندهای سایت">
        <?php foreach ($pages as $p): ?><a href="<?= e(url('p/' . $p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
        <?php foreach ($links as $l): ?><a href="<?= e(str_starts_with($l['url'], '/') ? url(ltrim($l['url'], '/')) : $l['url']) ?>"<?= str_starts_with($l['url'], '/') ? '' : ' rel="noopener" target="_blank"' ?>><?= e($l['label']) ?></a><?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <?php if ($social !== []): ?>
      <nav class="site-footer__social" aria-label="شبکه‌های اجتماعی">
        <?php foreach ($social as $s): ?><a href="<?= e($s['url']) ?>" rel="noopener me" target="_blank"><?= e($s['label']) ?></a><?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <div class="site-footer__meta">
      <?php if ($enamad !== null): ?>
        <a class="site-footer__seal" href="https://trustseal.enamad.ir/?id=<?= e($enamad['id']) ?>&amp;Code=<?= e($enamad['code']) ?>" target="_blank" rel="noopener" referrerpolicy="origin"><img src="https://trustseal.enamad.ir/logo.aspx?id=<?= e($enamad['id']) ?>&amp;Code=<?= e($enamad['code']) ?>" alt="نماد اعتماد الکترونیکی" width="72" height="72" referrerpolicy="origin" loading="lazy"></a>
      <?php endif; ?>
      <span class="text-xs muted">© <?= e(fa_num(App\Support\Jalali::fromDateTime(new DateTimeImmutable())[0])) ?> <?= e(brand()) ?> · <a href="<?= e(url('login')) ?>">ورود مدیران سالن</a></span>
    </div>
  </div>
</footer>
