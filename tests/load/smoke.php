<?php

declare(strict_types=1);

/**
 * آزمون بارِ سبک — چند درخواست موازی به چند مسیر، با curl_multi.
 *
 *   php tests/load/smoke.php [--base=http://127.0.0.1:8080] [--conc=50] [--reqs=400]
 *
 * برای هر مسیر: تعداد موفق، خطا، درخواست در ثانیه، و p50/p95/p99 میلی‌ثانیه.
 * هدف مقایسهٔ «پیش و پس» از بهینه‌سازی است، نه عدد مطلق؛ پس باید دو بار
 * با همان پارامتر اجرا شود. سرور باید با چند worker بالا آمده باشد:
 *
 *   PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8080 -t public public/index.php
 */

$opts = getopt('', ['base::', 'conc::', 'reqs::', 'paths::']);
$base = rtrim((string) ($opts['base'] ?? 'http://127.0.0.1:8080'), '/');
$conc = max(1, (int) ($opts['conc'] ?? 50));
$reqs = max($conc, (int) ($opts['reqs'] ?? 400));

$paths = $opts['paths'] ?? '/discover,/s/araishgah-parsa,/s/araishgah-parsa/menu,/salons/view/araishgah-parsa';
$paths = array_values(array_filter(array_map('trim', explode(',', (string) $paths))));

printf("بار: base=%s conc=%d reqs=%d\n\n", $base, $conc, $reqs);

foreach ($paths as $path) {
    run($base . $path, $conc, $reqs);
}

function run(string $url, int $conc, int $reqs): void
{
    $times = [];
    $ok = 0;
    $bad = 0;
    $codes = [];
    $done = 0;
    $started = microtime(true);

    $mh = curl_multi_init();
    $inflight = [];

    $add = function () use (&$inflight, $mh, $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: text/html'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $inflight[(int) $ch] = ['h' => $ch, 't' => microtime(true)];
    };

    for ($i = 0; $i < min($conc, $reqs); $i++) {
        $add();
    }
    $queued = min($conc, $reqs);

    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.1);
        while ($info = curl_multi_info_read($mh)) {
            $ch = $info['handle'];
            $id = (int) $ch;
            $elapsed = (microtime(true) - $inflight[$id]['t']) * 1000;
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $codes[$code] = ($codes[$code] ?? 0) + 1;
            if ($code >= 200 && $code < 400) {
                $ok++;
                $times[] = $elapsed;
            } else {
                $bad++;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            unset($inflight[$id]);
            $done++;
            if ($queued < $reqs) {
                $add();
                $queued++;
            }
        }
    } while ($done < $reqs && ($running || $inflight));

    curl_multi_close($mh);
    $wall = microtime(true) - $started;

    sort($times);
    $pct = static function (array $a, float $p): float {
        if ($a === []) {
            return 0.0;
        }
        $idx = (int) ceil($p / 100 * count($a)) - 1;

        return $a[max(0, min($idx, count($a) - 1))];
    };

    $codeStr = implode(' ', array_map(static fn ($c, $n) => "$c:$n", array_keys($codes), array_values($codes)));
    printf(
        "%-32s ok=%-4d bad=%-3d rps=%-7.1f p50=%-6.1f p95=%-6.1f p99=%-6.1f  [%s]\n",
        parse_url($url, PHP_URL_PATH),
        $ok,
        $bad,
        $wall > 0 ? $done / $wall : 0,
        $pct($times, 50),
        $pct($times, 95),
        $pct($times, 99),
        $codeStr
    );
}
