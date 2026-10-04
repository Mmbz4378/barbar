<?php
/**
 * نوار اقدامِ چسبیده به پایین صفحه.
 *
 * چرا: روی موبایل، دکمهٔ اصلی وسط اسکرول گم می‌شد. صفحهٔ رزرو ۲۰۸۰
 * پیکسل بود و دکمهٔ «ادامه» در ۱۸۱۰ — یعنی مشتری باید تا ته صفحه
 * می‌رفت تا بفهمد اصلاً دکمه‌ای هست. سنجش روی سه اندازهٔ موبایل نشان
 * داد در پنج صفحه دکمهٔ اصلی خارج از دید اول است.
 *
 * حالا نوار به پایینِ دید می‌چسبد و شست کاربر همیشه رویش است — پایین
 * صفحه، جایی که دست روی گوشی هست، نه بالای صفحه.
 *
 * `sticky` است و نه `fixed`: وقتی به انتهای فرم می‌رسیم سر جای خودش
 * می‌نشیند و روی محتوا نمی‌افتد.
 *
 * @var string      $label    متن دکمه
 * @var string|null $summary  خلاصهٔ انتخاب کاربر، بالای دکمه
 * @var string|null $hint     راهنمای کوتاه، وقتی هنوز چیزی انتخاب نشده
 * @var bool|null   $disabled دکمه خاموش باشد
 */
$summary = $summary ?? null;
$hint = $hint ?? null;
$disabled = $disabled ?? false;
?>
<div class="sticky-action sticky bottom-0 z-20 -mx-5 mt-5 px-5 pt-3 border-t"
     style="border-color:var(--line);
            padding-bottom:calc(0.75rem + env(safe-area-inset-bottom))">

  <?php if ($summary !== null || $hint !== null): ?>
    <!--
      خطِ بالای دکمه: تا وقتی چیزی انتخاب نشده راهنماست، و بعد خودِ
      انتخاب را نشان می‌دهد. مشتری پیش از زدن دکمه می‌بیند چه چیزی
      را تأیید می‌کند — همان کاری که نوار پایین اپلیکیشن‌های سفارش
      می‌کند.
    -->
    <p role="status" aria-live="polite" class="sticky-action-note text-[12px] mb-2 <?= $summary !== null ? 'text-ink-700 font-semibold' : 'text-ink-400' ?>"
       data-hint="<?= e($hint ?? '') ?>">
      <?= e($summary ?? $hint) ?>
    </p>
  <?php endif; ?>

  <button type="submit" class="btn-accent metal w-full"<?= $disabled ? ' disabled' : '' ?>>
    <?= e($label) ?>
    <?= icon('chevron-end', 'w-4 h-4') ?>
  </button>
</div>

<script>
/*
 * وقتی کاربر گزینه‌ای را انتخاب کرد، همان را در نوار پایین بنویس.
 *
 * بدون جاوااسکریپت هم صفحه کار می‌کند — آن‌وقت فقط متن راهنما
 * می‌ماند و دکمه سر جایش است. این فقط تأییدِ بصری است، نه شرطِ کار.
 */
(function () {
  var bar = document.currentScript.previousElementSibling;
  if (!bar) return;
  var note = bar.querySelector('.sticky-action-note');
  var form = bar.closest('form');
  if (!note || !form) return;

  var hint = note.getAttribute('data-hint') || '';

  function labelOf(input) {
    var wrap = input.closest('label');
    if (!wrap) return '';
    return (wrap.innerText || '').trim().split('\n')[0].trim();
  }

  function refresh() {
    var picked = [].slice.call(form.querySelectorAll('input:checked'))
      .map(labelOf).filter(Boolean);

    if (picked.length === 0) {
      note.textContent = hint;
      note.classList.add('text-ink-400');
      note.classList.remove('text-ink-700', 'font-semibold');
      return;
    }
    const selected=[...form.querySelectorAll('input[name="service_ids[]"]:checked')];
    if(selected.length){
      const amount=selected.reduce((sum,input)=>sum+Number(input.dataset.price||0),0)/10;
      note.textContent=new Intl.NumberFormat('fa').format(selected.length)+' خدمت · '+new Intl.NumberFormat('fa').format(amount)+' تومان، قیمت پایه';
    } else note.textContent = picked.join(' · ');
    note.classList.remove('text-ink-400');
    note.classList.add('text-ink-700', 'font-semibold');
  }

  form.addEventListener('change', refresh);
  refresh();
})();
</script>
