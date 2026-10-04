<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * Primary Iranian SMS provider, over Melipayamak's REST API (so the host
 * only needs curl — no php-soap dependency, which doc 8.7 warns is the
 * silent killer of first-day SMS delivery).
 *
 * Two send modes, and the difference matters operationally:
 *   - BaseServiceNumber: a pre-approved template ("الگو") identified by a
 *     bodyId. Required for OTP and any transactional message, because
 *     carriers do not deliver free-text transactional SMS over the shared
 *     service lines most salons will be on.
 *   - SendSMS: free text, but only works from a dedicated purchased line
 *     (خط اختصاصی).
 */
final class MelipayamakGateway implements SmsGatewayInterface
{
    private const ENDPOINT_PATTERN = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';
    private const ENDPOINT_SIMPLE = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
    private const ENDPOINT_CREDIT = 'https://rest.payamak-panel.com/api/SendSMS/GetCredit';

    /*
     * ثبت الگو فقط SOAP دارد؛ ملی‌پیامک برایش REST نداده.
     *
     * فضای‌نامِ tempuri.org پیش‌فرضِ سرویس‌های ‎.asmx‎ در ASP.NET است.
     * نتوانستم WSDL را بخوانم تا تأییدش کنم (خروجی شبکهٔ این محیط بسته
     * است)، پس اگر افزونهٔ soap روی هاست باشد از آن استفاده می‌شود —
     * که خودش WSDL را می‌خواند و فضای‌نام را درست برمی‌دارد — و
     * پاکتِ دستیِ زیر فقط پشتیبان است.
     */
    private const ENDPOINT_PATTERN_ADD = 'https://api.payamak-panel.com/post/SharedService.asmx';
    private const SOAP_NAMESPACE = 'http://tempuri.org/';

