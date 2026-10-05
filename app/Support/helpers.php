<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Support\Audience;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\ServiceVisual;
use App\Support\StaffColor;

/*
 * توابع کمکی ویو.
 *
 * نسخهٔ دارایی‌ها از فایل VERSION می‌آید: هر انتشار (و هر به‌روزرسانی
 * خودکار) نشانی CSS/JS و سرویس‌ورکر را عوض می‌کند و مرورگرها پوستهٔ
 * تازه را می‌گیرند — بدون اینکه کسی یادش باشد عددی را دستی بالا ببرد.
 */
if (!defined('RESHEN_ASSET_VERSION')) {
    define('RESHEN_ASSET_VERSION', 'v' . (defined('BASE_PATH') ? App\Support\Version::current() : '0'));
}

if (!function_exists('sw_url')) {
    /** نشانی سرویس‌ورکر با نسخه؛ تغییر نشانی، سرویس‌ورکر را تازه می‌کند. */
    function sw_url(): string
    {
        return url('service-worker.js') . '?v=' . rawurlencode(RESHEN_ASSET_VERSION);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csp_nonce')) {
    /** nonce سیاست امنیت محتوا برای تگ‌های <script> درون‌خطی. */
    function csp_nonce(): string
    {
        return \App\Core\Security::nonce();
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return Request::basePath() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('salon_logo_url')) {
    /** آدرس لوگوی سالن، یا null اگر نداشته باشد. */
    function salon_logo_url(?string $file): ?string
    {
        if ($file === null || $file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            return null;
        }

        $dir = (string) App\Core\Config::get('reshen.uploads.logos_dir', 'uploads/logos');

        return url($dir . '/' . basename($file));
    }
}

if (!function_exists('absolute_url')) {
    /**
     * آدرس کامل با دامنه — برای QR، پیامک و تقویم.
     *
     * دامنه از خودِ درخواست خوانده می‌شود نه از APP_URL، چون صاحب سالن
     * ممکن است دامنه را عوض کند و .env را فراموش کند.
     */
    function absolute_url(string $path = ''): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host === '' || !preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) {
            return rtrim((string) App\Core\Config::get('app.url', ''), '/') . '/' . ltrim($path, '/');
        }

        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
        $proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || $https ? 'https' : 'http';

        return $proto . '://' . $host . url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $assetUrl = url('assets/' . ltrim($path, '/'));

        return preg_match('/\.(css|js)$/', $path) ? $assetUrl . '?v=' . RESHEN_ASSET_VERSION : $assetUrl;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('flash')) {
    function flash(string $key): mixed
    {
        return Session::flash($key);
    }
}

if (!function_exists('field_error')) {
    /**
     * خطای یک فیلد مشخص — کنار همان فیلد نمایش داده می‌شود، نه فقط
     * به‌صورت یک پیام کلی بالای صفحه.
     */
    function field_error(string $key): ?string
    {
        static $errors = null;
        if ($errors === null) {
            $errors = Session::flash('field_errors') ?? [];
        }

        return isset($errors[$key]) ? (string) $errors[$key] : null;
    }
}

if (!function_exists('jdate')) {
    function jdate(?string $datetime, string $format = 'Y/m/d H:i'): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }

        return Jalali::format(new DateTimeImmutable($datetime), $format);
    }
}

if (!function_exists('jalali_date_from_request')) {
    /**
     * سه فیلدِ انتخابگر تاریخ شمسی را به «Y-m-d» میلادی تبدیل می‌کند.
     * «۳۱ مهر» به آخرین روزِ همان ماه بریده می‌شود.
     */
    function jalali_date_from_request(App\Core\Request $request, string $name): ?string
    {
        $y = int_input($request->input($name . '_y'));
        $m = int_input($request->input($name . '_m'));
        $d = int_input($request->input($name . '_d'));

        if ($y === null || $m === null || $d === null || $y < 1300 || $y > 1500 || $m < 1 || $m > 12 || $d < 1) {
            return null;
        }

        $d = min($d, Jalali::daysInJalaliMonth($y, $m));

        return Jalali::toDateTime($y, $m, $d)->format('Y-m-d');
    }
}

