/*
 * سرویس‌ورکر رشن.
 *
 * عمداً برای همه‌چیز «اول شبکه» است: صف، دادهٔ زنده است و کش کردنش یعنی
 * آرایشگر صفی را می‌بیند که همین حالا غلط است — که از نبودِ حالت آفلاین
 * بدتر است.
 *
 * فقط پوستهٔ ثابت کش می‌شود تا برنامه روی وای‌فای ضعیفِ سالن دست‌کم
 * **باز شود** و اسکلتش بیاید (سند ۸.۱).
 *
 * نسخه را با هر تغییرِ دارایی‌ها بالا ببر، وگرنه مرورگر CSS قدیمی را
 * نگه می‌دارد و طراحی تازه دیده نمی‌شود.
 */
const VERSION = 'v14';
const SHELL_CACHE = `reshen-shell-${VERSION}`;

/*
 * مسیرها نسبی‌اند چون پروژه ممکن است در زیرپوشه نصب شود
 * (example.com/reshen/). مسیر مطلق آنجا به جای اشتباه می‌خورد.
 * نسخهٔ دارایی‌ها باید با RESHEN_ASSET_VERSION در helpers.php یکی باشد.
 */
const SHELL = [
  './offline.html',
  './assets/css/reshen.css',
  './assets/js/app.js',
  './assets/fonts/IRANYekanX-Regular.woff2',
  './assets/fonts/IRANYekanX-Medium.woff2',
  './assets/fonts/IRANYekanX-DemiBold.woff2',
  './assets/fonts/IRANYekanX-Bold.woff2',
  './assets/icons/icon.svg',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
  './assets/icons/apple-touch-icon.png',
].map(url => /\.(css|js)$/.test(url) ? `${url}?v=${VERSION}` : url);

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) =>
      // addAll اگر یکی از فایل‌ها ۴۰۴ بدهد، **کل** نصب را رد می‌کند.
      // پس تک‌تک، و شکستِ یکی بقیه را زمین نمی‌زند.
      Promise.all(SHELL.map((url) => cache.add(url).catch(() => null)))
    )
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k.startsWith('reshen-shell-') && k !== SHELL_CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // فقط همین دامنه. درخواست بیرونی را دست نمی‌زنیم.
  if (url.origin !== self.location.origin) return;

  // دارایی‌های ثابت: اول کش، چون تغییر نمی‌کنند و نسخه در نام کش است.
  const isAsset = url.pathname.includes('/assets/');

  if (isAsset) {
    event.respondWith(
      caches.match(request).then((cached) =>
        cached || fetch(request).then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
          }
          return response;
        })
      )
    );
    return;
  }

  // صفحات و داده فقط از شبکه؛ هنگام قطع، صفحهٔ عمومی آفلاین نمایش داده می‌شود.
  event.respondWith(
    fetch(request).catch(async () => request.mode === 'navigate' ? (await caches.match(new URL('./offline.html', self.registration.scope))) : new Response('', {status:503}))
  );
});

