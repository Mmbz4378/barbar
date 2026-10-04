<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Support\Money;

/**
 * زرین‌پال — نسخهٔ ۴ API.
 *
 * ساخته شده ولی پیش‌فرض خاموش است (تصمیم ت-۳۱): پلتفرم فعلاً رایگان
 * است. روشن کردنش یعنی `PAYMENT_DRIVER=zarinpal` و یک merchant_id.
 *
 * دو نکته که در پیاده‌سازی‌های شتاب‌زده جا می‌مانند:
 *
 * ۱. **مبلغ به ریال می‌رود.** زرین‌پال از نسخهٔ ۴ ریال می‌گیرد. اگر
 *    تومان بفرستی، پرداخت با یک‌دهم مبلغ انجام می‌شود و کسی تا آخر ماه
 *    متوجه نمی‌شود.
 *
 * ۲. **کد ۱۰۱ یعنی «قبلاً تأیید شده»، نه خطا.** اگر مثل خطا با آن
 *    رفتار کنی، مشتری‌ای که دکمهٔ رفرش را زده، پولش رفته ولی نوبتش ثبت
 *    نشده. هر دو کد ۱۰۰ و ۱۰۱ یعنی پرداخت موفق.
 */
final class ZarinPalGateway implements PaymentGatewayInterface
{
    private const LIVE = 'https://payment.zarinpal.com/pg/';
    private const SANDBOX = 'https://sandbox.zarinpal.com/pg/';

    public function __construct(
        private readonly string $merchantId,
        private readonly bool $sandbox = false,
    ) {
    }

    public function request(Money $amount, string $callbackUrl, array $meta = []): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'redirectUrl' => null, 'reference' => null,
                    'error' => 'شناسهٔ پذیرندهٔ زرین‌پال تنظیم نشده است.'];
        }

        $payload = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount->rials,
            'callback_url' => $callbackUrl,
            'description' => $meta['description'] ?? 'پرداخت نوبت',
        ];

        // موبایل و ایمیل اختیاری‌اند ولی نرخ موفقیت را بالا می‌برند
        // چون درگاه، فرم را از پیش پر می‌کند.
        $metadata = array_filter([
            'mobile' => $meta['mobile'] ?? null,
            'email' => $meta['email'] ?? null,
        ]);
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->post('v4/payment/request.json', $payload);
        if (!$response['ok']) {
            return ['ok' => false, 'redirectUrl' => null, 'reference' => null, 'error' => $response['error']];
        }

        $code = (int) ($response['data']['data']['code'] ?? 0);
        $authority = (string) ($response['data']['data']['authority'] ?? '');

        if ($code !== 100 || $authority === '') {
            return ['ok' => false, 'redirectUrl' => null, 'reference' => null,
                    'error' => $this->errorText($response['data'])];
        }

        return [
            'ok' => true,
            'redirectUrl' => $this->base() . 'StartPay/' . $authority,
            'reference' => $authority,
            'error' => null,
        ];
    }

    public function verify(string $reference, Money $amount): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'paid' => false, 'refId' => null, 'cardPan' => null,
                    'error' => 'شناسهٔ پذیرندهٔ زرین‌پال تنظیم نشده است.'];
        }

        $response = $this->post('v4/payment/verify.json', [
            'merchant_id' => $this->merchantId,
            'amount' => $amount->rials,
            'authority' => $reference,
        ]);

        if (!$response['ok']) {
            return ['ok' => false, 'paid' => false, 'refId' => null, 'cardPan' => null, 'error' => $response['error']];
        }

        $code = (int) ($response['data']['data']['code'] ?? 0);

        // ۱۰۰ تأیید تازه، ۱۰۱ قبلاً تأیید شده — هر دو یعنی پول رسیده.
        $paid = $code === 100 || $code === 101;

        return [
            'ok' => true,
            'paid' => $paid,
            'refId' => $paid ? (string) ($response['data']['data']['ref_id'] ?? '') : null,
            'cardPan' => $response['data']['data']['card_pan'] ?? null,
            'error' => $paid ? null : $this->errorText($response['data']),
        ];
    }

    public function name(): string
    {
        return 'zarinpal';
    }

    public function isEnabled(): bool
    {
        return $this->merchantId !== '';
    }

    private function base(): string
    {
        return $this->sandbox ? self::SANDBOX : self::LIVE;
    }

    /** @return array{ok:bool,data:array,error:?string} */
    private function post(string $path, array $payload): array
    {
        $ch = curl_init($this->base() . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'data' => [], 'error' => $curlError !== '' ? $curlError : 'ارتباط با زرین‌پال برقرار نشد.'];
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'data' => [], 'error' => 'پاسخ زرین‌پال قابل خواندن نبود.'];
        }

        return ['ok' => true, 'data' => $decoded, 'error' => null];
    }

    /**
     * پیام خطا از پاسخ زرین‌پال.
     *
     * فیلد errors گاهی آرایه است و گاهی شیء — به هر دو شکل می‌آید و
     * اگر فقط یکی را در نظر بگیری، پیام خطا «خطای نامشخص» می‌شود
     * دقیقاً وقتی که بیشترین نیاز را به جزئیات داری.
     */
    private function errorText(array $response): string
    {
        $errors = $response['errors'] ?? null;

        if (is_array($errors)) {
            if (isset($errors['message'])) {
                return (string) $errors['message'];
            }
            if (isset($errors[0]['message'])) {
                return (string) $errors[0]['message'];
            }
        }

        $code = $response['data']['code'] ?? null;

        return $code !== null ? "زرین‌پال کد {$code} برگرداند." : 'خطای نامشخص زرین‌پال.';
    }
}
