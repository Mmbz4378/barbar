<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * درایور توسعه — به‌جای اپراتور واقعی در storage/logs/sms.log می‌نویسد.
 *
 * برای چه: کل مسیر کد ورود و یادآورها را می‌شود بدون حساب ملی‌پیامک یا
 * کاوه‌نگار آزمایش کرد. پیش‌فرض همین است، پس نصب تازه به‌اشتباه برای
 * کسی پیامک نمی‌فرستد.
 */
final class LogSmsGateway implements SmsGatewayInterface
{
    public function send(string $e164Phone, string $message): array
    {
        return $this->write($e164Phone, $message);
    }

    public function sendPattern(string $e164Phone, string $patternId, array $args): array
    {
        return $this->write($e164Phone, sprintf('[الگو %s] %s', $patternId, implode(' | ', $args)));
    }

    private function write(string $e164Phone, string $message): array
    {
        // شبیه‌سازی اپراتورِ کند برای آزمون بار (پیش‌فرض صفر)
        $delay = (int) \App\Core\Config::get('reshen.sms.log_delay_ms', 0);
        if ($delay > 0) {
            usleep(min($delay, 30000) * 1000);
        }
        $line = sprintf("[%s] -> %s: %s\n", date('Y-m-d H:i:s'), $e164Phone, $message);
        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        if (file_put_contents($logDir . '/sms.log', $line, FILE_APPEND | LOCK_EX) === false) return ['ok'=>false,'ref'=>null,'error'=>'ثبت پیامک آزمایشی ناموفق بود.'];

        return ['ok' => true, 'ref' => 'log-' . bin2hex(random_bytes(4)), 'error' => null];
    }

    public function name(): string
    {
        return 'log';
    }
}
