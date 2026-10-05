<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Cache;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Content\PageRepository;
use App\Domain\System\SiteSettings;
use App\Support\SafeMarkdown;

/**
 * صفحه‌های سطح سایت: robots.txt، sitemap.xml و صفحه‌های محتوایی /p/{slug}.
 */
final class SiteController extends Controller
{
    /** مسیرهایی که هرگز نباید در نتایج جست‌وجو بیایند. */
    private const PRIVATE_PATHS = ['/panel', '/platform', '/system', '/account', '/login', '/onboarding', '/salons/', '/me', '/q/', '/doctor.php', '/install.php'];

    public function robots(Request $request): Response
    {
        $base = $this->baseUrl();
        $lines = ['User-agent: *'];
        if (SiteSettings::indexingAllowed()) {
            $lines[] = 'Allow: /';
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            $lines[] = 'Allow: /salons/view/';
            if ($base !== '') {
                $lines[] = '';
                $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
            }
        } else {
            $lines[] = 'Disallow: /';
        }

        return new Response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(Request $request): Response
    {
        if (!SiteSettings::indexingAllowed()) {
            return $this->notFound();
        }
        $base = $this->baseUrl();
        $xml = Cache::remember('sitemap:v' . Cache::version('discovery') . '.' . Cache::version('pages') . '.' . Cache::version('site_settings'), 3600, function () use ($base): string {
            $urls = [[$base . '/discover', null, '0.8']];
            foreach (DB::select(
                "SELECT slug, publication_status, updated_at FROM salons WHERE is_active = 1 ORDER BY id LIMIT 5000"
            ) as $s) {
                $urls[] = [$base . '/s/' . rawurlencode((string) $s['slug']), $s['updated_at'] ?? null, '0.7'];
                if ($s['publication_status'] === 'published') {
                    $urls[] = [$base . '/salons/view/' . rawurlencode((string) $s['slug']), $s['updated_at'] ?? null, '0.9'];
                }
            }
            foreach ((new PageRepository())->publishedForSitemap() as $p) {
                $urls[] = [$base . '/p/' . rawurlencode((string) $p['slug']), $p['updated_at'] ?? null, '0.4'];
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($urls as [$loc, $updated, $priority]) {
                $out .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
                    . ($updated ? '<lastmod>' . date('Y-m-d', (int) strtotime((string) $updated)) . '</lastmod>' : '')
                    . '<priority>' . $priority . '</priority></url>' . "\n";
            }

            return $out . "</urlset>\n";
        });

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function showPage(Request $request): Response
    {
        $page = (new PageRepository())->findPublished((string) $request->param('slug'));
        if ($page === null) {
            return $this->notFound('این صفحه پیدا نشد.');
        }

        return $this->page('layouts.public', 'site.page', [
            'title' => $page['title'],
            'description' => $page['meta_description'] ?: mb_substr(trim(strip_tags(SafeMarkdown::render((string) $page['body']))), 0, 160),
            'page' => $page,
            'html' => SafeMarkdown::render((string) $page['body']),
            'wide' => false,
        ])->publicCache(300, 600);
    }

    private function baseUrl(): string
    {
        return rtrim((string) Config::get('app.url', ''), '/');
    }
}
