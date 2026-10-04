<?php

declare(strict_types=1);

namespace App\Core;

/**
 * کش بین‌درخواستی — با APCu اگر باشد، وگرنه فقط همین درخواست.
 *
 * چرا: داده‌هایی مثل سالن، منو، ساعت کاری و فهرست کشف، بیشتر خوانده
 * می‌شوند تا نوشته. بدون کش بین‌درخواستی، در هجومِ مرور، هر بازدید همهٔ
 * این‌ها را از نو از دیتابیس می‌خواند. با APCu، این‌ها در حافظهٔ مشترکِ
 * خود سرور می‌نشینند و بار دیتابیس چند برابر کم می‌شود.
 *
 * نکتهٔ مهمِ cPanel: APCu همیشه روشن نیست. اگر نباشد، این کلاس بی‌سروصدا
 * به آرایهٔ همان‌درخواست برمی‌گردد — یعنی رفتار دقیقاً مثلِ قبل، و هیچ
 * چیزی نمی‌شکند. روشن‌کردن APCu در cPanel فقط یک تیک در «Select PHP
 * Version» است (نصب نیست)؛ صفحهٔ «سلامت سیستم» این را یادآوری می‌کند.
 *
 * باطل‌کردن با نسخه است: به‌جای پاک‌کردن تک‌تک کلیدها، نسخهٔ یک فضا بالا
 * می‌رود (bump) و همهٔ کلیدهای آن فضا یک‌باره بی‌اعتبار می‌شوند. مهلت هر
 * ورودی هم یک تورِ ایمنی است: اگر جایی bump جا بماند، کهنگی حداکثر به
 * اندازهٔ همان مهلت طول می‌کشد.
 */
final class Cache
{
    private static ?bool $apcu = null;

    /** کش همین‌درخواست: هم برای fallback، هم جلوی رفت‌وبرگشت تکراری به APCu. */
    private static array $local = [];

    private static string $prefix = '';

    /** نسخهٔ کهنه تا این مدت پس از مهلت نگه داشته می‌شود، فقط برای وقت فشار (ثانیه). */
    private const STALE_GRACE = 600;

