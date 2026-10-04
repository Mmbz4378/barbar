<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\Cache;
use App\Core\DB;

final class SalonRepository
{
    /** مهلت امنِ کش سالن (ثانیه). باطل‌کردن دقیق با forget انجام می‌شود؛ این فقط تورِ ایمنی است. */
    private const TTL = 300;

    public function find(int $id): ?array
    {
        $ver = Cache::version("salon:$id");

        return Cache::remember("salon:$id:v$ver", self::TTL, static fn () => DB::selectOne('SELECT * FROM salons WHERE id = ?', [$id]));
    }

    /** سالنِ فعال با نشانی عمومی — برای صفحه‌های رزرو و عمومی. */
    public function findActiveBySlug(string $slug): ?array
    {
        if ($slug === '' || mb_strlen($slug) > 60) {
            return null;
        }

        /*
         * نگاشت اسلاگ→شناسه با مهلت بلند (اسلاگ تقریباً هرگز عوض نمی‌شود)، ولی
         * خودِ ردیف از find() می‌آید که دقیق باطل می‌شود؛ پس is_active و بقیه
         * بی‌درنگ تازه‌اند. اگر اسلاگ عوض شده باشد، ردیف تازه دیگر همین اسلاگ را
         * ندارد و نگاشت کهنه نادیده گرفته می‌شود — حتی اگر اسلاگ به سالن دیگری
         * رسیده باشد. فقط «پیدا شد» کش می‌شود، نه «نبود»: سالن تازه بی‌درنگ پیدا
         * می‌شود.
         */
        $key = "salonslug:$slug";
        $id = Cache::get($key);
        if ($id !== null) {
            $salon = $this->find((int) $id);
            if ($salon !== null && (string) $salon['slug'] === $slug) {
                return (int) $salon['is_active'] === 1 ? $salon : null;
            }
        }

        $row = DB::selectOne('SELECT id FROM salons WHERE slug = ?', [$slug]);
        if ($row === null) {
            return null;
        }
        Cache::set($key, (int) $row['id'], 86400);
        $salon = $this->find((int) $row['id']);

        return $salon !== null && (int) $salon['is_active'] === 1 ? $salon : null;
    }

    /**
     * کش این سالن را باطل می‌کند و فهرست کشف را هم تازه می‌کند.
     *
     * هر جا ردیف salons نوشته می‌شود این صدا زده می‌شود. بالا بردن نسخهٔ
     * «discovery» هم تضمین می‌کند صفحهٔ کشف، وضعیت انتشار/فعال/امتیاز/شهر
     * را بی‌درنگ درست نشان دهد.
     */
    public static function forget(int $id): void
    {
        Cache::bump("salon:$id");
        Cache::bump('discovery');
    }

    /** آیا الان ساعت کاری سالن است؟ */
    public function isOpenNow(int $salonId): bool
    {
        $now = new \DateTimeImmutable();

        return DB::selectOne(
            'SELECT 1 AS ok FROM working_hours
              WHERE salon_id = ? AND staff_id IS NULL AND weekday = ? AND is_closed = 0
                AND ? BETWEEN opens_at AND closes_at
              LIMIT 1',
            [$salonId, \App\Support\Jalali::weekday($now), $now->format('H:i:s')]
        ) !== null;
    }
}
