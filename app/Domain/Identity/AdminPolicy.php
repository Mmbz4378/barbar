<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\Auth;
use App\Core\DB;
use App\Domain\System\AuditLog;
use RuntimeException;

/**
 * قواعد مدیر کل و مدیر ارشد.
 *
 * مدیر ارشد اولین مدیر کل است (نصاب می‌سازدش). فقط او:
 *   - به کسی مدیریت کل می‌دهد یا از کسی می‌گیرد،
 *   - روی حساب مدیر کلِ دیگر کاری می‌کند (موبایل، رمز، مسدودی، نشست‌ها)،
 *   - تنظیمات حساس (ورود، پیامک، پرداخت) و بازگردانی دیتابیس را انجام می‌دهد.
 * روی حساب خود مدیر ارشد هیچ‌کس جز خودش کاری نمی‌کند.
 *
 * چرا موبایل و رمزِ مدیرها هم: مدیری که بتواند موبایل مدیر دیگری را به
 * شمارهٔ خودش عوض کند یا رمزش را بگذارد، عملاً همان حساب را گرفته است.
 * چرا پیامک: کسی که اعتبارنامهٔ پیامک را به حساب خودش در اپراتور ببرد، متن
 * کد «فراموشی رمز» مدیر ارشد را در صندوق ارسال اپراتور می‌خواند.
 *
 * ساختن نخستین مدیر (نصاب، خط فرمان، فایل در File Manager) فقط وقتی ممکن است
 * که هیچ مدیر کلِ فعالی نباشد؛ پس از آن، هیچ فایل یا دستوری مدیر تازه نمی‌سازد.
 */
final class AdminPolicy
{
    public static function activeAdminCount(): int
    {
        return (int) (DB::selectOne('SELECT COUNT(*) AS c FROM users WHERE is_platform_admin = 1 AND is_active = 1')['c'] ?? 0);
    }

    /** ساختن مدیر از بیرونِ پنل (نصاب، خط فرمان، فایل) فقط وقتی هیچ مدیری نیست. */
    public static function bootstrapAllowed(): bool
    {
        return self::activeAdminCount() === 0;
    }

    public static function superAdmin(): ?array
    {
        return DB::selectOne('SELECT * FROM users WHERE is_super_admin = 1 AND is_platform_admin = 1 AND is_active = 1 ORDER BY id LIMIT 1');
    }

    public static function isSuper(?array $user): bool
    {
        return $user !== null && (int) ($user['is_super_admin'] ?? 0) === 1
            && (int) ($user['is_platform_admin'] ?? 0) === 1 && (int) ($user['is_active'] ?? 1) === 1;
    }

    public static function currentIsSuper(): bool
    {
        return self::isSuper(Auth::user());
    }

    /**
     * آیا کاربرِ واردشده می‌تواند حساب $target را تغییر دهد؟ null یعنی بله،
     * وگرنه دلیلِ «نه» برای نشان‌دادن.
     */
    public static function denyActingOn(array $target): ?string
    {
        if ((int) $target['id'] === Auth::id()) {
            return null; // هرکس روی حساب خودش (قواعد خودی جدا سنجیده می‌شوند)
        }
        if (self::isSuper($target)) {
            return 'حساب مدیر ارشد را فقط خودش تغییر می‌دهد.';
        }
        if ((int) ($target['is_platform_admin'] ?? 0) === 1 && !self::currentIsSuper()) {
            return 'حساب مدیرهای کل را فقط مدیر ارشد تغییر می‌دهد.';
        }

        return null;
    }

    /** دادن یا گرفتن مدیریت کل و تنظیمات حساس. */
    public static function denyUnlessSuper(): ?string
    {
        return self::currentIsSuper() ? null : 'این کار فقط از مدیر ارشد ساخته است.';
    }

    public static function grant(int $userId, ?int $byUserId, bool $super = false): void
    {
        DB::update('users', [
            'is_platform_admin' => 1,
            'is_super_admin' => $super ? 1 : 0,
            'admin_granted_by' => $byUserId,
            'admin_granted_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $userId]);
    }

    public static function revoke(int $userId): void
    {
        DB::statement(
            'UPDATE users SET is_platform_admin = 0, is_super_admin = 0, admin_granted_by = NULL, admin_granted_at = NULL,
                    auth_version = auth_version + 1 WHERE id = ?',
            [$userId]
        );
    }

    /** مدیر ارشدی را به مدیر کلِ فعال دیگری می‌سپارد؛ خودش مدیر کل می‌ماند. */
    public static function transferSuper(int $fromUserId, int $toUserId): void
    {
        $to = DB::selectOne('SELECT * FROM users WHERE id = ?', [$toUserId]);
        if ($to === null || (int) $to['is_platform_admin'] !== 1 || (int) $to['is_active'] !== 1) {
            throw new RuntimeException('مدیر ارشدی فقط به یک مدیر کلِ فعال سپرده می‌شود.');
        }
        DB::transaction(static function () use ($fromUserId, $toUserId): void {
            DB::update('users', ['is_super_admin' => 0], 'id = :id', ['id' => $fromUserId]);
            DB::update('users', ['is_super_admin' => 1], 'id = :id', ['id' => $toUserId]);
        });
        AuditLog::record(null, 'user.super_transferred', 'user', $toUserId, ['previous' => $fromUserId]);
    }

    /**
     * مدیرهایی که مدیریت کل را از راه سامانه نگرفته‌اند (پرچم مستقیم در
     * دیتابیس عوض شده) — برای هشدار داشبورد.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function unrecordedAdmins(): array
    {
        return DB::select('SELECT id, name, phone FROM users WHERE is_platform_admin = 1 AND admin_granted_at IS NULL ORDER BY id');
    }
}
