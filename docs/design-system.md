# سیستم طراحی رشن — نسخهٔ ۲

یک فایل CSS، یک فایل JS، یک اسپرایت آیکون. هر صفحهٔ رشن — رزرو مشتری،
کشف سالن، پنل سالن، پنل پلتفرم، صفحهٔ سلامت — از همین سه فایل ساخته
می‌شود؛ پس هر اصلاح یک بار انجام می‌شود و همه‌جا دیده می‌شود.

| فایل | نقش |
|---|---|
| `public/assets/css/reshen.css` | توکن‌ها، تم‌ها و همهٔ اجزا (حدود ۷۳KB، gzip ≈ ۱۴KB) |
| `public/assets/js/app.js` | رفتارها با `data-*`؛ ES2017، بدون کتابخانه (gzip ≈ ۶KB) |
| `public/assets/js/discovery.js` | فقط صفحهٔ کشف: فاصله و مرتب‌سازی بر اساس موقعیت |
| `resources/views/components/icons.svg` | اسپرایت آیکون‌های Lucide (ISC)؛ با `icon('name')` |
| `tools/check-contrast.mjs` | سنجش کنتراست همهٔ تم‌ها در حالت روشن و تیره |
| `tools/check-inline-styles.php` | نگهبان: در نماها فقط پارامتر `--x` درون‌خطی مجاز است |
| `/system/design` | **گالری زنده**: همهٔ اجزا با همهٔ حالت‌ها، پیش‌نمایش ۱۳ رنگ برند و حالت تیره |
| `tests/ui/` | آزمون خودکار دسترس‌پذیری، چیدمان و تصویری (CI) |

نسخهٔ CSS/JS و سرویس‌ورکر از فایل `VERSION` خوانده می‌شود؛ با هر انتشار خودش عوض می‌شود.

## اصول

- **راست‌به‌چپ از پایه.** فقط ویژگی‌های منطقی (`inline`/`block`)؛ هیچ
  `left`/`right` ثابتی در اجزا نیست. آیکون‌های جهت‌دار `chevron-start` و
  `chevron-end` هستند، نه چپ و راست.
- **دسترس‌پذیری WCAG 2.2 AA.** هدف لمسی ≥ ۴۴px برای کنترل‌ها و ≥ ۲۴px
  برای پیوندهای مستقل، حلقهٔ فوکوس همیشه پیدا، ورودی‌ها ۱۶px (بدون
  بزرگ‌نمایی خودکار iOS)، مرز ورودی ≥ ۳:۱، متن ≥ ۴٫۵:۱.
- **رنگ برند وضعیت را حمل نمی‌کند.** خطر/موفقیت/هشدار ثابت‌اند؛ سالنی که
  رنگ قرمز (یاقوتی) انتخاب کند، دکمهٔ «لغو» را با دکمهٔ اصلی اشتباه
  نمی‌گیرد.
- **سازگاری.** بدون `@layer` و `:has()` برای رفتار اصلی، تا WebViewهای
  قدیمی اندروید هم درست نشان دهند. `<dialog>` بومی با جایگزین `confirm`.
- **بدون وابستگی بیرونی.** فونت‌ها محلی‌اند؛ صفحه بدون CDN و حتی در
  حالت آفلاین (پوستهٔ کش‌شده) باز می‌شود.

## سه لایهٔ توکن

1. **پایه (Primitives)** — طیف‌های خاکستری و رنگ، مقیاس فاصله، شعاع،
   سایه و تایپوگرافی. اجزا مستقیم از این لایه استفاده نمی‌کنند.
2. **معنایی (Semantic)** — نقش‌ها: `--bg`، `--surface`، `--surface-sunken`،
   `--text`، `--text-muted`، `--border`، `--border-input`، `--accent`،
   `--on-accent`، `--accent-soft`، `--accent-text`، `--success-*`،
   `--warning-*`، `--danger-*`، `--focus`. حالت تیره و تم‌های برند فقط
   همین لایه را بازتعریف می‌کنند.
3. **اجزا** — فقط توکن معنایی مصرف می‌کنند.

