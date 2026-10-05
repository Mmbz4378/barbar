<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Core\Auth;
use App\Core\DB;
use App\Domain\Identity\OtpService;
use Throwable;

/**
 * ثبت رویدادهای حساس در audit_logs.
 *
 * دسترسی‌ای که رد نگذارد، دسترسی‌ای است که کسی جوابگویش نیست. هر اقدام
 * مدیر کل (ساخت سالن، تغییر رمز دیگران، مسدودی، تغییر تنظیمات) از اینجا
 * ثبت می‌شود. خطای ثبت هرگز خودِ اقدام را نمی‌شکند.
 */
final class AuditLog
{
    /** @param array<string,mixed> $meta */
    public static function record(?int $salonId, string $action, ?string $subjectType = null, ?int $subjectId = null, array $meta = []): void
    {
        try {
            DB::insert('audit_logs', [
                'salon_id' => $salonId,
                'actor_user_id' => Auth::id(),
                'action' => mb_substr($action, 0, 80),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip_address' => OtpService::clientIp(),
            ]);
        } catch (Throwable $e) {
            error_log('audit log skipped: ' . $e->getMessage());
        }
    }
}
