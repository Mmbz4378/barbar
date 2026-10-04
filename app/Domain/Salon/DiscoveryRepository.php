<?php
declare(strict_types=1);
namespace App\Domain\Salon;
use App\Core\DB;

final class DiscoveryRepository
{
    public const VISIBLE = "s.is_active = 1 AND s.publication_status = 'published' AND COALESCE(s.city,'') <> '' AND COALESCE(s.address,'') <> '' AND COALESCE(s.phone,'') <> '' AND EXISTS (SELECT 1 FROM services v WHERE v.salon_id=s.id AND v.is_active=1) AND EXISTS (SELECT 1 FROM staff st WHERE st.salon_id=s.id AND st.is_active=1)";

    public function search(array $filters, ?string $phone = null): array
    {
        $where = [self::VISIBLE]; $params = [];
        foreach (['city','neighborhood'] as $field) {
            if (($filters[$field] ?? '') !== '') { $where[]="s.$field LIKE ?"; $params[]='%'.mb_substr($filters[$field],0,100).'%'; }
        }
        if (($filters['q'] ?? '') !== '') {
            $where[]='(s.name LIKE ? OR EXISTS(SELECT 1 FROM services q WHERE q.salon_id=s.id AND q.is_active=1 AND q.name LIKE ?))';
            $query='%'.mb_substr($filters['q'],0,100).'%'; array_push($params,$query,$query);
        }
        if (($filters['max_price'] ?? '') !== '') { $where[]='p.min_price <= ?'; $params[]=max(0,min(1000000000,(int)$filters['max_price']))*10; }
        if (($filters['rating'] ?? '') !== '') { $where[]='r.rating >= ?'; $params[]=max(1,min(5,(float)$filters['rating'])); }
        if (!empty($filters['favorites'])) { $where[]='EXISTS(SELECT 1 FROM salon_favorites f WHERE f.salon_id=s.id AND f.phone=?)'; $params[]=$phone ?? ''; }
        $page=max(1,min(1000,(int)($filters['page']??1))); $offset=($page-1)*12;
        return DB::select("SELECT s.*,p.min_price,r.rating,r.review_count FROM salons s
          LEFT JOIN (SELECT salon_id,MIN(price) min_price FROM services WHERE is_active=1 GROUP BY salon_id) p ON p.salon_id=s.id
          LEFT JOIN (SELECT salon_id,AVG(rating) rating,COUNT(*) review_count FROM reviews WHERE moderation_status='published' GROUP BY salon_id) r ON r.salon_id=s.id
          WHERE ".implode(' AND ',$where)." ORDER BY s.id DESC LIMIT 13 OFFSET $offset",$params);
    }

    public function find(string $slug): ?array
    {
        return DB::selectOne('SELECT s.* FROM salons s WHERE s.slug=? AND '.self::VISIBLE,[$slug]);
    }
}
