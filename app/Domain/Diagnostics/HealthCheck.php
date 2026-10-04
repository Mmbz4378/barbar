<?php

declare(strict_types=1);

namespace App\Domain\Diagnostics;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Cron;
use App\Core\DB;
use App\Core\Migrator;
use PDO;
use Throwable;

/**
 * بررسی آمادگی محیط.
 *
 * چرا این کلاس وجود دارد: چند چیز روی هاست اشتراکی نیست و نبودشان فقط
 * وقتی معلوم می‌شود که دیر شده باشد. مهم‌ترینش curl است — بدون آن
 * ملی‌پیامک اصلاً کار نمی‌کند و صاحب سالن وسط پنجشنبه شب می‌فهمد که هیچ
 * پیامکی نرفته. این کلاس همه را پیش از شروع می‌سنجد.
 *
 * هم نصاب وب از آن استفاده می‌کند، هم `php tools/doctor.php`.
 */
final class HealthCheck
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /**
     * @return array<string,array<int,array{label:string,status:string,value:string,hint:string}>>
     *         گروه‌بندی‌شده برای نمایش
     */
    public function run(): array
    {
        return [
            'PHP' => $this->php(),
            'افزونه‌ها' => $this->extensions(),
            'محدودیت‌ها' => $this->limits(),
            'پوشه‌ها' => $this->directories(),
            'دیتابیس' => $this->database(),
            'پیکربندی' => $this->configuration(),
            'کارایی زیر بار' => $this->performance(),
        ];
    }

    /** آیا چیزی هست که جلوی کار کردن را بگیرد؟ */
    public function hasFailures(): bool
    {
        foreach ($this->run() as $rows) {
            foreach ($rows as $row) {
                if ($row['status'] === self::FAIL) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<int,array{label:string,status:string,value:string,hint:string}> ردیف‌های یک بخش */
    private function php(): array
    {
        $ok = version_compare(PHP_VERSION, '8.1', '>=');

        return [[
            'label' => 'نسخهٔ PHP',
            'status' => $ok ? self::OK : self::FAIL,
            'value' => PHP_VERSION,
            'hint' => $ok ? '' : 'رشن دست‌کم PHP 8.1 می‌خواهد. در cPanel از بخش «Select PHP Version» عوضش کنید.',
        ]];
    }

    /** @return array<int,array{label:string,status:string,value:string,hint:string}> ردیف‌های یک بخش */
    private function extensions(): array
    {
        // [نام => [ضروری؟, چرا لازم است]]
        $needed = [
            'pdo_mysql' => [true,  'اتصال به دیتابیس. بدون این هیچ‌چیز کار نمی‌کند.'],
            'mbstring'  => [true,  'کار با متن فارسی.'],
            'json'      => [true,  'پایهٔ همهٔ APIها.'],
            'curl'      => [true,  'پیامک کاوه‌نگار و درگاه پرداخت.'],
            'openssl'   => [true,  'HTTPS و ساخت توکن امن.'],
            'soap'      => [false, 'ارسال پیامک با curl کار می‌کند و به این افزونه نیاز ندارد. فقط «ثبت الگو» از داخل پنل، با soap دقیق‌تر است — بدون آن هم با curl انجام می‌شود.'],
            'gd'        => [false, 'تغییر اندازهٔ عکس مشتری. اگر imagick باشد کافی است.'],
            'imagick'   => [false, 'جایگزین gd.'],
            'intl'      => [false, 'مرتب‌سازی درست متن فارسی. نبودش مرگبار نیست.'],
            'zip'       => [false, 'خروجی اکسل گزارش‌ها.'],
        ];

        $rows = [];
        foreach ($needed as $name => [$required, $why]) {
            $has = extension_loaded($name);
            $rows[] = [
                'label' => $name . ($required ? ' (ضروری)' : ' (اختیاری)'),
                'status' => $has ? self::OK : ($required ? self::FAIL : self::WARN),
                'value' => $has ? 'فعال' : 'غایب',
                'hint' => $has ? '' : $why,
            ];
        }

        // اگر هیچ‌کدام از دو مسیر پیامک ممکن نباشد، این جدی است.
        if (!extension_loaded('soap') && !extension_loaded('curl')) {
            $rows[] = [
                'label' => 'امکان ارسال پیامک',
                'status' => self::FAIL,
                'value' => 'هیچ‌کدام',
                'hint' => 'نه soap هست نه curl — هیچ ارائه‌دهندهٔ پیامکی کار نمی‌کند. از میزبان بخواهید یکی را فعال کند.',
            ];
        }

        return $rows;
    }

    /** @return array<int,array{label:string,status:string,value:string,hint:string}> ردیف‌های یک بخش */
    private function limits(): array
    {
        $memory = (string) ini_get('memory_limit');
        $memoryOk = $memory === '-1' || self::toBytes($memory) >= 128 * 1024 * 1024;

        $upload = (string) ini_get('upload_max_filesize');
        $post = (string) ini_get('post_max_size');

        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $blocking = array_values(array_intersect($disabled, ['file_get_contents', 'curl_exec', 'fsockopen']));

        return [
            [
                'label' => 'memory_limit',
                'status' => $memoryOk ? self::OK : self::WARN,
                'value' => $memory,
                'hint' => $memoryOk ? '' : 'برای گزارش‌های بزرگ دست‌کم ۱۲۸M خوب است.',
            ],
            [
                'label' => 'upload_max_filesize',
                'status' => self::toBytes($upload) >= 3 * 1024 * 1024 ? self::OK : self::WARN,
                'value' => $upload,
                'hint' => 'عکس سالن، خدمت و لوگو تا ۳ مگابایت پذیرفته می‌شود؛ کمتر از 3M یعنی عکس‌های بزرگ‌تر رد می‌شوند.',
            ],
            [
                'label' => 'post_max_size',
                'status' => self::toBytes($post) >= self::toBytes($upload) ? self::OK : self::WARN,
                'value' => $post,
                'hint' => 'نباید از upload_max_filesize کمتر باشد.',
            ],
            [
                'label' => 'توابع غیرفعال‌شده',
                'status' => $blocking === [] ? self::OK : self::FAIL,
                'value' => $blocking === [] ? 'مشکلی نیست' : implode('، ', $blocking),
                'hint' => $blocking === [] ? '' : 'بدون این توابع، پیامک و درگاه پرداخت کار نمی‌کنند. از میزبان بخواهید بازشان کند.',
            ],
        ];
    }

    /** @return array<int,array{label:string,status:string,value:string,hint:string}> ردیف‌های یک بخش */
    private function directories(): array
    {
        $rows = [];
        foreach ([
            'storage/logs' => 'لاگ خطا و پیامک',
            'storage/uploads/customer_photos' => 'عکس مشتری',
            'public/uploads/logos' => 'لوگوی سالن',
            'public/uploads/media' => 'عکس سالن و خدمات',
        ] as $relative => $why) {
            $path = BASE_PATH . '/' . $relative;
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);

            $rows[] = [
                'label' => $relative,
                'status' => $writable ? self::OK : self::FAIL,
                'value' => $writable ? 'قابل نوشتن' : ($exists ? 'فقط خواندنی' : 'وجود ندارد'),
                'hint' => $writable ? '' : "برای {$why} لازم است. دسترسی پوشه را ۷۵۵ کنید.",
            ];
        }

        return $rows;
    }

    /** @return array<int,array{label:string,status:string,value:string,hint:string}> ردیف‌های یک بخش */
    private function database(): array
    {
        try {
            $pdo = DB::connection();
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

            $rows = [[
                'label' => 'اتصال به دیتابیس',
                'status' => self::OK,
                'value' => $version,
                'hint' => '',
            ]];

            $charset = DB::selectOne(
                'SELECT @@character_set_database AS cs, @@collation_database AS co'
            );
            $utf8mb4 = str_starts_with((string) ($charset['cs'] ?? ''), 'utf8mb4');

            $rows[] = [
                'label' => 'کدگذاری دیتابیس',
                'status' => $utf8mb4 ? self::OK : self::FAIL,
                'value' => (string) ($charset['cs'] ?? 'نامشخص'),
                'hint' => $utf8mb4 ? '' : 'باید utf8mb4 باشد وگرنه متن فارسی و اموجی خراب ذخیره می‌شود.',
            ];

            $pending = (new Migrator(BASE_PATH . '/database/migrations'))->pendingCount();
            $rows[] = [
                'label' => 'مهاجرت‌های اجرانشده',
                'status' => $pending === 0 ? self::OK : self::WARN,
                'value' => $pending === 0 ? 'هیچ' : (string) $pending,
                'hint' => $pending === 0 ? '' : 'از دکمهٔ «اجرای مهاجرت‌ها» بالای همین صفحه، یا با `php tools/migrate.php` اجرا کنید. پیش از آن از دیتابیس پشتیبان بگیرید.',
            ];

            return $rows;
        } catch (Throwable $e) {
            return [[
                'label' => 'اتصال به دیتابیس',
                'status' => self::FAIL,
                'value' => 'ناموفق',
                'hint' => 'مقادیر DB_* در فایل .env را بررسی کنید. پیام خطا: ' . $e->getMessage(),
            ]];
        }
    }

    /** @return array<int,array{label:string,status:string,value:string,hint:string}> ردیف‌های یک بخش */
    private function configuration(): array
    {
        $rows = [];

        $rows[] = [
            'label' => 'نسخهٔ برنامه',
            'status' => self::OK,
            'value' => \App\Support\Version::current(),
            'hint' => '',
        ];

        $key = (string) Config::get('app.key', '');
        $rows[] = [
            'label' => 'کلید برنامه (APP_KEY)',
            'status' => strlen($key) >= 32 ? self::OK : self::FAIL,
            'value' => $key === '' ? 'خالی' : 'تنظیم شده',
            'hint' => strlen($key) >= 32 ? '' : 'برای امضای نشست لازم است. نصاب آن را می‌سازد.',
        ];

        $debug = (bool) Config::get('app.debug', false);
        $env = (string) Config::get('app.env', 'production');
        $rows[] = [
            'label' => 'حالت اشکال‌زدایی',
            'status' => ($debug && $env === 'production') ? self::FAIL : self::OK,
            'value' => $debug ? 'روشن' : 'خاموش',
            'hint' => ($debug && $env === 'production')
                ? 'روی سایت واقعی باید خاموش باشد — وگرنه مسیر فایل‌ها و جزئیات خطا به کاربر نشان داده می‌شود.'
                : '',
        ];

        $driver = (string) Config::get('reshen.sms.driver', 'log');
        $rows[] = [
            'label' => 'ارائه‌دهندهٔ پیامک',
            'status' => ($driver === 'log' && $env === 'production') ? self::WARN : self::OK,
            'value' => $driver,
            'hint' => ($driver === 'log' && $env === 'production')
                ? 'روی «log» هیچ پیامکی ارسال نمی‌شود، فقط در فایل نوشته می‌شود. برای سالن واقعی باید عوض شود.'
                : '',
        ];

        // کرون تنظیم‌نشده یکی از رایج‌ترین اشتباهات نصب است و علامتش
        // این است که «هیچ پیامکی نمی‌رود» — بدون اینکه خطایی جایی ثبت شود.
        $lastCron = Cron::lastRunAt();
        $cronAgeHours = $lastCron === null ? null : (time() - $lastCron) / 3600;
        $rows[] = [
            'label' => 'آخرین اجرای کرون',
            'status' => $lastCron === null ? self::FAIL : ($cronAgeHours > 6 ? self::WARN : self::OK),
            'value' => $lastCron === null ? 'هرگز' : jdate(date('Y-m-d H:i:s', $lastCron)),
            'hint' => $lastCron === null
                ? 'کرون هنوز یک بار هم اجرا نشده. تا تنظیم نشود، هیچ پیامک یادآوری ارسال نمی‌شود. راهنما: docs/40-deploy/01-cpanel.md'
                : ($cronAgeHours > 6 ? 'بیش از ۶ ساعت است که کرون اجرا نشده. تنظیماتش را بررسی کنید.' : ''),
        ];

        // ‏.env نباید از وب قابل خواندن باشد. اینجا فقط یادآوری می‌کنیم؛
        // آزمون واقعی‌اش در راهنمای استقرار است.
        $rows[] = [
            'label' => 'فایل نصاب',
            'status' => is_file(BASE_PATH . '/storage/installed.lock') ? self::OK : self::WARN,
            'value' => is_file(BASE_PATH . '/storage/installed.lock') ? 'قفل شده' : 'باز',
            'hint' => is_file(BASE_PATH . '/storage/installed.lock')
                ? ''
                : 'تا وقتی نصب تمام نشده، install.php در دسترس است. پس از نصب خودکار قفل می‌شود.',
        ];

        return $rows;
    }

    /**
     * چیزهایی که تعیین می‌کنند سایت زیر هجوم چقدر دوام می‌آورد.
     *
     * هیچ‌کدام نصب‌کردنی نیستند؛ روی cPanel همه یک تیک یا یک انتخاب‌اند و
     * راهنمای هر ردیف می‌گوید کجا.
     *
     * @return array<int,array{label:string,status:string,value:string,hint:string}>
     */
    private function performance(): array
    {
        $rows = [];

        $opcache = function_exists('opcache_get_status') && (bool) ((@opcache_get_status(false))['opcache_enabled'] ?? false);
        $rows[] = [
            'label' => 'opcache',
            'status' => $opcache ? self::OK : self::WARN,
            'value' => $opcache ? 'روشن، ' . (string) ini_get('opcache.memory_consumption') . 'M' : 'خاموش',
            'hint' => $opcache ? '' : 'بدون opcache هر درخواست همهٔ فایل‌های PHP را از نو کامپایل می‌کند و چند برابر کندتر است. در cPanel: Select PHP Version ← Extensions ← opcache.',
        ];

        $apcu = Cache::shared();
        $apcuValue = 'خاموش';
        $apcuFull = false;
        if ($apcu) {
            $info = function_exists('apcu_sma_info') ? @apcu_sma_info(true) : false;
            $size = is_array($info) ? (int) ($info['num_seg'] ?? 1) * (int) ($info['seg_size'] ?? 0) : 0;
            $free = is_array($info) ? (int) ($info['avail_mem'] ?? 0) : 0;
            $used = $size > 0 ? (int) round(100 * ($size - $free) / $size) : 0;
            $apcuFull = $size > 0 && $used >= 90;
            $apcuValue = 'روشن' . ($size > 0 ? '، ' . (int) round($size / 1048576) . 'M، ' . $used . '٪ پر' : '');
        }
        $rows[] = [
            'label' => 'کش مشترک (APCu)',
            'status' => $apcu && !$apcuFull ? self::OK : self::WARN,
            'value' => $apcuValue,
            'hint' => !$apcu
                ? 'خاموش است: منو، ساعت کاری، فهرست کشف و وقت‌های آزاد برای هر بازدید از دیتابیس خوانده می‌شوند و در هجوم دیتابیس زود پر می‌شود. در cPanel: Select PHP Version ← Extensions ← apcu (فقط یک تیک، نصب نیست).'
                : ($apcuFull ? 'حافظهٔ کش تقریباً پر است و ورودی‌ها زود بیرون رانده می‌شوند. apc.shm_size را بزرگ‌تر کنید (مثلاً 128M).' : ''),
        ];

        $background = function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
        $rows[] = [
            'label' => 'کار پس از پاسخ (پیامک)',
            'status' => $background ? self::OK : self::WARN,
            'value' => $background ? (function_exists('fastcgi_finish_request') ? 'PHP-FPM' : 'LiteSpeed') : 'پشتیبانی نمی‌شود',
            'hint' => $background ? '' : 'پیامک پس از پاسخ فرستاده می‌شود، ولی با این گردانندهٔ PHP کاربر تا پایانِ آن منتظر می‌ماند. در cPanel ← MultiPHP Manager، PHP-FPM را روشن کنید.',
        ];

        try {
            $vars = DB::selectOne('SELECT @@max_connections AS mc, @@max_user_connections AS muc, @@SESSION.innodb_lock_wait_timeout AS lw');
            $muc = (int) ($vars['muc'] ?? 0);
            $rows[] = [
                'label' => 'سقف اتصال هم‌زمان به دیتابیس',
                'status' => $muc > 0 && $muc < 25 ? self::WARN : self::OK,
                'value' => 'max_connections=' . (int) ($vars['mc'] ?? 0) . '، max_user_connections=' . ($muc > 0 ? $muc : 'بی‌سقف'),
                'hint' => $muc > 0 && $muc < 25
                    ? 'هر درخواستِ هم‌زمان یک اتصال می‌خواهد؛ با این سقف، در هجوم زود پر می‌شود و بازدیدکننده صفحهٔ «شلوغ است» می‌بیند (صفحه‌های کش‌شده باز می‌مانند). از میزبان بالا بردنش را بخواهید.'
                    : '',
            ];
            $rows[] = [
                'label' => 'سقف انتظار برای قفل',
                'status' => self::OK,
                'value' => (int) ($vars['lw'] ?? 0) . ' ثانیه',
                'hint' => '',
            ];

            $outbox = DB::selectOne(
                "SELECT COUNT(*) AS c, MIN(created_at) AS oldest FROM sms_messages WHERE status IN ('queued','sending')"
            );
            $waiting = (int) ($outbox['c'] ?? 0);
            $age = $waiting > 0 && !empty($outbox['oldest']) ? (int) floor((time() - strtotime((string) $outbox['oldest'])) / 60) : 0;
            $rows[] = [
                'label' => 'صندوق خروجی پیامک',
                'status' => $age > 15 ? self::WARN : self::OK,
                'value' => $waiting === 0 ? 'خالی' : $waiting . ' در صف، قدیمی‌ترین ' . $age . ' دقیقه',
                'hint' => $age > 15 ? 'پیامک‌هایی که پس از پاسخ فرستاده نشدند را cron می‌فرستد. اگر مانده‌اند، cron اجرا نمی‌شود یا اپراتور در دسترس نیست.' : '',
            ];
        } catch (\Throwable) {
            $rows[] = ['label' => 'دیتابیس', 'status' => self::WARN, 'value' => 'نامعلوم', 'hint' => 'برای سنجش سقف اتصال و صندوق پیامک باید به دیتابیس وصل شد.'];
        }

        return $rows;
    }

    private static function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (int) $value;
        switch (strtolower(substr($value, -1))) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
        }

        return $number;
    }
}