    private static function enabled(): bool
    {
        if (self::$apcu === null) {
            self::$apcu = function_exists('apcu_enabled') && apcu_enabled();
            /*
             * پیشوند = همین نصب + همین نسخه.
             *  - نصب: چند سایت روی یک سرور قاطی نشوند.
             *  - نسخه: هر به‌روزرسانی کل کش را خودبه‌خود کنار می‌گذارد. به‌روزرسان
             *    از cron (CLI) اجرا می‌شود و به APCu وب‌سرور دسترسی ندارد؛ ولی چون
             *    کلیدهای نسخهٔ تازه با پیشوند تازه ساخته می‌شوند، ردیف‌های کش‌شدهٔ
             *    پیش از مهاجرت (که ستون تازه را ندارند) هرگز خوانده نمی‌شوند.
             */
            $base = defined('BASE_PATH') ? BASE_PATH : __DIR__;
            self::$prefix = 'rsh:' . substr(hash('sha256', $base . '|' . (string) Config::get('app.key', '') . '|' . \App\Support\Version::current()), 0, 12) . ':';
        }

        return self::$apcu;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$local)) {
            return self::$local[$key];
        }
        if (self::enabled()) {
            $ok = false;
            $value = apcu_fetch(self::$prefix . $key, $ok);
            if ($ok) {
                self::$local[$key] = $value;

                return $value;
            }
        }

        return $default;
    }

    public static function set(string $key, mixed $value, int $ttl = 300): void
    {
        self::$local[$key] = $value;
        if (self::enabled()) {
            apcu_store(self::$prefix . $key, $value, max(1, $ttl));
        }
    }

    /**
     * مقدار را از کش می‌دهد؛ اگر نبود، $producer را اجرا می‌کند، نتیجه را
     * کش و برمی‌گرداند. مقدارِ null هم کش می‌شود (تا «نبودن» را هم به‌خاطر
     * بسپارد و کوئریِ تکراریِ بی‌نتیجه نزند).
     *
     * کهنه به‌جای خطا: هر ورودی پس از مهلتش تا STALE_GRACE ثانیه نگه داشته
     * می‌شود. اگر تازه‌کردنش دقیقاً با «دیتابیس زیر فشار است» (Overloaded)
     * شکست بخورد، همان نسخهٔ کمی کهنه داده می‌شود — مرور سایت در هجوم با پر شدن
     * اتصال‌های دیتابیس از کار نمی‌افتد. دادهٔ باطل‌شده هرگز داده نمی‌شود: bump
     * کلید را عوض می‌کند و برای کلید تازه نسخهٔ کهنه‌ای نیست.
     */
    public static function remember(string $key, int $ttl, callable $producer): mixed
    {
        $entry = self::get($key);
        if (is_array($entry) && array_key_exists('rv', $entry)) {
            if ($entry['exp'] > time()) {
                return $entry['rv'];
            }
            try {
                $value = $producer();
            } catch (Overloaded) {
                return $entry['rv'];
            }
            self::store($key, $value, $ttl);

            return $value;
        }

        $value = $producer();
        self::store($key, $value, $ttl);

        return $value;
    }

    /** ورودیِ remember: مقدار + زمان تازگی؛ در APCu تا مهلت + فرصت کهنگی می‌ماند. */
    private static function store(string $key, mixed $value, int $ttl): void
    {
        $entry = ['rv' => $value, 'exp' => time() + max(1, $ttl)];
        self::$local[$key] = $entry;
        if (self::enabled()) {
            apcu_store(self::$prefix . $key, $entry, max(1, $ttl) + self::STALE_GRACE);
        }
    }

    /**
     * فقط اگر کلید نباشد می‌گذارد (اتمی در APCu). برای «جا گرفتن» بین چند
     * پردازش — مثل سقف ارسال‌های هم‌زمانِ پیامک. با مهلت، تا اگر پردازشی بمیرد
     * جایش خودبه‌خود آزاد شود.
     */
    public static function add(string $key, mixed $value, int $ttl): bool
    {
        if (self::enabled()) {
            if (!apcu_add(self::$prefix . $key, $value, max(1, $ttl))) {
                return false;
            }
            self::$local[$key] = $value;

            return true;
        }
        if (array_key_exists($key, self::$local)) {
            return false;
        }
        self::$local[$key] = $value;

        return true;
    }

    public static function forget(string $key): void
    {
        unset(self::$local[$key]);
        if (self::enabled()) {
            apcu_delete(self::$prefix . $key);
        }
    }

    /**
     * نسخهٔ یک فضا را یکی بالا می‌برد. هر کلیدی که نسخه را در خود دارد
     * (مثل version()) یک‌باره بی‌اعتبار می‌شود. برای باطل‌کردنِ یک‌جای
     * همهٔ دادهٔ یک سالن یا کل فهرست کشف.
     */
    public static function bump(string $space): void
    {
        $key = 'ver:' . $space;
        if (self::enabled()) {
            // اگر نبود بساز، بعد یکی اضافه کن؛ نسخهٔ محلی را رها کن تا تازه خوانده شود.
            if (apcu_exists(self::$prefix . $key)) {
                apcu_inc(self::$prefix . $key);
            } else {
                apcu_store(self::$prefix . $key, 1);
            }
            unset(self::$local[$key]);

            return;
        }
        // بدون APCu: نسخه را همین‌جا بالا ببر، تا خواندنِ بعدیِ همین درخواست
        // کلید تازه بسازد و دادهٔ کهنهٔ پیش از نوشتن را نبیند.
        self::$local[$key] = (int) (self::$local[$key] ?? 0) + 1;
    }

    /**
     * کشِ همین‌درخواست را خالی می‌کند (APCu دست نمی‌خورد).
     *
     * پیش از ثبت نهاییِ رزرو داخل قفل، و بین سناریوهای آزمون — همان جاهایی
     * که پیش‌تر کش‌های درون‌درخواستی خالی می‌شدند.
     */
    public static function flushLocal(): void
    {
        self::$local = [];
    }

    /**
     * کل کشِ همین نصب را پاک می‌کند (هر دو لایه).
     *
     * برای «سلامت سیستم» پس از اجرای دستی مهاجرت، و برای آزمون‌هایی که
     * دیتابیس را مستقیم دست‌کاری می‌کنند و «درخواست بعدی» را شبیه‌سازی می‌کنند.
     */
    public static function flushAll(): void
    {
        self::$local = [];
        if (self::enabled() && class_exists(\APCUIterator::class)) {
            apcu_delete(new \APCUIterator('/^' . preg_quote(self::$prefix, '/') . '/', APC_ITER_KEY));
        }
    }

    /** آیا کش بین‌درخواستی (APCu) فعال است؟ برای صفحهٔ سلامت سیستم. */
    public static function shared(): bool
    {
        return self::enabled();
    }

    /** نسخهٔ فعلی یک فضا؛ جزوی از کلیدهای کش می‌شود تا bump آن‌ها را بی‌اعتبار کند. */
    public static function version(string $space): int
    {
        $key = 'ver:' . $space;
        if (array_key_exists($key, self::$local)) {
            return (int) self::$local[$key];
        }
        $v = 0;
        if (self::enabled()) {
            $ok = false;
            $v = (int) apcu_fetch(self::$prefix . $key, $ok);
            if (!$ok) {
                $v = 0;
            }
        }
        self::$local[$key] = $v;

        return $v;
    }
}