### حالت روشن/تیره

کلاس `dark` روی `<html>`؛ اسکریپت درون‌خطی `body-start.php` پیش از
نقاشی صفحه آن را از `localStorage` یا تنظیم سیستم می‌خواند (بدون
چشمک). دکمهٔ `data-mode-toggle` آن را عوض می‌کند و متای `theme-color`
را هم هماهنگ می‌کند.

### تم‌های برند سالن (۱۳ رنگ)

`data-theme="rose"` روی هر عنصری — نه فقط `<html>` — توکن‌های تأکید را
برای همان زیردرخت عوض می‌کند. به همین دلیل در صفحهٔ «کشف» هر کارت با
رنگ سالن خودش دیده می‌شود.

| کلید | نام | پیشنهاد برای |
|---|---|---|
| forest | سبز رشن (پیش‌فرض) | همه |
| teal، gold، emerald | فیروزه‌ای، طلایی، زمردی | همه |
| ink، indigo، copper | زغالی، نیلی، مسی | آرایشگاه مردانه |
| rose، ruby، plum، lavender، mauve، nude | رز، یاقوتی، آلویی، یاسی، گلبهی، کرم‌قهوه‌ای | سالن بانوان |

هر تم در هر دو حالت آزموده می‌شود: `node tools/check-contrast.mjs`
(۱۹۶ جفت رنگ؛ اگر یکی رد شود، خروجی غیرصفر است).

## سالن مردانه، سالن بانوان، هر دو

نوع سالن (`salons.audience`: `men` / `women` / `unisex`) فقط رنگ نیست:

- **واژه‌ها** از `Audience::term()` / `term()`: «آرایشگر» یا «متخصص»،
  «آرایشگاه» یا «سالن».
- **روند رزرو پیش‌فرض:** مردانه «اول زمان» (کی آزادی؟)، بانوان
  «اول خدمت» (چه کاری؟ چون مدت و متخصص به خدمت بستگی دارد).
- **تصاویر:** عکس‌های نمونهٔ همراه برنامه همه مردانه‌اند؛ پس فقط برای
  آرایشگاه مردانه و همیشه با برچسب «تصویر نمونه» نشان داده می‌شوند.
  سالن بانوان و مختلط تا عکس واقعی بارگذاری نکنند، کاشی رنگی برند با
  نماد دستهٔ خدمت می‌بینند (`service_media()`، `salon_cover()`).
- **نشان سالن** در پنل: قیچی برای مردانه، درخشش برای بانوان.

## اجزا

| جزء | کلاس‌ها | نکته |
|---|---|---|
| دکمه | `btn` + `btn--primary/secondary/tonal/ghost/danger/danger-ghost/success/link` و `btn--sm/lg/xl/block/icon` | حالت ارسال با `aria-busy` |
| فیلد | `field`، `field__label`، `field__hint`، `field__error`، `input`، `select`، `textarea`، `input-group` | خطا با `aria-invalid` و `aria-describedby="{key}-error"`؛ جزء `field-error` |
| انتخاب کارتی | `choice`، `choice__input`، `choice__card`، `choice-grid`، `choice-list` | رادیو/چک‌باکس واقعی؛ با صفحه‌کلید کار می‌کند |
| سوییچ | `switch`، `switch__track` | |
| کارت و بخش | `card`، `card__header/body/footer`، `section`، `page-head` | |
| فهرست | `list`، `list-row` و زیربخش‌ها، `kv` برای کلید/مقدار | |
| نشان و وضعیت | `badge--*`، `dot--*`، `status_badge()` | |
| هشدار | `alert--info/success/warning/danger/accent` | پیام‌های flash در `components/flash.php` |
| زبانه | `tabs`، `tab` با `data-tabs` و `data-hash` | زبانهٔ فعال در نشانی (`#rules`) می‌ماند |
| آمار و نمودار | `stats`، `stat`، `bars`/`bars__bar` (ارتفاع با `--v`)، `meter` | نمودار بدون کتابخانه |
| جدول | `table-wrap`، `table`، `table--stack` | زیر ۷۶۸px هر ردیف کارت می‌شود (برچسب از `data-label`) |
| پنجره | `dialog`، `sheet` (شیت پایین موبایل) | `data-open`/`data-close`؛ تأیید عمومی با `data-confirm` |
| خالی | `components/empty-state.php` | |
| مراحل رزرو | `stepper`، `context-chips`، `day-strip`، `slot-grid`، `action-bar` | |
| بارگذاری | `skeleton` (`--text`، `--circle`، `--block`، `--pill`)، `components/skeleton.php` | ناحیهٔ `data-skeleton-region` با `<template data-skeleton-tpl>`؛ لینک با `data-skeleton-for` |
| صفحه‌بندی | `components/pagination.php` | `page` + `pages` یا `hasNext`، `url`، `skeletonFor` |
| tooltip | `.btn--icon[aria-label]` خودکار، `[data-tooltip]` | hover و فوکوس صفحه‌کلید؛ Escape می‌بندد |

