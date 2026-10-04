<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Core\Auth;
use App\Core\DB;
use Throwable;

/**
 * چه کسی کارهای سطح سامانه (به‌روزرسانی، مهاجرت) را انجام می‌دهد.
 *
 * مدیر پلتفرم همیشه. در نصب تک‌سالنی که هیچ‌کس مدیر پلتفرم نشده
 * (ساختنش Terminal می‌خواهد)، صاحب یا مدیر سالن — وگرنه سامانه هیچ
 * گردانندهٔ ممکنی نداشت.
 */
final class SystemAccess
{
    private static ?bool $hasPlatformAdmin = null;

    public static function allowed(): bool
    {
        if (!Auth::check()) {
            return false;
        }
        if (Auth::isPlatformAdmin()) {
            return true;
        }

        return !self::platformAdminExists() && in_array(Auth::role(), ['owner', 'manager'], true);
    }

    public static function platformAdminExists(): bool
    {
        if (self::$hasPlatformAdmin === null) {
            try {
                self::$hasPlatformAdmin = DB::selectOne('SELECT id FROM users WHERE is_platform_admin = 1 LIMIT 1') !== null;
            } catch (Throwable) {
                self::$hasPlatformAdmin = true; // در شک، سخت‌گیر
            }
        }

        return self::$hasPlatformAdmin;
    }
}
