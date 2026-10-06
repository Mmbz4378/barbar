<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Identity\AdminPolicy;
use App\Domain\Messaging\SmsManager;
use App\Domain\System\AuditLog;
use App\Domain\System\BrandAssets;
use App\Domain\System\SiteSettings;
use App\Support\IranMobile;
use App\Support\Version;
use Throwable;

/**
 * تنظیمات سایت در پنل مدیر کل — به‌جای ویرایش .env.
 *
 * هر زبانه فرم خودش را دارد و جدا ذخیره می‌شود. رمزها (پیامک، درگاه) فقط
 * نوشتنی‌اند: خالی گذاشتن یعنی «عوض نکن»، و مقدار ذخیره‌شده هرگز در صفحه
 * برنمی‌گردد.
 */
final class PlatformSettingsController extends Controller
{
    public const TABS = [
        'brand' => ['برند و تماس', 'store'],
        'seo' => ['سئو و گوگل', 'search'],
        'links' => ['لینک‌ها و شبکه‌ها', 'share'],
        'auth' => ['ورود و ثبت‌نام', 'lock'],
        'sms' => ['پیامک', 'message'],
        'payment' => ['پرداخت', 'card'],
        'maintenance' => ['نگهداری', 'cog'],
    ];

    /**
     * زبانه‌هایی که فقط مدیر ارشد عوض می‌کند: هرکس اعتبارنامهٔ پیامک را به حساب
     * خودش ببرد، کدهای ورود و بازیابی رمز همه را در اپراتور می‌خواند؛ روش‌های
     * ورود و درگاه پرداخت هم همین‌قدر حساس‌اند (docs/admin-panel.md).
     */
    public const SUPER_TABS = ['auth', 'sms', 'payment'];

    public function show(Request $request): Response
    {
        $tab = (string) $request->query('tab', 'brand');
        if (!array_key_exists($tab, self::TABS)) {
            $tab = 'brand';
        }

        return $this->page('layouts.platform', 'platform.settings.index', [
            'title' => 'تنظیمات · ' . self::TABS[$tab][0],
            'tab' => $tab,
            'env' => [
                'sms_driver' => (string) Config::get('reshen.sms.driver', 'log'),
                'payment_driver' => (string) Config::get('reshen.payment.driver', 'disabled'),
            ],
            'system' => $tab === 'maintenance' ? $this->systemInfo() : [],
            'locked' => in_array($tab, self::SUPER_TABS, true) && !AdminPolicy::currentIsSuper(),
        ]);
    }

    public function save(Request $request): Response
    {
        $tab = (string) $request->param('tab');
        if (!array_key_exists($tab, self::TABS)) {
            return $this->notFound();
        }
        $back = '/platform/settings?tab=' . $tab;
        if (in_array($tab, self::SUPER_TABS, true) && ($deny = AdminPolicy::denyUnlessSuper()) !== null) {
            return $this->withError($deny, $back);
        }

        try {
            $result = match ($tab) {
                'brand' => $this->saveBrand($request),
                'seo' => $this->saveSeo($request),
                'links' => $this->saveLinks($request),
                'auth' => $this->saveAuth($request),
                'sms' => $this->saveSms($request),
                'payment' => $this->savePayment($request),
                'maintenance' => $this->saveMaintenance($request),
            };
        } catch (\RuntimeException $e) {
            return $this->withError($e->getMessage(), $back);
        }
        if (is_array($result)) {
            return $this->invalid($request, $result, $back);
        }
        AuditLog::record(null, 'settings.updated', 'settings', null, ['tab' => $tab]);

        return $this->withSuccess($result, $back);
    }

    // ─── برند ─────────────────────────────────────────────────────────

    /** @return string|array<string,string> */
    private function saveBrand(Request $request): string|array
    {
        $name = mb_substr(trim((string) $request->input('name', '')), 0, 60);
        $email = trim((string) $request->input('contact_email', ''));
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'نام سامانه را بنویسید.';
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['contact_email'] = 'نشانی ایمیل معتبر نیست.';
        }
        if ($errors !== []) {
            return $errors;
        }

        SiteSettings::setMany([
            'brand.name' => $name === SiteSettings::DEFAULT_BRAND ? null : $name,
            'brand.tagline' => $this->optional($request, 'tagline', 120),
            'brand.contact_phone' => $this->optional($request, 'contact_phone', 30),
            'brand.contact_email' => $email !== '' ? mb_substr($email, 0, 120) : null,
            'brand.address' => $this->optional($request, 'address', 255),
            'brand.footer_text' => $this->optional($request, 'footer_text', 300),
        ]);

