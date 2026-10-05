<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Core\Config;

final class SmsManager
{
    private static ?SmsGatewayInterface $primary = null;

    private static ?SmsGatewayInterface $fallback = null;

    /**
     * با ارائه‌دهندهٔ اصلی می‌فرستد؛ اگر نشد و ارائه‌دهندهٔ پشتیبان
     * تنظیم شده باشد، یک بار از آن راه دوباره امتحان می‌کند.
     *
     * برمی‌گرداند که در عمل کدام‌یک پیامک را رساند — بدون این، وقتی
     * مشتری می‌گوید «نیامد» هیچ راهی برای دنبال کردنش نیست.
     *
     * @return array{ok:bool,provider:string,ref:?string,error:?string}
     */
    public static function send(string $e164Phone, string $message): array
    {
        return self::attempt(static fn (SmsGatewayInterface $gateway) => $gateway->send($e164Phone, $message));
    }

    /**
     * با الگوی تأییدشده می‌فرستد، و اگر برای ارائه‌دهنده‌ای که واقعاً
     * دارد می‌فرستد الگویی تنظیم نشده باشد، به متن آزاد برمی‌گردد.
     *
     * `$patternKey` برای هر ارائه‌دهنده جدا ترجمه می‌شود: یک پیام واحد
     * در ملی‌پیامک یک شمارهٔ bodyId است و در کاوه‌نگار یک نام الگو. پس
     * پشتیبان هرگز نباید شناسهٔ اصلی را دوباره استفاده کند — پیامک
     * بی‌صدا رد می‌شود.
     *
     * @param string[] $args
     */
    public static function sendPattern(string $e164Phone, string $patternKey, array $args, string $fallbackText = ''): array
    {
        return self::attempt(static function (SmsGatewayInterface $gateway) use ($e164Phone, $patternKey, $args, $fallbackText) {
            $patternId = (string) Config::get('reshen.sms.patterns.' . $gateway->name() . '.' . $patternKey, '');

            if ($patternId === '') {
                if ($fallbackText === '') {
                    return ['ok' => false, 'ref' => null, 'error' => "الگوی «{$patternKey}» برای {$gateway->name()} تنظیم نشده است."];
                }

                return $gateway->send($e164Phone, $fallbackText);
            }

            return $gateway->sendPattern($e164Phone, $patternId, $args);
        });
    }

    /** پس از تغییر تنظیمات پیامک در همین درخواست (ارسال آزمایشی). */
    public static function reset(): void
    {
        self::$primary = null;
        self::$fallback = null;
    }

    /**
     * درایور را جایگزین می‌کند — فقط برای آزمون (اپراتورِ کند یا از کارافتاده را
     * شبیه‌سازی می‌کند). null یعنی برگشت به پیکربندی.
     */
    public static function fake(?SmsGatewayInterface $gateway): void
    {
        self::$primary = $gateway;
        self::$fallback = null;
    }

    /** @param callable(SmsGatewayInterface):array $send */
    private static function attempt(callable $send): array
    {
        $primary = self::primary();
        $result = $send($primary);

        if ($result['ok']) {
            return ['ok' => true, 'provider' => $primary->name(), 'ref' => $result['ref'], 'error' => null];
        }

        $fallback = self::fallback();
        if ($fallback !== null) {
            $fallbackResult = $send($fallback);
            if ($fallbackResult['ok']) {
                return ['ok' => true, 'provider' => $fallback->name(), 'ref' => $fallbackResult['ref'], 'error' => null];
            }

            return ['ok' => false, 'provider' => $fallback->name(), 'ref' => null, 'error' => $fallbackResult['error']];
        }

        return ['ok' => false, 'provider' => $primary->name(), 'ref' => null, 'error' => $result['error']];
    }

    private static function primary(): SmsGatewayInterface
    {
        if (self::$primary === null) {
            self::$primary = self::make((string) Config::get('reshen.sms.driver', 'log'));
        }

        return self::$primary;
    }

    private static function fallback(): ?SmsGatewayInterface
    {
        $driver = Config::get('reshen.sms.driver', 'log');
        if ($driver !== 'melipayamak') {
            return null; // فقط ارائه‌دهندهٔ واقعی به پشتیبان واقعی نیاز دارد
        }
        if (self::$fallback === null) {
            self::$fallback = self::make('kavenegar');
        }

        return self::$fallback;
    }

    private static function make(string $driver): SmsGatewayInterface
    {
        return match ($driver) {
            'melipayamak' => new MelipayamakGateway(
                (string) Config::get('reshen.sms.credentials.melipayamak.username', ''),
                (string) Config::get('reshen.sms.credentials.melipayamak.password', ''),
                (string) Config::get('reshen.sms.credentials.melipayamak.sender', ''),
            ),
            'kavenegar' => new KavenegarGateway((string) Config::get('reshen.sms.credentials.kavenegar.api_key', '')),
            default => new LogSmsGateway(),
        };
    }
}
