<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Cache;
use App\Core\DB;
use App\Core\Deferred;

/**
 * صندوق خروجی پیامک: ثبت در جدول، فرستادن بیرون از مسیر درخواست.
 *
 * چرخهٔ هر پیامک:
 *
 *   queued ──(مال خود کردن با claim_token)──► sending ──► sent
 *                                                    └──► failed ─(سررسید)─► دوباره
 *
 *  - dispatch(): پس از رسیدن پاسخ به کاربر (Deferred) همان پیامک را می‌فرستد.
 *  - drain():    cron هرچه سررسیده را می‌فرستد — هم پیامکی که پردازشش مُرد،
 *                هم تلاش دوبارهٔ خطاها.
 *
 * دو بار فرستاده نمی‌شود: «مال خود کردن» یک UPDATE اتمی با توکن یکتاست؛ اگر
 * وب و cron هم‌زمان سراغ یک ردیف بروند، فقط یکی آن را می‌گیرد. تنها حالتِ
 * دوباره‌فرستادن این است که پردازش پس از تحویل به اپراتور و پیش از ثبت «sent»
 * بمیرد و ردیف پس از ۱۰ دقیقه به صف برگردد — «دست‌کم یک بار» را به «شاید هیچ‌بار»
 * ترجیح می‌دهیم.
 */
final class SmsOutbox
{
    /** پس از این تعداد تلاش، پیامک نهایی failed می‌ماند. */
    private const MAX_ATTEMPTS = 3;

    /** فاصلهٔ تلاش دوباره پس از تلاش n‌ام (ثانیه). */
    private const BACKOFF = [1 => 60, 2 => 300];

    /** ردیفی که بیش از این در sending مانده، یعنی پردازشش مُرده. */
    private const STUCK_SECONDS = 600;

    /** cron در هر دور دسته‌های کوچک برمی‌دارد… */
    private const DRAIN_BATCH = 10;

    /** …و پس از این مدت، بقیه را به دور بعد می‌سپارد (زیر سقف ۱۲۰ ثانیهٔ cronِ وب). */
    private const DRAIN_BUDGET_SECONDS = 60;

