<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نام خوانای رویدادهای audit_logs، برای صفحه‌های مدیر کل.
 *
 * رویدادِ ناشناخته با همان نام فنی نشان داده می‌شود؛ پس افزودن رویداد
 * تازه بدون به‌روزکردن این فهرست هم چیزی را نمی‌شکند.
 */
final class AuditLabels
{
    private const ACTIONS = [
        // سالن
        'salon.created' => 'ساخت سالن',
        'salon.registered' => 'ثبت‌نام سالن',
        'salon.registered_pending' => 'ثبت‌نام سالن (در انتظار تأیید)',
        'salon.updated' => 'ویرایش مشخصات سالن',
        'salon.sms_credit' => 'تغییر اعتبار پیامک',
        'salon_activate' => 'فعال‌سازی سالن',
        'salon_deactivate' => 'غیرفعال‌سازی سالن',
        'support_login_as' => 'ورود پشتیبانی به پنل سالن',
        'publication_approve' => 'تأیید انتشار',
        'publication_reject' => 'رد انتشار',
        // اعضا و کاربران
        'member.added' => 'افزودن عضو پنل',
        'member.removed' => 'برداشتن دسترسی عضو',
        'member.restored' => 'بازگرداندن دسترسی عضو',
        'member.role_changed' => 'تغییر نقش عضو',
        'member.credentials_set' => 'تعیین رمز اولیهٔ عضو',
        'user.created' => 'ساخت حساب کاربری',
        'user.updated' => 'ویرایش مشخصات کاربر',
        'user.password_reset' => 'تعیین رمز توسط مدیر',
        'user.blocked' => 'مسدودکردن حساب',
        'user.unblocked' => 'رفع مسدودی حساب',
        'user.unlocked' => 'بازکردن قفل ورود',
        'user.sessions_revoked' => 'بستن نشست‌های کاربر',
        'user.admin_granted' => 'دادن مدیریت کل',
        'user.admin_granted_cli' => 'دادن مدیریت کل (خط فرمان)',
        'user.admin_revoked' => 'گرفتن مدیریت کل',
        // حساب خود کاربر
        'account.password_set' => 'تعیین رمز',
        'account.password_changed' => 'تغییر رمز',
        'account.password_reset' => 'بازیابی رمز با پیامک',
        'account.username_changed' => 'تغییر نام کاربری',
        'account.sessions_revoked' => 'خروج از دستگاه‌های دیگر',
        // سامانه
        'install.completed' => 'نصب سامانه',
        'settings.updated' => 'تغییر تنظیمات سایت',
        'settings.logo' => 'تغییر لوگو',
        'settings.cache_cleared' => 'پاک‌کردن کش',
        'settings.maintenance_on' => 'روشن‌کردن حالت تعمیر',
        'settings.maintenance_off' => 'خاموش‌کردن حالت تعمیر',
        'settings.sms_test' => 'پیامک آزمایشی',
        'page.created' => 'ساخت صفحه',
        'page.updated' => 'ویرایش صفحه',
        'page.deleted' => 'حذف صفحه',
        'holiday_add' => 'افزودن تعطیلی',
        'holiday_seed' => 'افزودن تعطیلات سال',
        'holiday_remove' => 'حذف تعطیلی',
        'migrations_run' => 'اجرای مهاجرت‌های دیتابیس',
        'database_restored' => 'بازگردانی دیتابیس',
    ];

    public static function action(string $action): string
    {
        return self::ACTIONS[$action] ?? $action;
    }

    /** @return array<string,string> برای فیلتر صفحهٔ رویدادها */
    public static function all(): array
    {
        return self::ACTIONS;
    }
}