if (!function_exists('int_input')) {
    /**
     * عدد صحیح از ورودی کاربر، با ارقام فارسی/عربی و جداکنندهٔ هزارگان.
     *
     * (int) "۱۲۰٬۰۰۰" در PHP صفر می‌شود؛ یعنی قیمتی که با کیبورد فارسی
     * تایپ شده بی‌صدا رایگان ذخیره می‌شد.
     */
    function int_input(mixed $value): ?int
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        $clean = preg_replace('/[\s,٬\x{066C}\x{2009}\x{202F}]/u', '', Jalali::fromPersianDigits((string) $value)) ?? '';
        $clean = strtr($clean, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return preg_match('/^-?\d{1,15}$/', $clean) ? (int) $clean : null;
    }
}

if (!function_exists('fa_time')) {
    /** «۱۹:۳۰» — ۲۴ساعته با رقم فارسی. */
    function fa_time(?string $time): string
    {
        return $time === null || $time === '' ? '' : App\Support\Clock::hm($time);
    }
}

if (!function_exists('fa_time_label')) {
    /** «۷:۳۰ شب» — برای جایی که ساعت تنهاست. */
    function fa_time_label(?string $time): string
    {
        return $time === null || $time === '' ? '' : App\Support\Clock::label($time);
    }
}

if (!function_exists('toman')) {
    function toman(int $rials): string
    {
        return Money::fromRials($rials)->formatToman();
    }
}

if (!function_exists('price_text')) {
    /**
     * قیمت برای نمایش به مشتری.
     *
     * «از …» برای خدمتی که قیمتش به طول و حجم مو بستگی دارد. قیمت صفرِ
     * «از» یعنی «پس از مشاوره» — نه «رایگان»، که وعدهٔ غلط است.
     */
    function price_text(int $rials, string $priceType = 'fixed'): string
    {
        if ($rials <= 0) {
            return $priceType === 'from' ? 'پس از مشاوره' : 'رایگان';
        }

        return ($priceType === 'from' ? 'از ' : '') . toman($rials);
    }
}

if (!function_exists('price_range_text')) {
    function price_range_text(int $min, int $max, bool $from = false): string
    {
        if ($min === $max) {
            return price_text($min, $from ? 'from' : 'fixed');
        }

        return ($from ? 'از ' : '') . Jalali::toPersianDigits(number_format(intdiv($min, 10), 0, '.', '٬'))
            . ' تا ' . toman($max);
    }
}

