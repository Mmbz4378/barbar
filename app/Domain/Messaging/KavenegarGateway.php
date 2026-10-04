<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * ارائه‌دهندهٔ پشتیبان.
 *
 * پیامک تنها نقطه‌ای است که اگر بخوابد کل محصول می‌خوابد: کد ورود
 * نمی‌رسد، یادآور نمی‌رسد. پس اعلان صفی که روی ملی‌پیامک شکست بخورد
 * باید خودکار از این راه برود — ‏SmsManager همین کار را می‌کند.
 *
 * معادل «ارسال با الگو»ی ملی‌پیامک اینجا `verify/lookup` است، که تنها
 * مسیری هم هست که کاوه‌نگار برای کد یک‌بارمصرف تضمین می‌کند.
 */
final class KavenegarGateway implements SmsGatewayInterface
{
    public function __construct(private readonly string $apiKey)
    {
    }

    public function send(string $e164Phone, string $message): array
    {
        return $this->call('sms/send.json', [
            'receptor' => $this->localNumber($e164Phone),
            'message' => $message,
        ]);
    }

    public function sendPattern(string $e164Phone, string $patternId, array $args): array
    {
        if ($patternId === '') {
            return ['ok' => false, 'ref' => null, 'error' => 'نام الگوی کاوه‌نگار تنظیم نشده است.'];
        }

        $params = [
            'receptor' => $this->localNumber($e164Phone),
            'template' => $patternId,
        ];

        // کاوه‌نگار اسمشان را token، token2، token3 گذاشته — و فاصلهٔ داخل یک توکن را قبول نمی‌کند.
        foreach (array_values($args) as $index => $value) {
            $key = $index === 0 ? 'token' : 'token' . ($index + 1);
            $params[$key] = str_replace([' ', "\r", "\n"], '_', (string) $value);
        }

        return $this->call('verify/lookup.json', $params);
    }

    public function name(): string
    {
        return 'kavenegar';
    }

    private function call(string $path, array $params): array
    {
        if ($this->apiKey === '') {
            return ['ok' => false, 'ref' => null, 'error' => 'کلید API کاوه‌نگار تنظیم نشده است.'];
        }

        $url = sprintf('https://api.kavenegar.com/v1/%s/%s', $this->apiKey, $path);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'ref' => null, 'error' => $error !== '' ? $error : 'ارتباط برقرار نشد.'];
        }

        $decoded = json_decode((string) $raw, true);
        $status = (int) ($decoded['return']['status'] ?? 0);

        if ($status !== 200) {
            return ['ok' => false, 'ref' => null, 'error' => $decoded['return']['message'] ?? 'خطای نامشخص کاوه‌نگار'];
        }

        $messageId = $decoded['entries'][0]['messageid'] ?? null;

        return ['ok' => true, 'ref' => $messageId !== null ? (string) $messageId : null, 'error' => null];
    }

    private function localNumber(string $e164Phone): string
    {
        return '0' . substr($e164Phone, 3);
    }
}
