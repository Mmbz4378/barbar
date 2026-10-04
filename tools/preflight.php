<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Core\{Config, DB, Migrator};
$failed = false;
$check = static function(bool $ok, string $label) use (&$failed): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    $failed = $failed || !$ok;
};
$check(PHP_VERSION_ID >= 80100, 'PHP >= 8.1');
foreach (['pdo_mysql','mbstring','curl','openssl'] as $extension) $check(extension_loaded($extension), $extension);
$check(function_exists('imagecreatefromstring') && function_exists('imagewebp'), 'GD with WebP (image uploads)');
$check(is_writable(BASE_PATH . '/storage'), 'storage writable');
$check(is_dir(BASE_PATH . '/public/uploads/media') && is_writable(BASE_PATH . '/public/uploads/media'), 'media uploads writable');
$check(class_exists(\Endroid\QrCode\QrCode::class), 'QR dependency available');
if (Config::get('app.env') === 'production') {
    $check(!Config::get('app.debug'), 'production debug disabled');
    $check(str_starts_with((string)Config::get('app.url'), 'https://'), 'production URL uses HTTPS');
    $check((string)Config::get('app.key') !== '', 'application signing key configured');
}
try {
    DB::connection()->query('SELECT 1');
    $check(true, 'database connection');
    $check((new Migrator(BASE_PATH . '/database/migrations'))->pendingCount() === 0, 'all migrations applied');
} catch (\Throwable $e) { $check(false, 'database readiness (check connection and migrations)'); }
echo 'SMS driver: ' . Config::get('reshen.sms.driver','log') . PHP_EOL;
echo 'Payment driver: ' . Config::get('reshen.payment.driver','disabled') . PHP_EOL;
echo 'External provider delivery must be verified separately before publication.' . PHP_EOL;
exit($failed ? 1 : 0);
