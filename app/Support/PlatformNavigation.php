<?php

declare(strict_types=1);

namespace App\Support;

/**
 * منوی پنل مدیر کل.
 *
 * گروه‌ها در نوار کناری دسکتاپ؛ چهار مقصد اول در نوار پایین موبایل و بقیه
 * در برگهٔ «بیشتر». هر مقصد: [نشانی، برچسب، آیکون، تطابق دقیق].
 */
final class PlatformNavigation
{
    /** @return array<string, array<int, array{href:string,label:string,icon:string,exact:bool}>> */
    public static function groups(): array
    {
        $item = static fn (string $href, string $label, string $icon, bool $exact = false): array => compact('href', 'label', 'icon', 'exact');

        return [
            'مدیریت' => [
                $item('/platform', 'داشبورد', 'chart', true),
                $item('/platform/salons', 'سالن‌ها', 'store'),
                $item('/platform/users', 'کاربران', 'users'),
                $item('/platform/reports', 'گزارش‌ها', 'trending'),
                $item('/platform/audit', 'رویدادها و ورودها', 'list'),
            ],
            'سایت' => [
                $item('/platform/settings', 'تنظیمات', 'cog'),
                $item('/platform/pages', 'صفحه‌ها', 'layers'),
                $item('/platform/moderation', 'بررسی انتشار و نظرها', 'shield'),
                $item('/platform/holidays', 'تعطیلات رسمی', 'calendar'),
            ],
            'سامانه' => [
                $item('/system/updates', 'به‌روزرسانی', 'refresh'),
                $item('/system/design', 'سیستم طراحی', 'palette'),
                $item('/account', 'حساب من', 'user'),
            ],
        ];
    }

    /** @return array<int, array{href:string,label:string,icon:string,exact:bool}> */
    public static function primary(): array
    {
        return array_slice(array_merge(...array_values(self::groups())), 0, 4);
    }

    /** @return array<int, array{href:string,label:string,icon:string,exact:bool}> */
    public static function more(): array
    {
        return array_slice(array_merge(...array_values(self::groups())), 4);
    }
}