## پوسته‌ها (قالب‌ها)

| قالب | کاربرد |
|---|---|
| `layouts/panel.php` | پنل سالن. دسکتاپ: نوار کناری گروه‌بندی‌شده؛ موبایل: نوار بالا + منوی پایین با حداکثر ۵ مقصد و شیت «بیشتر». منو از `PanelNavigation` و بر اساس نقش ساخته می‌شود. |
| `layouts/platform.php` | پنل مدیر پلتفرم |
| `layouts/booking.php` | مسیر رزرو مشتری با رنگ سالن |
| `layouts/discover.php`، `public.php`، `customer.php` | کشف سالن، صفحات عمومی، «نوبت‌های من» |
| `layouts/auth.php` | ورود، ثبت‌نام و ساخت سالن |
| `layouts/minimal.php` | خطاها (۴۰۳، ۴۰۴، ۵۰۰) |

## رفتارهای JS (`app.js`)

همه با ویژگی داده فعال می‌شوند؛ صفحه بدون JS هم کار می‌کند (فرم‌ها
معمولی ارسال می‌شوند).

| ویژگی | رفتار |
|---|---|
| `data-confirm`، `data-confirm-tone`، `data-confirm-ok` | پنجرهٔ تأیید پیش از ارسال فرم (روی فرم یا دکمه) |
| `data-numeric` | تبدیل ارقام فارسی/عربی و حذف جداکننده پیش از ارسال |
| (همهٔ فرم‌های POST) | جلوگیری از ارسال دوباره و ارسال در حالت آفلاین |
| `data-tabs`، `data-hash` | زبانه‌های قابل دسترس با صفحه‌کلید |
| `data-open`، `data-close` | باز و بسته کردن `<dialog>` و شیت |
| `data-filter*` | جست‌وجو و فیلتر دسته در فهرست خدمات |
| `data-sum`، `data-live-summary`، `data-summary*` | جمع زندهٔ مبلغ و مدت در انتخاب خدمت |
| `data-hides`، `data-toggle` | نمایش شرطی بخش‌های فرم |
| `data-copy` | کپی در حافظه با بازخورد |
| `data-auto-refresh`، `data-refresh-*` | به‌روزرسانی سبک صف امروز |
| `data-mode-toggle` | حالت روشن/تیره |
| `data-dismiss` | بستن پیام |
| `data-skeleton-for` | نمایش skeleton ناحیهٔ هدف تا رسیدن صفحهٔ بعد |
| `data-tooltip` | tooltip با توضیح جدا از برچسب |
| `data-theme-preview` | عوض‌کردن رنگ برند یک ظرف (گالری) |

> فیلدی به نام `method`، `action` یا `submit` ویژگی هم‌نام فرم را
> می‌پوشاند؛ در JS همیشه از `getAttribute` استفاده کنید.

## ابزارها و قاعدهٔ «بدون استایل درون‌خطی»

