<?php
/**
 * دعوت به نصب.
 *
 * سه مسیر، چون مرورگرها یکسان رفتار نمی‌کنند:
 *
 *   اندروید و کروم دسکتاپ — رویداد beforeinstallprompt می‌آید و
 *   می‌شود پنجرهٔ نصبِ خودِ سیستم را باز کرد. مرورگر این رویداد را فقط
 *   یک بار می‌دهد، پس باید نگهش داشت.
 *
 *   iOS — هیچ APIای ندارد. تنها راه، «اشتراک‌گذاری ← افزودن به صفحهٔ
 *   اصلی» است. پس به‌جای دکمه، همان مسیر را نشان می‌دهیم.
 *
 *   نصب‌شده — هیچ چیز. تشخیصش با display-mode است و روی سافاری با
 *   navigator.standalone، چون سافاری display-mode: standalone را
 *   دیر پشتیبانی کرد.
 *
 * و یک قاعده: اگر کاربر بست، تا یک هفته دوباره نشان داده نمی‌شود.
 * پیشنهاد نصبی که هر بار برگردد، خودش دلیل پاک کردن برنامه است.
 */
?>
<div id="install-card" class="hidden fixed inset-x-3 bottom-3 z-40 sm:mx-auto sm:max-w-sm">
  <div class="glass-bar rounded-2xl shadow-deep p-3.5 flex items-start gap-3">
    <span class="w-11 h-11 shrink-0 rounded-xl overflow-hidden" aria-hidden="true">
      <img src="<?= e(asset('icons/icon-192.png')) ?>" alt="" width="44" height="44" class="w-full h-full">
    </span>

    <div class="flex-1 min-w-0">
      <p class="text-[13px] font-extrabold text-ink-900">نصب روی گوشی</p>

      <p id="install-text-android" class="hidden text-[12px] text-ink-500 mt-0.5 leading-relaxed">
        مثل یک برنامه باز می‌شود، بدون نوار مرورگر.
      </p>

      <p id="install-text-ios" class="hidden text-[12px] text-ink-500 mt-0.5 leading-relaxed">
        دکمهٔ <span class="font-bold text-ink-700">اشتراک‌گذاری</span> پایین صفحه را بزن،
        بعد <span class="font-bold text-ink-700">«افزودن به صفحهٔ اصلی»</span>.
      </p>

      <div class="flex items-center gap-2 mt-2.5">
        <button type="button" id="install-go" class="hidden btn-accent metal h-10 text-[12px] px-4">
          نصب کن
        </button>
        <button type="button" id="install-close"
                class="h-10 px-3 rounded-xl text-[12px] font-bold text-ink-500
                       hover:bg-ink-100 transition-colors cursor-pointer">
          بعداً
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var KEY = 'reshen-install-dismissed';
  var WEEK = 7 * 24 * 60 * 60 * 1000;

  var card = document.getElementById('install-card');
  var goBtn = document.getElementById('install-go');
  var closeBtn = document.getElementById('install-close');
  if (!card) return;

  function installed() {
    try {
      if (window.matchMedia('(display-mode: standalone)').matches) return true;
      if (window.matchMedia('(display-mode: window-controls-overlay)').matches) return true;
    } catch (e) {}
    // سافاری قدیمی
    return navigator.standalone === true;
  }

  function snoozed() {
    try {
      var at = parseInt(localStorage.getItem(KEY) || '0', 10);
      return at > 0 && (Date.now() - at) < WEEK;
    } catch (e) { return false; }
  }

  function show(kind) {
    if (installed() || snoozed()) return;
    document.getElementById('install-text-' + kind).classList.remove('hidden');
    if (kind === 'android') goBtn.classList.remove('hidden');
    card.classList.remove('hidden');
  }

  function hide() {
    card.classList.add('hidden');
    try { localStorage.setItem(KEY, String(Date.now())); } catch (e) {}
  }

  closeBtn.addEventListener('click', hide);

  // --- اندروید و کروم ---
  var deferred = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    // جلوی نوار پیش‌فرض را می‌گیریم تا کارت خودمان را نشان بدهیم
    e.preventDefault();
    deferred = e;
    show('android');
  });

  goBtn.addEventListener('click', function () {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.then(function () {
      deferred = null;
      hide();
    });
  });

  window.addEventListener('appinstalled', function () {
    card.classList.add('hidden');
    try { localStorage.removeItem(KEY); } catch (e) {}
  });

  // ─── iOS ───
  // سافاری روی آیفون و آیپد. کروم و فایرفاکس روی iOS هم موتور سافاری
  // دارند ولی «افزودن به صفحهٔ اصلی» ندارند، پس استثنا می‌شوند.
  var ua = navigator.userAgent;
  var isIOS = /iPad|iPhone|iPod/.test(ua)
           || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  var isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);

  if (isIOS && isSafari && !installed()) {
    // کمی صبر: پیشنهاد نصب پیش از دیدن صفحه، بی‌معنی است.
    setTimeout(function () { show('ios'); }, 2500);
  }
})();
</script>
