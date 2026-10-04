<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Support\Money;

/**
 * درگاه پرداخت (سند معماری، بخش ۲).
 *
 * دو مرحله دارد و هر دو لازم‌اند:
 *   request() کاربر را به درگاه می‌فرستد و یک «مرجع» می‌گیرد
 *   verify()  پس از بازگشت، **سمت سرور** تأیید می‌کند
 *
 * تأیید هرگز نباید بر اساس پارامترهای بازگشتی مرورگر انجام شود: کاربر
 * می‌تواند آدرس بازگشت را دستی بزند و وانمود کند پرداخت موفق بوده.
 */
interface PaymentGatewayInterface
{
    /**
     * شروع پرداخت. آدرسی برمی‌گرداند که باید کاربر را به آن فرستاد.
     *
     * @param array<string,string> $meta موبایل، ایمیل، توضیح
     * @return array{ok:bool,redirectUrl:?string,reference:?string,error:?string}
     */
    public function request(Money $amount, string $callbackUrl, array $meta = []): array;

    /**
     * تأیید سمت سرور.
     *
     * $amount باید همان مبلغی باشد که در request رفته. عدم تطابق یعنی
     * دست‌کاری و باید رد شود، نه اینکه «نزدیک بود، قبول».
     *
     * @return array{ok:bool,paid:bool,refId:?string,cardPan:?string,error:?string}
     */
    public function verify(string $reference, Money $amount): array;

    public function name(): string;

    /** آیا اصلاً قابل استفاده است؟ (کلید تنظیم شده و درگاه روشن است) */
    public function isEnabled(): bool;
}
