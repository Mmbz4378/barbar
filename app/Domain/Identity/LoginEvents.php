<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\DB;
use Throwable;

/**
 * تاریخچهٔ تلاش‌های ورود (login_events).
 *
 * سه کاربرد: سقف تلاش برای هر IP، تاریخچهٔ «حساب من» تا کاربر ورود
 * ناآشنا را ببیند، و گزارش ورودهای ناموفق برای مدیر.
 */
final class LoginEvents
{
    public const PASSWORD = 'password';
    public const OTP = 'otp';
    public const LINK = 'link';
    public const RESET = 'reset';

    public static function record(?int $userId, ?string $identifier, string $method, bool $success, ?string $reason = null): void
    {
        try {
            DB::insert('login_events', [
                'user_id' => $userId,
                'identifier' => $identifier !== null ? mb_substr($identifier, 0, 80) : null,
                'method' => $method,
                'success' => $success ? 1 : 0,
                'reason' => $reason,
                'ip_address' => OtpService::clientIp(),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200) ?: null,
            ]);
        } catch (Throwable $e) {
            // پیش از مهاجرت ۰۰۲۵ جدول نیست؛ ورود نباید به‌خاطر ثبت تاریخچه بشکند
            error_log('login event skipped: ' . $e->getMessage());
        }
    }

    /** ورودهای ناموفقِ اخیر از یک IP (برای سقف تلاش). */
    public static function recentFailuresFromIp(string $ip, int $minutes): int
    {
        try {
            return (int) (DB::selectOne(
                'SELECT COUNT(*) AS c FROM login_events WHERE ip_address = ? AND success = 0 AND created_at >= ?',
                [$ip, date('Y-m-d H:i:s', time() - $minutes * 60)]
            )['c'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId, int $limit = 10): array
    {
        try {
            return DB::select(
                'SELECT method, success, reason, ip_address, user_agent, created_at FROM login_events
                  WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)),
                [$userId]
            );
        } catch (Throwable) {
            return [];
        }
    }

    /** نام خوانای روش ورود. */
    public static function methodLabel(string $method): string
    {
        return match ($method) {
            self::PASSWORD => 'رمز عبور',
            self::OTP => 'کد پیامکی',
            self::LINK => 'لینک یک‌بارمصرف',
            self::RESET => 'بازیابی رمز',
            default => $method,
        };
    }

    /** نام خوانای علت شکست. */
    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'bad_credentials' => 'نام کاربری یا رمز نادرست',
            'locked' => 'حساب قفل بود',
            'blocked' => 'حساب مسدود است',
            'no_password' => 'رمزی تعیین نشده',
            'no_access' => 'بدون دسترسی به پنل',
            'rate_limited' => 'تلاش بیش از حد',
            'bad_code' => 'کد نادرست',
            null, '' => '',
            default => $reason,
        };
    }
}
