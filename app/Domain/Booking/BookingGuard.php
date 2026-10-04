<?php

declare(strict_types=1);

namespace App\Domain\Booking;

use App\Core\Config;
use App\Core\DB;

/**
 * حفاظ رزرو — جایگزین کد تأیید.
 *
 * وقتی تأیید پیامکی برداشته می‌شود (ت-۳۵)، چیزی باید جای کارِ **دومش**
 * را بگیرد. کد تأیید دو کار می‌کرد:
 *   ۱. مطمئن می‌شد شماره مال همان آدم است
 *   ۲. جلوی ساختن انبوهِ نوبتِ الکی را می‌گرفت
 *
 * کارِ اول را عمداً رها می‌کنیم: مشتری به دادهٔ حساسی دسترسی ندارد و
 * شمارهٔ اشتباه فقط به ضرر خودش است.
 *
 * کارِ دوم را این کلاس انجام می‌دهد، با سه سقف که هر کدام یک نوع
 * سوءاستفادهٔ متفاوت را می‌گیرند:
 *
 *   شماره در روز   — یک نفر که مدام نوبت می‌گیرد و می‌آید
 *   IP در ساعت     — اسکریپتی که با شماره‌های ساختگی صف را پر می‌کند
 *   نوبت باز       — کسی که ده تا سانسِ آینده را قفل می‌کند
 *
 * سقف‌ها سخاوتمندند: هدف، بستن درِ سوءاستفادهٔ انبوه است نه اذیت کردن
 * خانواده‌ای که از یک وای‌فای سه نوبت می‌گیرد.
 */
final class BookingGuard
{
    /** @return array{ok:bool,error:?string} */
    public function check(int $salonId, string $e164Phone, ?string $ip): array
    {
        $perPhone = (int) Config::get('reshen.booking.max_per_phone_per_day', 5);
        $perIp = (int) Config::get('reshen.booking.max_per_ip_per_hour', 10);
        $openMax = (int) Config::get('reshen.booking.max_open_per_phone', 3);

        $customerIds = array_column(
            DB::select('SELECT id FROM customers WHERE salon_id = ? AND phone = ?', [$salonId, $e164Phone]),
            'id'
        );

        if ($customerIds !== []) {
            $placeholders = implode(',', array_fill(0, count($customerIds), '?'));

            $today = (int) DB::selectOne(
                "SELECT COUNT(*) AS c FROM appointments
                 WHERE salon_id = ? AND customer_id IN ({$placeholders})
                   AND created_at >= NOW() - INTERVAL 1 DAY",
                array_merge([$salonId], $customerIds)
            )['c'];

            if ($today >= $perPhone) {
                return ['ok' => false, 'error' => 'با این شماره امروز چند نوبت گرفته شده. فردا دوباره تلاش کن یا با سالن تماس بگیر.'];
            }

            $open = (int) DB::selectOne(
                "SELECT COUNT(*) AS c FROM appointments
                 WHERE salon_id = ? AND customer_id IN ({$placeholders})
                   AND status IN ('pending','confirmed')
                   AND scheduled_at >= NOW()",
                array_merge([$salonId], $customerIds)
            )['c'];

            if ($open >= $openMax) {
                return ['ok' => false, 'error' => 'با این شماره چند نوبتِ آینده ثبت شده. اول یکی‌شان را لغو کن.'];
            }
        }

        if ($ip !== null && $ip !== '') {
            $fromIp = (int) DB::selectOne(
                'SELECT COUNT(*) AS c FROM appointments
                 WHERE salon_id = ? AND created_ip = ? AND created_at >= NOW() - INTERVAL 1 HOUR',
                [$salonId, $ip]
            )['c'];

            if ($fromIp >= $perIp) {
                return ['ok' => false, 'error' => 'تعداد رزروها از این دستگاه زیاد شده. کمی بعد دوباره تلاش کن.'];
            }
        }

        return ['ok' => true, 'error' => null];
    }
}
