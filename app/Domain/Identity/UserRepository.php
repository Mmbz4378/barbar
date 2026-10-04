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
}
