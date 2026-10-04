<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Core\Config;
use App\Core\Cron;
use App\Core\DB;
use App\Core\Maintenance;
use App\Core\Migrator;
use App\Support\Version;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * به‌روزرسانی خودکار برنامه روی همان هاست (docs/updates.md).
 *
 * مراحل، به ترتیب و هرکدام قابل برگشت:
 *   ۱. دریافت مانیفست و بسته از منبع انتشار
 *   ۲. بررسی حجم، sha256 و (اگر کلید عمومی تنظیم شده) امضای Ed25519
 *   ۳. استخراج امن در پوشهٔ موقت (بدون ../، مسیر مطلق یا symlink) و
 *      تطبیق هر فایل با فهرست چکیده‌های داخل بسته
 *   ۴. بررسی پیشاپیش: نسخهٔ PHP، دسترسی نوشتن، فضای دیسک
 *   ۵. پشتیبان دیتابیس، اگر بسته مهاجرت تازه دارد
 *   ۶. حالت نگه‌داری؛ جایگزینی فایل‌ها با «دفترچه» (هر فایلی که عوض یا
 *      حذف می‌شود اول کپی می‌شود) — VERSION آخر از همه
 *   ۷. اجرای مهاجرت‌ها
 *   ۸. بررسی سلامت با یک درخواست HTTP به خود سایت با کد تازه
 *   ۹. هر خطا در ۶ تا ۸ ← برگرداندن همهٔ فایل‌ها از دفترچه
 *
 * هرگز دست نمی‌خورد: .env، محتوای storage و public/uploads (به‌جز
 * فایل‌های .htaccess محافظ)، و هر فایلی که جزو بستهٔ قبلی نبوده است.
 */
final class Updater
{
    public const MODES = ['off' => 'خاموش', 'notify' => 'فقط اعلام', 'auto' => 'نصب خودکار'];

    private const STATE_KEY = 'updates.state';
    private const MODE_KEY = 'updates.mode';
    private const WINDOW_KEY = 'updates.window';
    private const MANIFEST_FILE = 'release-files.json';

    /** فایل‌هایی که نصب‌های بدون فهرست فایل (پیش از ۱۴.۲) ممکن است داشته باشند و دیگر لازم نیستند. */
    private const LEGACY_REMOVED = [
        'public/assets/css/app.css', 'public/assets/css/design-tokens.css', 'public/assets/css/refined.css',
        'public/assets/css/comfort.css', 'public/assets/js/comfort.js', 'public/assets/js/network-status.js',
        'public/assets/js/queue-refresh.js', 'public/assets/js/service-discovery.js',
        'resources/views/components/customer-navigation.php', 'resources/views/components/discovery-links.php',
        'resources/views/components/install-prompt.php', 'resources/views/components/pwa-head.php',
        'resources/views/components/service-discovery.php', 'resources/views/components/sticky-action.php',
        'resources/views/components/theme-boot.php', 'resources/views/components/theme-toggle.php',
    ];

    /** @var string[] */
    private array $log = [];

    public function __construct(private readonly ReleaseSource $source = new ReleaseSource())
    {
    }

    // ─── تنظیمات و وضعیت ───────────────────────────────────────────────

    public function mode(): string
    {
        $mode = SystemSettings::get(self::MODE_KEY) ?? (string) Config::get('reshen.updates.default_mode', 'auto');

        return array_key_exists($mode, self::MODES) ? $mode : 'notify';
    }

    public function setMode(string $mode): void
    {
        if (!array_key_exists($mode, self::MODES)) {
            throw new RuntimeException('حالت نامعتبر است.');
        }
        SystemSettings::set(self::MODE_KEY, $mode);
    }

    /** @return array{start:int,end:int} */
    public function window(): array
    {
        $w = SystemSettings::getJson(self::WINDOW_KEY) ?? [];

        return [
            'start' => max(0, min(23, (int) ($w['start'] ?? Config::get('reshen.updates.window_start', 3)))),
            'end' => max(1, min(24, (int) ($w['end'] ?? Config::get('reshen.updates.window_end', 5)))),
        ];
    }

    public function setWindow(int $start, int $end): void
    {
        if ($start < 0 || $start > 23 || $end < 1 || $end > 24 || $start === $end) {
            throw new RuntimeException('بازهٔ ساعت معتبر نیست.');
        }
        SystemSettings::setJson(self::WINDOW_KEY, ['start' => $start, 'end' => $end]);
    }

    public function state(): array
    {
        return SystemSettings::getJson(self::STATE_KEY) ?? [];
    }

