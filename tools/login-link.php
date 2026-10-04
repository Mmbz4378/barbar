<?php

declare(strict_types=1);

/**
 * ساخت لینک ورود یک‌بارمصرف.
 *
 *     php tools/login-link.php 09121234567
 *
 * برای دو وضعیت:
 *   ۱. نصب تازه — پیامک هنوز راه نیفتاده و صاحب سالن باید وارد شود
 *      تا سالن را تنظیم کند
 *   ۲. خرابیِ پیامک — حساب اپراتور تمام شده و همه بیرون مانده‌اند
 *
 * روی هاستی که SSH ندارد، همین فایل را می‌شود از «Cron Job» یک‌باره یا
 * از ترمینالِ cPanel اجرا کرد.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Domain\Identity\LoginLinkService;
use App\Support\IranMobile;

$raw = $argv[1] ?? '';

if ($raw === '') {
    fwrite(STDERR, "شمارهٔ موبایل را بده:\n  php tools/login-link.php 09121234567\n");
    exit(1);
}

$phone = IranMobile::tryParse($raw);

if ($phone === null) {
    fwrite(STDERR, "شمارهٔ «{$raw}» معتبر نیست.\n");
    exit(1);
}

$result = (new LoginLinkService())->issue($phone);

if (!$result['ok']) {
    fwrite(STDERR, $result['error'] . "\n");
    exit(1);
}

$base = rtrim((string) App\Core\Config::get('app.url', ''), '/');

echo "\n\033[32mلینک ورود ساخته شد.\033[0m\n\n";
echo "  {$base}/login/link/{$result['token']}\n\n";
echo "  • فقط یک بار کار می‌کند\n";
echo "  • تا ۱۵ دقیقه معتبر است\n";
echo "  • اگر APP_URL در .env درست نباشد، دامنهٔ بالا غلط است — فقط\n";
echo "    بخش /login/link/... را بعد از دامنهٔ واقعی بگذار\n\n";
