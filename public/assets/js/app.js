/*
 * Reshen — رفتارهای مشترک رابط.
 *
 * همهٔ صفحه‌ها بدون این فایل هم کار می‌کنند (فرم‌ها POST معمولی‌اند،
 * details/summary بدون اسکریپت باز و بسته می‌شود). این فایل فقط تجربه
 * را روان‌تر می‌کند. نحو ES2017 است تا WebView قدیمی اندروید هم اجرا کند.
 */
(function () {
  'use strict';

  var doc = document;
  var root = doc.documentElement;
  var fa = function (n) { return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
  var latin = function (s) {
    return String(s).replace(/[۰-۹]/g, function (d) { return String(d.charCodeAt(0) - 1776); })
      .replace(/[٠-٩]/g, function (d) { return String(d.charCodeAt(0) - 1632); });
  };
  var money = function (rials) {
    var toman = Math.round(rials / 10);
    return fa(toman.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '٬')) + ' تومان';
  };
  var each = function (selector, fn, scope) { Array.prototype.forEach.call((scope || doc).querySelectorAll(selector), fn); };
  var supportsDialog = typeof HTMLDialogElement === 'function' && typeof HTMLDialogElement.prototype.showModal === 'function';

  function openDialog(dialog) {
    if (!dialog) return;
    if (supportsDialog) { if (!dialog.open) dialog.showModal(); } else { dialog.setAttribute('open', ''); }
  }
  function closeDialog(dialog) {
    if (!dialog) return;
    if (supportsDialog && dialog.open) dialog.close(); else dialog.removeAttribute('open');
  }

  /* ── حالت روشن/تیره ─────────────────────────────────────────────── */
  function paintModeButtons() {
    var dark = root.classList.contains('dark');
    each('[data-mode-toggle]', function (btn) {
      btn.setAttribute('aria-pressed', String(dark));
      btn.setAttribute('aria-label', dark ? 'روشن کردن صفحه' : 'تیره کردن صفحه');
      var sun = btn.querySelector('[data-mode-icon=sun]');
      var moon = btn.querySelector('[data-mode-icon=moon]');
      if (sun) sun.hidden = dark;
      if (moon) moon.hidden = !dark;
    });
  }
  doc.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-mode-toggle]');
    if (!btn) return;
    var dark = !root.classList.contains('dark');
    if (window.reshenApplyMode) window.reshenApplyMode(dark); else root.classList.toggle('dark', dark);
    try { localStorage.setItem('reshen-mode', dark ? 'dark' : 'light'); } catch (err) { /* حالت ناشناس */ }
    paintModeButtons();
  });
  paintModeButtons();

  /* ── تأیید پیش از اقدام خطرناک ───────────────────────────────────── */
  var confirmDialog = doc.getElementById('confirm-dialog');
  var pendingConfirm = null;
  doc.addEventListener('submit', function (e) {
    var form = e.target;
    var message = form.getAttribute('data-confirm') || (e.submitter && e.submitter.getAttribute('data-confirm'));
    if (!message || form.dataset.confirmed === 'true') return;
    e.preventDefault();
    if (!confirmDialog || !supportsDialog) {
      if (window.confirm(message)) { form.dataset.confirmed = 'true'; submitForm(form, e.submitter); }
      return;
    }
    pendingConfirm = { form: form, submitter: e.submitter };
    confirmDialog.querySelector('[data-confirm-text]').textContent = message;
    var ok = confirmDialog.querySelector('[data-confirm-ok]');
    var danger = (form.getAttribute('data-confirm-tone') || (e.submitter && e.submitter.getAttribute('data-confirm-tone'))) !== 'neutral';
    ok.className = 'btn ' + (danger ? 'btn--danger' : 'btn--primary');
    ok.textContent = form.getAttribute('data-confirm-ok') || (e.submitter && e.submitter.getAttribute('data-confirm-ok')) || 'بله، انجام شود';
    openDialog(confirmDialog);
    ok.focus();
  }, true);
  if (confirmDialog) {
    confirmDialog.addEventListener('click', function (e) {
      if (e.target.closest('[data-confirm-ok]') && pendingConfirm) {
        var p = pendingConfirm; pendingConfirm = null;
        closeDialog(confirmDialog);
        p.form.dataset.confirmed = 'true';
        submitForm(p.form, p.submitter);
      } else if (e.target.closest('[data-confirm-cancel]') || e.target === confirmDialog) {
        pendingConfirm = null;
        closeDialog(confirmDialog);
      }
    });
  }
  function submitForm(form, submitter) {
    if (typeof form.requestSubmit === 'function') { form.requestSubmit(submitter || undefined); return; }
    if (submitter && submitter.name) {
      var hidden = doc.createElement('input');
      hidden.type = 'hidden'; hidden.name = submitter.name; hidden.value = submitter.value;
      form.appendChild(hidden);
    }
    form.submit();
  }

  /* ── جلوگیری از ثبت دوباره و قطع اینترنت ─────────────────────────── */
  var offlineBar = null;
  function setOffline(off) {
    if (off && !offlineBar) {
      offlineBar = doc.createElement('div');
      offlineBar.className = 'offline-bar';
      offlineBar.setAttribute('role', 'status');
      offlineBar.textContent = 'اتصال اینترنت قطع است. اطلاعات ممکن است به‌روز نباشد و ثبت فرم تا وصل شدن ممکن نیست.';
      doc.body.appendChild(offlineBar);
    }
    if (offlineBar) offlineBar.hidden = !off;
  }
  window.addEventListener('online', function () { setOffline(false); });
  window.addEventListener('offline', function () { setOffline(true); });
  if (navigator.onLine === false) setOffline(true);

  doc.addEventListener('submit', function (e) {
    var form = e.target;
    // getAttribute، نه form.method: فیلدی به نام «method» (روش پرداخت) آن ویژگی را می‌پوشاند
    if (e.defaultPrevented || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    if (navigator.onLine === false) { e.preventDefault(); setOffline(true); return; }
    if (form.dataset.submitting === 'true') { e.preventDefault(); return; }
    // ارقام فارسی در فیلدهای عددی به لاتین تبدیل می‌شوند تا سرور درست بخواند
    each('[data-numeric]', function (input) { input.value = latin(input.value).replace(/[٬,\s]/g, ''); }, form);
    form.dataset.submitting = 'true';
    var btn = e.submitter || form.querySelector('[type=submit]');
    if (btn && btn.classList.contains('btn')) btn.setAttribute('aria-busy', 'true');
  });
  window.addEventListener('pageshow', function () {
    each('form[data-submitting]', function (form) {
      delete form.dataset.submitting; delete form.dataset.confirmed;
      each('[aria-busy=true]', function (b) { b.removeAttribute('aria-busy'); }, form);
    });
  });

  /* ── شیت و پنجره ─────────────────────────────────────────────────── */
  doc.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-open]');
    if (opener) { e.preventDefault(); openDialog(doc.getElementById(opener.getAttribute('data-open'))); return; }
    var closer = e.target.closest('[data-close]');
    if (closer) { closeDialog(closer.closest('dialog')); return; }
    if (e.target.tagName === 'DIALOG' && e.target.classList.contains('sheet')) closeDialog(e.target);
  });

  /* ── چاپ و بازگشت (جای onclick/javascript: درون‌خطی، تا CSP سفت بماند) ── */
  doc.addEventListener('click', function (e) {
    if (e.target.closest('[data-print]')) { e.preventDefault(); window.print(); return; }
    if (e.target.closest('[data-back]')) { e.preventDefault(); history.back(); }
  });

  /* ── منوی «بیشتر» (details) با Escape بسته می‌شود ─────────────────── */
  doc.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    each('details.more-menu[open]', function (d) { d.removeAttribute('open'); var s = d.querySelector('summary'); if (s) s.focus(); });
  });
  doc.addEventListener('click', function (e) {
    each('details.more-menu[open]', function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  /* ── باز و بسته کردن بخش (مثل فرم پذیرش) ─────────────────────────── */
  doc.addEventListener('click', function (e) {
    var t = e.target.closest('[data-toggle]');
    if (!t) return;
    var target = doc.getElementById(t.getAttribute('data-toggle'));
    if (!target) return;
    var open = target.hidden;
    target.hidden = !open;
    each('[data-toggle="' + target.id + '"]', function (b) { b.setAttribute('aria-expanded', String(open)); });
    if (open) { var f = target.querySelector('input:not([type=hidden]),select,textarea'); if (f) f.focus(); }
  });

  /* ── تب‌ها (WAI-ARIA, جهت‌یابی راست‌به‌چپ) ──────────────────────────── */
  each('[data-tabs]', function (list) {
    var tabs = Array.prototype.slice.call(list.querySelectorAll('[role=tab]'));
    var key = 'reshen-tab:' + (list.getAttribute('data-tabs') || location.pathname);
    function select(index, focus) {
      tabs.forEach(function (tab, i) {
        var on = i === index;
        tab.setAttribute('aria-selected', String(on));
        tab.tabIndex = on ? 0 : -1;
        var panel = doc.getElementById(tab.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tabs[index].focus();
      try { sessionStorage.setItem(key, tabs[index].id); } catch (err) { /* */ }
      if (history.replaceState && tabs[index].getAttribute('data-hash')) history.replaceState(null, '', '#' + tabs[index].getAttribute('data-hash'));
    }
    var initial = 0;
    var hash = location.hash.slice(1);
    tabs.forEach(function (tab, i) { if (hash && tab.getAttribute('data-hash') === hash) initial = i; });
    if (!hash) { try { var saved = sessionStorage.getItem(key); tabs.forEach(function (t, i) { if (t.id === saved) initial = i; }); } catch (err) { /* */ } }
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(i, false); });
      tab.addEventListener('keydown', function (e) {
        var next = null;
        if (e.key === 'ArrowLeft') next = (i + 1) % tabs.length;
        else if (e.key === 'ArrowRight') next = (i - 1 + tabs.length) % tabs.length;
        else if (e.key === 'Home') next = 0;
        else if (e.key === 'End') next = tabs.length - 1;
        if (next !== null) { e.preventDefault(); select(next, true); }
      });
    });
    select(initial, false);
  });

  /* ── خلاصهٔ زندهٔ انتخاب خدمت/زمان ──────────────────────────────── */
  each('form[data-live-summary]', function (form) {
    var out = form.querySelector('[data-summary]');
    var submit = form.querySelector('[data-summary-submit]');
    var empty = out ? out.getAttribute('data-empty') : '';
    function update() {
      var picked = Array.prototype.slice.call(form.querySelectorAll('input[data-price]:checked, input[data-label]:checked'));
      if (submit) submit.disabled = picked.length === 0;
      if (!out) return;
      if (!picked.length) { out.textContent = empty; return; }
      if (picked[0].hasAttribute('data-price')) {
        var rials = 0, minutes = 0, from = false;
        picked.forEach(function (i) { rials += +i.getAttribute('data-price'); minutes += +i.getAttribute('data-minutes'); from = from || i.hasAttribute('data-from'); });
        out.textContent = fa(picked.length) + ' خدمت · ' + (from ? 'از ' : '') + money(rials) + ' · حدود ' + fa(minutes) + ' دقیقه';
      } else {
        out.textContent = picked.map(function (i) { return i.getAttribute('data-label'); }).join('، ');
      }
    }
    form.addEventListener('change', update);
    update();
  });

  /* ── جست‌وجو و دسته‌بندی در فهرست ──────────────────────────────── */
  each('[data-filter]', function (host) {
    var scope = doc.getElementById(host.getAttribute('data-filter')) || doc;
    var input = host.querySelector('input[type=search]');
    var chips = Array.prototype.slice.call(host.querySelectorAll('[data-filter-chip]'));
    var status = host.querySelector('[data-filter-status]');
    var emptyBox = doc.getElementById(host.getAttribute('data-filter') + '-empty');
    var current = 'all';
    host.hidden = false;
    function norm(s) { return latin(s || '').toLowerCase().replace(/‌/g, ' ').replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').trim(); }
    function apply() {
      var q = norm(input ? input.value : '');
      var shown = 0;
      each('[data-filter-item]', function (item) {
        var okText = !q || norm(item.getAttribute('data-filter-item')).indexOf(q) !== -1;
        var okCat = current === 'all' || item.getAttribute('data-filter-cat') === current;
        var show = okText && okCat;
        // انتخاب‌شده‌ها هیچ‌وقت پنهان نمی‌شوند
        var input2 = item.querySelector('input:checked');
        item.hidden = !(show || input2);
        if (!item.hidden) shown++;
      }, scope);
      each('[data-filter-group]', function (group) { group.hidden = !group.querySelector('[data-filter-item]:not([hidden])'); }, scope);
      if (status) status.textContent = q || current !== 'all' ? fa(shown) + ' مورد' : '';
      if (emptyBox) emptyBox.hidden = shown !== 0;
    }
    if (input) input.addEventListener('input', apply);
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        current = chip.getAttribute('data-filter-chip');
        chips.forEach(function (c) { c.setAttribute('aria-pressed', String(c === chip)); });
        apply();
      });
    });
    each('[data-filter-reset]', function (b) { b.addEventListener('click', function () { if (input) input.value = ''; current = 'all'; chips.forEach(function (c, i) { c.setAttribute('aria-pressed', String(i === 0)); }); apply(); }); });
  });

  /* ── فیلدهای وابسته (مثلاً «تعطیل» ساعت‌ها را پنهان می‌کند) ────────── */
  each('[data-hides]', function (control) {
    var targets = doc.querySelectorAll(control.getAttribute('data-hides'));
    var invert = control.hasAttribute('data-hides-invert');
    function update() {
      var hide = invert ? !control.checked : control.checked;
      Array.prototype.forEach.call(targets, function (t) {
        t.hidden = hide;
        each('input,select,textarea', function (f) { f.disabled = hide; }, t);
      });
    }
    control.addEventListener('change', update);
    update();
  });

  /* ── جمع مبلغ تسویه ─────────────────────────────────────────────── */
  each('[data-sum]', function (out) {
    var form = out.closest('form');
    var plus = (out.getAttribute('data-sum') || '').split(',');
    var minus = (out.getAttribute('data-sum-minus') || '').split(',').filter(Boolean);
    function val(name) { var el = form.elements[name]; return el ? (+latin(el.value).replace(/[٬,\s]/g, '') || 0) : 0; }
    function update() {
      var total = 0;
      plus.forEach(function (n) { total += val(n); });
      minus.forEach(function (n) { total -= val(n); });
      out.textContent = fa(Math.max(0, total).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '٬')) + ' تومان';
    }
    form.addEventListener('input', update);
    update();
  });

  /* ── کپی (شمارهٔ کارت، لینک سالن) ───────────────────────────────── */
  doc.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var text = b.getAttribute('data-copy');
    var done = function () {
      var label = b.getAttribute('data-copied') || 'کپی شد';
      var old = b.textContent;
      b.textContent = label;
      setTimeout(function () { b.textContent = old; }, 1600);
    };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, function () {});
    else { var t = doc.createElement('textarea'); t.value = text; doc.body.appendChild(t); t.select(); try { doc.execCommand('copy'); done(); } catch (err) { /* */ } t.remove(); }
  });

  /* ── بستن پیام‌ها ───────────────────────────────────────────────── */
  doc.addEventListener('click', function (e) {
    var b = e.target.closest('[data-dismiss]');
    if (b) { var box = b.closest('.alert'); if (box) box.remove(); }
  });

  /* ── تازه‌سازی خودکار (کارت نوبت، صف امروز) ─────────────────────── */
  each('[data-auto-refresh]', function (el) {
    var seconds = Math.max(15, +el.getAttribute('data-auto-refresh') || 30);
    var ids = (el.getAttribute('data-refresh-ids') || '').split(',').filter(Boolean);
    var status = doc.getElementById(el.getAttribute('data-refresh-status') || '');
    var busy = false;
    function refresh() {
      if (busy || doc.hidden || navigator.onLine === false) return;
      if (!ids.length) { location.reload(); return; }
      // اگر کاربر وسط کار با یک فرم است، صفحه زیر دستش عوض نشود
      for (var i = 0; i < ids.length; i++) { var n = doc.getElementById(ids[i]); if (n && n.contains(doc.activeElement) && doc.activeElement !== doc.body) return; }
      if (doc.querySelector('details.more-menu[open]')) return;
      busy = true;
      fetch(location.href, { cache: 'no-store', credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { if (!r.ok || r.redirected) throw new Error('x'); return r.text(); })
        .then(function (html) {
          var next = new DOMParser().parseFromString(html, 'text/html');
          ids.forEach(function (id) { var a = doc.getElementById(id), b = next.getElementById(id); if (a && b) a.replaceWith(b); });
          if (status) status.textContent = 'به‌روز شد: ' + new Intl.DateTimeFormat('fa', { hour: '2-digit', minute: '2-digit' }).format(new Date());
        })
        .catch(function () { if (status) status.textContent = 'به‌روزرسانی ممکن نشد؛ اطلاعات ممکن است قدیمی باشد.'; })
        .then(function () { busy = false; });
    }
    setInterval(refresh, seconds * 1000);
    doc.addEventListener('visibilitychange', function () { if (!doc.hidden) refresh(); });
    window.reshenRefresh = refresh;
  });
  doc.addEventListener('click', function (e) { if (e.target.closest('[data-refresh-now]') && window.reshenRefresh) window.reshenRefresh(); });

  /* ── ارقام فارسی در فیلدهای عددی ────────────────────────────────── */
  doc.addEventListener('blur', function (e) {
    var t = e.target;
    if (t && t.hasAttribute && t.hasAttribute('data-numeric')) t.value = latin(t.value);
  }, true);

  /* ── دعوت به نصب PWA ────────────────────────────────────────────── */
  var install = doc.getElementById('install-card');
  if (install) {
    var KEY = 'reshen-install-dismissed', WEEK = 6048e5, deferred = null;
    var installed = function () { try { if (matchMedia('(display-mode: standalone)').matches) return true; } catch (err) { /* */ } return navigator.standalone === true; };
    var snoozed = function () { try { var at = +localStorage.getItem(KEY) || 0; return at > 0 && Date.now() - at < WEEK; } catch (err) { return false; } };
    var show = function (kind) {
      if (installed() || snoozed()) return;
      var text = doc.getElementById('install-text-' + kind); if (text) text.hidden = false;
      if (kind === 'android') doc.getElementById('install-go').hidden = false;
      install.hidden = false;
    };
    var hide = function () { install.hidden = true; try { localStorage.setItem(KEY, String(Date.now())); } catch (err) { /* */ } };
    doc.getElementById('install-close').addEventListener('click', hide);
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; show('android'); });
    doc.getElementById('install-go').addEventListener('click', function () { if (!deferred) return; deferred.prompt(); deferred.userChoice.then(function () { deferred = null; hide(); }); });
    var ua = navigator.userAgent;
    var ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (ios && /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua)) setTimeout(function () { show('ios'); }, 4000);
  }

  /* ── Skeleton هنگام رفتن به صفحهٔ بعد ───────────────────────────────
   * لینکی با data-skeleton-for="شناسه" محتوای آن ناحیه را تا رسیدن صفحهٔ
   * تازه با <template data-skeleton-tpl> همان ناحیه عوض می‌کند — روی
   * اینترنت کند، کاربر می‌بیند که کلیکش ثبت شده. */
  var skeletonSaved = [];
  doc.addEventListener('click', function (e) {
    var link = e.target.closest('a[data-skeleton-for]');
    if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || link.target === '_blank') return;
    var region = doc.getElementById(link.getAttribute('data-skeleton-for'));
    var tpl = region && region.querySelector('template[data-skeleton-tpl]');
    if (!tpl || region.getAttribute('aria-busy') === 'true') return;
    var kept = doc.createDocumentFragment();
    Array.prototype.slice.call(region.childNodes).forEach(function (n) { if (n !== tpl) kept.appendChild(n); });
    skeletonSaved.push({ region: region, nodes: kept });
    region.appendChild(tpl.content.cloneNode(true));
    region.setAttribute('aria-busy', 'true');
  });
  // برگشت با دکمهٔ «عقب» صفحه را از کش مرورگر می‌آورد؛ محتوای واقعی برگردد
  window.addEventListener('pageshow', function () {
    skeletonSaved.forEach(function (s) {
      var tpl = s.region.querySelector('template[data-skeleton-tpl]');
      Array.prototype.slice.call(s.region.childNodes).forEach(function (n) { if (n !== tpl) n.remove(); });
      s.region.insertBefore(s.nodes, tpl);
      s.region.removeAttribute('aria-busy');
    });
    skeletonSaved = [];
  });

  /* ── Tooltip ────────────────────────────────────────────────────────
   * دکمه‌های فقط‌آیکون (btn--icon با aria-label) خودکار؛ هر عنصر دیگر با
   * data-tooltip. با hover و فوکوس صفحه‌کلید باز، با Escape بسته می‌شود.
   * متن همان aria-label است، پس برای صفحه‌خوان دوباره خوانده نمی‌شود؛
   * data-tooltip متفاوت با aria-describedby وصل می‌شود. */
  var tip = null, tipOwner = null;
  function tipText(el) { return el.getAttribute('data-tooltip') || el.getAttribute('aria-label') || ''; }
  function tipFor(target) {
    var el = target && target.closest ? target.closest('[data-tooltip], .btn--icon[aria-label]') : null;
    return el && tipText(el) ? el : null;
  }
  function showTip(el) {
    if (!tip) { tip = doc.createElement('div'); tip.className = 'tooltip'; tip.id = 'reshen-tooltip'; tip.setAttribute('role', 'tooltip'); tip.hidden = true; doc.body.appendChild(tip); }
    tipOwner = el;
    tip.textContent = tipText(el);
    if (el.title) { el.setAttribute('data-title', el.title); el.removeAttribute('title'); } // جلوگیری از tooltip دوگانهٔ مرورگر
    if (el.hasAttribute('data-tooltip') && el.getAttribute('data-tooltip') !== el.getAttribute('aria-label')) el.setAttribute('aria-describedby', tip.id);
    tip.hidden = false;
    var r = el.getBoundingClientRect(), t = tip.getBoundingClientRect();
    var top = r.top - t.height - 8;
    if (top < 8) top = r.bottom + 8;
    var left = Math.min(Math.max(8, r.left + r.width / 2 - t.width / 2), window.innerWidth - t.width - 8);
    tip.style.top = top + 'px';
    tip.style.left = left + 'px';
  }
  function hideTip() {
    if (!tip || tip.hidden) return;
    tip.hidden = true;
    if (tipOwner && tipOwner.getAttribute('aria-describedby') === 'reshen-tooltip') tipOwner.removeAttribute('aria-describedby');
    tipOwner = null;
  }
  if (window.matchMedia && window.matchMedia('(hover:hover)').matches) {
    doc.addEventListener('mouseover', function (e) { var el = tipFor(e.target); if (el && el !== tipOwner) showTip(el); else if (!el) hideTip(); });
  }
  function focusVisible(el) { try { return el.matches(':focus-visible'); } catch (err) { return true; } } // WebView قدیمی
  doc.addEventListener('focusin', function (e) { var el = tipFor(e.target); if (el && focusVisible(e.target)) showTip(el); });
  doc.addEventListener('focusout', hideTip);
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') hideTip(); });
  window.addEventListener('scroll', hideTip, true);

  /* ── پیش‌نمایش تم (گالری سیستم طراحی) ───────────────────────────── */
  each('[data-theme-preview]', function (select) {
    var target = doc.querySelector(select.getAttribute('data-theme-preview'));
    if (!target) return;
    select.addEventListener('change', function () { target.setAttribute('data-theme', select.value); });
  });

  /* ── سرویس‌ورکر ─────────────────────────────────────────────────── */
  if ('serviceWorker' in navigator && window.isSecureContext && root.getAttribute('data-sw')) {
    window.addEventListener('load', function () { navigator.serviceWorker.register(root.getAttribute('data-sw')).catch(function () {}); });
  }
})();