    /** نسخهٔ تازه‌ای که آخرین بررسی پیدا کرده و هنوز نصب نشده. */
    public function available(): ?array
    {
        $latest = $this->state()['latest'] ?? null;

        return is_array($latest) && Version::isNewer((string) ($latest['version'] ?? '')) ? $latest : null;
    }

    public function isFailedBefore(string $version): bool
    {
        return in_array(Version::normalize($version), (array) ($this->state()['failed'] ?? []), true);
    }

    // ─── بررسی ─────────────────────────────────────────────────────────

    /**
     * آخرین انتشار را از منبع می‌گیرد و وضعیت را ذخیره می‌کند.
     *
     * @return array|null انتشار کامل (با سرآیندهای دانلود) اگر از نسخهٔ فعلی تازه‌تر باشد
     */
    public function check(): ?array
    {
        $state = $this->state();
        $state['checked_at'] = date('Y-m-d H:i:s');
        try {
            $release = $this->source->latest();
            $state['error'] = null;
            $state['latest'] = $release === null ? null : array_diff_key($release, ['package_headers' => true]);
            SystemSettings::setJson(self::STATE_KEY, $state);

            return $release !== null && Version::isNewer($release['version']) ? $release : null;
        } catch (Throwable $e) {
            $state['error'] = $e->getMessage();
            SystemSettings::setJson(self::STATE_KEY, $state);
            throw $e;
        }
    }

