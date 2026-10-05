<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\DB;
use App\Support\IranMobile;

/**
 * ساخت حساب تازه از پنل مدیر کل (یا ساخت صاحب سالن هم‌زمان با سالن).
 *
 * اعتبارسنجی یک‌جاست تا فرم «کاربر تازه»، «سالن تازه» و «افزودن عضو»
 * دقیقاً همان قواعد را داشته باشند.
 */
final class AccountService
{
    /**
     * ورودیِ فرم کاربر تازه را می‌سنجد.
     *
     * فیلدها با پیشوند $prefix خوانده می‌شوند (مثلاً «owner_») تا در یک فرم
     * کنار فیلدهای دیگر بنشینند.
     *
     * @param array<string,mixed> $input
     * @return array{0: array<string,string>, 1: array{name:string,phone:string,username:?string,password:string,generated:bool}}
     */
    public static function validateNew(array $input, string $prefix = ''): array
    {
        $get = static fn (string $key): string => trim((string) ($input[$prefix . $key] ?? ''));
        $errors = [];

        $name = mb_substr($get('name'), 0, 120);
        if ($name === '') {
            $errors[$prefix . 'name'] = 'نام را بنویسید.';
        }

        $phone = IranMobile::tryParse($get('phone'));
        if ($phone === null) {
            $errors[$prefix . 'phone'] = 'شمارهٔ موبایل معتبر نیست؛ مثل ۰۹۱۲۳۴۵۶۷۸۹.';
        } elseif (DB::selectOne('SELECT id FROM users WHERE phone = ?', [$phone->e164]) !== null) {
            $errors[$prefix . 'phone'] = 'این شماره از قبل حساب دارد؛ گزینهٔ «کاربر موجود» را انتخاب کنید.';
        }

        $username = PasswordAuth::normalizeUsername($get('username'));
        if ($username !== '') {
            $error = PasswordAuth::usernameError($username)
                ?? (PasswordAuth::usernameTaken($username) ? 'این نام کاربری مال کس دیگری است.' : null);
            if ($error !== null) {
                $errors[$prefix . 'username'] = $error;
            }
        }

        $generated = ($input[$prefix . 'generate'] ?? '') === '1';
        $password = $generated ? PasswordAuth::generate() : (string) ($input[$prefix . 'password'] ?? '');
        if (!$generated) {
            $policy = PasswordAuth::policyError($password, $phone?->e164, $username !== '' ? $username : null);
            if ($policy !== null) {
                $errors[$prefix . 'password'] = $policy;
            }
        }

        return [$errors, [
            'name' => $name,
            'phone' => $phone?->e164 ?? '',
            'username' => $username !== '' ? $username : null,
            'password' => $password,
            'generated' => $generated,
        ]];
    }

    /**
     * @param array{name:string,phone:string,username:?string,password:string} $data
     */
    public static function create(array $data, bool $mustChange, bool $isAdmin = false): int
    {
        return (int) DB::insert('users', [
            'phone' => $data['phone'],
            'name' => $data['name'],
            'username' => $data['username'],
            'password_hash' => PasswordAuth::hash($data['password']),
            'must_change_password' => $mustChange ? 1 : 0,
            'password_changed_at' => date('Y-m-d H:i:s'),
            'is_platform_admin' => $isAdmin ? 1 : 0,
        ]);
    }

    /** کاربر موجود با موبایل یا نام کاربری (برای «کاربر موجود» در فرم‌ها). */
    public static function findExisting(string $identifier): ?array
    {
        return PasswordAuth::findByIdentifier($identifier);
    }

    /** چند مدیر کلِ فعال داریم؟ (آخرین مدیر را نمی‌شود برداشت) */
    public static function activeAdminCount(): int
    {
        return (int) (DB::selectOne('SELECT COUNT(*) AS c FROM users WHERE is_platform_admin = 1 AND is_active = 1')['c'] ?? 0);
    }

    /** چند صاحبِ فعال برای این سالن مانده؟ */
    public static function activeOwnerCount(int $salonId): int
    {
        return (int) (DB::selectOne(
            "SELECT COUNT(*) AS c FROM salon_user su JOIN users u ON u.id = su.user_id
              WHERE su.salon_id = ? AND su.role = 'owner' AND su.is_active = 1 AND u.is_active = 1",
            [$salonId]
        )['c'] ?? 0);
    }
}
