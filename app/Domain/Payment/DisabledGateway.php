<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Support\Money;

/**
 * درگاه خاموش — پیش‌فرض.
 *
 * چرا وجود دارد و چرا پیش‌فرض است: پلتفرم فعلاً رایگان است و هیچ
 * پرداخت آنلاینی در کار نیست (تصمیم ت-۳۱). ولی کد درگاه باید ساخته و
 * آزموده باشد تا روشن کردنش یک تغییر تنظیمات باشد نه یک پروژه.
 *
 * به‌جای «درگاهی تنظیم نشده» که شبیه باگ است، پیام روشن می‌دهد. و
 * مهم‌تر: هرگز `paid => true` برنمی‌گرداند، پس اگر کسی اشتباهی کدِ
 * پرداخت را صدا بزند، چیزی «پرداخت‌شده» علامت نمی‌خورد.
 */
final class DisabledGateway implements PaymentGatewayInterface
{
    private const MESSAGE = 'پرداخت آنلاین روی این نصب فعال نیست.';

    public function request(Money $amount, string $callbackUrl, array $meta = []): array
    {
        return ['ok' => false, 'redirectUrl' => null, 'reference' => null, 'error' => self::MESSAGE];
    }

    public function verify(string $reference, Money $amount): array
    {
        return ['ok' => false, 'paid' => false, 'refId' => null, 'cardPan' => null, 'error' => self::MESSAGE];
    }

    public function name(): string
    {
        return 'disabled';
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
