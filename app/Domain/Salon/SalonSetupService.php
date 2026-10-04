<?php

declare(strict_types=1);

namespace App\Domain\Salon;

use App\Core\DB;
use App\Domain\Catalog\CatalogTemplates;
use App\Domain\Catalog\ServiceRepository;
use App\Support\Audience;
use App\Support\Str;
use RuntimeException;

/**
 * ساخت سالن تازه — از ثبت‌نام تا آمادهٔ رزرو.
 *
 * هرچه اینجا ساخته می‌شود پیش‌فرضی است که بدونش سالن کار نمی‌کند:
 * ساعت کاری، عضویت صاحب سالن، و (اگر انتخاب شده باشد) دسته‌ها و
 * خدمات پیشنهادی. خدمتی که قیمتش وارد نشده غیرفعال ساخته می‌شود.
 */
final class SalonSetupService
{
    /**
     * @param array{name:string,audience:string,city?:?string,address?:?string,phone?:?string,seats?:int} $data
     * @param array<int,array{template:string,price:?int}> $services  کلید قالب «دسته:خدمت» با قیمت ریالی
     */
    public function create(array $data, int $ownerUserId, array $services = [], ?string $ownerAsStaffName = null): int
    {
        $name = mb_substr(trim($data['name']), 0, 150);
        if ($name === '') {
            throw new RuntimeException('نام سالن را وارد کنید.');
        }
        $audience = Audience::resolve($data['audience'] ?? null);

        return (int) DB::transaction(function () use ($data, $name, $audience, $ownerUserId, $services, $ownerAsStaffName) {
            $salonId = (int) DB::insert('salons', [
                'slug' => $this->uniqueSlug($name),
                'name' => $name,
                'audience' => $audience,
                'theme' => Audience::defaultTheme($audience),
                'booking_flow' => Audience::defaultBookingFlow($audience),
                'city' => ($data['city'] ?? '') !== '' ? mb_substr((string) $data['city'], 0, 80) : null,
                'address' => ($data['address'] ?? '') !== '' ? mb_substr((string) $data['address'], 0, 255) : null,
                'phone' => ($data['phone'] ?? '') !== '' ? (string) $data['phone'] : null,
                'seats' => max(1, min(50, (int) ($data['seats'] ?? 1))),
                'plan_code' => 'trial',
                'sms_credit' => 200,
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            ]);

            DB::insert('salon_user', ['salon_id' => $salonId, 'user_id' => $ownerUserId, 'role' => 'owner']);

            // ساعت پیش‌فرض: ۹ تا ۲۱، جمعه تعطیل. بدون این، صفحهٔ رزرو خالی است.
            for ($weekday = 0; $weekday <= 6; $weekday++) {
                DB::insert('working_hours', [
                    'salon_id' => $salonId,
                    'staff_id' => null,
                    'weekday' => $weekday,
                    'opens_at' => '09:00:00',
                    'closes_at' => '21:00:00',
                    'is_closed' => $weekday === 6 ? 1 : 0,
                ]);
            }

            if ($ownerAsStaffName !== null && trim($ownerAsStaffName) !== '') {
                $phone = DB::selectOne('SELECT phone FROM users WHERE id = ?', [$ownerUserId])['phone'] ?? null;
                DB::insert('staff', [
                    'salon_id' => $salonId,
                    'user_id' => $ownerUserId,
                    'name' => mb_substr(trim($ownerAsStaffName), 0, 120),
                    'phone' => $phone,
                    'color' => '#1D4ED8',
                ]);
            }

            $this->seedCatalog($salonId, $audience, $services);

            return $salonId;
        });
    }

    /**
     * دسته‌ها و خدمات انتخاب‌شده از فهرست پیشنهادی.
     *
     * @param array<int,array{template:string,price:?int}> $selected
     */
    public function seedCatalog(int $salonId, string $audience, array $selected): void
    {
        if ($selected === []) {
            return;
        }

        $wanted = [];
        foreach ($selected as $row) {
            $wanted[(string) $row['template']] = $row['price'];
        }

        $categoryOrder = 0;
        foreach (CatalogTemplates::for($audience) as $ci => $category) {
            $picked = [];
            foreach ($category['services'] as $si => $service) {
                $key = $ci . ':' . $si;
                if (array_key_exists($key, $wanted)) {
                    $picked[] = [$service, $wanted[$key]];
                }
            }
            if ($picked === []) {
                continue;
            }

            $categoryId = (int) DB::insert('service_categories', [
                'salon_id' => $salonId,
                'name' => $category['name'],
                'visual' => $category['visual'],
                'sort_order' => ++$categoryOrder,
            ]);

            foreach ($picked as $order => [$service, $price]) {
                $price = $price !== null ? max(0, (int) $price) : 0;
                DB::insert('services', [
                    'salon_id' => $salonId,
                    'category_id' => $categoryId,
                    'name' => $service['name'],
                    'duration_minutes' => $service['minutes'],
                    'buffer_minutes' => $service['buffer'],
                    'price' => $price,
                    'price_type' => $service['price_type'],
                    // بی‌قیمت در صفحهٔ عمومی با «۰ تومان» دیده نشود
                    'is_active' => $price > 0 ? 1 : 0,
                    'sort_order' => $order + 1,
                ]);
            }
        }

        ServiceRepository::flushCache();
        (new SalonStats())->refreshPrices($salonId);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'salon';
        $base = substr($base, 0, 50);
        $slug = $base;
        $i = 1;
        while (DB::selectOne('SELECT id FROM salons WHERE slug = ?', [$slug]) !== null) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
