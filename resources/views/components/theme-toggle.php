<?php
/**
 * کلید روشن/تیره.
 *
 * سه‌حالته نیست، دوحالته است: کاربر یا روشن می‌خواهد یا تیره. حالت
 * «خودکار» یک لایهٔ ذهنی اضافه است که کسی دنبالش نمی‌گردد — و اگر
 * چیزی ذخیره نشده باشد، پیش‌فرض همان سیستم است.
 *
 * $toggleTone: روی سرصفحهٔ تیرهٔ صفحهٔ سالن، رنگ‌های جوهری دیده نمی‌شوند.
 * پس دو لحن دارد: 'ink' (پیش‌فرض، روی پس‌زمینهٔ روشن) و 'on-dark'.
 */
$toggleTone = $toggleTone ?? 'ink';
$toggleClass = $toggleTone === 'on-dark'
    ? 'text-white/85 hover:bg-white/15 focus-visible:outline-white'
    : 'text-ink-500 hover:bg-ink-100 focus-visible:outline-accent';
?>
<button type="button" data-mode-toggle
        class="w-11 h-11 grid place-items-center rounded-xl transition-colors cursor-pointer
               focus-visible:outline-2 <?= $toggleClass ?>"
        aria-label="تغییر حالت روشن و تیره">
  <span class="mode-sun"><?= icon('sun', 'w-5 h-5') ?></span>
  <span class="mode-moon hidden"><?= icon('moon', 'w-5 h-5') ?></span>
</button>

<script>
(function () {
  var btn = document.currentScript.previousElementSibling;
  if (!btn) return;

  function paint() {
    var dark = document.documentElement.classList.contains('dark');
    btn.querySelector('.mode-sun').classList.toggle('hidden', dark);
    btn.querySelector('.mode-moon').classList.toggle('hidden', !dark);
    btn.setAttribute('aria-pressed', String(dark));
  }

  btn.addEventListener('click', function () {
    var dark = !document.documentElement.classList.contains('dark');
    if (window.reshenApplyMode) {
      window.reshenApplyMode(dark);
    } else {
      document.documentElement.classList.toggle('dark', dark);
      document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    }
    try { localStorage.setItem('reshen-mode', dark ? 'dark' : 'light'); } catch (e) {}
    document.dispatchEvent(new Event('reshen:theme'));
  });

  document.addEventListener('reshen:theme', paint);
  paint();
})();
</script>
