<?php
/**
 * ساختن نخستین مدیر کل (مدیر ارشد) — برای نصب‌هایی که پیش از نسخهٔ ۱۵ نصب
 * شده‌اند و مدیری ندارند (نصاب نسخهٔ ۱۵ خودش مدیر ارشد را می‌سازد).
 *
 *     php tools/make_platform_admin.php 09121234567 [نام]
 *
 * فقط وقتی کار می‌کند که هیچ مدیر کلِ فعالی نباشد: پس از آن، مدیر کل تازه را
 * فقط مدیر ارشد از پنل می‌سازد و این دستور (مثل نصاب و File Manager) کاری
 * نمی‌کند. تنها استثنا: اگر مدیر ارشدی نمانده (مثلاً در دیتابیس مسدود شده)،
 * یکی از مدیرهای کلِ فعالِ موجود را مدیر ارشد می‌کند — نه کسی را از بیرون.
 *
 * در پایان یک لینک ورود یک‌بارمصرف (۱۵ دقیقه) چاپ می‌شود؛ با آن وارد شوید و از
 * «حساب من» رمز بگذارید. روی هاستِ بی‌ترمینال، همین دستور را یک بار از
 * «Cron Jobs» اجرا کنید، لینک را از فایل خروجی کرون بردارید و کرون و فایل را پاک کنید.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Domain\Identity\AdminPolicy;
use App\Domain\Identity\LoginLinkService;
use App\Domain\Identity\UserRepository;
use App\Domain\System\AuditLog;
use App\Support\IranMobile;

$phoneArg = $argv[1] ?? null;
if ($phoneArg === null) {
    fwrite(STDERR, "Usage: php tools/make_platform_admin.php <phone> [name]\n");
    exit(1);
}

$phone = IranMobile::tryParse($phoneArg);
if ($phone === null) {
    fwrite(STDERR, "شمارهٔ موبایل معتبر نیست: {$phoneArg}\n");
    exit(1);
}

if (AdminPolicy::bootstrapAllowed()) {
    $name = isset($argv[2]) ? mb_substr(trim($argv[2]), 0, 120) : null;
    $user = (new UserRepository())->findOrCreate($phone, $name);
    DB::update('users', ['is_active' => 1], 'id = :id', ['id' => $user['id']]);
    AdminPolicy::grant((int) $user['id'], null, true);
    AuditLog::record(null, 'user.admin_granted_cli', 'user', (int) $user['id'], ['super' => true]);
    echo "✓ {$phone->e164} مدیر ارشد سامانه شد.\n";
} elseif (AdminPolicy::superAdmin() === null) {
    $user = DB::selectOne('SELECT * FROM users WHERE phone = ? AND is_platform_admin = 1 AND is_active = 1', [$phone->e164]);
    if ($user === null) {
        fwrite(STDERR, "سامانه مدیر کل دارد ولی مدیر ارشدی نمانده. فقط یکی از مدیرهای کلِ فعالِ موجود را می‌شود مدیر ارشد کرد؛\n"
            . "{$phone->e164} مدیر کلِ فعال نیست.\n");
        exit(1);
    }
    DB::update('users', ['is_super_admin' => 1], 'id = :id', ['id' => $user['id']]);
    AuditLog::record(null, 'user.admin_granted_cli', 'user', (int) $user['id'], ['super' => true]);
    echo "✓ {$phone->e164} مدیر ارشد سامانه شد.\n";
} else {
    fwrite(STDERR, "✗ این سامانه مدیر ارشد دارد؛ مدیر کل تازه را فقط او از پنل مدیریت ← کاربران می‌سازد.\n"
        . "  برای بازیابی دسترسی مدیر ارشد، در صفحهٔ ورود «رمز را فراموش کرده‌اید؟» را بزنید (کد به موبایل خودش می‌رود).\n");
    exit(1);
}

try {
    $link = (new LoginLinkService())->issue($phone);
    if (!$link['ok']) {
        echo "\n(لینک ورود ساخته نشد: {$link['error']})\n";
        exit(0);
    }
    $base = rtrim((string) Config::get('app.url', ''), '/');
    echo "\nلینک ورود یک‌بارمصرف (۱۵ دقیقه):\n  {$base}/login/link/{$link['token']}\n";
    echo "بازش کنید و از «حساب من» (/account) رمز بگذارید.\n";
} catch (Throwable $e) {
    echo "\n(لینک ورود ساخته نشد: {$e->getMessage()})\n";
}
