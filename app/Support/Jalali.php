<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * تبدیل میلادی ↔ شمسی، با همان الگوریتم نجومیِ چرخهٔ ۳۳ ساله.
 *
 * این فقط یک جزئیات نمایشی نیست. تقویم شمسی روی منطق برنامه اثر دارد:
 * آخر هفته پنج‌شنبه و جمعه است، هفته از شنبه شروع می‌شود، اسفند در
 * بعضی سال‌ها ۳۰ روز است، و تعطیلات با تاریخ شمسی پیدا می‌شوند.
 *
 * یعنی اگر اینجا اشتباه شود، سالن یک روز اشتباه باز می‌ماند.
 */
final class Jalali
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const WEEKDAY_NAMES = ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'];

    private const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    private const BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    /** @return array{0:int,1:int,2:int} [سال، ماه، روزِ شمسی] */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        return self::d2j(self::g2d($gy, $gm, $gd));
    }

    /** @return array{0:int,1:int,2:int} [سال، ماه، روزِ میلادی] */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    public static function isGregorianLeap(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    public static function isJalaliLeapYear(int $jy): bool
    {
        return self::jalCal($jy)['leap'] === 0;
    }

    public static function daysInJalaliMonth(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }

        return self::isJalaliLeapYear($jy) ? 30 : 29;
    }

    public static function fromDateTime(DateTimeInterface $dt): array
    {
        return self::fromGregorian((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
    }

    public static function toDateTime(int $jy, int $jm, int $jd, string $time = '00:00:00', ?DateTimeZone $tz = null): DateTimeImmutable
    {
        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, $jd);
        $date = sprintf('%04d-%02d-%02d %s', $gy, $gm, $gd, $time);

        return new DateTimeImmutable($date, $tz);
    }

    /** هفتهٔ ایرانی: شنبه = ۰ تا جمعه = ۶ (در PHP یکشنبه = ۰ است). */
    public static function weekday(DateTimeInterface $dt): int
    {
        $phpWeekday = (int) $dt->format('w'); // ۰ (یکشنبه) تا ۶ (شنبه)

        return ($phpWeekday + 1) % 7;
    }

    public static function weekdayName(DateTimeInterface $dt): string
    {
        return self::WEEKDAY_NAMES[self::weekday($dt)];
    }

    public static function isWeekend(DateTimeInterface $dt): bool
    {
        return self::weekday($dt) >= 5; // پنج‌شنبه (۵) یا جمعه (۶)
    }

    public static function format(DateTimeInterface $dt, string $format = 'Y/m/d H:i'): string
    {
        [$jy, $jm, $jd] = self::fromDateTime($dt);

        $replacements = [
            'Y' => (string) $jy,
            'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
            'n' => (string) $jm,
            'M' => self::MONTH_NAMES[$jm],
            'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
            'j' => (string) $jd,
            'D' => self::weekdayName($dt),
            'H' => $dt->format('H'),
            'i' => $dt->format('i'),
        ];

        $out = strtr($format, $replacements);

        return self::toPersianDigits($out);
    }

    public static function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => self::PERSIAN_DIGITS[0], '1' => self::PERSIAN_DIGITS[1], '2' => self::PERSIAN_DIGITS[2],
            '3' => self::PERSIAN_DIGITS[3], '4' => self::PERSIAN_DIGITS[4], '5' => self::PERSIAN_DIGITS[5],
            '6' => self::PERSIAN_DIGITS[6], '7' => self::PERSIAN_DIGITS[7], '8' => self::PERSIAN_DIGITS[8],
            '9' => self::PERSIAN_DIGITS[9],
        ]);
    }

    public static function fromPersianDigits(string $value): string
    {
        return strtr($value, array_flip(self::PERSIAN_DIGITS));
    }

    // ─── خودِ الگوریتم ────────────────────────────────────────────────────

    private static function div(int $a, int $b): int
    {
        return intdiv($a, $b);
    }

    private static function mod(int $a, int $b): int
    {
        return $a - intdiv($a, $b) * $b;
    }

    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4)
            + self::div(153 * self::mod($gm + 9, 12) + 2, 5)
            + $gd - 34840408;
        $d = $d - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;

        return $d;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + self::div(self::div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = self::div(self::mod($j, 1461), 4) * 5 + 308;
        $gd = self::div(self::mod($i, 153), 5) + 1;
        $gm = self::mod(self::div($i, 153), 12) + 1;
        $gy = self::div($j, 1461) - 100100 + self::div(8 - $gm, 6);

        return [$gy, $gm, $gd];
    }

    /** @return array{leap:int,gy:int,march:int} کبیسه، سال میلادی، روزِ مارس */
    private static function jalCal(int $jy): array
    {
        $bl = count(self::BREAKS);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = self::BREAKS[0];

        if ($jy < $jp || $jy >= self::BREAKS[$bl - 1]) {
            throw new InvalidArgumentException("Invalid Jalali year $jy");
        }

        $jump = 0;
        for ($i = 1; $i < $bl; $i++) {
            $jm = self::BREAKS[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ += self::div($jump, 33) * 8 + self::div(self::mod($jump, 33), 4);
            $jp = $jm;
        }

        $n = $jy - $jp;
        $leapJ += self::div($n, 33) * 8 + self::div(self::mod($n, 33) + 3, 4);
        if (self::mod($jump, 33) === 4 && $jump - $n === 4) {
            $leapJ += 1;
        }

        $leapG = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + self::div($jump + 4, 33) * 33;
        }

        $leap = self::mod(self::mod($n + 1, 33) - 1, 4);
        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    private static function j2d(int $jy, int $jm, int $jd): int
    {
        $r = self::jalCal($jy);

        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /** @return array{0:int,1:int,2:int} [jy, jm, jd] */
    private static function d2j(int $jdn): array
    {
        [$gy] = self::d2g($jdn);
        $jy = $gy - 621;
        $r = self::jalCal($jy);
        $jdn1f = self::g2d($r['gy'], 3, $r['march']);

        $k = $jdn - $jdn1f;
        if ($k >= 0) {
            if ($k <= 185) {
                $jm = 1 + self::div($k, 31);
                $jd = self::mod($k, 31) + 1;

                return [$jy, $jm, $jd];
            }
            $k -= 186;
        } else {
            // تاریخ پیش از ۱ فروردینِ $jy است، پس به سال قبل تعلق دارد.
            //
            // نکتهٔ ظریف: تصحیح کبیسه باید روی `$r` انجام شود که با
            // **$jy اصلی** حساب شده، نه با مقدار کم‌شده. در قرارداد این
            // الگوریتم، `leap === 1` یعنی «سال قبلی کبیسه است» — دقیقاً
            // همان چیزی که اینجا لازم داریم، چون اسفندِ سال قبل ۳۰ روزه
            // می‌شود.
            //
            // اگر بعد از `$jy -= 1` دوباره jalCal صدا زده شود، وضعیت کبیسهٔ
            // یک سال عقب‌تر سنجیده می‌شود و نتیجه یک روز جابه‌جا می‌شود.
            // این باگ فقط در بازهٔ ۱ ژانویه تا ۲۰ مارس و فقط در بعضی
            // سال‌ها خودش را نشان می‌داد — یعنی همان‌جایی که کمتر کسی
            // دستی امتحان می‌کند.
            $jy -= 1;
            $k += 179;
            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        $jm = 7 + self::div($k, 30);
        $jd = self::mod($k, 30) + 1;

        return [$jy, $jm, $jd];
    }
}
