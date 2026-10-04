<?php

declare(strict_types=1);

namespace App\Domain\Queue;

use App\Core\Config;
use App\Core\DB;

/**
 * این خدمت چقدر طول می‌کشد؟ — آبشار تخمین.
 *
 * از دقیق به مبهم، اولین چیزی که در دسترس باشد برنده است:
 *   ۱. آنچه یاد گرفته‌ایم: p50/p80 واقعیِ همین آرایشگر برای همین خدمت،
 *      به شرطی که به‌اندازهٔ کافی نمونه جمع شده باشد
 *   ۲. زمانی که خود آن آرایشگر برای این خدمت ثبت کرده
 *   ۳. زمان اسمی خدمت در سالن
 *   ۴. و در نهایت ۳۰ دقیقه
 *
 * بعد ضریب سرعت شخصی مشتری اعمال می‌شود — اگر سابقهٔ کافی داشته باشد.
 * ضریب محدود شده تا یک دادهٔ پرت (روزی که مشتری وسط کار تلفن داشت)
 * نتواند همهٔ تخمین‌های بعدی را خراب کند.
 */
final class DurationEstimator
{
    /*
     * حافظهٔ درون‌درخواستی.
     *
     * آبشار تخمین تا سه کوئری برای هر جفتِ (آرایشگر، خدمت) می‌زند. در
     * یک صف، همان چند خدمت بارها تکرار می‌شوند — ده نفر با «اصلاح مو»
     * یعنی سی کوئریِ یکسان. نتیجه در طول یک درخواست عوض نمی‌شود.
     *
     * @var array<string,array{p50:float,p80:float,source:string}>
     */
    private static array $memo = [];

    /** @return array{p50:float,p80:float,source:string} برحسب دقیقه */
    public function forStaffService(int $staffId, int $serviceId): array
    {
        $key = $staffId . ':' . $serviceId;
        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        return self::$memo[$key] = $this->resolve($staffId, $serviceId);
    }

    /** کشِ درون‌درخواستی را خالی می‌کند — برای تست، که چند سناریو پشت سر هم دارد. */
    public static function flushCache(): void
    {
        self::$memo = [];
    }

    /** @return array{p50:float,p80:float,source:string} */
    /**
     * آبشار تخمین، در یک کوئری.
     *
     * سه پرسش پشت سر هم بود (آمار یادگرفته‌شده، عدد اختصاصی آرایشگر،
     * عدد اسمی خدمت) و هر کدام یک رفت‌وبرگشت. صفحهٔ صف که هر ۱۵ ثانیه
     * تازه می‌شود، همین را برای هر جفتِ آرایشگر-خدمت تکرار می‌کرد.
     *
     * ترتیب اولویت همان است؛ فقط به‌جای سه پرسشِ متوالی، یک LEFT JOIN
     * هر سه را می‌آورد و تصمیم در PHP گرفته می‌شود.
     *
     * @return array{p50:float,p80:float,source:string}
     */
    private function resolve(int $staffId, int $serviceId): array
    {
        $minSamples = (int) Config::get('reshen.estimation.min_samples_for_learning', 8);

        $row = DB::selectOne(
            'SELECT sv.duration_minutes          AS nominal,
                    ss.duration_minutes          AS staff_override,
                    ds.sample_count              AS samples,
                    ds.p50_minutes               AS learned_p50,
                    ds.p80_minutes               AS learned_p80
             FROM services sv
             LEFT JOIN staff_service ss
                    ON ss.service_id = sv.id AND ss.staff_id = ?
             LEFT JOIN duration_stats ds
                    ON ds.service_id = sv.id AND ds.staff_id = ?
             WHERE sv.id = ?',
            [$staffId, $staffId, $serviceId]
        );

        // ۱. آنچه از مدت‌های واقعی یاد گرفته‌ایم — وقتی نمونهٔ کافی هست
        if ($row !== null && $row['samples'] !== null && (int) $row['samples'] >= $minSamples
            && $row['learned_p50'] !== null) {
            return [
                'p50' => (float) $row['learned_p50'],
                'p80' => (float) $row['learned_p80'],
                'source' => 'learned',
            ];
        }

        // ۲. عددی که خود آرایشگر برای این خدمت گذاشته
        if ($row !== null && $row['staff_override'] !== null) {
            $m = (float) $row['staff_override'];

            return ['p50' => $m, 'p80' => $m * 1.3, 'source' => 'staff_override'];
        }

        // ۳. عدد اسمی خدمت
        if ($row !== null && $row['nominal'] !== null) {
            $m = (float) $row['nominal'];

            return ['p50' => $m, 'p80' => $m * 1.3, 'source' => 'nominal'];
        }

        // ۴. آخرین پناه
        $fallback = (float) Config::get('reshen.estimation.fallback_minutes', 30);

        return ['p50' => $fallback, 'p80' => $fallback * 1.3, 'source' => 'fallback'];
    }

    /** ضریب سرعت شخصی مشتری را اعمال می‌کند، اگر فعال باشد. */
    public function customerFactor(?array $customer): float
    {
        if ($customer === null || $customer['duration_factor'] === null) {
            return 1.0;
        }

        $min = (float) Config::get('reshen.estimation.customer_factor_min', 0.7);
        $max = (float) Config::get('reshen.estimation.customer_factor_max', 1.5);
        $factor = (float) $customer['duration_factor'];

        return max($min, min($max, $factor));
    }

    /**
     * مدت انتظاری کل یک نوبت — جمع همهٔ خدماتش، با ضریب سرعت مشتری.
     *
     * p50 یعنی «نصف مواقع از این کمتر»، p80 یعنی «۸ بار از ۱۰ بار از
     * این کمتر». دومی همان چیزی است که به مشتری وعده داده می‌شود: بهتر
     * است زودتر تمام شود تا اینکه دیرتر.
     *
     * @param int[] $serviceIds
     * @return array{p50:float,p80:float}
     */
    public function forAppointment(int $staffId, array $serviceIds, ?array $customer): array
    {
        $p50 = 0.0;
        $p80 = 0.0;
        foreach ($serviceIds as $serviceId) {
            $est = $this->forStaffService($staffId, $serviceId);
            $p50 += $est['p50'];
            $p80 += $est['p80'];
        }

        $factor = $this->customerFactor($customer);

        return ['p50' => $p50 * $factor, 'p80' => $p80 * $factor];
    }
}
