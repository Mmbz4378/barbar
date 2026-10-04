<?php

declare(strict_types=1);

/**
 * ساخت بستهٔ انتشار — همان بسته‌ای که هم دستی روی cPanel استخراج
 * می‌شود و هم به‌روزرسان خودکار نصبش می‌کند.
 *
 *   php tools/build-release.php [--out=dist] [--notes-file=notes.md]
 *                               [--package-url-base=https://…/releases/download/v1.2.3]
 *
 * خروجی در پوشهٔ out:
 *   reshen-<نسخه>.zip         فایل‌ها در ریشهٔ ZIP، نام‌ها UTF-8
 *   reshen-<نسخه>.zip.sha256
 *   manifest.json             نسخه، چکیده، حجم، امضا، یادداشت انتشار
 *   notes.md                  یادداشت انتشار (برای صفحهٔ Release)
 *
 * فهرست فایل‌ها از git ls-files می‌آید (پس .env، لاگ و آپلودها هرگز داخل
 * بسته نمی‌روند). امضا: اگر متغیر محیطی RESHEN_SIGNING_KEY (کلید خصوصی
 * base64 از tools/release-keygen.php) باشد، مانیفست امضا می‌شود.
 */

$root = dirname(__DIR__);
$opts = getopt('', ['out::', 'notes-file::', 'package-url-base::']);
$out = rtrim((string) ($opts['out'] ?? $root . '/dist'), '/');
if (!str_starts_with($out, '/')) {
    $out = getcwd() . '/' . $out;
}

function fail(string $message): never
{
    fwrite(STDERR, "✗ {$message}\n");
    exit(1);
}

$version = trim((string) @file_get_contents($root . '/VERSION'));
if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version)) {
    fail('VERSION معتبر نیست: ' . $version);
}
if (!class_exists(ZipArchive::class)) {
    fail('افزونهٔ zip لازم است.');
}

// ─── فهرست فایل‌ها ───────────────────────────────────────────────────
$list = [];
exec('git -C ' . escapeshellarg($root) . ' ls-files -z 2>/dev/null', $lines, $code);
if ($code !== 0 || $lines === []) {
    fail('git ls-files اجرا نشد؛ ساخت بسته فقط از مخزن git ممکن است.');
}
$files = array_filter(explode("\0", implode("\n", $lines)));
$exclude = [
    '#^\.github/#', '#^\.gitignore$#', '#^dist/#',
    '#^tools/seed-demo[^/]*\.php$#',     // دادهٔ نمونه فقط برای توسعه
    '#^release-files\.json$#',
];
foreach ($files as $rel) {
    foreach ($exclude as $pattern) {
        if (preg_match($pattern, $rel)) {
            continue 2;
        }
    }
    if (!is_file($root . '/' . $rel) || is_link($root . '/' . $rel)) {
        continue; // حذف‌شده در درخت کاری یا symlink
    }
    $list[] = $rel;
}
sort($list, SORT_STRING);

$hashes = [];
foreach ($list as $rel) {
    $hashes[$rel] = hash_file('sha256', $root . '/' . $rel);
}
$filesManifest = json_encode([
    'name' => 'reshen',
    'version' => $version,
    'generated_at' => gmdate('c'),
    'files' => $hashes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

// ─── ZIP ─────────────────────────────────────────────────────────────
if (!is_dir($out) && !mkdir($out, 0755, true)) {
    fail('پوشهٔ خروجی ساخته نشد: ' . $out);
}
$zipName = 'reshen-' . $version . '.zip';
$zipPath = $out . '/' . $zipName;
@unlink($zipPath);
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fail('ZIP ساخته نشد.');
}
foreach ($list as $rel) {
    $zip->addFile($root . '/' . $rel, $rel);
    $zip->setCompressionName($rel, ZipArchive::CM_DEFLATE, 9);
}
$zip->addFromString('release-files.json', $filesManifest);
// پوشه‌های خالیِ لازم (آپلودها) با .gitkeep داخل فهرست‌اند
if (!$zip->close()) {
    fail('بستن ZIP ناموفق بود.');
}

$sha = hash_file('sha256', $zipPath);
$size = filesize($zipPath);
file_put_contents($zipPath . '.sha256', $sha . '  ' . $zipName . "\n");

// ─── یادداشت انتشار ───────────────────────────────────────────────────
$notes = '';
if (!empty($opts['notes-file']) && is_file((string) $opts['notes-file'])) {
    $notes = trim((string) file_get_contents((string) $opts['notes-file']));
} elseif (is_file($root . '/CHANGELOG.md')) {
    $changelog = (string) file_get_contents($root . '/CHANGELOG.md');
    if (preg_match('/^##\s*\[?' . preg_quote($version, '/') . '\]?[^\n]*\n(.*?)(?=^##\s|\z)/ms', $changelog, $m)) {
        $notes = trim($m[1]);
    }
}
if ($notes === '') {
    $notes = 'نسخهٔ ' . $version;
}
file_put_contents($out . '/notes.md', $notes . "\n");

// ─── مانیفست ─────────────────────────────────────────────────────────
$urlBase = (string) ($opts['package-url-base'] ?? '');
if ($urlBase === '' && getenv('GITHUB_REPOSITORY')) {
    $urlBase = 'https://github.com/' . getenv('GITHUB_REPOSITORY') . '/releases/download/v' . $version;
}
if ($urlBase === '') {
    $urlBase = 'https://github.com/Mmbz4378/barbar/releases/download/v' . $version;
}

$minPhp = '8.1.0';
$composer = json_decode((string) @file_get_contents($root . '/composer.json'), true);
if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', (string) ($composer['require']['php'] ?? ''), $m)) {
    $minPhp = substr_count($m[1], '.') === 1 ? $m[1] . '.0' : $m[1];
}

$manifest = [
    'format' => 1,
    'name' => 'reshen',
    'version' => $version,
    'tag' => 'v' . $version,
    'released_at' => gmdate('c'),
    'min_php' => $minPhp,
    'notes' => $notes,
    'package' => [
        'file' => $zipName,
        'url' => rtrim($urlBase, '/') . '/' . $zipName,
        'sha256' => $sha,
        'size' => $size,
    ],
];

$secret = trim((string) getenv('RESHEN_SIGNING_KEY'));
if ($secret !== '') {
    $key = base64_decode($secret, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        fail('RESHEN_SIGNING_KEY معتبر نیست (کلید خصوصی base64 از tools/release-keygen.php).');
    }
    $message = 'reshen-release:1:' . $version . ':' . $sha;   // همان ReleaseSource::signatureMessage
    $manifest['signature'] = base64_encode(sodium_crypto_sign_detached($message, $key));
    $manifest['public_key'] = base64_encode(sodium_crypto_sign_publickey_from_secretkey($key));
}

file_put_contents($out . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

printf("✓ %s\n  %d فایل، %s KB\n  sha256 %s\n  %s\n", $zipPath, count($list) + 1, number_format($size / 1024, 0), $sha, isset($manifest['signature']) ? 'امضاشده' : 'بدون امضا');
