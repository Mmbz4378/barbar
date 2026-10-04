<?php

declare(strict_types=1);

/**
 * نگهبان سیستم طراحی: در نماها فقط «پارامتر» درون‌خطی مجاز است.
 *
 *   php tools/check-inline-styles.php
 *
 * style="--v:42;--c:#0f766e" یعنی داده‌ای که به یک جزء سیستم داده می‌شود
 * (درصد نمودار، رنگ کارکنان) و مجاز است. هر ویژگی دیگری (margin، width،
 * color…) یعنی وصله‌ای بیرون از reshen.css که دیر یا زود ناهماهنگ
 * می‌شود؛ جایش یک کلاس ابزار یا نسخهٔ جزء است (docs/design-system.md).
 *
 * همچنین: هر <script> درون‌خطی (بدون src) باید nonce داشته باشد، وگرنه
 * سیاست امنیت محتوا (CSP) اجرایش را مسدود می‌کند و بی‌صدا از کار می‌افتد.
 *     <script nonce="<?= e(csp_nonce()) ?>"> … </script>
 */

$root = dirname(__DIR__) . '/resources/views';
$allow = ['components/icons.svg'];   // اسپرایت SVG: display:none لازمِ خود اسپرایت است
$violations = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $rel = substr($file->getPathname(), strlen($root) + 1);
    if (!preg_match('/\.(php|svg|html)$/', $rel) || in_array($rel, $allow, true)) {
        continue;
    }
    foreach (file($file->getPathname()) as $n => $line) {
        // اسکریپت درون‌خطی بدون nonce — CSP اجرایش را مسدود می‌کند
        if (preg_match_all('/<script\b([^>]*)>/i', $line, $tags, PREG_SET_ORDER)) {
            foreach ($tags as $tag) {
                $attrs = $tag[1];
                if (!preg_match('/\bsrc\s*=/i', $attrs) && !preg_match('/\bnonce\s*=/i', $attrs)) {
                    $violations[] = sprintf('%s:%d  <script> بدون nonce (برای CSP: nonce="<?= e(csp_nonce()) ?>")', $rel, $n + 1);
                }
            }
        }

        if (!preg_match_all('/\bstyle\s*=\s*("([^"]*)"|\'([^\']*)\')/', $line, $matches, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($matches as $m) {
            $value = $m[2] !== '' ? $m[2] : ($m[3] ?? '');
            // مقدارهای PHP داخل ویژگی را کنار بگذار؛ فقط نام ویژگی‌ها مهم است
            $value = preg_replace('/<\?(?:=|php).*?\?>/s', 'X', $value) ?? $value;
            foreach (array_filter(array_map('trim', explode(';', $value))) as $declaration) {
                if (!str_starts_with($declaration, '--') && $declaration !== 'X') {
                    $violations[] = sprintf('%s:%d  %s', $rel, $n + 1, $declaration);
                }
            }
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "✗ استایل درون‌خطی غیرمجاز (به‌جایش کلاس سیستم طراحی بگذارید):\n  " . implode("\n  ", $violations) . "\n");
    exit(1);
}
echo "✓ نماها فقط پارامتر --x درون‌خطی دارند و هر اسکریپت درون‌خطی nonce دارد.\n";
