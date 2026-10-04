<?php

declare(strict_types=1);

namespace App\Domain\System;

use RuntimeException;

/**
 * دریافت HTTP برای به‌روزرسان — با curl، بدون وابستگی.
 *
 * تغییر مسیرها دستی دنبال می‌شوند: دانلود از گیت‌هاب به یک میزبان دیگر
 * (objects.githubusercontent.com) هدایت می‌شود و نباید توکن دسترسی به
 * آن میزبان فرستاده شود.
 */
final class HttpFetcher
{
    private const MAX_REDIRECTS = 5;

    /**
     * @param array<int,string> $headers
     * @return array{status:int,body:string}
     */
    public function get(string $url, array $headers = [], int $maxBytes = 2_000_000, int $timeout = 20): array
    {
        $result = $this->request($url, $headers, null, $maxBytes, $timeout);

        return ['status' => $result['status'], 'body' => $result['body']];
    }

    /**
     * دانلود در فایل؛ حجم بیش از سقف، شکست است.
     *
     * @param array<int,string> $headers
     */
    public function download(string $url, string $target, array $headers = [], int $maxBytes = 60_000_000, int $timeout = 300): void
    {
        $handle = @fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('فایل موقت دانلود ساخته نشد: ' . $target);
        }
        try {
            $result = $this->request($url, $headers, $handle, $maxBytes, $timeout);
        } finally {
            fclose($handle);
        }
        if ($result['status'] !== 200) {
            @unlink($target);
            throw new RuntimeException('دانلود بسته ناموفق بود (HTTP ' . $result['status'] . ').');
        }
    }

    /**
     * @param array<int,string> $headers
     * @param resource|null $sink
     * @return array{status:int,body:string}
     */
    private function request(string $url, array $headers, $sink, int $maxBytes, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('افزونهٔ curl روی سرور فعال نیست.');
        }

        $originHost = (string) parse_url($url, PHP_URL_HOST);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            self::assertAllowedUrl($url);
            $host = (string) parse_url($url, PHP_URL_HOST);
            // سرآیند Authorization فقط برای همان میزبانی که درخواست اول به آن رفت
            $sendHeaders = $host === $originHost
                ? $headers
                : array_values(array_filter($headers, static fn ($h) => stripos($h, 'authorization:') !== 0));

            $received = 0;
            $body = '';
            $location = null;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => array_merge(['User-Agent: reshen-updater'], $sendHeaders),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$location): int {
                    if (stripos($line, 'location:') === 0) {
                        $location = trim(substr($line, 9));
                    }

                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$received, &$body, $sink, $maxBytes): int {
                    $received += strlen($chunk);
                    if ($received > $maxBytes) {
                        return 0; // قطع انتقال
                    }
                    if ($sink !== null) {
                        return (int) fwrite($sink, $chunk);
                    }
                    $body .= $chunk;

                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($received > $maxBytes) {
                throw new RuntimeException('حجم پاسخ از سقف مجاز بیشتر است.');
            }
            if ($ok === false && !in_array($status, [301, 302, 303, 307, 308], true)) {
                throw new RuntimeException('اتصال به ' . $host . ' برقرار نشد: ' . $error);
            }
            if (in_array($status, [301, 302, 303, 307, 308], true) && $location !== null) {
                $url = self::resolve($url, $location);
                if ($sink !== null) {
                    ftruncate($sink, 0);
                    rewind($sink);
                }
                continue;
            }

            return ['status' => $status, 'body' => $body];
        }

        throw new RuntimeException('تعداد تغییر مسیرها بیش از حد است.');
    }

    /** فقط HTTPS؛ HTTP تنها برای localhost (آزمون محلی). */
    public static function assertAllowedUrl(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            throw new RuntimeException('نشانی نامعتبر است: ' . $url);
        }
        if ($scheme === 'https') {
            return;
        }
        if ($scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return;
        }

        throw new RuntimeException('فقط نشانی HTTPS پذیرفته می‌شود: ' . $url);
    }

    private static function resolve(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return str_starts_with($location, '/') ? $origin . $location : $origin . '/' . $location;
    }
}
