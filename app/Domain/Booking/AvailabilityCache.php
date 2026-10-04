<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\Cache;
use App\Core\DB;
use App\Support\Now;

/**
 * کشِ کوتاهِ «وقت‌های آزاد» — فقط برای نمایش، هرگز برای ثبت.
 *
 * چرا: گام انتخاب زمان در هر بار نمایش، دسترسی‌پذیری را برای نوار روزها،
 * روز انتخاب‌شده و همهٔ روزهای تقویم ماه حساب می‌کند (ده‌ها بار). در هجومِ
 * متمرکز روی یک سالن، هزاران بازدیدکننده همین جواب را از نو حساب می‌کنند.
 * با این کش، یک محاسبه بین همه تقسیم می‌شود.
 *
 * چرا امن است:
 *   ۱. فقط نمایش از این کش می‌خواند. ثبت نوبت (BookingService) همیشه زیر
 *      قفل از نو حساب می‌کند، پس دوبار رزروِ یک وقت ممکن نیست.
 *   ۲. هر نوشتن روی نوبت‌ها یا مرخصی‌های یک سالن، نسخهٔ «sched» آن سالن را
 *      بالا می‌برد؛ هر تغییر تنظیمات/ساعت/خدمت، نسخهٔ «salon» را. هر دو در
 *      کلیدند، پس نتیجهٔ کهنه خوانده نمی‌شود.
 *   ۳. سطلِ زمانیِ یک‌دقیقه‌ای در کلید است: حتی اگر جایی bump نرسد (مثلاً
 *      منقضی‌شدن بیعانه در cron که به APCu وب‌سرور دسترسی ندارد)، کهنگی
 *      حداکثر یک دقیقه است — و بدترین پیامدش این است که کاربر وقتی را
 *      انتخاب کند و پیام «دیگر آزاد نیست» ببیند.
 */
final class AvailabilityCache
{
    /** طول سطل زمانی (ثانیه). */
    private const BUCKET = 60;

    /** بلندتر از سطل، تا ورودی تا پایان سطلش زنده بماند. */
    private const TTL = 90;

    /** پس از هر نوشتن روی نوبت‌ها یا مرخصی‌های این سالن. */
    public static function bump(int $salonId): void
    {
        // پس از commit: وگرنه خواننده‌ای هم‌زمان، دادهٔ قدیمی را زیر نسخهٔ تازه کش می‌کرد
        DB::afterCommit(static fn () => Cache::bump("sched:$salonId"));
    }

    /** پس از جاروی سراسری (مثل انقضای بیعانه‌ها) که سالن مشخصی ندارد. */
    public static function bumpAll(): void
    {
        DB::afterCommit(static fn () => Cache::bump('sched:all'));
    }

    /** نسخهٔ برنامهٔ نوبت‌های سالن — برای ETag صفحهٔ صف هم به کار می‌رود. */
    public static function version(int $salonId): string
    {
        return Cache::version("sched:$salonId") . '.' . Cache::version('sched:all');
    }

    public static function remember(int $salonId, string $suffix, callable $compute): mixed
    {
        $key = sprintf(
            'avail:%d:c%d:s%s:t%d:%s',
            $salonId,
            Cache::version("salon:$salonId"),
            self::version($salonId),
            intdiv(Now::get()->getTimestamp(), self::BUCKET),
            $suffix
        );

        return Cache::remember($key, self::TTL, $compute);
    }
}