    /** شرط «آمادهٔ فرستادن» — صف تازه، یا خطایی که وقت تلاش دوباره‌اش رسیده. */
    private const DUE = "((status = 'queued' AND (next_attempt_at IS NULL OR next_attempt_at <= ?))
                       OR (status = 'failed' AND next_attempt_at IS NOT NULL AND next_attempt_at <= ?))";

    /**
     * چند پردازش هم‌زمان می‌توانند پس از پاسخ پیامک بفرستند.
     *
     * پس از پاسخ، کاربر منتظر نیست ولی پردازش PHP تا تحویل به اپراتور مشغول
     * می‌ماند. اگر اپراتور کند باشد ولی جواب بدهد (مدار باز نمی‌شود، چون OTP
     * باید کار کند)، هجومِ رزرو می‌توانست بیشتر پردازش‌ها را به فرستادن پیامک
     * مشغول کند. با این سقف، بقیهٔ پردازش‌ها همیشه برای صفحه‌ها آزادند و پیامکِ
     * اضافه در صندوق می‌ماند تا cron بفرستد.
     */
    private const INLINE_SLOTS = 2;

    /** جای گرفته‌شده پس از این مدت خودبه‌خود آزاد می‌شود (اگر پردازش بمیرد). */
    private const SLOT_TTL = 30;

    /** این پیامک را پس از پاسخ به کاربر بفرست (در CLI همان لحظه). */
    public static function dispatch(int $id): void
    {
        Deferred::push(static function () use ($id): void {
            $slot = self::acquireSlot();
            if ($slot === null) {
                return; // همهٔ جاها پر است؛ cron می‌فرستد
            }
            try {
                self::deliver([$id]);
            } finally {
                Cache::forget($slot);
            }
        });
    }

    private static function acquireSlot(): ?string
    {
        for ($i = 0; $i < self::INLINE_SLOTS; $i++) {
            $key = "sms:slot:$i";
            if (Cache::add($key, getmypid(), self::SLOT_TTL)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param int[] $ids
     * @return int تعداد فرستاده‌شده
     */
    public static function deliver(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === [] || SmsBreaker::isOpen()) {
            return 0;
        }

        $token = bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');
        $in = implode(',', array_fill(0, count($ids), '?'));
        DB::statement(
            "UPDATE sms_messages SET status = 'sending', claim_token = ?, claimed_at = ?, attempts = attempts + 1
              WHERE id IN ($in) AND " . self::DUE,
            array_merge([$token, $now], $ids, [$now, $now])
        );

        return self::sendClaimed($token);
    }

    /**
     * برای cron: ردیف‌های گیرکرده را برمی‌گرداند و هرچه سررسیده را می‌فرستد.
     *
     * @return array{sent:int,requeued:int,skipped:bool}
     */
    public static function drain(int $limit = 50): array
    {
        $requeued = DB::statement(
            "UPDATE sms_messages SET status = 'queued', claim_token = NULL
              WHERE status = 'sending' AND claimed_at < ?",
            [date('Y-m-d H:i:s', time() - self::STUCK_SECONDS)]
        )->rowCount();

        if (SmsBreaker::isOpen()) {
            return ['sent' => 0, 'requeued' => $requeued, 'skipped' => true];
        }

        /*
         * دسته‌های کوچک با سقف زمانی: اگر cronِ وب به سقف زمان اجرا بخورد و
         * کشته شود، فقط همان دستهٔ در دست در «sending» می‌ماند (و پس از ۱۰ دقیقه
         * برمی‌گردد)، نه همهٔ پیامک‌هایی که یک‌جا برداشته بود.
         */
        $sent = 0;
        $taken = 0;
        $deadline = microtime(true) + self::DRAIN_BUDGET_SECONDS;
        while ($taken < $limit && microtime(true) < $deadline && !SmsBreaker::isOpen()) {
            $token = bin2hex(random_bytes(8));
            $now = date('Y-m-d H:i:s');
            $claimed = DB::statement(
                "UPDATE sms_messages SET status = 'sending', claim_token = ?, claimed_at = ?, attempts = attempts + 1
                  WHERE " . self::DUE . ' ORDER BY id LIMIT ' . min(self::DRAIN_BATCH, $limit - $taken),
                [$token, $now, $now, $now]
            )->rowCount();
            if ($claimed === 0) {
                break;
            }
            $taken += $claimed;
            $sent += self::sendClaimed($token);
        }

        return ['sent' => $sent, 'requeued' => $requeued, 'skipped' => false];
    }

    private static function sendClaimed(string $token): int
    {
        $sent = 0;
        foreach (DB::select("SELECT * FROM sms_messages WHERE claim_token = ? AND status = 'sending' ORDER BY id", [$token]) as $row) {
            // مدار وسط کار باز شد: بقیه را بی‌آنکه تلاشی شمرده شود به صف برگردان
            if (SmsBreaker::isOpen()) {
                DB::statement(
                    "UPDATE sms_messages SET status = 'queued', claim_token = NULL, attempts = GREATEST(attempts - 1, 0) WHERE id = ?",
                    [$row['id']]
                );
                continue;
            }

            // پیامک غیرضروری که تلاش دوباره‌اش به ساعت سکوت افتاده، فرستاده نمی‌شود
            if ((int) $row['is_critical'] === 0 && SmsNotifier::inQuietHours()) {
                DB::update('sms_messages', ['status' => 'skipped_quiet_hours', 'claim_token' => null], 'id = :id', ['id' => $row['id']]);
                continue;
            }

            $args = json_decode((string) ($row['args_json'] ?? '[]'), true);
            $result = SmsBreaker::send(static fn (): array => SmsManager::sendPattern(
                (string) $row['to_phone'],
                (string) $row['template_code'],
                is_array($args) ? $args : [],
                SmsNotifier::plainTextAllowed() ? (string) $row['body'] : ''
            ));

            if ($result['ok']) {
                DB::update('sms_messages', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                    'provider' => $result['provider'] ?? $row['provider'],
                    'provider_ref' => $result['ref'] ?? null,
                    'error_message' => null,
                    'next_attempt_at' => null,
                    'claim_token' => null,
                ], 'id = :id', ['id' => $row['id']]);
                // نسبی، تا دو فرستادنِ هم‌زمان اعتبار را درست کم کنند
                DB::statement('UPDATE salons SET sms_credit = sms_credit - 1 WHERE id = ?', [$row['salon_id']]);
                $sent++;
                continue;
            }

            $attempts = (int) $row['attempts'];
            $retryIn = $attempts < self::MAX_ATTEMPTS ? (self::BACKOFF[$attempts] ?? null) : null;
            DB::update('sms_messages', [
                'status' => 'failed',
                'provider' => $result['provider'] ?? $row['provider'],
                'error_message' => mb_substr((string) ($result['error'] ?? 'خطای نامشخص'), 0, 255),
                'next_attempt_at' => $retryIn !== null ? date('Y-m-d H:i:s', time() + $retryIn) : null,
                'claim_token' => null,
            ], 'id = :id', ['id' => $row['id']]);
        }

        return $sent;
    }
}
