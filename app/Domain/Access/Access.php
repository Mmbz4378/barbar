<?php

declare(strict_types=1);

namespace App\Domain\Access;

use App\Core\Auth;

/**
 * تنها جایی که می‌گوید چه کسی چه کاری می‌تواند بکند.
 *
 * سه سطح دسترسی داریم:
 *
 *   ۱. مدیر کل پلتفرم — بیرون از سالن‌ها می‌ایستد و همه را می‌بیند.
 *      با is_platform_admin مشخص می‌شود، نه با نقش داخل سالن.
 *   ۲. آرایشگاه — هر کسی که در salon_user عضو یک سالن است. خودش سه
 *      درجه دارد: صاحب/مدیر (همه‌چیز)، پذیرش (جلوی پیشخوان)، و
 *      آرایشگر (فقط کار خودش).
 *   ۳. مشتری — اصلاً وارد این پنل نمی‌شود؛ حساب جداگانه دارد.
 *
 * چرا یک کلاس و نه if توی کنترلرها: پیش از این، «آرایشگر» به فهرست
 * مشتری‌ها و شمارهٔ تلفنشان، به تسویهٔ هر نوبت، و به کامل/لغو کردن
 * نوبتِ آرایشگرهای دیگر دسترسی داشت — چون هیچ‌جا گفته نشده بود که
 * نباید. فهرست اجازه‌ها وقتی در یک فایل باشد، جای خالی‌اش دیده می‌شود.
 */
final class Access
{
    /** تنظیمات سالن، آرایشگرها، خدمات، گزارش‌ها، الگوی پیامک. */
    public const MANAGE_SALON = 'manage_salon';

    /** پروندهٔ مشتریان و شمارهٔ تماسشان. */
    public const VIEW_CUSTOMERS = 'view_customers';

    /** ثبت تسویه. */
    public const TAKE_PAYMENT = 'take_payment';

    /** ثبت نوبت به نام دیگران از داخل پنل. */
    public const BOOK_FOR_OTHERS = 'book_for_others';

    /** درآمد کل سالن، نه فقط سهم خودِ آرایشگر. */
    public const VIEW_SALON_EARNINGS = 'view_salon_earnings';

    /**
     * نقش‌هایی که هر اجازه را دارند.
     *
     * پذیرش عمداً به تنظیمات و گزارش دسترسی ندارد: کارش نوبت دادن و
     * تسویه است، نه تغییر ساعت کاری یا دیدن درآمد.
     *
     * @var array<string,string[]>
     */
    private const MATRIX = [
        self::MANAGE_SALON => ['owner', 'manager'],
        self::VIEW_CUSTOMERS => ['owner', 'manager', 'reception'],
        self::TAKE_PAYMENT => ['owner', 'manager', 'reception'],
        self::BOOK_FOR_OTHERS => ['owner', 'manager', 'reception'],
        self::VIEW_SALON_EARNINGS => ['owner', 'manager'],
    ];

    public static function allows(string $ability): bool
    {
        $roles = self::MATRIX[$ability] ?? null;

        /*
         * اجازهٔ ناشناخته یعنی غلط تایپی در نام ثابت. در آن حالت «نه»
         * می‌گوییم، نه «بله» — وگرنه یک اشتباه املایی، در را باز می‌کرد.
         */
        if ($roles === null) {
            return false;
        }

        return Auth::hasRole(...$roles);
    }

    /**
     * آیا این کاربر می‌تواند روی این نوبت کار کند (شروع، تمام، لغو، غیبت)؟
     *
     * آرایشگر فقط نوبت‌های خودش را؛ بقیه هر نوبتی از سالن را.
     */
    public static function canActOnAppointment(array $appointment): bool
    {
        if (self::allows(self::BOOK_FOR_OTHERS)) {
            return true;
        }

        $staffId = Auth::staffId();

        return $staffId !== null && (int) ($appointment['staff_id'] ?? 0) === $staffId;
    }
}