        $note = '';
        if ($request->input('remove_logo') === '1') {
            BrandAssets::removeLogo();
            AuditLog::record(null, 'settings.logo', 'settings', null, ['removed' => true]);
            $note = ' لوگو برداشته شد.';
        } else {
            $file = $_FILES['logo'] ?? null;
            if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = BrandAssets::storeLogo($file);
                if (!$stored['ok']) {
                    return ['logo' => (string) $stored['error']];
                }
                AuditLog::record(null, 'settings.logo', 'settings', null);
                $note = ' لوگو و آیکون‌ها به‌روز شدند.' . ($stored['error'] !== null ? ' ' . $stored['error'] : '');
            }
        }

        return 'برند و اطلاعات تماس ذخیره شد.' . $note;
    }

    // ─── سئو ──────────────────────────────────────────────────────────

    /** @return string|array<string,string> */
    private function saveSeo(Request $request): string|array
    {
        $errors = [];
        $google = trim((string) $request->input('google_verification', ''));
        $googleCode = $google !== '' ? SiteSettings::parseVerification($google) : '';
        if ($google !== '' && $googleCode === '') {
            $errors['google_verification'] = 'کد تأیید گوگل شناخته نشد. کل تگ meta را از Search Console کپی کنید.';
        }
        $bing = trim((string) $request->input('bing_verification', ''));
        $bingCode = $bing !== '' ? SiteSettings::parseVerification($bing) : '';
        if ($bing !== '' && $bingCode === '') {
            $errors['bing_verification'] = 'کد تأیید Bing شناخته نشد.';
        }
        $ga = strtoupper(trim((string) $request->input('ga_id', '')));
        if ($ga !== '' && !preg_match('/^G-[A-Z0-9]{4,20}$/', $ga)) {
            $errors['ga_id'] = 'شناسهٔ Google Analytics 4 به شکل G-XXXXXXX است.';
        }
        if ($errors !== []) {
            return $errors;
        }

        SiteSettings::setMany([
            'seo.title' => $this->optional($request, 'home_title', 70),
            'seo.description' => $this->optional($request, 'description', 300),
            'seo.google_verification' => $googleCode !== '' ? $googleCode : null,
            'seo.bing_verification' => $bingCode !== '' ? $bingCode : null,
            'seo.ga_id' => $ga !== '' ? $ga : null,
            'seo.indexing' => $request->input('indexing') === '1' ? null : '0',
        ]);
        Cache::bump('discovery'); // sitemap و صفحه‌های عمومیِ کش‌شده

        return 'تنظیمات سئو ذخیره شد.' . ($googleCode !== '' ? ' حالا در Search Console دکمهٔ «Verify» را بزنید.' : '');
    }

    // ─── لینک‌ها ──────────────────────────────────────────────────────

    /** @return string|array<string,string> */
    private function saveLinks(Request $request): string|array
    {
        $errors = [];
        $social = [];
        foreach (SiteSettings::SOCIAL as $key => [$label, $prefix]) {
            $raw = trim((string) $request->input('social_' . $key, ''));
            if ($raw === '') {
                continue;
            }
            // نام کاربری خالی (بدون نشانی) به نشانی کامل تبدیل می‌شود
            $url = preg_match('#^https?://#i', $raw) ? $raw : $prefix . ltrim($raw, '@/');
            if (SiteSettings::safeUrl($url) === null || str_starts_with($url, '/')) {
                $errors['social_' . $key] = 'نشانی ' . $label . ' معتبر نیست.';
                continue;
            }
            $social[$key] = mb_substr($url, 0, 300);
        }

        $footer = [];
        $labels = (array) $request->input('footer_label', []);
        $urls = (array) $request->input('footer_url', []);
        foreach ($labels as $i => $label) {
            $label = mb_substr(trim((string) $label), 0, 60);
            $url = trim((string) ($urls[$i] ?? ''));
            if ($label === '' && $url === '') {
                continue;
            }
            if ($label === '' || SiteSettings::safeUrl($url) === null) {
                $errors['footer_links'] = 'هر پیوند پانویس عنوان و نشانیِ معتبر (https://… یا /…) می‌خواهد.';
                continue;
            }
            $footer[] = ['label' => $label, 'url' => mb_substr($url, 0, 300)];
        }
        $footer = array_slice($footer, 0, 10);

        $enamadInput = trim((string) $request->input('enamad', ''));
        $enamad = null;
        if ($enamadInput !== '' && $request->input('remove_enamad') !== '1') {
            $enamad = SiteSettings::parseEnamad($enamadInput);
            if ($enamad === null) {
                $errors['enamad'] = 'کد اینماد شناخته نشد. کد HTML نماد را از پنل اینماد کامل کپی کنید.';
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        $values = [
            'links.social' => $social !== [] ? json_encode($social, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'links.footer' => $footer !== [] ? json_encode($footer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
        ];
        if ($request->input('remove_enamad') === '1') {
            $values['links.enamad'] = null;
        } elseif ($enamad !== null) {
            $values['links.enamad'] = json_encode($enamad);
        }
        SiteSettings::setMany($values);

        return 'لینک‌ها ذخیره شد.';
    }

    // ─── ورود و ثبت‌نام ───────────────────────────────────────────────

    /** @return string|array<string,string> */
    private function saveAuth(Request $request): string|array
    {
        $password = $request->input('password_login') === '1';
        $otp = $request->input('otp_login') === '1';
        if (!$password && !$otp) {
            return ['methods' => 'دست‌کم یک روش ورود باید روشن بماند.'];
        }
        // مدیرِ بی‌رمز با خاموش‌کردن کد پیامکی خودش را بیرون می‌اندازد
        if (!$otp && empty(Auth::user()['password_hash'])) {
            return ['methods' => 'خودتان هنوز رمز ندارید؛ پیش از خاموش‌کردن ورود با کد پیامکی، از «حساب من» رمز بگذارید.'];
        }
        $mode = (string) $request->input('registration', SiteSettings::REG_CLOSED);
        if (!in_array($mode, [SiteSettings::REG_CLOSED, SiteSettings::REG_OPEN, SiteSettings::REG_APPROVAL], true)) {
            $mode = SiteSettings::REG_CLOSED;
        }

        SiteSettings::setMany([
            'auth.password' => $password ? null : '0',
            'auth.otp' => $otp ? null : '0',
            'auth.registration' => $mode === SiteSettings::REG_CLOSED ? null : $mode,
            'auth.min_password' => (string) max(6, min(64, (int) int_input($request->input('min_password', '8')))),
            'auth.max_attempts' => (string) max(3, min(20, (int) int_input($request->input('max_attempts', '5')))),
            'auth.lock_minutes' => (string) max(1, min(1440, (int) int_input($request->input('lock_minutes', '15')))),
        ]);

        return 'تنظیمات ورود ذخیره شد.';
    }

    // ─── پیامک ────────────────────────────────────────────────────────

    /** @return string|array<string,string> */
    private function saveSms(Request $request): string|array
    {
        if ($request->input('action') === 'test') {
            return $this->testSms($request);
        }

        $driver = (string) $request->input('driver', '');
        if (!in_array($driver, ['', 'log', 'melipayamak', 'kavenegar'], true)) {
            return ['driver' => 'درگاه پیامک نامعتبر است.'];
        }

        $values = [
            'sms.driver' => $driver !== '' ? $driver : null,
            'sms.melipayamak.username' => $this->optional($request, 'melipayamak_username', 100),
            'sms.melipayamak.sender' => $this->optional($request, 'melipayamak_sender', 30),
            'sms.dedicated_line' => $request->input('dedicated_line') === '1' ? '1' : null,
        ];
        foreach (['melipayamak', 'kavenegar'] as $provider) {
            foreach (array_keys(SiteSettings::SMS_PATTERNS) as $pattern) {
                $values['sms.patterns.' . $provider . '.' . $pattern] = $this->optional($request, 'pattern_' . $provider . '_' . $pattern, 60);
            }
        }
        SiteSettings::setMany($values);

        // رمزها: خالی یعنی «عوض نکن»؛ تیک «پاک کن» یعنی برگرد به .env
        foreach (['melipayamak_password' => 'sms.melipayamak.password', 'kavenegar_api_key' => 'sms.kavenegar.api_key'] as $field => $key) {
            if ($request->input('clear_' . $field) === '1') {
                SiteSettings::set($key, null);
            } elseif (($secret = trim((string) $request->input($field, ''))) !== '') {
                SiteSettings::setSecret($key, $secret);
            }
        }
        SmsManager::reset();

        return 'تنظیمات پیامک ذخیره شد.';
    }

    /** @return string|array<string,string> */
    private function testSms(Request $request): string|array
    {
        $phone = IranMobile::tryParse((string) $request->input('test_phone', ''));
        if ($phone === null) {
            return ['test_phone' => 'شمارهٔ موبایل آزمایشی معتبر نیست.'];
        }
        SmsManager::reset();
        try {
            $result = SmsManager::send($phone->e164, 'پیامک آزمایشی ' . SiteSettings::brandName() . ' — اگر این را می‌خوانید، درگاه پیامک درست کار می‌کند.');
        } catch (Throwable $e) {
            $result = ['ok' => false, 'error' => $e->getMessage()];
        }
        AuditLog::record(null, 'settings.sms_test', 'settings', null, ['ok' => (bool) $result['ok'], 'provider' => $result['provider'] ?? null]);
        if (!$result['ok']) {
            throw new \RuntimeException('پیامک آزمایشی فرستاده نشد: ' . ($result['error'] ?? 'خطای نامشخص') . '. توجه: روی خط خدماتی، متن آزاد معمولاً تحویل داده نمی‌شود؛ ورود با کد به «الگو» نیاز دارد.');
        }

        return 'پیامک آزمایشی با ' . ($result['provider'] ?? 'درگاه') . ' فرستاده شد' . (Config::get('reshen.sms.driver') === 'log' ? ' (درگاه «log»: فقط در storage/logs/sms.log نوشته شد).' : '.');
    }

    // ─── پرداخت ───────────────────────────────────────────────────────

    /** @return string|array<string,string> */
    private function savePayment(Request $request): string|array
    {
        $driver = (string) $request->input('driver', '');
        if (!in_array($driver, ['', 'disabled', 'zarinpal'], true)) {
            return ['driver' => 'درگاه پرداخت نامعتبر است.'];
        }
        $merchant = trim((string) $request->input('merchant_id', ''));
        if ($merchant !== '' && !preg_match('/^[A-Za-z0-9-]{20,50}$/', $merchant)) {
            return ['merchant_id' => 'کد پذیرندهٔ زرین‌پال ۳۶ نویسه است (مثل xxxxxxxx-xxxx-…).'];
        }
        SiteSettings::setMany([
            'payment.driver' => $driver !== '' ? $driver : null,
            'payment.zarinpal.sandbox' => $request->input('sandbox') === '1' ? '1' : null,
        ]);
        if ($request->input('clear_merchant_id') === '1') {
            SiteSettings::set('payment.zarinpal.merchant_id', null);
        } elseif ($merchant !== '') {
            SiteSettings::setSecret('payment.zarinpal.merchant_id', $merchant);
        }

        return 'تنظیمات پرداخت ذخیره شد.';
    }

    // ─── نگهداری ──────────────────────────────────────────────────────

    /** @return string|array<string,string> */
    private function saveMaintenance(Request $request): string|array
    {
        if ($request->input('action') === 'clear_cache') {
            Cache::flushAll();
            AuditLog::record(null, 'settings.cache_cleared', 'settings', null);

            return 'کش سامانه پاک شد.';
        }

        $on = $request->input('maintenance') === '1';
        $was = SiteSettings::maintenanceOn();
        SiteSettings::setMany([
            'maintenance.on' => $on ? '1' : null,
            'maintenance.message' => $this->optional($request, 'message', 500),
        ]);
        if ($on !== $was) {
            AuditLog::record(null, $on ? 'settings.maintenance_on' : 'settings.maintenance_off', 'settings', null);
        }

        return $on ? 'حالت تعمیر روشن است: سایت فقط برای مدیران کل باز است.' : 'سایت برای همه باز است.';
    }

    /** @return array<string,string> */
    private function systemInfo(): array
    {
        $cron = \App\Core\Cron::lastRunAt();

        return [
            'نسخهٔ سامانه' => Version::current(),
            'PHP' => PHP_VERSION,
            'کش مشترک (APCu)' => Cache::shared() ? 'روشن' : 'خاموش',
            'آخرین اجرای کرون' => $cron !== null ? jdate(date('Y-m-d H:i:s', $cron)) : 'هرگز — کرون تنظیم نشده',
            'درگاه پیامک فعال' => (string) Config::get('reshen.sms.driver', 'log'),
        ];
    }

    private function optional(Request $request, string $field, int $max): ?string
    {
        $value = mb_substr(trim((string) $request->input($field, '')), 0, $max);

        return $value !== '' ? $value : null;
    }
}
