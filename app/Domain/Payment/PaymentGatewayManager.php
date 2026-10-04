<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Core\Config;

/**
 * انتخاب درگاه بر اساس تنظیمات.
 *
 * پیش‌فرض `disabled` است و این عمدی است: نصبی که تنظیمش نکرده‌اند
 * نباید ناگهان پول بگیرد.
 */
final class PaymentGatewayManager
{
    private static ?PaymentGatewayInterface $instance = null;

    public static function gateway(): PaymentGatewayInterface
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = (string) Config::get('reshen.payment.driver', 'disabled');

        self::$instance = match ($driver) {
            'zarinpal' => new ZarinPalGateway(
                (string) Config::get('reshen.payment.zarinpal.merchant_id', ''),
                (bool) Config::get('reshen.payment.zarinpal.sandbox', false),
            ),
            default => new DisabledGateway(),
        };

        return self::$instance;
    }

    public static function isEnabled(): bool
    {
        return self::gateway()->isEnabled();
    }

    /** برای تست — کش را خالی می‌کند. */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
