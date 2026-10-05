<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Markdown بسیار ساده و امن برای صفحه‌های محتوایی.
 *
 * اول کل متن escape می‌شود، بعد فقط این‌ها ساخته می‌شوند: سرتیتر (## و ###)،
 * فهرست («- » و «۱. »)، پاراگراف، **پررنگ**، و [پیوند](نشانی) فقط با
 * http(s)، mailto، tel یا مسیر داخلی. پس حتی اگر متن HTML یا اسکریپت داشته
 * باشد، به‌صورت متن نمایش داده می‌شود.
 */
final class SafeMarkdown
{
    public static function render(string $markdown): string
    {
        $lines = preg_split('/\R/u', str_replace("\r\n", "\n", $markdown)) ?: [];
        $html = [];
        $paragraph = [];
        $list = null; // 'ul' | 'ol'
        $items = [];

        $flushParagraph = static function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $flushList = static function () use (&$list, &$items, &$html): void {
            if ($list !== null) {
                $html[] = '<' . $list . '>' . implode('', array_map(static fn ($i) => '<li>' . self::inline($i) . '</li>', $items)) . '</' . $list . '>';
                $list = null;
                $items = [];
            }
        };

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $flushParagraph();
                $flushList();
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/u', $trim, $m)) {
                $flushParagraph();
                $flushList();
                $level = strlen($m[1]);
                $html[] = '<h' . $level . '>' . self::inline($m[2]) . '</h' . $level . '>';
                continue;
            }
            if (preg_match('/^[-*•]\s+(.+)$/u', $trim, $m)) {
                $flushParagraph();
                if ($list !== 'ul') {
                    $flushList();
                    $list = 'ul';
                }
                $items[] = $m[1];
                continue;
            }
            if (preg_match('/^[0-9۰-۹]+[.)]\s+(.+)$/u', $trim, $m)) {
                $flushParagraph();
                if ($list !== 'ol') {
                    $flushList();
                    $list = 'ol';
                }
                $items[] = $m[1];
                continue;
            }
            $flushList();
            $paragraph[] = $trim;
        }
        $flushParagraph();
        $flushList();

        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $safe = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $safe) ?? $safe;

        return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', static function (array $m): string {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $ok = preg_match('#^(https?://|mailto:|tel:)#i', $url) || (str_starts_with($url, '/') && !str_starts_with($url, '//'));
            if (!$ok) {
                return $m[1];
            }
            $external = preg_match('#^https?://#i', $url) === 1;

            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . ($external ? ' rel="noopener nofollow" target="_blank"' : '') . '>' . $m[1] . '</a>';
        }, $safe) ?? $safe;
    }
}
