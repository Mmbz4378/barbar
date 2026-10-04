<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * الگوهای پیامک — فهرست مرجع.
 *
 * چرا این فایل وجود دارد: روی **خط خدماتی** (خطوطی که با ۳۰۰۰ و ۲۰۰۰ و
 * ۹۸۲۱ شروع می‌شوند و همه‌شان اشتراکی‌اند)، اپراتور پیامکِ متنِ آزاد را
 * تحویل نمی‌دهد. سامانهٔ «الگوی ملی پیامک» می‌گوید متن باید از پیش ثبت و
 * تأیید شده باشد و فقط متغیرهایش موقع ارسال پر شود.
 *
 * نتیجهٔ نادیده گرفتنش این است: پیامک‌ها در پنل «ارسال شد» می‌خورند،
 * پول هم کم می‌شود، و هیچ‌کدام به دست مشتری نمی‌رسد. این بدترین نوع
 * خرابی است چون بی‌صداست.
 *
 * پس هر پیامکی که این برنامه می‌فرستد، اینجا یک ردیف دارد با:
 *   - متنِ دقیقی که باید در پنل اپراتور ثبت شود
 *   - ترتیب متغیرها (ترتیب مهم است؛ اپراتور با شماره می‌شناسدشان)
 *   - متن جایگزین، فقط برای وقتی که سالن خط اختصاصی دارد یا در حالت
 *     توسعه با درایور log کار می‌کنیم
 *
 * صفحهٔ `/panel/sms` همین فهرست را نشان می‌دهد تا صاحب سالن بتواند
 * متن‌ها را کپی کند و در پنل اپراتور ثبت کند.
 */
final class SmsTemplates
{
    /**
     * @var array<string,array{title:string,vars:string[],pattern:string,critical:bool,note:string}>
     */
    private const TEMPLATES = [
        'otp' => [
            'title' => 'کد ورود',
            'vars' => ['code'],
            'pattern' => "کد ورود شما: %code%\nاین کد را در اختیار کسی قرار ندهید.",
            'critical' => true,
            'note' => 'بدون این الگو، هیچ‌کس نمی‌تواند وارد شود. اولین الگویی است که باید ثبت شود.',
        ],
        'booking_confirmed' => [
            'title' => 'تأیید رزرو',
            'vars' => ['name', 'salon', 'date', 'time'],
            'pattern' => "%name% عزیز، نوبت شما در %salon% ثبت شد.\nتاریخ: %date%\nساعت: %time%",
            'critical' => true,
            'note' => 'بلافاصله پس از ثبت نوبت آنلاین فرستاده می‌شود.',
        ],
        'booking_cancelled' => [
            'title' => 'لغو نوبت',
            'vars' => ['name', 'salon', 'date', 'time'],
            'pattern' => "%name% عزیز، نوبت شما در %salon% برای %date% ساعت %time% لغو شد.",
            'critical' => true,
            'note' => 'وقتی سالن نوبتی را لغو می‌کند.',
        ],
        'reminder_24h' => [
            'title' => 'یادآوری یک روز قبل',
            'vars' => ['name', 'salon', 'time'],
            'pattern' => "%name% عزیز، یادآوری نوبت فردا در %salon% ساعت %time%.",
            'critical' => false,
            'note' => 'با کرون فرستاده می‌شود.',
        ],
        'reminder_2h' => [
            'title' => 'یادآوری دو ساعت قبل',
            'vars' => ['name', 'salon', 'time'],
            'pattern' => "%name% عزیز، نوبت شما در %salon% ساعت %time% است.",
            'critical' => false,
            'note' => 'با کرون فرستاده می‌شود.',
        ],
        'queue_chair_ready' => [
            'title' => 'نوبتت رسید',
            'vars' => ['name', 'staff'],
            'pattern' => "%name% عزیز، نوبت بعدی شماست. لطفاً به سمت %staff% بیایید.",
            'critical' => true,
            'note' => 'مهم‌ترین پیامک محصول. حتی در ساعات سکوت هم فرستاده می‌شود.',
        ],
        'queue_nearly_up' => [
            'title' => 'نوبتت نزدیک است',
            'vars' => ['name', 'ahead', 'minutes'],
            'pattern' => "%name% عزیز، %ahead% نفر جلوتر از شما هستند؛ حدود %minutes% دقیقهٔ دیگر نوبت شماست.",
            'critical' => false,
            'note' => 'وقتی تخمین زمان وارد بازهٔ «نزدیک» می‌شود.',
        ],
        'queue_delayed' => [
            'title' => 'عقب افتادیم',
            'vars' => ['name', 'time'],
            'pattern' => "%name% عزیز، امروز شلوغ شد و کمی عقبیم. نوبت شما حدود %time% خواهد بود.",
            'critical' => false,
            'note' => 'حداکثر یک بار برای هر نوبت. دومی بدتر از سکوت است.',
        ],
    ];