در نماها `style="…"` فقط برای **پارامترِ** یک جزء مجاز است: `--v` (درصد
نمودار)، `--c` (رنگ کارکنان)، `--gap`، `--cols`، `--min`، `--avatar-bg`.
هر چیز دیگری (margin، width، color…) با یک کلاس انجام می‌شود؛ اگر کلاسش
نیست، به `reshen.css` اضافه‌اش کنید. `php tools/check-inline-styles.php`
در CI این را می‌سنجد.

| گروه | کلاس‌ها |
|---|---|
| چیدمان | `.wrap` `.justify-center` `.justify-between` `.items-start` `.items-center` `.items-end` `.gap-0` `.grow` `.grow-200` `.basis-200` `.shrink-0` `.span-full` `.min-w-0` |
| اندازه | `.w-full` `.w-xs` (۱۱۰) `.w-sm` (۱۶۰) `.w-md` (۲۴۰) `.w-lg` (۳۶۰) `.container-xs` (۴۴۰) `.container-sm` (۵۶۰) `.container-md` (۶۴۰) |
| متن | `.link-plain` `.break-anywhere` `.truncate` `.clamp-2` `.nowrap` |
| حالت | `.is-inactive` `.is-struck` `.is-placeholder` `.sr-only` `.only-mobile` `.only-desktop` |
| بلوک | `.list-reset` `.list-bulleted` `.code-block` `.scroll-box` `.divider` |
| نسخه‌های اندازه | `.icon-tile--sm/--md/--lg` `.brand-mark--sm/--lg/--xl` `.avatar--sm/--lg` `.table-wrap--flush` `.check--compact` `.input--static` |

## آزمون خودکار (tests/ui)

در CI روی هر push اجرا می‌شود و **پیش‌شرط انتشار** است؛ نسخه‌ای که ظاهر یا
دسترس‌پذیری را خراب کند به هاست‌ها نمی‌رسد.

| آزمون | چه می‌سنجد |
|---|---|
| `a11y.spec.mjs` | axe با WCAG 2.2 AA روی ۲۹ صفحهٔ همهٔ نقش‌ها، روشن و تیره؛ خطای serious/critical آزمون را می‌شکند |
| `layout.spec.mjs` | همان صفحه‌ها در ۳۲۰ و ۱۲۸۰ پیکسل: بدون اسکرول افقی، بدون خطای JS |
| `visual.spec.mjs` | عکس هر بخش گالری در دسکتاپ روشن/تیره، موبایل و تم `rose`، مقایسه با `__screenshots__` |

اجرای محلی (سرور روی `127.0.0.1:8080` و دادهٔ `tools/seed-demo.php`):

```text
cd tests/ui && npm ci
npx playwright test               # همه
npm run update                    # پس از تغییر عمدی ظاهر: عکس‌های مبنای تازه
```

اگر عکس‌های ساخته‌شده روی رایانهٔ شما با محیط CI اندکی فرق داشت، از
Actions ← CI ← Run workflow با گزینهٔ `update_snapshots` عکس‌های مبنا را
روی خود CI بسازید و از artifact جایگزین کنید.

## افزودن صفحهٔ تازه

1. نما را با `page-head` شروع کنید؛ عنوان یک `h1`.
2. فقط از کلاس‌ها و توکن‌های بالا استفاده کنید؛ رنگ هگز در نما ننویسید
   (به‌جز رنگ اختصاصی کارکنان که `StaffColor` تیرگی‌اش را تضمین می‌کند).
3. متن فارسی؛ ارقام با `fa_num()`، مبلغ با `toman()`/`price_text()`، مدت با
   `duration_text()`، تاریخ با `jdate()` یا `JalaliCalendar::humanDate()`.
4. چسباندن بخش‌های متن با `join_parts()` — هرگز `trim($x, '، ')` (روی بایت
   کار می‌کند و حروف فارسی را می‌بُرد).
5. نشانی صفحه را به `tests/ui/pages.mjs` اضافه کنید تا دسترس‌پذیری و چیدمانش
   خودکار آزموده شود. جزء تازه را در گالری (`resources/views/system/design.php`)
   بگذارید و عکس مبنا بسازید.
