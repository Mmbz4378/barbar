<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Core\DB;
use App\Support\IranMobile;

final class UserRepository
{
    public function findByPhone(IranMobile $phone): ?array
    {
        return DB::selectOne('SELECT * FROM users WHERE phone = ?', [$phone->e164]);
    }

    public function findOrCreate(IranMobile $phone, ?string $name = null): array
    {
        $existing = $this->findByPhone($phone);
        if ($existing !== null) {
            return $existing;
        }

        $id = DB::insert('users', ['phone' => $phone->e164, 'name' => $name]);

        return DB::selectOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function find(int $id): ?array
    {
        return DB::selectOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /**
     * آیا این کاربر به پنلی راه دارد؟ (مدیر کل، یا عضویت فعال در سالنی)
     *
     * مشتری‌ها هم در users ردیف دارند (برای «نوبت‌های من»)؛ این تفاوت آن‌ها
     * را با کارکنان نشان می‌دهد.
     */
    public static function hasPanelAccess(int $userId): bool
    {
        $row = DB::selectOne(
            'SELECT u.is_platform_admin,
                    EXISTS(SELECT 1 FROM salon_user su WHERE su.user_id = u.id AND su.is_active = 1) AS member
               FROM users u WHERE u.id = ?',
            [$userId]
        );

        return $row !== null && ((int) $row['is_platform_admin'] === 1 || (int) $row['member'] === 1);
    }
}
