<?php
/**
 * نگهبان ارجاع کلاس‌ها: هر Class::، new Class و instanceof Class در app/ که
 * به فضای نام App\ برسد، باید واقعاً وجود داشته باشد.
 *
 * چرا: «App\Domain\X\Y» بدون «\» اول، درون فایلی با namespace، نسبت به همان
 * فضای نام خوانده می‌شود (App\Domain\Identity\App\Domain\X\Y) و فقط وقتی همان
 * خط اجرا شود خطای ۵۰۰ می‌دهد؛ php -l آن را نمی‌بیند. یک بار ارسال کد ورود را
 * همین‌طور خواباند.
 *
 *     php tools/check-class-refs.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

$skip = static fn (array $tokens, int $j): int => (function () use ($tokens, $j): int {
    while (isset($tokens[$j]) && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
        $j++;
    }

    return $j;
})();
$nameTokens = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

$problems = [];
$checked = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $tokens = token_get_all((string) file_get_contents((string) $file));
    $count = count($tokens);
    $namespace = '';
    $imports = [];
    $depth = 0;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
            continue;
        }
        if ($token === '}') {
            $depth--;
            continue;
        }
        if (!is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $j = $skip($tokens, $i + 1);
            $namespace = is_array($tokens[$j]) && in_array($tokens[$j][0], $nameTokens, true) ? $tokens[$j][1] : '';
            $imports = [];
            continue;
        }

        // «use» سطح فایل (نه use درون کلاس برای trait، نه use بستار)
        if ($token[0] === T_USE && $depth === 0) {
            $j = $skip($tokens, $i + 1);
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && in_array(strtolower($tokens[$j][1]), ['function', 'const'], true)) {
                continue;
            }
            $statement = '';
            for ($k = $j; $k < $count && $tokens[$k] !== ';'; $k++) {
                $statement .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            }
            $statement = trim(preg_replace('/\s+/', ' ', $statement) ?? '');
            if (preg_match('/^\\\\?([\w\\\\]+)\\\\\s*\{(.+)\}$/', $statement, $group)) {
                $entries = array_map(static fn (string $e): string => $group[1] . '\\' . trim($e), explode(',', $group[2]));
            } else {
                $entries = array_map('trim', explode(',', $statement));
            }
            foreach ($entries as $entry) {
                [$full, $alias] = array_pad(preg_split('/\s+as\s+/i', ltrim($entry, '\\'), 2) ?: [], 2, null);
                if ($full === null || $full === '') {
                    continue;
                }
                $alias ??= substr($full, (int) strrpos('\\' . $full, '\\'));
                $imports[strtolower($alias)] = $full;
            }
            continue;
        }

        if (!in_array($token[0], $nameTokens, true)) {
            continue;
        }
        $next = $skip($tokens, $i + 1);
        $prev = $i - 1;
        while ($prev > 0 && is_array($tokens[$prev]) && $tokens[$prev][0] === T_WHITESPACE) {
            $prev--;
        }
        $isClassRef = (is_array($tokens[$next] ?? null) && $tokens[$next][0] === T_DOUBLE_COLON)
            || (is_array($tokens[$prev] ?? null) && in_array($tokens[$prev][0], [T_NEW, T_INSTANCEOF], true));
        $name = $token[1];
        if (!$isClassRef || in_array(strtolower($name), ['self', 'static', 'parent'], true)) {
            continue;
        }

        if ($name[0] === '\\') {
            $resolved = substr($name, 1);
        } else {
            $first = strtolower(explode('\\', $name)[0]);
            $resolved = isset($imports[$first])
                ? $imports[$first] . substr($name, strlen($first))
                : ($namespace !== '' ? $namespace . '\\' . $name : $name);
        }
        if (!str_starts_with($resolved, 'App\\')) {
            continue; // کلاس‌های PHP و افزونه‌های اختیاری (مثل SoapClient) کار این نگهبان نیست
        }
        $checked++;
        if (!class_exists($resolved) && !interface_exists($resolved) && !enum_exists($resolved) && !trait_exists($resolved)) {
            $problems[] = substr((string) $file, strlen($root) + 1) . ':' . $token[2] . "  {$name}  →  {$resolved}";
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "✗ ارجاع به کلاسی که وجود ندارد (اغلب «\\» اول جا افتاده یا use نشده):\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}
echo "✓ {$checked} ارجاع کلاس در app/ همه پیدا شدند.\n";
