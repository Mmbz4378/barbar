<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Session;
use App\Support\Jalali;
use App\Support\Money;

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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
        if ($file === null || $file === '') {
            return null;
        }

        $dir = (string) App\Core\Config::get('reshen.uploads.logos_dir', 'uploads/logos');

        // basename: نام از دیتابیس می‌آید ولی باز هم مسیرزدایی می‌شود.
        return url($dir . '/' . basename($file));
    }
}

if (!function_exists('absolute_url')) {
    /**
     * آدرس کامل با دامنه — برای QR، پیامک، و هر چیزی که بیرون از مرورگر
     * می‌رود و آدرس نسبی برایش بی‌معنی است.
     *
     * دامنه از خودِ درخواست خوانده می‌شود نه از APP_URL، چون صاحب سالن
     * ممکن است دامنه را عوض کند و یادش برود .env را به‌روز کند — آن‌وقت
     * QRای چاپ می‌شود که به جای اشتباه می‌برد. اگر درخواستی در کار نباشد
     * (اجرای کران از خط فرمان)، APP_URL می‌ماند.
     */
    function absolute_url(string $path = ''): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host === '') {
            return rtrim((string) App\Core\Config::get('app.url', ''), '/')
                . '/' . ltrim($path, '/');
        }

        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
        // پشت پراکسی یا کش (روی cPanel معمول است) طرح اصلی اینجا می‌آید.
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($https ? 'https' : 'http');

        return $proto . '://' . $host . url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $assetUrl = url('assets/' . ltrim($path, '/'));
        // Coordinate with service-worker.js so long-lived hosting caches refresh.
        return preg_match('/\.(css|js)$/', $path) ? $assetUrl . '?v=v13' : $assetUrl;
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

if (!function_exists('jdate')) {
    function jdate(?string $datetime, string $format = 'Y/m/d H:i'): string
    {
        if ($datetime === null) {
            return '';
        }

        return Jalali::format(new DateTimeImmutable($datetime), $format);
    }
}

if (!function_exists('jalali_date_from_request')) {
    /**
     * سه فیلدِ انتخابگر تاریخ شمسی را به «Y-m-d» میلادی تبدیل می‌کند.
     *
     * فهرست روز همیشه ۱ تا ۳۱ است چون طول ماه شمسی ثابت نیست (شش ماه
     * اول ۳۱، شش ماه بعد ۳۰، و اسفند ۲۹ یا ۳۰). اگر کسی «۳۱ مهر» را
     * انتخاب کند، به‌جای خطا دادن به آخرین روزِ همان ماه بریده می‌شود —
     * منظورِ کاربر روشن است و پرت کردنش از فرم بیرون، کمکی نمی‌کند.
     *
     * تاریخ ناقص یا بیرون از بازه، null برمی‌گرداند تا فراخوان تصمیم
     * بگیرد.
     */
    function jalali_date_from_request(App\Core\Request $request, string $name): ?string
    {
        $y = $request->input($name . '_y');
        $m = $request->input($name . '_m');
        $d = $request->input($name . '_d');

        if ($y === null || $y === '' || $m === null || $m === '' || $d === null || $d === '') {
            return null;
        }

        $y = (int) $y;
        $m = (int) $m;
        $d = (int) $d;

        if ($y < 1300 || $y > 1500 || $m < 1 || $m > 12 || $d < 1) {
            return null;
        }

        $d = min($d, Jalali::daysInJalaliMonth($y, $m));

        return Jalali::toDateTime($y, $m, $d)->format('Y-m-d');
    }
}

if (!function_exists('fa_time')) {
    /** «۱۹:۳۰» — ۲۴ساعته با رقم فارسی. برای ستون‌هایی که باید تراز بمانند. */
    function fa_time(?string $time): string
    {
        return $time === null || $time === '' ? '' : App\Support\Clock::hm($time);
    }
}

