<?php
/**
 * برگهٔ QR — روی صفحه شکیل، روی کاغذ ساده.
 *
 * @var array $salon
 * @var string $link
 * @var ?string $qrDataUri
 * @var bool $pngAvailable
 */
?>
<h1 class="page-title mb-1">کد QR سالن</h1>
<p class="text-[12px] text-ink-400 mb-5 leading-relaxed">
  این برگه را چاپ کن و پشت آینه یا روی پیشخوان بچسبان. مشتری با دوربین موبایل
  اسکن می‌کند و مستقیم می‌رود سر صفحهٔ رزرو — بدون تایپ کردن نشانی.
</p>

<?php if ($qrDataUri === null): ?>
  <div class="glass rounded-2xl p-6 text-center">
    <p class="text-sm font-bold text-ink-700 mb-2">ساخت QR روی این سرور در دسترس نیست.</p>
    <p class="text-[12px] text-ink-400 mb-4">
      کتابخانهٔ لازم نصب نشده. اگر پروژه را مستقیم از گیت گرفته‌ای،
      <span class="code">composer install</span> را اجرا کن.
    </p>
    <p class="text-[13px] text-ink-600">لینک سالن:
      <span dir="ltr" class="code text-accent break-all"><?= e($link) ?></span>
    </p>
  </div>
<?php else: ?>

<div class="grid lg:grid-cols-[auto_1fr] gap-5 items-start">

  <!-- همین بخش چاپ می‌شود -->
  <section id="qr-sheet"
           class="glass rounded-2xl p-6 sm:p-8 text-center mx-auto w-full max-w-xs sm:max-w-sm">
    <p class="text-[12px] tracking-wide text-ink-400 mb-1">رزرو نوبت</p>
    <h2 class="text-lg sm:text-xl font-extrabold text-ink-900 mb-4 leading-tight">
      <?= e($salon['name']) ?>
    </h2>

    <img src="<?= e($qrDataUri) ?>" alt="کد QR صفحهٔ رزرو <?= e($salon['name']) ?>"
         class="w-full max-w-[15rem] mx-auto rounded-xl bg-white p-3"
         width="240" height="240">

    <p class="mt-4 text-[12px] font-bold text-ink-700">دوربین موبایلت را بگیر روی این کد</p>
    <p dir="ltr" class="mt-1.5 text-[12px] code text-ink-400 break-all"><?= e($link) ?></p>
  </section>

  <div class="space-y-3">
    <div class="glass rounded-2xl p-5">
      <h3 class="card-title mb-3">گرفتن فایل</h3>
      <div class="flex flex-col sm:flex-row lg:flex-col gap-2">
        <button type="button" onclick="window.print()" class="btn-accent metal w-full">
          <?= icon('printer', 'w-4 h-4') ?>
          چاپ برگه
        </button>
        <a href="<?= e(url('panel/qr.svg')) ?>" class="btn-ink w-full" download>
          دانلود برای چاپ (SVG)
        </a>
        <?php if ($pngAvailable): ?>
          <a href="<?= e(url('panel/qr.png')) ?>" class="btn-ink w-full" download>
            دانلود برای واتساپ (PNG)
          </a>
        <?php endif; ?>
      </div>
      <p class="text-[12px] text-ink-400 mt-3 leading-relaxed">
        SVG برای چاپ است — هر قدر بزرگش کنی لبه‌ها تیز می‌ماند.
        PNG برای فرستادن در واتساپ و اینستاگرام که SVG را نشان نمی‌دهند.
      </p>
    </div>

    <div class="glass rounded-2xl p-5">
      <h3 class="card-title mb-2">لینک مستقیم</h3>
      <p class="text-[12px] text-ink-400 mb-2.5">
        همین را در بیو اینستاگرام یا وضعیت واتساپ بگذار.
      </p>
      <div class="flex items-center gap-2">
        <input id="salon-link" type="text" readonly value="<?= e($link) ?>" dir="ltr"
               class="flex-1 min-w-0 h-11 rounded-lg border border-ink-200 bg-transparent px-3
                      text-[12px] code focus:outline-none focus:ring-2 focus:ring-accent"
               aria-label="لینک عمومی سالن">
        <button type="button" id="copy-link" class="btn-ink h-11 px-4 text-[13px] shrink-0">کپی</button>
      </div>
      <p id="copy-done" class="text-[12px] text-green-700 mt-2 hidden">کپی شد.</p>
    </div>
  </div>
</div>

<script>
(function () {
  var btn = document.getElementById('copy-link');
  var field = document.getElementById('salon-link');
  var done = document.getElementById('copy-done');
  if (!btn || !field) return;

  btn.addEventListener('click', function () {
    // در مرورگر قدیمی یا اتصال بدون HTTPS، clipboard در دسترس نیست؛
    // انتخاب کردن متن، راه برگشتی است که همه‌جا کار می‌کند.
    field.select();
    field.setSelectionRange(0, field.value.length);
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}

    if (!ok && navigator.clipboard) {
      navigator.clipboard.writeText(field.value).then(show);
      return;
    }
    if (ok) show();
  });

  function show() {
    done.classList.remove('hidden');
    setTimeout(function () { done.classList.add('hidden'); }, 2500);
  }
})();
</script>
<?php endif; ?>