    /**
     * به‌روزرسانی‌ای که پردازه‌اش وسط کار کشته شده (محدودیت زمان هاست،
     * ری‌استارت) — فایل‌ها را از دفترچه برمی‌گرداند.
     */
    public function recoverInterrupted(): ?string
    {
        try {
            $stale = DB::select(
                "SELECT * FROM system_updates WHERE status = 'running' AND started_at < ? ORDER BY id",
                [date('Y-m-d H:i:s', time() - 30 * 60)]
            );
        } catch (Throwable) {
            return null;
        }
        if ($stale === []) {
            return null;
        }
        $lock = $this->acquireLock();
        if ($lock === null) {
            return null; // هنوز واقعاً در حال اجراست
        }
        $messages = [];
        try {
            foreach ($stale as $row) {
                $this->log = [];
                $backupDir = $row['backup_path'] ? BASE_PATH . '/' . $row['backup_path'] : null;
                $journal = $backupDir ? json_decode((string) @file_get_contents($backupDir . '/journal.json'), true) : null;
                $status = 'failed';
                $message = 'به‌روزرسانی نیمه‌کاره متوقف شده بود.';
                if (is_array($journal) && $journal !== []) {
                    try {
                        $this->rollback($journal, $backupDir);
                        $this->resetOpcache(array_column($journal, 0));
                        $status = 'rolled_back';
                        $message .= ' فایل‌ها به نسخهٔ ' . $row['from_version'] . ' برگشتند.';
                    } catch (Throwable $e) {
                        $message .= ' برگشت خودکار کامل نشد: ' . $e->getMessage();
                    }
                }
                $this->rememberFailure((string) $row['to_version']);
                $this->finishRecord((int) $row['id'], $status, $message);
                $messages[] = $message;
            }
        } finally {
            Maintenance::disable();
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return implode(' ', $messages);
    }

    /** کار کرون: بررسی دوره‌ای و در حالت خودکار، نصب در بازهٔ شبانه. */
    public function cronTick(): string
    {
        if (($recovered = $this->recoverInterrupted()) !== null) {
            return $recovered;
        }
        $mode = $this->mode();
        if ($mode === 'off') {
            return 'خاموش';
        }

        $state = $this->state();
        $interval = max(1, (int) Config::get('reshen.updates.check_interval_hours', 6)) * 3600;
        $release = null;
        if (empty($state['checked_at']) || strtotime((string) $state['checked_at']) < time() - $interval) {
            try {
                $release = $this->check();
            } catch (Throwable $e) {
                return 'بررسی ناموفق: ' . $e->getMessage();
            }
        }

        $available = $this->available();
        if ($available === null) {
            return 'به‌روز است (' . Version::current() . ')';
        }
        $label = 'نسخهٔ ' . $available['version'];
        if ($mode === 'notify') {
            return $label . ' موجود است (فقط اعلام)';
        }
        if ($this->isFailedBefore($available['version'])) {
            return $label . ' قبلاً ناموفق بود؛ از پنل بررسی کنید';
        }
        if ((int) explode('.', $available['version'])[0] > (int) explode('.', Version::current())[0]) {
            return $label . ' نسخهٔ اصلی تازه است؛ نصبش دستی و از پنل است';
        }
        if (!$this->inWindow()) {
            $w = $this->window();

            return $label . " بین ساعت {$w['start']} تا {$w['end']} نصب می‌شود";
        }

        $release ??= $this->check();
        if ($release === null) {
            return 'به‌روز است';
        }
        $result = $this->apply($release, 'auto', null);

        return $result['ok'] ? 'به ' . $release['version'] . ' به‌روز شد' : 'به‌روزرسانی ناموفق: ' . $result['message'];
    }

    public function inWindow(?int $hour = null): bool
    {
        $hour ??= (int) date('G');
        $w = $this->window();

        return $w['start'] < $w['end']
            ? $hour >= $w['start'] && $hour < $w['end']
            : $hour >= $w['start'] || $hour < $w['end']; // بازه‌ای که از نیمه‌شب می‌گذرد
    }

    // ─── آمادگی ────────────────────────────────────────────────────────

    /** @return array<int,array{label:string,ok:bool,value:string}> */
    public function readiness(): array
    {
        $checks = [];
        $checks[] = ['label' => 'افزونهٔ zip', 'ok' => class_exists(ZipArchive::class), 'value' => class_exists(ZipArchive::class) ? 'فعال' : 'غایب — برای باز کردن بسته لازم است'];
        $checks[] = ['label' => 'افزونهٔ curl', 'ok' => function_exists('curl_init'), 'value' => function_exists('curl_init') ? 'فعال' : 'غایب'];
        $writable = $this->firstUnwritable();
        $checks[] = ['label' => 'دسترسی نوشتن روی فایل‌های برنامه', 'ok' => $writable === null, 'value' => $writable === null ? 'دارد' : 'ندارد: ' . $writable];
        $free = function_exists('disk_free_space') ? @disk_free_space(BASE_PATH) : false;
        $checks[] = ['label' => 'فضای خالی دیسک', 'ok' => $free === false || $free > 150 * 1024 * 1024, 'value' => $free === false ? 'نامشخص' : round($free / 1048576) . ' MB'];
        $key = trim((string) Config::get('reshen.updates.public_key', ''));
        $checks[] = ['label' => 'امضای بسته', 'ok' => true, 'value' => $key !== '' ? (function_exists('sodium_crypto_sign_verify_detached') ? 'الزامی (کلید عمومی تنظیم شده)' : 'کلید هست ولی sodium غایب است') : 'خاموش — فقط sha256 و HTTPS'];
        $cron = Cron::lastRunAt();
        $checks[] = ['label' => 'کرون', 'ok' => $cron !== null && $cron > time() - 3 * 3600, 'value' => $cron === null ? 'هرگز اجرا نشده — نصب خودکار بدون کرون انجام نمی‌شود' : date('Y-m-d H:i', $cron)];

        return $checks;
    }

    private function firstUnwritable(): ?string
    {
        foreach (['', 'app', 'public', 'resources/views', 'vendor', 'database/migrations', 'storage'] as $rel) {
            $path = rtrim(BASE_PATH . '/' . $rel, '/');
            if (is_dir($path) && !is_writable($path)) {
                return $rel === '' ? 'ریشهٔ برنامه' : $rel;
            }
        }

        return null;
    }

    // ─── نصب ───────────────────────────────────────────────────────────

    /**
     * @return array{ok:bool,message:string,update_id:?int}
     */
    public function apply(array $release, string $trigger, ?int $actorId): array
    {
        $this->log = [];
        $lock = $this->acquireLock();
        if ($lock === null) {
            return ['ok' => false, 'message' => 'یک به‌روزرسانی دیگر همین حالا در حال اجراست.', 'update_id' => null];
        }

        @set_time_limit(900);
        ignore_user_abort(true);
        // کلاس‌هایی که بعد از جایگزینی فایل‌ها لازم‌اند، از همین نسخه بارگذاری شوند
        foreach ([Migrator::class, Maintenance::class, DatabaseBackup::class, SystemSettings::class, HttpFetcher::class, Version::class, Cron::class] as $class) {
            class_exists($class);
        }

        $from = Version::current();
        $to = Version::normalize((string) $release['version']);
        $updateId = $this->startRecord($from, $to, $trigger, $actorId);
        $work = BASE_PATH . '/storage/updates/' . preg_replace('/[^0-9A-Za-z.-]/', '', $to) . '-' . bin2hex(random_bytes(4));
        $journal = null;
        $backupDir = null;

        try {
            if (!Version::isNewer($to, $from)) {
                throw new RuntimeException("نسخهٔ {$to} از نسخهٔ نصب‌شده ({$from}) تازه‌تر نیست.");
            }
            if (version_compare(PHP_VERSION, (string) ($release['min_php'] ?? '8.1.0'), '<')) {
                throw new RuntimeException('این نسخه PHP ' . $release['min_php'] . ' یا بالاتر لازم دارد؛ نسخهٔ فعلی ' . PHP_VERSION . ' است.');
            }
            if (!class_exists(ZipArchive::class)) {
                throw new RuntimeException('افزونهٔ zip روی سرور فعال نیست.');
            }
            self::mkdir($work);

            // ۱ و ۲ — دانلود و بررسی
            $zipFile = $work . '/package.zip';
            $maxBytes = max(5, (int) Config::get('reshen.updates.max_package_mb', 60)) * 1024 * 1024;
            $this->note('دریافت ' . $release['package_url']);
            (new HttpFetcher())->download($release['package_url'], $zipFile, (array) ($release['package_headers'] ?? []), $maxBytes);
            $size = (int) filesize($zipFile);
            if ($release['size'] > 0 && $size !== (int) $release['size']) {
                throw new RuntimeException("حجم بسته ({$size}) با مانیفست ({$release['size']}) یکی نیست.");
            }
            if (!hash_equals($release['sha256'], hash_file('sha256', $zipFile))) {
                throw new RuntimeException('چکیدهٔ sha256 بسته با مانیفست یکی نیست؛ بسته خراب یا دست‌کاری‌شده است.');
            }
            $this->note('sha256 درست است');
            $publicKey = trim((string) Config::get('reshen.updates.public_key', ''));
            if ($publicKey !== '') {
                if (!ReleaseSource::verifySignature($release, $publicKey)) {
                    throw new RuntimeException('امضای بسته معتبر نیست؛ نصب انجام نشد.');
                }
                $this->note('امضای Ed25519 درست است');
            }

            // ۳ — استخراج
            $staging = $work . '/staging';
            $this->extract($zipFile, $staging);
            $packageFiles = $this->verifyStaging($staging, $to);
            $this->note(count($packageFiles) . ' فایل در بسته');

            // ۴ — برنامه‌ریزی و بررسی پیشاپیش
            $plan = $this->plan($staging, $packageFiles);
            $this->note(count($plan['write']) . ' فایل تازه/تغییرکرده، ' . count($plan['delete']) . ' فایل حذفی');
            foreach ($plan['kept'] as $rel) {
                $this->note("نسخهٔ محلیِ تغییرکردهٔ {$rel} نگه داشته شد؛ نسخهٔ تازه در {$rel}.new است");
            }
            $this->preflight($plan, $staging);

            // ۵ — پشتیبان
            $backupDir = BASE_PATH . '/storage/backups/' . date('Ymd-His') . '-' . $from . '-to-' . $to;
            self::mkdir($backupDir . '/files');
            @file_put_contents(BASE_PATH . '/storage/backups/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
            $pending = $this->pendingMigrations($staging);
            $dbFile = null;
            if ($pending !== []) {
                $dbFile = $backupDir . '/database.sql.gz';
                $this->note('پشتیبان دیتابیس پیش از ' . count($pending) . ' مهاجرت');
                (new DatabaseBackup())->dump($dbFile);
            }
            $this->updateRecord($updateId, ['backup_path' => $this->relative($backupDir), 'db_backup_file' => $dbFile ? $this->relative($dbFile) : null]);

            // ۶ — جایگزینی
            Maintenance::enable('به‌روزرسانی به ' . $to);
            $journal = [];
            $this->applyPlan($plan, $staging, $backupDir, $journal);
            $this->note('فایل‌ها جایگزین شدند');
            $this->resetOpcache(array_column($journal, 0));

            // ۷ — مهاجرت
            if ($pending !== []) {
                foreach ((new Migrator(BASE_PATH . '/database/migrations'))->run() as $row) {
                    if (!$row['ok']) {
                        throw new RuntimeException('مهاجرت ' . $row['file'] . ' شکست خورد: ' . $row['error']);
                    }
                    $this->note('مهاجرت ' . $row['file']);
                }
            }

            // ۸ — سلامت
            $this->healthCheck($to);

            Maintenance::disable();
            $this->finishRecord($updateId, 'succeeded', "به‌روزرسانی از {$from} به {$to} انجام شد.");
            $this->forgetFailure($to);
            $this->pruneBackups();
            self::removeDir($work);

            return ['ok' => true, 'message' => "سامانه به نسخهٔ {$to} به‌روز شد.", 'update_id' => $updateId];
        } catch (Throwable $e) {
            $this->note('خطا: ' . $e->getMessage());
            $rolledBack = false;
            if ($journal !== null && $backupDir !== null) {
                try {
                    $this->rollback($journal, $backupDir);
                    $this->resetOpcache(array_column($journal, 0));
                    $rolledBack = true;
                    $this->note('همهٔ فایل‌ها به نسخهٔ ' . $from . ' برگشتند');
                } catch (Throwable $re) {
                    $this->note('برگشت فایل‌ها کامل نشد: ' . $re->getMessage() . ' — فایل‌های قبلی در ' . $this->relative($backupDir) . ' هستند');
                }
            }
            Maintenance::disable();
            $this->rememberFailure($to);
            $message = $e->getMessage() . ($rolledBack ? ' فایل‌ها به نسخهٔ قبل برگشتند.' : '');
            $this->finishRecord($updateId, $rolledBack ? 'rolled_back' : 'failed', $message);
            self::removeDir($work);

            return ['ok' => false, 'message' => $message, 'update_id' => $updateId];
        } finally {
            Maintenance::disable();
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** بازگردانی دیتابیس از پشتیبانِ یک به‌روزرسانی — فقط با درخواست صریح مدیر. */
    public function restoreDatabase(int $updateId): void
    {
        $row = DB::selectOne('SELECT * FROM system_updates WHERE id = ?', [$updateId]);
        $file = $row ? BASE_PATH . '/' . ltrim((string) $row['db_backup_file'], '/') : '';
        if ($row === null || empty($row['db_backup_file']) || !is_file($file)) {
            throw new RuntimeException('پشتیبان دیتابیس این به‌روزرسانی موجود نیست.');
        }
        if (!in_array($row['status'], ['failed', 'rolled_back'], true)) {
            // پس از نصب موفق، فایل‌ها نسخهٔ تازه‌اند؛ دیتابیس قدیمی با آن‌ها نمی‌خواند
            throw new RuntimeException('بازگردانی دیتابیس فقط برای به‌روزرسانی ناموفق یا برگشت‌خورده ممکن است.');
        }
        if (!str_starts_with(realpath($file) ?: '', realpath(BASE_PATH . '/storage/backups') ?: '//')) {
            throw new RuntimeException('مسیر پشتیبان نامعتبر است.');
        }
        Maintenance::enable('بازگردانی دیتابیس', 15);
        try {
            (new DatabaseBackup())->restore($file, ['system_updates', 'system_settings']);
        } finally {
            Maintenance::disable();
        }
    }

    /** @return array<int,array> */
    public function history(int $limit = 15): array
    {
        try {
            return DB::select('SELECT * FROM system_updates ORDER BY id DESC LIMIT ' . max(1, $limit));
        } catch (Throwable) {
            return [];
        }
    }

    // ─── جزئیات ────────────────────────────────────────────────────────

    private function extract(string $zipFile, string $staging): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException('فایل ZIP باز نشد.');
        }
        try {
            if ($zip->numFiles < 10 || $zip->numFiles > 20000) {
                throw new RuntimeException('تعداد فایل‌های بسته غیرعادی است.');
            }
            $names = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) $stat['name'];
                $total += (int) $stat['size'];
                if ($total > 400 * 1024 * 1024) {
                    throw new RuntimeException('حجم بازشدهٔ بسته غیرعادی است.');
                }
                if (!self::safeRelativePath(rtrim($name, '/'))) {
                    throw new RuntimeException('مسیر ناامن در بسته: ' . $name);
                }
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('بسته symlink دارد: ' . $name);
                }
                $names[$i] = $name;
            }

            // بسته ممکن است فایل‌ها را در ریشه داشته باشد یا داخل یک پوشه
            $prefix = '';
            if (!in_array('VERSION', $names, true)) {
                foreach ($names as $name) {
                    if (preg_match('#^([^/]+)/VERSION$#', $name, $m)) {
                        $prefix = $m[1] . '/';
                        break;
                    }
                }
                if ($prefix === '') {
                    throw new RuntimeException('فایل VERSION در بسته نیست؛ این بستهٔ انتشار رشن نیست.');
                }
            }

            foreach ($names as $i => $name) {
                if (str_ends_with($name, '/') || ($prefix !== '' && !str_starts_with($name, $prefix))) {
                    continue;
                }
                $rel = substr($name, strlen($prefix));
                $target = $staging . '/' . $rel;
                self::mkdir(dirname($target));
                $data = $zip->getFromIndex($i);
                if ($data === false || file_put_contents($target, $data) === false) {
                    throw new RuntimeException('استخراج ' . $rel . ' ناموفق بود (فضای دیسک؟).');
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** @return array<string,string> مسیر نسبی ← sha256 */
    private function verifyStaging(string $staging, string $version): array
    {
        $packaged = trim((string) @file_get_contents($staging . '/VERSION'));
        if (Version::normalize($packaged) !== $version) {
            throw new RuntimeException("VERSION داخل بسته ({$packaged}) با مانیفست ({$version}) یکی نیست.");
        }
        foreach (['public/index.php', 'app/bootstrap.php', 'app/Core/Migrator.php', self::MANIFEST_FILE] as $required) {
            if (!is_file($staging . '/' . $required)) {
                throw new RuntimeException('بسته ناقص است: ' . $required . ' نیست.');
            }
        }
        $manifest = json_decode((string) file_get_contents($staging . '/' . self::MANIFEST_FILE), true);
        $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : null;
        if ($files === null) {
            throw new RuntimeException(self::MANIFEST_FILE . ' بسته معتبر نیست.');
        }
        foreach ($files as $rel => $hash) {
            if (!self::safeRelativePath((string) $rel)) {
                throw new RuntimeException('مسیر ناامن در فهرست فایل‌ها: ' . $rel);
            }
            $path = $staging . '/' . $rel;
            if (!is_file($path) || !hash_equals((string) $hash, hash_file('sha256', $path))) {
                throw new RuntimeException('فایل ' . $rel . ' با فهرست چکیده‌های بسته نمی‌خواند.');
            }
        }

        return $files;
    }

    /**
     * @param array<string,string> $packageFiles
     * @return array{write:array<int,string>,delete:array<int,string>,kept:array<int,string>}
     */
    private function plan(string $staging, array $packageFiles): array
    {
        $installed = $this->installedManifest();
        $write = [];
        $kept = [];
        foreach ($packageFiles as $rel => $hash) {
            if (!$this->mayWrite($rel)) {
                continue;
            }
            $target = BASE_PATH . '/' . $rel;
            if (is_file($target) && hash_equals($hash, (string) @hash_file('sha256', $target))) {
                continue; // بدون تغییر
            }
            // .htaccessی که مدیر هاست دستی عوض کرده (مثلاً RewriteBase) بازنویسی نمی‌شود
            $old = $installed[$rel] ?? null;
            if (str_ends_with($rel, '.htaccess') && is_file($target) && $old !== null && !hash_equals($old, (string) hash_file('sha256', $target))) {
                $kept[] = $rel;
                $write[] = $rel . '.new';
                continue;
            }
            if (str_ends_with($rel, '.gitkeep') && is_file($target)) {
                continue;
            }
            $write[] = $rel;
        }
        // فهرست فایل‌های خود بسته (در فهرست خودش نیست) — مبنای حذف‌های به‌روزرسانی بعدی
        $newList = $staging . '/' . self::MANIFEST_FILE;
        $oldList = BASE_PATH . '/' . self::MANIFEST_FILE;
        if (!is_file($oldList) || !hash_equals(hash_file('sha256', $newList), (string) hash_file('sha256', $oldList))) {
            $write[] = self::MANIFEST_FILE;
        }
        // VERSION و فهرست فایل‌ها آخر از همه: اگر وسط کار قطع شود، شمارهٔ نسخه هنوز قبلی است
        usort($write, static fn ($a, $b) => (int) in_array($a, ['VERSION', self::MANIFEST_FILE], true) <=> (int) in_array($b, ['VERSION', self::MANIFEST_FILE], true));

        $delete = [];
        // فایل‌های بستهٔ قبلی + فایل‌های قدیمیِ شناخته‌شده (نصب‌هایی که از نسخه‌های پیش از فهرست فایل آمده‌اند)
        $candidates = array_unique(array_merge(array_keys($installed), self::LEGACY_REMOVED));
        foreach ($candidates as $rel) {
            if (!isset($packageFiles[$rel]) && self::safeRelativePath($rel) && $this->mayWrite($rel) && !str_ends_with($rel, '.htaccess') && is_file(BASE_PATH . '/' . $rel)) {
                $delete[] = $rel;
            }
        }

        return ['write' => $write, 'delete' => $delete, 'kept' => $kept];
    }

    /** @return array<string,string> */
    private function installedManifest(): array
    {
        $data = json_decode((string) @file_get_contents(BASE_PATH . '/' . self::MANIFEST_FILE), true);

        return is_array($data['files'] ?? null) ? $data['files'] : [];
    }

    /** مسیرهایی که به‌روزرسان اجازهٔ نوشتن/حذفشان را دارد. */
    private function mayWrite(string $rel): bool
    {
        $base = basename($rel);
        if ($rel === '.env' || (str_starts_with($base, '.env') && $base !== '.env.example')) {
            return false;
        }
        if (str_starts_with($rel, '.git/') || str_starts_with($rel, '.github/')) {
            return false;
        }
        foreach (['storage/', 'public/uploads/'] as $dataDir) {
            if (str_starts_with($rel, $dataDir)) {
                return in_array($base, ['.htaccess', '.gitkeep'], true);
            }
        }

        return true;
    }

    private function preflight(array $plan, string $staging): void
    {
        $bytes = 0;
        foreach ($plan['write'] as $rel) {
            $target = BASE_PATH . '/' . $rel;
            $source = $staging . '/' . (str_ends_with($rel, '.new') ? substr($rel, 0, -4) : $rel);
            $bytes += (int) @filesize($source);
            $dir = dirname($target);
            while (!is_dir($dir) && $dir !== BASE_PATH && $dir !== dirname($dir)) {
                $dir = dirname($dir);
            }
            if ((is_file($target) && !is_writable($target)) || !is_writable($dir)) {
                throw new RuntimeException('اجازهٔ نوشتن روی ' . $rel . ' نیست؛ دسترسی فایل‌ها را در cPanel بررسی کنید.');
            }
        }
        foreach ($plan['delete'] as $rel) {
            if (!is_writable(dirname(BASE_PATH . '/' . $rel))) {
                throw new RuntimeException('اجازهٔ حذف ' . $rel . ' نیست.');
            }
        }
        $free = function_exists('disk_free_space') ? @disk_free_space(BASE_PATH) : false;
        if ($free !== false && $free < $bytes * 2 + 30 * 1024 * 1024) {
            throw new RuntimeException('فضای دیسک برای به‌روزرسانی و پشتیبان کافی نیست.');
        }
    }

    /** @return string[] */
    private function pendingMigrations(string $staging): array
    {
        $applied = [];
        try {
            $applied = array_column(DB::select('SELECT filename FROM schema_migrations'), 'filename');
        } catch (Throwable) {
        }
        $pending = [];
        foreach (glob($staging . '/database/migrations/*.sql') ?: [] as $file) {
            if (!in_array(basename($file), $applied, true)) {
                $pending[] = basename($file);
            }
        }

        return $pending;
    }

    /** @param array<int,array{0:string,1:string}> $journal [مسیر، created|replaced|deleted] */
    private function applyPlan(array $plan, string $staging, string $backupDir, array &$journal): void
    {
        $journalFile = $backupDir . '/journal.json';
        foreach ($plan['delete'] as $rel) {
            $target = BASE_PATH . '/' . $rel;
            $this->backupFile($rel, $backupDir);
            $journal[] = [$rel, 'deleted'];
            @file_put_contents($journalFile, json_encode($journal, JSON_UNESCAPED_UNICODE));
            if (!@unlink($target)) {
                throw new RuntimeException('حذف ' . $rel . ' ناموفق بود.');
            }
        }
        foreach ($plan['write'] as $rel) {
            $target = BASE_PATH . '/' . $rel;
            $source = $staging . '/' . (str_ends_with($rel, '.new') ? substr($rel, 0, -4) : $rel);
            $existed = is_file($target);
            if ($existed) {
                $this->backupFile($rel, $backupDir);
            }
            $journal[] = [$rel, $existed ? 'replaced' : 'created'];
            @file_put_contents($journalFile, json_encode($journal, JSON_UNESCAPED_UNICODE));
            self::mkdir(dirname($target));
            // نوشتن در فایل موقت کنار مقصد و rename — جایگزینی اتمی
            $tmp = $target . '.upd-' . bin2hex(random_bytes(3));
            if (!@copy($source, $tmp) || !@rename($tmp, $target)) {
                @unlink($tmp);
                throw new RuntimeException('نوشتن ' . $rel . ' ناموفق بود.');
            }
            @chmod($target, 0644);
        }
    }

    private function rollback(array $journal, string $backupDir): void
    {
        $errors = [];
        foreach (array_reverse($journal) as [$rel, $action]) {
            $target = BASE_PATH . '/' . $rel;
            if ($action === 'created') {
                if (is_file($target) && !@unlink($target)) {
                    $errors[] = $rel;
                }
                continue;
            }
            $saved = $backupDir . '/files/' . $rel;
            self::mkdir(dirname($target));
            if (!is_file($saved) || !@copy($saved, $target)) {
                $errors[] = $rel;
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(count($errors) . ' فایل برنگشت: ' . implode('، ', array_slice($errors, 0, 5)));
        }
    }

    private function backupFile(string $rel, string $backupDir): void
    {
        $dest = $backupDir . '/files/' . $rel;
        self::mkdir(dirname($dest));
        if (!@copy(BASE_PATH . '/' . $rel, $dest)) {
            throw new RuntimeException('پشتیبان ' . $rel . ' ساخته نشد.');
        }
    }

    /**
     * درخواست به خود سایت با کد تازه. دو بار: بار اول کش opcache وب را هم
     * پاک می‌کند (کرون CLI به کش وب دسترسی ندارد).
     */
    private function healthCheck(string $version): void
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $token = Cron::token();
        if ($base === '' || $token === '' || PHP_SAPI === 'cli' && !function_exists('curl_init')) {
            $this->note('بررسی سلامت HTTP انجام نشد (APP_URL یا APP_KEY تنظیم نیست)');

            return;
        }
        $url = $base . '/health?token=' . rawurlencode($token);
        $last = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $res = (new HttpFetcher())->get($url . '&reset=1&n=' . $attempt, [], 100_000, 30);
            } catch (Throwable $e) {
                // سایت از روی خودش در دسترس نیست (فایروال هاست) — جلوی نصب را نمی‌گیرد
                $this->note('بررسی سلامت HTTP ممکن نشد: ' . $e->getMessage());

                return;
            }
            $data = json_decode($res['body'], true);
            $last = $res['status'] . ' ' . substr($res['body'], 0, 200);
            if ($res['status'] === 200 && is_array($data) && ($data['ok'] ?? false) === true && ($data['version'] ?? '') === $version) {
                $this->note('بررسی سلامت: نسخهٔ ' . $version . ' پاسخ داد');

                return;
            }
            usleep(800_000);
        }

        throw new RuntimeException('سایت پس از به‌روزرسانی سالم پاسخ نداد (' . $last . ').');
    }

    /** @param string[] $files */
    private function resetOpcache(array $files): void
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }
        foreach ($files as $rel) {
            if (str_ends_with($rel, '.php')) {
                @opcache_invalidate(BASE_PATH . '/' . $rel, true);
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function pruneBackups(): void
    {
        $keep = max(1, (int) Config::get('reshen.updates.keep_backups', 3));
        $dirs = glob(BASE_PATH . '/storage/backups/*', GLOB_ONLYDIR) ?: [];
        rsort($dirs);
        foreach (array_slice($dirs, $keep) as $dir) {
            self::removeDir($dir);
        }
    }

    /** @return resource|null */
    private function acquireLock()
    {
        self::mkdir(BASE_PATH . '/storage/updates');
        $handle = @fopen(BASE_PATH . '/storage/updates/update.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('پوشهٔ storage/updates قابل نوشتن نیست.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    private function rememberFailure(string $version): void
    {
        $state = $this->state();
        $failed = (array) ($state['failed'] ?? []);
        $failed[] = $version;
        $state['failed'] = array_values(array_unique($failed));
        SystemSettings::setJson(self::STATE_KEY, $state);
    }

    private function forgetFailure(string $version): void
    {
        $state = $this->state();
        $state['failed'] = array_values(array_diff((array) ($state['failed'] ?? []), [$version]));
        SystemSettings::setJson(self::STATE_KEY, $state);
    }

    private function startRecord(string $from, string $to, string $trigger, ?int $actorId): ?int
    {
        try {
            return (int) DB::insert('system_updates', [
                'from_version' => $from,
                'to_version' => $to,
                'status' => 'running',
                'trigger_type' => $trigger === 'auto' ? 'auto' : 'manual',
                'actor_user_id' => $actorId,
                'started_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function updateRecord(?int $id, array $data): void
    {
        if ($id === null) {
            return;
        }
        try {
            DB::update('system_updates', $data, 'id = :id', ['id' => $id]);
        } catch (Throwable) {
        }
    }

    private function finishRecord(?int $id, string $status, string $message): void
    {
        $this->updateRecord($id, [
            'status' => $status,
            'message' => mb_substr($message, 0, 2000),
            'log_text' => implode("\n", $this->log),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function note(string $line): void
    {
        $this->log[] = date('H:i:s') . ' ' . $line;
    }

    private function relative(string $path): string
    {
        return ltrim(substr($path, strlen(BASE_PATH)), '/');
    }

    public static function safeRelativePath(string $rel): bool
    {
        if ($rel === '' || str_starts_with($rel, '/') || str_contains($rel, '\\') || str_contains($rel, "\0") || preg_match('#^[A-Za-z]:#', $rel)) {
            return false;
        }
        foreach (explode('/', $rel) as $segment) {
            if ($segment === '..' || $segment === '.' || $segment === '') {
                return false;
            }
        }

        return true;
    }

    private static function mkdir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('پوشهٔ ' . $dir . ' ساخته نشد.');
        }
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