    /** @return array<string,array{title:string,vars:string[],pattern:string,critical:bool,note:string}> */
    public static function all(): array
    {
        return self::TEMPLATES;
    }

    public static function exists(string $code): bool
    {
        return isset(self::TEMPLATES[$code]);
    }

    /** @return array{title:string,vars:string[],pattern:string,critical:bool,note:string}|null */
    public static function get(string $code): ?array
    {
        return self::TEMPLATES[$code] ?? null;
    }

    public static function isCritical(string $code): bool
    {
        return (bool) (self::TEMPLATES[$code]['critical'] ?? false);
    }

    /**
     * متغیرها به ترتیبی که اپراتور انتظار دارد.
     *
     * اپراتور متغیرها را با **شماره** می‌شناسد نه با نام (token، token2،
     * token3 در کاوه‌نگار؛ text0، text1 در ملی‌پیامک). پس اگر ترتیب اینجا
     * با ترتیبِ ثبت‌شده در پنل یکی نباشد، پیامک می‌رود ولی جای اسم،
     * ساعت می‌نشیند — و کسی متوجه نمی‌شود مگر مشتری زنگ بزند.
     *
     * @param array<string,string|int> $values
     * @return string[]
     */
    public static function orderedArgs(string $code, array $values): array
    {
        $template = self::get($code);
        if ($template === null) {
            return [];
        }

        $out = [];
        foreach ($template['vars'] as $var) {
            $out[] = (string) ($values[$var] ?? '');
        }

        return $out;
    }

    /**
     * همان متن، ولی با شکلِ متغیرهایی که *اپراتور* می‌شناسد.
     *
     * این جایی است که یک اشتباه بی‌صدا نشسته بود: صفحهٔ پیامک متنِ
     * داخلی رشن را نشان می‌داد («‎%name% عزیز…‎») و می‌گفت همین را در
     * پنل اپراتور ثبت کن. ولی `%name%` برای هیچ‌کدام از دو اپراتور
     * معنایی ندارد — ملی‌پیامک متغیرها را با `{0}` و `{1}` می‌شناسد و
     * کاوه‌نگار با `%token%` و `%token2%`.
     *
     * نتیجه‌اش این بود: الگو ثبت می‌شد ولی *بدون هیچ متغیری*، و ارسال
     * با کد ‎-5‎ («متغیرها با الگو نمی‌خواند») برمی‌گشت. صاحب سالن هم
     * دقیقاً همان متنی را ثبت کرده بود که برنامه گفته بود.
     *
     * ترتیب متغیرها همان `vars` است، چون ارسال هم به همان ترتیب
     * مقادیر را می‌فرستد.
     */
    public static function providerPattern(string $code, string $provider): string
    {
        $template = self::get($code);
        if ($template === null) {
            return '';
        }

        $text = $template['pattern'];

        foreach ($template['vars'] as $i => $var) {
            $text = str_replace('%' . $var . '%', self::placeholder($provider, $i), $text);
        }

        return $text;
    }

    /** شکل متغیرِ شمارهٔ $index نزد یک اپراتور. */
    public static function placeholder(string $provider, int $index): string
    {
        return match ($provider) {
            'kavenegar' => $index === 0 ? '%token%' : '%token' . ($index + 1) . '%',
            default => '{' . $index . '}',
        };
    }

    /**
     * متن نهایی با مقادیر جاگذاری‌شده.
     *
     * دو کاربرد دارد و هر دو واقعی‌اند: متن جایگزین برای خط اختصاصی، و
     * چیزی که در جدول `sms_messages` ذخیره می‌شود تا بعداً بشود فهمید
     * دقیقاً چه چیزی برای مشتری رفته.
     *
     * @param array<string,string|int> $values
     */
    public static function render(string $code, array $values): string
    {
        $template = self::get($code);
        if ($template === null) {
            return '';
        }

        $text = $template['pattern'];
        foreach ($template['vars'] as $var) {
            $text = str_replace('%' . $var . '%', (string) ($values[$var] ?? ''), $text);
        }

        return $text;
    }
}
