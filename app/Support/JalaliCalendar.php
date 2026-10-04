<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * ساخت شبکهٔ ماه شمسی برای نمایش تقویم.
 *
 * چرا کلاس جدا: هم صفحهٔ رزرو مشتری و هم پنل سالن تقویم می‌خواهند. اگر
 * منطق چیدن ماه در ویو نوشته شود، دو جا تکرار می‌شود و یکی‌شان دیر یا
 * زود اشتباه می‌شود — و اشتباه در تقویمِ سامانهٔ نوبت‌دهی یعنی مشتری
 * برای روز غلط می‌آید.
 *
 * قاعده‌هایی که اینجا رعایت می‌شوند و در تقویم میلادی فرق دارند:
 *
 *   • هفته از **شنبه** شروع می‌شود، نه یک‌شنبه و نه دوشنبه.
 *   • آخر هفته **پنج‌شنبه و جمعه** است — که اتفاقاً اوج کار آرایشگاه
 *     مردانه هم هست، پس باید دیده شود نه اینکه کم‌رنگ شود.
 *   • اسفند در سال کبیسه ۳۰ روز دارد.
 */
final class JalaliCalendar
{
    public const MONTHS = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    /** شنبه تا جمعه — ترتیب ستون‌های تقویم. */
    public const WEEKDAY_INITIALS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    public const WEEKDAY_NAMES = [
        'شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه',
    ];

    /**
     * شبکهٔ یک ماه شمسی.
     *
     * خروجی همیشه هفته‌های کامل هفت‌روزه است؛ خانه‌های خارج از ماه
     * `null` می‌شوند تا ویو بتواند جای خالی بگذارد و چیدمان نلرزد.
     *
     * @param array<string,array{available:bool,label?:string}> $dayStates
     *        وضعیت هر روز، کلید به‌صورت میلادیِ Y-m-d
     *
     * @return array{
     *     year:int, month:int, monthName:string,
     *     weeks:array<int,array<int,?array{
     *         jday:int, gregorian:string, isToday:bool, isWeekend:bool,
     *         isPast:bool, available:bool, label:string
     *     }>>,
     *     prev:array{year:int,month:int}, next:array{year:int,month:int}
     * }
     */
    public static function month(int $jy, int $jm, array $dayStates = [], ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $todayKey = $today->format('Y-m-d');

        $daysInMonth = Jalali::daysInJalaliMonth($jy, $jm);

        // ستون روز اول ماه: ۰ = شنبه … ۶ = جمعه
        $firstDay = Jalali::toDateTime($jy, $jm, 1);
        $offset = Jalali::weekday($firstDay);

        $weeks = [];
        $week = array_fill(0, 7, null);
        $column = $offset;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Jalali::toDateTime($jy, $jm, $day);
            $key = $date->format('Y-m-d');
            $state = $dayStates[$key] ?? null;

            $week[$column] = [
                'jday' => $day,
                'gregorian' => $key,
                'isToday' => $key === $todayKey,
                'isWeekend' => Jalali::isWeekend($date),
                'isPast' => $key < $todayKey,
                // وقتی وضعیتی داده نشده، روز را «باز» فرض نکن مگر اینکه
                // گذشته نباشد — تا تقویمی که هنوز داده ندارد، روزهای
                // گذشته را قابل انتخاب نشان ندهد.
                'available' => $state['available'] ?? ($key >= $todayKey),
                'label' => $state['label'] ?? '',
            ];

            $column++;
            if ($column === 7) {
                $weeks[] = $week;
                $week = array_fill(0, 7, null);
                $column = 0;
            }
        }

        if ($column !== 0) {
            $weeks[] = $week;
        }

        return [
            'year' => $jy,
            'month' => $jm,
            'monthName' => self::MONTHS[$jm],
            'weeks' => $weeks,
            'prev' => $jm === 1 ? ['year' => $jy - 1, 'month' => 12] : ['year' => $jy, 'month' => $jm - 1],
            'next' => $jm === 12 ? ['year' => $jy + 1, 'month' => 1] : ['year' => $jy, 'month' => $jm + 1],
        ];
    }

    /** ماه شمسیِ یک تاریخ میلادی. */
    public static function monthOf(DateTimeImmutable $date): array
    {
        [$jy, $jm] = Jalali::fromDateTime($date);

        return ['year' => $jy, 'month' => $jm];
    }

    /**
     * برچسب خوانا برای یک تاریخ — «پنج‌شنبه ۲۹ شهریور».
     *
     * برای صفحهٔ «نوبت من» و متن پیامک، که در آن‌ها عدد خشک بی‌معنی است.
     */
    public static function humanDate(DateTimeImmutable $date, bool $withYear = false): string
    {
        [$jy, $jm, $jd] = Jalali::fromDateTime($date);
        $weekday = self::WEEKDAY_NAMES[Jalali::weekday($date)];

        $text = $weekday . ' ' . Jalali::toPersianDigits((string) $jd) . ' ' . self::MONTHS[$jm];

        return $withYear ? $text . ' ' . Jalali::toPersianDigits((string) $jy) : $text;
    }

    /**
     * «امروز» / «فردا» / «پنج‌شنبه ۲۹ شهریور».
     *
     * مشتری‌ای که پیامک می‌خواند، «فردا» را بهتر از تاریخ می‌فهمد.
     */
    public static function relativeDate(DateTimeImmutable $date, ?DateTimeImmutable $today = null): string
    {
        $today ??= new DateTimeImmutable('today');
        $diff = (int) $today->diff($date->setTime(0, 0))->format('%r%a');

        return match ($diff) {
            0 => 'امروز',
            1 => 'فردا',
            2 => 'پس‌فردا',
            default => self::humanDate($date),
        };
    }
}
