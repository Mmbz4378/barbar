<?php
/**
 * مدیر کل کردن یک کاربر — برای نصب‌هایی که پیش از نسخهٔ ۱۵ نصب شده‌اند
 * (نصاب نسخهٔ ۱۵ خودش حساب مدیر را می‌سازد).
 *
 *     php tools/make_platform_admin.php 09121234567 [نام]
 *
 * اگر کاربری با این شماره نیست، ساخته می‌شود. در پایان یک لینک ورود
 * یک‌بارمصرف چاپ می‌شود؛ با آن وارد شوید و از «حساب من» رمز بگذارید.
 * روی هاستِ بی‌ترمینال، همین دستور را یک بار از «Cron Jobs» اجرا کنید و
 * لینک را از فایل خروجی کرون بردارید.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
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
    fwrite(STDERR, "Invalid Iranian mobile number: {$phoneArg}\n");
    exit(1);
}

$name = isset($argv[2]) ? mb_substr(trim($argv[2]), 0, 120) : null;
$user = (new UserRepository())->findOrCreate($phone, $name);
DB::update('users', ['is_platform_admin' => 1, 'is_active' => 1], 'id = :id', ['id' => $user['id']]);
AuditLog::record(null, 'user.admin_granted_cli', 'user', (int) $user['id']);
echo "OK — {$phone->e164} is now a platform admin.\n";

try {
    $link = (new LoginLinkService())->issue($phone);
    if (!$link['ok']) {
        echo "\n(login link not issued: {$link['error']})\n";
        exit(0);
    }
    $base = rtrim((string) Config::get('app.url', ''), '/');
    echo "\nOne-time login link (15 minutes):\n  {$base}/login/link/{$link['token']}\n";
    echo "Open it, then set a password under «حساب من» (/account).\n";
} catch (Throwable $e) {
    echo "\n(login link not issued: {$e->getMessage()})\n";
}
