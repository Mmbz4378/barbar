<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * خروجی CSV برای Excel.
 *
 * BOM اول فایل لازم است، وگرنه Excel فارسی را به‌هم‌ریخته نشان می‌دهد.
 * خانه‌هایی که با = + - @ شروع می‌شوند یک ' جلوشان می‌گیرند تا Excel آن‌ها را
 * فرمول اجرا نکند (CSV injection) — نام سالن یا مشتری را کاربران می‌نویسند.
 */
final class Csv
{
    /**
     * @param array<int,string>            $headers
     * @param iterable<array<int,mixed>>   $rows
     */
    public static function response(string $filename, array $headers, iterable $rows): Response
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map([self::class, 'cell'], $headers), ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'cell'], $row), ',', '"', '\\');
        }
        rewind($out);
        $body = (string) stream_get_contents($out);
        fclose($out);

        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'report.csv';

        return new Response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($text)) {
            $text = "'" . $text;
        }

        return $text;
    }
}
