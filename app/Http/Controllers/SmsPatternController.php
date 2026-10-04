<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Messaging\MelipayamakGateway;
use App\Domain\Messaging\SmsPatternRepository;
use App\Domain\Messaging\SmsTemplates;

/**
 * الگوهای پیامک — دیدن و ثبت کردن.
 *
 * شناسهٔ الگو در `.env` می‌نشیند، نه در دیتابیس. دلیلش این است که
 * الگوها به **حساب اپراتور** بسته‌اند نه به سالن — همهٔ سالن‌های روی
 * این نصب از یک خط خدماتی می‌فرستند. اگر هر سالن شناسهٔ خودش را داشت،
 * معنی‌اش این بود که هر سالن حساب جدا دارد، که نه درست است نه ارزان.
 *
 * ولی *ثبت* الگو دیگر دستی نیست. پیش از این باید متن را کپی می‌کردی،
 * وارد پنل ملی‌پیامک می‌شدی، ثبت می‌کردی و شناسه را برمی‌گرداندی —
 * شش الگو، شش بار. حالا یک دکمه این کار را می‌کند و شناسه را نشان
 * می‌دهد تا در `.env` بگذاری.
 */
final class SmsPatternController extends Controller
{
    public function index(Request $request): Response
    {
        $driver = (string) Config::get('reshen.sms.driver', 'log');

        // در حالت توسعه (درایور log) هنوز الگوهای ملی‌پیامک را نشان
        // می‌دهیم، چون همان چیزی است که موقع راه‌اندازی واقعی لازم می‌شود.
        $provider = $driver === 'log' ? 'melipayamak' : $driver;

        $registered = (new SmsPatternRepository())->forProvider($provider);
        $rows = [];

        foreach (SmsTemplates::all() as $code => $template) {
            $rows[$code] = $template + [
                'melipayamak' => (string) Config::get("reshen.sms.patterns.melipayamak.{$code}", ''),
                'kavenegar' => (string) Config::get("reshen.sms.patterns.kavenegar.{$code}", ''),
                'envKey' => 'SMS_PATTERN_' . strtoupper($provider) . '_' . strtoupper($code),

                // متنی که باید ثبت شود، با شکل متغیرهای همان اپراتور —
                // نه با «‎%name%‎» که فقط داخل رشن معنا دارد.
                'providerPattern' => SmsTemplates::providerPattern($code, $provider),
                'registered' => $registered[$code] ?? null,
            ];
        }

        return $this->page('layouts.panel', 'panel.sms.index', [
            'title' => 'الگوهای پیامک',
            'rows' => $rows,
            'driver' => $driver,
            'provider' => $provider,
            'canRegister' => $provider === 'melipayamak' && $this->melipayamakConfigured(),
            'dedicatedLine' => (bool) Config::get('reshen.sms.dedicated_line', false),
        ]);
    }

    /**
     * ثبت یک الگو در سامانهٔ اپراتور.
     *
     * شناسه‌ای که برمی‌گردد فوری کار نمی‌کند: تا وقتی مدیر سامانه
     * تأییدش نکرده، ارسال با کد ‎-4‎ برمی‌گردد. پس پیام موفقیت هم همین
     * را می‌گوید — «ثبت شد، منتظر تأیید» — نه «تمام شد».
     */
    public function register(Request $request): Response
    {
        $code = (string) $request->input('code', '');

        if (!SmsTemplates::exists($code)) {
            return $this->withError('این الگو وجود ندارد.', '/panel/sms');
        }
        if (!$this->melipayamakConfigured()) {
            return $this->withError(
                'نام کاربری و رمز ملی‌پیامک در .env تنظیم نشده است.',
                '/panel/sms'
            );
        }

        $template = SmsTemplates::get($code);
        $body = SmsTemplates::providerPattern($code, 'melipayamak');

        $gateway = new MelipayamakGateway(
            (string) Env::get('SMS_MELIPAYAMAK_USERNAME', ''),
            (string) Env::get('SMS_MELIPAYAMAK_PASSWORD', ''),
            (string) Env::get('SMS_MELIPAYAMAK_SENDER', ''),
        );

        $result = $gateway->registerPattern($template['title'], $body);

        if (!$result['ok']) {
            return $this->withError('ثبت الگو انجام نشد — ' . $result['error'], '/panel/sms');
        }

        (new SmsPatternRepository())->remember(
            'melipayamak',
            $code,
            (string) $result['body_id'],
            $template['title'],
            $body,
            Auth::id()
        );

        return $this->withSuccess(
            sprintf(
                'الگوی «%s» ثبت شد. شناسه: %s — تا تأیید ملی‌پیامک (چند روز) هنوز کار نمی‌کند.',
                $template['title'],
                $result['body_id']
            ),
            '/panel/sms'
        );
    }

    private function melipayamakConfigured(): bool
    {
        return (string) Env::get('SMS_MELIPAYAMAK_USERNAME', '') !== ''
            && (string) Env::get('SMS_MELIPAYAMAK_PASSWORD', '') !== '';
    }
}
