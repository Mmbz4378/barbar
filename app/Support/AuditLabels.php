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
        'user.super_transferred' => 'سپردن مدیر ارشدی',
        'user.admin_claimed' => 'ساخت نخستین مدیر از صفحهٔ سلامت',
        'install.admin_skipped' => 'نصب دوباره بی‌ساخت مدیر (مدیر از قبل بود)',
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

    /** نام خوانای ستون‌هایی که در «changes» ثبت می‌شوند */
    private const FIELDS = [
        'name' => 'نام', 'slug' => 'نشانی صفحه', 'audience' => 'نوع', 'city' => 'شهر', 'address' => 'نشانی',
        'phone' => 'تلفن', 'seats' => 'صندلی', 'plan_code' => 'طرح', 'trial_ends_at' => 'پایان آزمایشی',
        'publication_status' => 'انتشار', 'is_active' => 'فعال', 'username' => 'نام کاربری',
    ];

    /**
     * جزئیات رویداد به زبان آدم، به‌جای JSON خام — مثلاً «اعتبار: +۵۰۰ · مانده: ۶۵۰ · دلیل: …».
     * کلید ناشناخته با همان نام فنی می‌آید تا چیزی پنهان نماند.
     *
     * @return array<int,string>
     */
    public static function describe(?string $metaJson): array
    {
        $meta = json_decode((string) $metaJson, true);
        if (!is_array($meta) || $meta === []) {
            return [];
        }
        $out = [];
        foreach ($meta as $key => $value) {
            $out[] = match ((string) $key) {
                'changes' => is_array($value) ? implode(' · ', array_map(
                    static fn (string $field, mixed $c): string => (self::FIELDS[$field] ?? $field) . ': از ' . self::value($field, $c['from'] ?? null) . ' به ' . self::value($field, $c['to'] ?? null),
                    array_keys($value),
                    array_values($value)
                )) : '',
                'tab' => 'بخش: ' . (\App\Http\Controllers\PlatformSettingsController::TABS[$value][0] ?? (string) $value),
                'role' => 'نقش: ' . self::value('role', $value),
                'from' => 'از: ' . self::value('role', $value),
                'to' => 'به: ' . self::value('role', $value),
                'delta' => 'تغییر اعتبار: ' . ((int) $value > 0 ? '+' : '') . fa_num((int) $value) . ' پیامک',
                'after' => 'مانده: ' . fa_num((int) $value),
                'reason' => 'دلیل: ' . self::value('text', $value),
                'owner' => 'صاحب: کاربر #' . fa_num((int) $value),
                'previous' => 'مدیر ارشد قبلی: کاربر #' . fa_num((int) $value),
                'new_owner' => $value ? 'حساب صاحب تازه ساخته شد' : 'صاحب از کاربران موجود',
                'admin' => $value ? 'با دسترسی مدیر کل' : '',
                'generated' => $value ? 'رمز تصادفی' : 'رمز دستی',
                'must_change' => $value ? 'باید در ورود بعدی عوض شود' : '',
                'removed' => $value ? 'حذف شد' : '',
                'ok' => 'نتیجه: ' . ($value ? 'موفق' : 'ناموفق'),
                'provider' => 'درگاه: ' . self::value('text', $value),
                'slug' => 'نشانی: /p/' . self::value('text', $value),
                'date' => 'تاریخ: ' . (is_string($value) && strtotime($value) !== false ? jdate($value, 'Y/m/d') : self::value('text', $value)),
                'label' => 'عنوان: ' . self::value('text', $value),
                'year' => 'سال ' . fa_num((int) $value),
                default => $key . ': ' . (is_scalar($value) || $value === null ? self::value('text', $value) : mb_substr((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 80)),
            };
        }

        return array_values(array_filter($out, static fn (string $part): bool => $part !== ''));
    }

    private static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (!is_scalar($value)) {
            return mb_substr((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 60);
        }
        $text = match ($field) {
            'plan_code' => \App\Http\Controllers\PlatformSalonController::PLANS[$value] ?? (string) $value,
            'publication_status' => \App\Http\Controllers\PlatformSalonController::PUBLICATION[$value] ?? (string) $value,
            'role' => \App\Http\Controllers\PlatformSalonController::ROLES[$value] ?? (string) $value,
            'audience' => Audience::options()[$value] ?? (string) $value,
            'is_active' => (int) $value === 1 ? 'بله' : 'خیر',
            'trial_ends_at' => jdate((string) $value, 'Y/m/d'),
            'seats' => fa_num((int) $value),
            default => is_bool($value) ? ($value ? 'بله' : 'خیر') : (string) $value,
        };

        return '«' . mb_substr($text, 0, 60) . '»';
    }
}
