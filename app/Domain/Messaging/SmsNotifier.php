<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Config;
use App\Core\DB;
use DateTimeImmutable;

/**
 * قاعده‌های ضدِ مزاحمت (سند ۸.۶) دور SmsManager: حداکثر ۴ پیامک برای هر
 * نوبت، هیچ چیز بین ۲۳ تا ۸ صبح جز «صندلی آماده است»، و «عقب افتادیم»
 * فقط یک بار — دومی‌اش بدتر از سکوت است.
 *
 * **همه‌چیز از راه الگو می‌رود، نه متن آزاد.** روی خط خدماتی، پیامکِ
 * متنِ آزاد تحویل داده نمی‌شود ولی در پنل «ارسال شد» می‌خورد. متن
 * جایگزین فقط وقتی استفاده می‌شود که سالن خط اختصاصی دارد
 * (`SMS_DEDICATED_LINE=true`) یا در حالت توسعه با درایور log.
 */
final class SmsNotifier
{
    /**
     * @param array<string,string|int> $vars متغیرهای الگو، با نام
     * @param bool|null $critical اگر null باشد از خود الگو خوانده می‌شود
     */
    public function notify(int $salonId, array $appointment, string $templateCode, array $vars, ?bool $critical = null): bool
    {
        if (!SmsTemplates::exists($templateCode)) {
            return false;
        }

        $critical ??= SmsTemplates::isCritical($templateCode);
        // متن رندرشده هم لازم است: هم برای خط اختصاصی، هم برای اینکه در
        // جدول پیامک‌ها بماند و بعداً بشود فهمید چه چیزی برای مشتری رفت.
        $body = SmsTemplates::render($templateCode, $vars);

        $toPhone = DB::selectOne('SELECT phone FROM customers WHERE id = ?', [$appointment['customer_id']])['phone'] ?? null;
        if ($toPhone === null) {
            return false;
        }

        $maxPerAppointment = (int) Config::get('reshen.sms.max_per_appointment', 4);
        $sentCount = (int) (DB::selectOne(
            "SELECT COUNT(*) AS c FROM sms_messages WHERE appointment_id = ? AND status = 'sent'",
            [$appointment['id']]
        )['c'] ?? 0);

        if ($sentCount >= $maxPerAppointment) {
            $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_rate_limit');

            return false;
        }

        if (!$critical && $this->inQuietHours()) {
            $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_quiet_hours');

            return false;
        }

        if ($templateCode === 'queue_delayed') {
            $already = DB::selectOne(
                "SELECT id FROM sms_messages WHERE appointment_id = ? AND template_code = 'queue_delayed' AND status = 'sent'",
                [$appointment['id']]
            );
            if ($already !== null) {
                $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_rate_limit');

                return false;
            }
        }

        /*
         * کیف پیامک فعلاً اعمال نمی‌شود چون پلتفرم رایگان است (تصمیم
         * ت-۲۶). ستون‌ها و شمارش سر جایشان مانده‌اند تا وقتی شارژ
         * برگشت، فقط این پرچم روشن شود — نه اینکه منطق از نو نوشته شود.
         */
        $salon = DB::selectOne('SELECT sms_credit FROM salons WHERE id = ?', [$salonId]);

        if (Config::get('reshen.sms.enforce_credit', false)) {
            if (!$critical && (int) ($salon['sms_credit'] ?? 0) <= 0) {
                $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_no_credit');

                return false;
            }
            $emergencyFloor = -1 * (int) Config::get('reshen.sms.emergency_credit', 100);
            if ($critical && (int) ($salon['sms_credit'] ?? 0) <= $emergencyFloor) {
                $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, 'skipped_no_credit');

                return false;
            }
        }

        $result = SmsManager::sendPattern(
            $toPhone,
            $templateCode,
            SmsTemplates::orderedArgs($templateCode, $vars),
            self::plainTextAllowed() ? $body : ''
        );
        $this->log($salonId, $appointment, $toPhone, $templateCode, $body, $critical, $result['ok'] ? 'sent' : 'failed', $result);

        if ($result['ok']) {
            DB::update('salons', ['sms_credit' => (int) ($salon['sms_credit'] ?? 0) - 1], 'id = :id', ['id' => $salonId]);
        }

        return $result['ok'];
    }

    public function alreadySent(int $appointmentId, string $templateCode): bool
    {
        return DB::selectOne(
            "SELECT id FROM sms_messages WHERE appointment_id = ? AND template_code = ? AND status = 'sent'",
            [$appointmentId, $templateCode]
        ) !== null;
    }

    /**
     * آیا متنِ آزاد مجاز است؟
     *
     * دو حالت، و هر دو واقعی‌اند:
     *
     *   خط اختصاصی — اپراتور متنِ آزاد را تحویل می‌دهد. خط خدماتی
     *   (۳۰۰۰، ۲۰۰۰، ۹۸۲۱) نمی‌دهد. پیش‌فرض false است یعنی سخت‌گیرانه؛
     *   اگر اشتباه true باشد، پیامک‌ها بی‌صدا به مقصد نمی‌رسند.
     *
     *   درایور log — حالت توسعه. اینجا اصلاً پیامکی در کار نیست و
     *   الگو معنا ندارد؛ بدون این، کل جریان رزرو در محیط توسعه با
     *   «الگو تنظیم نشده» می‌خورد زمین و آزمودنش ممکن نیست.
     */
    private static function plainTextAllowed(): bool
    {
        if (Config::get('reshen.sms.driver', 'log') === 'log') {
            return true;
        }

        return (bool) Config::get('reshen.sms.dedicated_line', false);
    }

    private function inQuietHours(): bool
    {
        $hour = (int) date('G');
        $start = (int) Config::get('reshen.sms.quiet_hours_start', 23);
        $end = (int) Config::get('reshen.sms.quiet_hours_end', 8);

        return $hour >= $start || $hour < $end;
    }

    private function log(int $salonId, array $appointment, string $phone, string $template, string $body, bool $critical, string $status, ?array $result = null): void
    {
        DB::insert('sms_messages', [
            'salon_id' => $salonId,
            'appointment_id' => $appointment['id'],
            'to_phone' => $phone,
            'template_code' => $template,
            'body' => $body,
            'is_critical' => $critical ? 1 : 0,
            'provider' => $result['provider'] ?? Config::get('reshen.sms.driver', 'log'),
            'provider_ref' => $result['ref'] ?? null,
            'status' => $status,
            'error_message' => $result['error'] ?? null,
            'sent_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ]);
    }
}
