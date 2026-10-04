<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\Cache;
use App\Core\DB;
use App\Support\Audience;
use App\Support\ServiceVisual;

/**
 * کشف سالن‌ها.
 *
 * فقط سالنی دیده می‌شود که منتشر شده، فعال است، و اطلاعات لازم برای
 * مراجعه (شهر، نشانی، تلفن) و دست‌کم یک خدمت و یک نفر فعال دارد.
 * کمینهٔ قیمت و امتیاز از ستون‌های خود سالن خوانده می‌شود (SalonStats).
 */
final class DiscoveryRepository
{
    public const PAGE_SIZE = 12;

    public const VISIBLE = "s.is_active = 1 AND s.publication_status = 'published' AND COALESCE(s.city,'') <> '' AND COALESCE(s.address,'') <> '' AND COALESCE(s.phone,'') <> '' AND EXISTS (SELECT 1 FROM services v WHERE v.salon_id=s.id AND v.is_active=1) AND EXISTS (SELECT 1 FROM staff st WHERE st.salon_id=s.id AND st.is_active=1)";

    /** @return array{rows:array<int,array>,hasNext:bool} */
    public function search(array $filters, ?string $phone = null): array
    {
        $where = [self::VISIBLE];
        $params = [];

        foreach (['city', 'neighborhood'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $where[] = "s.$field LIKE ?";
                $params[] = '%' . self::escapeLike(mb_substr((string) $filters[$field], 0, 100)) . '%';
            }
        }

        $audience = (string) ($filters['audience'] ?? '');
        if (in_array($audience, [Audience::MEN, Audience::WOMEN], true)) {
            // سالن «هر دو» در هر دو فهرست می‌آید
            $where[] = "s.audience IN (?, 'unisex')";
            $params[] = $audience;
        }

        if (($filters['q'] ?? '') !== '') {
            $q = '%' . self::escapeLike(mb_substr((string) $filters['q'], 0, 100)) . '%';
            $where[] = '(s.name LIKE ? OR EXISTS (SELECT 1 FROM services q WHERE q.salon_id = s.id AND q.is_active = 1 AND q.name LIKE ?))';
            array_push($params, $q, $q);
        }

        $category = (string) ($filters['cat'] ?? '');
        if (ServiceVisual::exists($category)) {
            $where[] = 'EXISTS (SELECT 1 FROM services cv JOIN service_categories cc ON cc.id = cv.category_id
                         WHERE cv.salon_id = s.id AND cv.is_active = 1 AND cc.visual = ?)';
            $params[] = $category;
        }

        if (($filters['max_price'] ?? '') !== '' && ($max = int_input($filters['max_price'])) !== null) {
            $where[] = 's.min_price <= ?';
            $params[] = max(0, min(1000000000, $max)) * 10;
        }

        if (($filters['rating'] ?? '') !== '') {
            $where[] = 's.rating_avg >= ?';
            $params[] = max(1, min(5, (float) $filters['rating']));
        }

        if (!empty($filters['favorites'])) {
            $where[] = 'EXISTS (SELECT 1 FROM salon_favorites f WHERE f.salon_id = s.id AND f.phone = ?)';
            $params[] = $phone ?? '';
        }

        $page = max(1, min(500, (int) ($filters['page'] ?? 1)));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $sql = 'SELECT s.id, s.slug, s.name, s.audience, s.theme, s.city, s.neighborhood, s.address, s.cover_path, s.logo_file,
                    s.map_lat, s.map_lng, s.min_price, s.rating_avg, s.rating_count
               FROM salons s
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY (s.rating_count >= 3) DESC, s.rating_avg DESC, s.id DESC
              LIMIT ' . (self::PAGE_SIZE + 1) . ' OFFSET ' . $offset;
        $run = static fn () => DB::select($sql, $params);

        // نتیجهٔ جست‌وجو برای همه یکی است (جز «علاقه‌مندی‌ها» که به شمارهٔ هر
        // کاربر بسته است). صفحهٔ کشف داغ‌ترین صفحهٔ هجوم است؛ با کش ۶۰ ثانیه‌ای،
        // جست‌وجوهای تکراری به دیتابیس نمی‌رسند. تغییر هر سالن نسخهٔ discovery
        // را بالا می‌برد، پس انتشار/امتیاز/فعال‌بودن بی‌درنگ دیده می‌شود.
        $rows = empty($filters['favorites'])
            ? Cache::remember(self::key('search:' . md5($sql . "\0" . serialize($params))), 60, $run)
            : $run();

        return ['rows' => array_slice($rows, 0, self::PAGE_SIZE), 'hasNext' => count($rows) > self::PAGE_SIZE];
    }

    public function find(string $slug): ?array
    {
        return Cache::remember(self::key('find:' . $slug), 300, static fn () => DB::selectOne('SELECT s.* FROM salons s WHERE s.slug = ? AND ' . self::VISIBLE, [$slug]));
    }

    /** شهرهای دارای سالن منتشرشده — برای پیشنهاد در فیلد شهر. */
    public function cities(): array
    {
        return Cache::remember(self::key('cities'), 300, static fn () => array_column(DB::select(
            "SELECT DISTINCT s.city FROM salons s WHERE s.is_active = 1 AND s.publication_status = 'published' AND COALESCE(s.city,'') <> '' ORDER BY s.city LIMIT 50"
        ), 'city'));
    }

    /** کلید کش در فضای «discovery»؛ هر تغییر سالن نسخه را بالا می‌برد و همه را بی‌اعتبار می‌کند. */
    private static function key(string $suffix): string
    {
        return 'discovery:v' . Cache::version('discovery') . ':' . $suffix;
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