if (!function_exists('fa_time_label')) {
    /** «۷:۳۰ شب» — برای جایی که ساعت تنهاست و باید یک‌نگاهی خوانده شود. */
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

if (!function_exists('fa_num')) {
    function fa_num(int|float|string $value): string
    {
        return Jalali::toPersianDigits((string) $value);
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

if (!function_exists('icon')) {
    /**
     * آیکون از اسپرایت — «Lucide» با لایسنس ISC.
     *
     * چرا اسپرایت و نه SVG درون‌خطی در هر ویو: مسیرهای SVG تکراری،
     * هم HTML را باد می‌کنند هم نگهداری را سخت. با <use> هر آیکون یک
     * ارجاع است و مرورگر یک بار تعریفش را می‌خواند.
     *
     * چرا اموجی نه: اموجی روی هر سیستم‌عامل شکل دیگری دارد، با رنگ متن
     * هماهنگ نمی‌شود، و صفحه‌خوان اسمش را بلند می‌خواند.
     */
    function icon(string $name, string $class = 'w-5 h-5', ?string $label = null): string
    {
        $aria = $label === null
            ? 'aria-hidden="true"'
            : 'role="img" aria-label="' . e($label) . '"';

        return '<svg class="' . e($class) . '" ' . $aria . '>'
             . '<use href="#i-' . e($name) . '"></use></svg>';
    }
}

if (!function_exists('service_icon')) {
    /** Keep service photography and its supporting icon in the same category. */
    function service_icon(string $name): string
    {
        return service_visual($name)['icon'];
    }
}

if (!function_exists('service_visual')) {
    /** Known local assets only; a useful image is available for every service. */
    function service_visual(string $name): array
    {
        $rules = [
            ['facial', 'sparkles', '/فشیال|فیشیال|پوست|پاکسازی|پاک‌سازی|ماسک صورت|بخور|ابرو|وکس|facial|skin/iu'],
            ['color', 'sparkle', '/رنگ|هایلایت|لایت|دکلره|مش مو|color|colour|highlight/iu'],
            ['beard', 'beard', '/ریش|سبیل|صورت|تیغ|beard|shave/iu'],
            ['care', 'sparkles', '/مراقبت|شست|شامپو|کراتین|احیا|تقویت|پروتئین|اسکالپ|ماسک مو|wash|keratin|treatment/iu'],
            ['styling', 'comb', '/شانه|براش|حالت|سشوار|استایل|styling|blow/iu'],
        ];
        foreach ($rules as [$category, $icon, $pattern]) {
            if (preg_match($pattern, $name)) {
                return ['category' => $category, 'icon' => $icon];
            }
        }
        return ['category' => 'haircut', 'icon' => 'hair'];
    }
}

if (!function_exists('service_photo')) {
    /** Redundant with the adjacent service name, so keep the image decorative. */
    function service_photo(string $name, string $class = 'service-photo', bool $eager = false, ?string $imageFile = null): string
    {
        if ($imageFile && preg_match('/^[a-zA-Z0-9-]+\.webp$/', $imageFile)) {
            return '<img src="' . e(url('uploads/media/' . $imageFile)) . '" class="' . e($class) . '" alt="" width="640" height="640" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async">';
        }
        $category = service_visual($name)['category'];
        $small = e(asset('images/services/' . $category . '-240.webp'));
        $large = e(asset('images/services/' . $category . '-640.webp'));
        $sizes = str_contains($class, 'service-menu-card__photo')
            ? '(min-width:520px) 270px, 104px'
            : (str_contains($class, 'service-admin-row__photo') ? '64px' : '(max-width:359px) 68px, 104px');
        return '<img class="' . e($class) . '" src="' . $small . '"'
            . ' srcset="' . $small . ' 240w, ' . $large . ' 640w"'
            . ' sizes="' . $sizes . '" width="640" height="640" alt=""'
            . ' loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async">';
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
function salon_cover_url(array $salon): string
{
    $file = $salon['cover_path'] ?? '';
    return preg_match('/^[a-zA-Z0-9-]+\.webp$/', $file) ? url('uploads/media/'.$file) : asset('images/services/haircut-640.webp');
}
}