    /** مستندات: «این مقدار را برابر با ۱ قرار دهید». */
    private const BLACKLIST_ID = 1;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $sender,
    ) {
    }

    public function send(string $e164Phone, string $message): array
    {
        if ($this->sender === '') {
            return ['ok' => false, 'ref' => null, 'error' => 'خط اختصاصی (شمارهٔ فرستنده) تنظیم نشده است.'];
        }

        return $this->handle($this->request(self::ENDPOINT_SIMPLE, [
            'username' => $this->username,
            'password' => $this->password,
            'to' => $this->localNumber($e164Phone),
            'from' => $this->sender,
            'text' => $message,
            'isflash' => 'false',
        ]));
    }

    public function sendPattern(string $e164Phone, string $patternId, array $args): array
    {
        if ($patternId === '') {
            return ['ok' => false, 'ref' => null, 'error' => 'شناسهٔ الگو (bodyId) تنظیم نشده است.'];
        }

        // متغیرهای الگو با «;» جدا می‌شوند، پس هیچ مقداری نباید این
        // نویسه را داشته باشد — وگرنه پیامک با متن جابه‌جا ارسال می‌شود.
        $text = implode(';', array_map([$this, 'cleanParam'], $args));

        return $this->handle($this->request(self::ENDPOINT_PATTERN, [
            'username' => $this->username,
            'password' => $this->password,
            'text' => $text,
            'to' => $this->localNumber($e164Phone),
            'bodyId' => $patternId,
        ]));
    }

    /**
     * اعتبار پنل — برای صفحهٔ نصب و سلامت.
     *
     * بدون این، اعتبار تمام‌شده بی‌صدا شکست می‌خورد: پیامک‌ها ارسال
     * «موفق» می‌گیرند و هیچ‌وقت نمی‌رسند.
     */
    public function credit(): ?float
    {
        $response = $this->request(self::ENDPOINT_CREDIT, [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if (($response['RetStatus'] ?? 0) === 1) {
            return (float) ($response['Value'] ?? 0);
        }

        return null;
    }

    public function name(): string
    {
        return 'melipayamak';
    }

    /**
     * ثبت یک الگوی تازه در ملی‌پیامک.
     *
     * چرا در برنامه و نه دستی در پنل اپراتور: پیش از این، صاحب سالن
     * باید متن هر الگو را از صفحهٔ پیامکِ رشن کپی می‌کرد، وارد پنل
     * ملی‌پیامک می‌شد، ثبت می‌کرد، شناسه را برمی‌داشت و در ‎.env‎
     * می‌گذاشت — شش الگو، شش بار. هر جای این زنجیره که اشتباه شود،
     * پیامک بی‌صدا نمی‌رسد.
     *
     * شناسه‌ای که برمی‌گردد **فوری کار نمی‌کند**: تا وقتی مدیر سامانه
     * تأییدش نکرده، ارسال با کد ‎-4‎ برمی‌گردد. پس ثبت، شروع انتظار
     * است نه پایان کار — و همین را هم به کاربر می‌گوییم.
     *
     * @return array{ok:bool,body_id:?string,error:?string}
     */
    public function registerPattern(string $title, string $body): array
    {
        if ($this->username === '' || $this->password === '') {
            return ['ok' => false, 'body_id' => null, 'error' => 'نام کاربری یا رمز ملی‌پیامک تنظیم نشده است.'];
        }
        if (trim($title) === '' || trim($body) === '') {
            return ['ok' => false, 'body_id' => null, 'error' => 'عنوان و متن الگو نمی‌توانند خالی باشند.'];
        }

        $raw = extension_loaded('soap')
            ? $this->addPatternViaSoapExtension($title, $body)
            : $this->addPatternViaCurl($title, $body);

        if (isset($raw['_error'])) {
            return ['ok' => false, 'body_id' => null, 'error' => $raw['_error']];
        }

        return self::interpretPatternResult((string) $raw['result']);
    }

    /**
     * پاسخِ SharedServiceBodyAdd را معنا می‌کند.
     *
     * مستندات: شناسهٔ الگو عددی ۵ یا ۶ رقمی است؛ «‎-2‎» یعنی شناسهٔ
     * لیست سیاه اشتباه و «0» یعنی نام کاربری یا رمز غلط.
     *
     * @return array{ok:bool,body_id:?string,error:?string}
     */
    public static function interpretPatternResult(string $result): array
    {
        $result = trim($result);

        if ($result === '-2') {
            return ['ok' => false, 'body_id' => null, 'error' => 'شناسهٔ لیست سیاه پذیرفته نشد.'];
        }
        if ($result === '0' || $result === '') {
            return ['ok' => false, 'body_id' => null, 'error' => 'نام کاربری یا رمز ملی‌پیامک اشتباه است.'];
        }
        if (!ctype_digit($result)) {
            return ['ok' => false, 'body_id' => null, 'error' => 'پاسخ ملی‌پیامک قابل خواندن نبود: ' . $result];
        }

        // شناسهٔ واقعی ۵ یا ۶ رقم است. عددِ کوچک‌تر، کد خطاست.
        if (strlen($result) < 4) {
            return ['ok' => false, 'body_id' => null, 'error' => self::statusMessage((int) $result)];
        }

        return ['ok' => true, 'body_id' => $result, 'error' => null];
    }

    /** با افزونهٔ soap — خودش WSDL را می‌خواند، پس دقیق‌تر است. */
    private function addPatternViaSoapExtension(string $title, string $body): array
    {
        try {
            $client = new \SoapClient(self::ENDPOINT_PATTERN_ADD . '?wsdl', [
                'encoding' => 'UTF-8',
                'connection_timeout' => 20,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ]);
            $response = $client->SharedServiceBodyAdd([
                'username' => $this->username,
                'password' => $this->password,
                'title' => $title,
                'body' => $body,
                'blackListId' => self::BLACKLIST_ID,
            ]);

            return ['result' => (string) ($response->SharedServiceBodyAddResult ?? '')];
        } catch (\Throwable $e) {
            return ['_error' => 'ارتباط با ملی‌پیامک برقرار نشد: ' . $e->getMessage()];
        }
    }

    /** بدون افزونهٔ soap: پاکت SOAP را دستی می‌سازیم و با curl می‌فرستیم. */
    private function addPatternViaCurl(string $title, string $body): array
    {
        $envelope = self::buildPatternEnvelope(
            $this->username,
            $this->password,
            $title,
            $body
        );

        $ch = curl_init(self::ENDPOINT_PATTERN_ADD);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $envelope,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "' . self::SOAP_NAMESPACE . 'SharedServiceBodyAdd"',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['_error' => $curlError !== '' ? $curlError : 'ارتباط با ملی‌پیامک برقرار نشد.'];
        }

        $result = self::extractPatternResult((string) $raw);

        if ($result === null) {
            $fault = self::extractSoapFault((string) $raw);

            return ['_error' => $fault ?? sprintf('پاسخ ملی‌پیامک قابل خواندن نبود (کد %d).', $httpCode)];
        }

        return ['result' => $result];
    }

    /** پاکت SOAP 1.1 برای SharedServiceBodyAdd. */
    public static function buildPatternEnvelope(
        string $username,
        string $password,
        string $title,
        string $body
    ): string {
        $x = static fn (string $v): string => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $ns = self::SOAP_NAMESPACE;

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>'
            . '<SharedServiceBodyAdd xmlns="' . $ns . '">'
            . '<username>' . $x($username) . '</username>'
            . '<password>' . $x($password) . '</password>'
            . '<title>' . $x($title) . '</title>'
            . '<body>' . $x($body) . '</body>'
            . '<blackListId>' . self::BLACKLIST_ID . '</blackListId>'
            . '</SharedServiceBodyAdd>'
            . '</soap:Body>'
            . '</soap:Envelope>';
    }

    /** مقدار داخل <SharedServiceBodyAddResult> — یا null اگر نبود. */
    public static function extractPatternResult(string $xml): ?string
    {
        if (preg_match('#<SharedServiceBodyAddResult[^>]*>(.*?)</SharedServiceBodyAddResult>#s', $xml, $m) === 1) {
            return html_entity_decode(trim($m[1]), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }

        return null;
    }

    /** متن <faultstring> وقتی سرویس خطای SOAP برگردانده. */
    public static function extractSoapFault(string $xml): ?string
    {
        if (preg_match('#<faultstring[^>]*>(.*?)</faultstring>#s', $xml, $m) === 1) {
            $text = html_entity_decode(trim($m[1]), ENT_XML1 | ENT_QUOTES, 'UTF-8');

            return $text === '' ? null : 'ملی‌پیامک خطا داد: ' . $text;
        }

        return null;
    }

    private function request(string $url, array $body): array
    {
        if ($this->username === '' || $this->password === '') {
            return ['_error' => 'نام کاربری یا رمز ملی‌پیامک تنظیم نشده است.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['_error' => $curlError !== '' ? $curlError : 'ارتباط با سرویس پیامک برقرار نشد.'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['_error' => sprintf('خطای ارتباط با سرویس پیامک (کد %d).', $httpCode)];
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded)) {
            return ['_error' => 'پاسخ سرویس پیامک قابل خواندن نبود.'];
        }

        return $decoded;
    }

    /**
     * موفقیت فقط با recId — عددی بیش از ۱۵ رقم.
     *
     * این تنها معیارِ مستنداتِ ملی‌پیامک است، و رعایت نکردنش گران تمام
     * می‌شود. سرویس، کدهای خطا را هم داخل همان `Value` برمی‌گرداند:
     * «2» یعنی اعتبار ناکافی، «35» یعنی شماره در لیست سیاه، «‎-4‎» یعنی
     * الگو هنوز تأیید نشده. همه‌شان عددِ غیرخالی‌اند.
     *
     * پیش‌تر هر Value غیرخالیِ غیرصفر موفق شمرده می‌شد، پس آن سه حالت
     * در `sms_messages` با وضعیت `sent` می‌نشستند. و چون SmsNotifier
     * برای جلوگیری از ارسال تکراری دقیقاً همان وضعیت را می‌خواند،
     * یادآورِ آن مشتری **هرگز** دوباره فرستاده نمی‌شد. یعنی خرابی
     * بی‌صدا، ماندگار، و دقیقاً همان چیزی که این محصول قرار بود از بین
     * ببرد.
     *
     * حالا هر چیزی که شکلِ recId نداشته باشد، کد خطا فرض می‌شود.
     */
    private function handle(array $response): array
    {
        if (isset($response['_error'])) {
            return ['ok' => false, 'ref' => null, 'error' => $response['_error']];
        }

        $value = trim((string) ($response['Value'] ?? ''));

        if (self::isReceiptId($value)) {
            return ['ok' => true, 'ref' => $value, 'error' => null];
        }

        /*
         * کد خطا کجاست؟ سرویس گاهی در `Value` می‌گذاردش و گاهی در
         * `RetStatus`. اولی را ترجیح می‌دهیم چون دقیق‌تر است — مثلاً
         * RetStatus برابر ۱ همراه با Value برابر «2» یعنی اعتبار ناکافی،
         * نه موفقیت.
         */
        $code = is_numeric($value)
            ? (int) $value
            : (int) ($response['RetStatus'] ?? 0);

        $message = self::statusMessage($code);

        // پیام خودِ سرویس، وقتی چیزی فراتر از جدولِ ما می‌گوید.
        $detail = trim((string) ($response['StrRetStatus'] ?? ''));
        if ($detail !== '' && !str_contains($message, $detail) && !self::isKnownCode($code)) {
            $message .= ' (' . $detail . ')';
        }

        return ['ok' => false, 'ref' => null, 'error' => $message];
    }

    /**
     * آیا این مقدار یک شناسهٔ ارسال است؟
     *
     * مستندات: «در صورت دریافت recId یک عدد بیش از ۱۵ رقم به معنای
     * ارسال موفق بوده». پس فقط رقم، و بیش از ۱۵ رقم.
     */
    public static function isReceiptId(string $value): bool
    {
        return $value !== ''
            && ctype_digit($value)
            && strlen($value) > 15;
    }

    private static function isKnownCode(int $code): bool
    {
        return array_key_exists($code, self::STATUS_MESSAGES);
    }

    /**
     * کدهای بازگشتی ملی‌پیامک، طبق مستندات خط خدماتی اشتراکی.
     *
     * عمداً همه‌شان اینجا هستند و نه فقط پرتکرارها: صاحب سالن SSH ندارد
     * و تنها چیزی که از یک ارسالِ ناموفق می‌بیند همین جمله است. «کد ‎-4‎»
     * چیزی به او نمی‌گوید؛ «الگو هنوز تأیید نشده» می‌گوید باید منتظر
     * بماند و به چه کسی زنگ بزند.
     *
     * @var array<int,string>
     */
    private const STATUS_MESSAGES = [
        // خطاهای دسترسی و آی‌پی
        -111 => 'آی‌پی این سرور نزد ملی‌پیامک مجاز نیست. در پنل اپراتور، آی‌پی هاست را ثبت کنید.',
        -110 => 'ملی‌پیامک برای این حساب ApiKey می‌خواهد، نه رمز عبور. مقدار SMS_MELI_PASSWORD را با ApiKey جایگزین کنید.',
        -109 => 'تا وقتی آی‌پی مجاز در پنل اپراتور تنظیم نشود، وب‌سرویس کار نمی‌کند.',
        -108 => 'آی‌پی این سرور به‌خاطر تلاش‌های ناموفق مسدود شده. با پشتیبانی ملی‌پیامک تماس بگیرید.',
        -1 => 'دسترسی این حساب به وب‌سرویس فعال نیست. با پشتیبانی ملی‌پیامک تماس بگیرید.',

        // خطاهای الگو — همان‌هایی که روز راه‌اندازی پیش می‌آیند
        -10 => 'یکی از متغیرها شبیه لینک است و اپراتور آن را رد می‌کند. نام سالن یا مشتری را بررسی کنید.',
        -5 => 'متغیرهای فرستاده‌شده با الگوی ثبت‌شده نمی‌خواند. تعداد و ترتیبشان باید دقیقاً مثل متن الگو باشد.',
        -4 => 'این شناسهٔ الگو معتبر نیست یا هنوز توسط ملی‌پیامک تأیید نشده. تأیید معمولاً چند روز طول می‌کشد.',
        -3 => 'خط ارسال برای این حساب تعریف نشده. با پشتیبانی ملی‌پیامک تماس بگیرید.',
        -2 => 'هر بار فقط یک شمارهٔ موبایل پذیرفته می‌شود.',

        // خطاهای داخلی سرویس
        -7 => 'مشکلی در شمارهٔ فرستنده هست. با پشتیبانی ملی‌پیامک تماس بگیرید.',
        -6 => 'خطای داخلی ملی‌پیامک. با پشتیبانی تماس بگیرید.',

        // خطاهای حساب و اعتبار
        0 => 'نام کاربری یا رمز ملی‌پیامک اشتباه است.',
        2 => 'اعتبار پنل پیامک کافی نیست.',
        6 => 'سامانهٔ ملی‌پیامک در حال به‌روزرسانی است. کمی بعد دوباره تلاش می‌شود.',
        7 => 'متن پیام کلمهٔ فیلترشده دارد. با واحد اداری ملی‌پیامک تماس بگیرید.',
        10 => 'این حساب ملی‌پیامک فعال نیست.',
        11 => 'پیامک ارسال نشد.',
        12 => 'مدارک حساب ملی‌پیامک کامل نیست.',

        // خطاهای گیرنده
        16 => 'شمارهٔ گیرنده یافت نشد.',
        17 => 'متن پیامک خالی است.',
        18 => 'شمارهٔ گیرنده نامعتبر است.',
        19 => 'از محدودیت ساعتی ارسال فراتر رفته‌اید.',
        35 => 'این شماره در لیست سیاه مخابرات است و پیامک خدماتی هم نمی‌گیرد.',
    ];

    public static function statusMessage(int $status): string
    {
        return self::STATUS_MESSAGES[$status]
            ?? sprintf('ارسال پیامک ناموفق بود (کد %d).', $status);
    }

    private function cleanParam(string $value): string
    {
        return str_replace([';', "\r", "\n"], ['،', ' ', ' '], $value);
    }

    private function localNumber(string $e164Phone): string
    {
        return '0' . substr($e164Phone, 3);
    }
}
