<?php
/**
 * قالب صفحات مشتری.
 *
 * $step / $steps اختیاری‌اند؛ اگر باشند نوار پیشرفت بالای صفحه می‌آید.
 * مشتری باید بداند در کدام مرحله است و چند مرحله مانده — وگرنه وسط کار
 * رها می‌کند.
 */
/*
 * نام گام چهارم به وضعیت واقعی بستگی دارد: وقتی کد تأیید خاموش است
 * (ت-۳۵) همان‌جا نوبت ثبت می‌شود، پس «تأیید» گمراه‌کننده است — مشتری
 * منتظر یک گام دیگر می‌ماند که نمی‌آید.
 */
$lastStep = App\Core\Config::get('reshen.booking.verify_phone', false) ? 'تأیید' : 'اطلاعات';
$stepTitles = ['زمان', 'خدمت', 'آرایشگر', $lastStep];
$step = $step ?? null;
?>
<!doctype html>
<html lang="fa" dir="rtl" data-font="<?= e((string) App\Core\Config::get('reshen.ui.font', 'iranyekan')) ?>" <?= theme_attr($salon['theme'] ?? null) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'رشن') ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/design-tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/refined.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/comfort.css')) ?>">
<link rel="preload" href="<?= e(asset('fonts/IRANYekanX-Regular.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<?php
  $pwaSalonSlug = $salon['slug'] ?? null;
  $pwaAppTitle = $salon['name'] ?? 'رشن';
  include BASE_PATH . '/resources/views/components/pwa-head.php';
?>
<meta name="theme-color" content="#1C1917" id="theme-color">
<meta name="color-scheme" content="light dark">
<?php include BASE_PATH . '/resources/views/components/theme-boot.php'; ?>
</head>
<body class="min-h-dvh">

<?php include BASE_PATH . '/resources/views/components/icons.svg'; ?>
<a class="skip-to-content" href="#main-content">رفتن به محتوای اصلی</a>

<div class="booking-shell mx-auto min-h-dvh flex flex-col shadow-deep"
     style="background:var(--surface)">

  <?php if (!empty($salon)): ?>
    <!--
      سرصفحه. بافت مورب و درخشش طلایی، عمق می‌دهد بدون اینکه عکس لازم
      باشد — سالنی که هنوز لوگو آپلود نکرده هم باید آبرومند به نظر برسد.
    -->
    <header class="booking-header booking-header--compact">
      <div class="booking-identity"><img src="<?= e(salon_cover_url($salon)) ?>" width="48" height="48" alt=""><div><span class="eyebrow">رزرو آنلاین</span><p><?= e($salon['name']) ?></p></div>
      <?php $toggleTone='ink'; include BASE_PATH.'/resources/views/components/theme-toggle.php'; ?></div>
      <div class="booking-context">
      <?php if ($step !== null && $step > 1): $backPaths=[2=>'s/'.$salon['slug'],3=>'s/'.$salon['slug'].'/services',4=>'s/'.$salon['slug'].'/staff']; ?>
        <a class="text-action" href="<?= e(url($backPaths[min(4,$step)])) ?>"><?= icon('chevron-start','w-4 h-4') ?> مرحلهٔ قبل</a>
      <?php else: ?><a class="text-action" href="<?= e(url('s/'.$salon['slug'].'/menu')) ?>">خدمات و قیمت‌ها</a><?php endif; ?>
      <?php if(!empty($salon['phone'])): ?><a class="text-action" href="tel:<?= e($salon['phone']) ?>"><?= icon('phone','w-4 h-4') ?> تماس با سالن</a><?php endif; ?>
      </div>
    </header>

    <?php if ($step !== null): ?>
      <!-- نوار پیشرفت: کجای کاریم و چند قدم مانده -->
      <nav class="booking-progress px-5 py-3.5 border-b" style="border-color:var(--line)"
           aria-label="مراحل رزرو">
        <ol class="flex items-center gap-1.5">
          <?php foreach ($stepTitles as $i => $label):
              $n = $i + 1;
              $done = $n < $step;
              $now  = $n === $step;
          ?>
            <li class="flex-1 flex flex-col gap-1.5"
                <?= $now ? 'aria-current="step"' : '' ?>>
              <span class="h-1 rounded-full transition-colors duration-300
                           <?= $done ? 'bg-accent' : ($now ? 'bg-ink-900' : 'bg-ink-200') ?>"></span>
              <span class="text-[12px] font-semibold text-center
                           <?= $now ? 'text-ink-900' : ($done ? 'text-accent' : 'text-ink-400') ?>">
                <span class="step-number" aria-hidden="true"><?= $done ? '✓' : e(fa_num($n)) ?></span><?= e($label) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <main id="main-content" class="booking-main flex-1 px-5 py-5">
    <?= $content ?>
  </main>

  <footer class="text-center pb-5 pt-2">
    <span class="text-[12px] text-ink-400">رشن — زمانِ راست می‌گوید</span>
  </footer>
</div>

<?php include BASE_PATH . '/resources/views/components/install-prompt.php'; ?>

<script src="<?= e(asset('js/network-status.js')) ?>" defer></script>
<script src="<?= e(asset('js/comfort.js')) ?>" defer></script>
</body>
</html>
