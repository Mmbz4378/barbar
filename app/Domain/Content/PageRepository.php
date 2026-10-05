<?php

declare(strict_types=1);

namespace App\Domain\Content;

use App\Core\Cache;
use App\Core\DB;
use Throwable;

/**
 * صفحه‌های محتوایی سایت (درباره، قوانین، حریم خصوصی، …) — /p/{slug}.
 *
 * فهرست پانویس در هر صفحهٔ عمومی لازم است، پس در کش مشترک می‌ماند و با
 * هر نوشتن تازه می‌شود.
 */
final class PageRepository
{
    /** صفحه‌های پیشنهادی برای شروع (اسلاگ ← عنوان). */
    public const SUGGESTED = [
        'about' => 'دربارهٔ ما',
        'terms' => 'قوانین و مقررات',
        'privacy' => 'حریم خصوصی',
        'contact' => 'تماس با ما',
        'faq' => 'پرسش‌های پرتکرار',
    ];

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return DB::select('SELECT * FROM pages ORDER BY sort_order, id');
    }

    public function find(int $id): ?array
    {
        return DB::selectOne('SELECT * FROM pages WHERE id = ?', [$id]);
    }

    public function findPublished(string $slug): ?array
    {
        return Cache::remember('page:' . Cache::version('pages') . ':' . $slug, 3600, static fn () => DB::selectOne(
            'SELECT * FROM pages WHERE slug = ? AND is_published = 1',
            [$slug]
        ));
    }

    /** @return array<int,array{slug:string,title:string}> */
    public static function footer(): array
    {
        try {
            return Cache::remember('pages:footer:v' . Cache::version('pages'), 3600, static fn (): array => DB::select(
                'SELECT slug, title FROM pages WHERE is_published = 1 AND show_in_footer = 1 ORDER BY sort_order, id LIMIT 12'
            ));
        } catch (Throwable) {
            return []; // پیش از مهاجرت ۰۰۲۵
        }
    }

    /** @return array<int,array{slug:string,updated_at:string}> برای sitemap */
    public function publishedForSitemap(): array
    {
        return DB::select('SELECT slug, updated_at FROM pages WHERE is_published = 1 ORDER BY sort_order, id');
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        $row = DB::selectOne('SELECT id FROM pages WHERE slug = ?', [$slug]);

        return $row !== null && (int) $row['id'] !== (int) $exceptId;
    }

    /** @param array<string,mixed> $data */
    public function save(?int $id, array $data): int
    {
        if ($id === null) {
            $id = (int) DB::insert('pages', $data);
        } else {
            DB::update('pages', $data, 'id = :id', ['id' => $id]);
        }
        Cache::bump('pages');

        return $id;
    }

    public function delete(int $id): void
    {
        DB::delete('pages', 'id = :id', ['id' => $id]);
        Cache::bump('pages');
    }
}