if (!function_exists('duration_text')) {
    /** «۴۵ دقیقه»، «۱ ساعت و ۳۰ دقیقه». */
    function duration_text(int $minutes): string
    {
        if ($minutes < 60) {
            return fa_num($minutes) . ' دقیقه';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return fa_num($h) . ' ساعت' . ($m > 0 ? ' و ' . fa_num($m) . ' دقیقه' : '');
    }
}

if (!function_exists('fa_num')) {
    function fa_num(int|float|string $value): string
    {
        return Jalali::toPersianDigits((string) $value);
    }
}

if (!function_exists('phone_local')) {
    /** ‎+989121234567 → «۰۹۱۲ ۱۲۳ ۴۵۶۷» */
    function phone_local(?string $e164): string
    {
        if ($e164 === null || $e164 === '') {
            return '';
        }
        $phone = App\Support\IranMobile::tryParse($e164);

        return fa_num($phone !== null ? $phone->local() : $e164);
    }
}

if (!function_exists('brand')) {
    /** نام سامانه که مدیر کل در تنظیمات گذاشته (پیش‌فرض «رشن»). */
    function brand(): string
    {
        return App\Domain\System\SiteSettings::brandName();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        static $old = null;
        if ($old === null) {
            $old = Session::flash('_old') ?? [];
        }

        return $old[$key] ?? $default;
    }
}

if (!function_exists('term')) {
    /** واژهٔ متناسب با مخاطب سالن: «آرایشگر» یا «متخصص»… */
    function term(string $key, ?string $audience = null): string
    {
        return Audience::term($key, $audience);
    }
}

if (!function_exists('icon')) {
    /**
     * آیکون از اسپرایت (Lucide، لایسنس ISC).
     *
     * بدون برچسب، تزئینی است و صفحه‌خوان نادیده‌اش می‌گیرد.
     */
    function icon(string $name, string $class = 'icon', ?string $label = null): string
    {
        $aria = $label === null
            ? 'aria-hidden="true" focusable="false"'
            : 'role="img" aria-label="' . e($label) . '"';

        return '<svg class="' . e($class) . '" ' . $aria . '><use href="#i-' . e($name) . '"></use></svg>';
    }
}

if (!function_exists('partial')) {
    /** رندر یک جزء از resources/views/components. */
    function partial(string $name, array $data = []): string
    {
        return View::render('components.' . $name, $data);
    }
}

if (!function_exists('service_visual')) {
    /** @return array{category:string,icon:string} */
    function service_visual(string $name, ?string $categoryVisual = null): array
    {
        $key = ServiceVisual::resolve($name, $categoryVisual);

        return ['category' => $key, 'icon' => ServiceVisual::icon($key)];
    }
}

if (!function_exists('service_icon')) {
    function service_icon(string $name, ?string $categoryVisual = null): string
    {
        return service_visual($name, $categoryVisual)['icon'];
    }
}

if (!function_exists('service_media')) {
    /**
     * تصویر کوچک خدمت.
     *
     * عکس واقعی سالن اگر بارگذاری شده باشد. عکس‌های نمونهٔ همراه برنامه
     * همه از آرایشگاه مردانه‌اند، پس فقط برای سالن مردانه استفاده
     * می‌شوند؛ سالن بانوان و مختلط کاشیِ آیکون‌دار با رنگ برند خودش را
     * می‌گیرد — نه عکس مردی که موی سرش کوتاه می‌شود.
     */
    function service_media(array $service, string $class = 'service-thumb', bool $eager = false, ?string $audience = null): string
    {
        $file = (string) ($service['image_file'] ?? '');
        $loading = $eager ? 'eager' : 'lazy';

        if ($file !== '' && preg_match('/^[a-zA-Z0-9-]+\.webp$/', $file)) {
            return '<span class="media ' . e($class) . '"><img src="' . e(url('uploads/media/' . $file)) . '" alt="" width="240" height="240" loading="' . $loading . '" decoding="async"></span>';
        }

        $audience ??= App\Support\SalonContext::get() !== null ? App\Support\SalonContext::audience() : 'men';
        $visual = ServiceVisual::resolve((string) ($service['name'] ?? ''), $service['category_visual'] ?? null);
        if ($audience === 'men' && ServiceVisual::hasPhoto($visual)) {
            $small = e(asset('images/services/' . $visual . '-240.webp'));
            $large = e(asset('images/services/' . $visual . '-640.webp'));

            return '<span class="media ' . e($class) . '"><img src="' . $small . '" srcset="' . $small . ' 240w, ' . $large . ' 640w" sizes="96px" alt="" width="240" height="240" loading="' . $loading . '" decoding="async"></span>';
        }

        return '<span class="media ' . e($class) . ' is-icon">' . icon(ServiceVisual::icon($visual)) . '</span>';
    }
}

if (!function_exists('service_photo')) {
    /** نام قدیمی؛ برای سازگاری با ویوهای سفارشی. */
    function service_photo(string $name, string $class = 'service-thumb', bool $eager = false, ?string $imageFile = null): string
    {
        return service_media(['name' => $name, 'image_file' => $imageFile], 'service-thumb', $eager);
    }
}

if (!function_exists('status_meta')) {
    /**
     * برچسب و لحن رنگی هر وضعیت نوبت.
     *
     * @return array{0:string,1:string} [برچسب، tone برای کلاس badge--*]
     */
    function status_meta(string $status, ?string $audience = null): array
    {
        return match ($status) {
            'pending' => ['منتظر بیعانه', 'warning'],
            'confirmed' => ['تأییدشده', 'accent'],
            'queued' => ['در صف', 'info'],
            'in_chair' => [term('in_service', $audience), 'success'],
            'completed' => ['انجام‌شده', 'neutral'],
            'cancelled' => ['لغوشده', 'danger'],
            'no_show' => ['غیبت', 'neutral'],
            default => [$status, 'neutral'],
        };
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        [$label, $tone] = status_meta($status);

        return '<span class="badge badge--' . e($tone) . '">' . e($label) . '</span>';
    }
}

if (!function_exists('role_label')) {
    function role_label(?string $role): string
    {
        return match ($role) {
            'owner' => 'صاحب سالن',
            'manager' => 'مدیر',
            'reception' => 'پذیرش',
            'staff' => term('staff'),
            default => '',
        };
    }
}

if (!function_exists('staff_color')) {
    /** رنگ آواتار، فقط از پالت سنجیده‌شده — مقدار خام هرگز در style نمی‌نشیند. */
    function staff_color(?string $hex): string
    {
        return StaffColor::resolve($hex);
    }
}

if (!function_exists('initial')) {
    function initial(?string $name): string
    {
        $name = trim((string) $name);

        return $name === '' ? '؟' : mb_substr($name, 0, 1);
    }
}

if (!function_exists('theme_attr')) {
    /** ویژگیِ data-theme برای تگ <html>. */
    function theme_attr(?string $key): string
    {
        return 'data-theme="' . e(App\Support\Theme::resolve($key)) . '"';
    }
}

if (!function_exists('salon_cover_url')) {
    /** نشانی عکس سالن، یا null اگر عکس واقعی ندارد و نمونهٔ مناسبی هم نیست. */
    function salon_cover_url(array $salon): ?string
    {
        $file = (string) ($salon['cover_path'] ?? '');
        if (preg_match('/^[a-zA-Z0-9-]+\.webp$/', $file)) {
            return url('uploads/media/' . $file);
        }

        return ($salon['audience'] ?? 'men') === 'men' ? asset('images/services/haircut-640.webp') : null;
    }
}

if (!function_exists('salon_cover')) {
    /**
     * عکس سالن یا جایگزین برند.
     *
     * عکس نمونه فقط برای آرایشگاه مردانه (همهٔ عکس‌های نمونه مردانه‌اند)
     * و همیشه با برچسب «تصویر نمونه»؛ بقیه کاشی رنگی با نشان سالن.
     */
    function salon_cover(array $salon, bool $eager = false): string
    {
        $real = preg_match('/^[a-zA-Z0-9-]+\.webp$/', (string) ($salon['cover_path'] ?? '')) === 1;
        $url = salon_cover_url($salon);
        if ($url !== null) {
            return '<img src="' . e($url) . '" alt="' . ($real ? e('تصویر ' . ($salon['name'] ?? 'سالن')) : '') . '" width="1200" height="750" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async">'
                . ($real ? '' : '<span class="cover-fallback__mark" style="color:#fff;text-shadow:0 1px 3px #000a">تصویر نمونه</span>');
        }
        $symbol = ($salon['audience'] ?? 'men') === 'women' ? 'sparkles' : 'scissors';

        return '<span class="cover-fallback" data-theme="' . e(App\Support\Theme::resolve($salon['theme'] ?? null)) . '" aria-hidden="true">' . icon($symbol) . '</span>';
    }
}

if (!function_exists('join_parts')) {
    /**
     * چسباندن بخش‌های غیرخالی با جداکننده.
     *
     * به‌جای trim($x, '، ') — trim روی بایت کار می‌کند و با جداکنندهٔ
     * فارسی، بایت اول حروفی مثل «س» را هم می‌بُرد؛ متن خراب می‌شود و
     * htmlspecialchars برای UTF-8 نامعتبر رشتهٔ خالی برمی‌گرداند.
     *
     * @param array<int,mixed> $parts
     */
    function join_parts(array $parts, string $separator = '، '): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return implode($separator, $clean);
    }
}

if (!function_exists('is_path')) {
    /** آیا مسیر فعلی با این پیشوند شروع می‌شود؟ برای aria-current. */
    function is_path(string $prefix, bool $exact = false): bool
    {
        $current = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $target = rtrim(url($prefix), '/');
        $target = $target === '' ? '/' : $target;

        return $exact ? $current === $target : ($current === $target || str_starts_with($current, $target . '/'));
    }
}
