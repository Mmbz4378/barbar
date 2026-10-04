<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array{0:int,1:int}|null [max-age, s-maxage] اگر مسیر کش عمومی را پذیرفته باشد */
    private ?array $public = null;

    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => Request::basePath() . $to]);
    }

    /**
     * این پاسخ را برای بازدیدکنندهٔ ناشناس قابل کش عمومی اعلام می‌کند.
     *
     * فقط «اجازه» است، نه تضمین: اگر درخواست به نشست دست زده باشد (کوکی
     * داشت، یا توکن CSRF/flash ساخته شد) کش نمی‌شود. چون هر صفحه‌ای که فرم
     * دارد توکن CSRF می‌سازد و نشست باز می‌کند، صفحهٔ دارای توکن **از روی
     * ساختار** هرگز عمومی کش نمی‌شود — توکن یک نفر به دیگری نمی‌رسد.
     */
    public function publicCache(int $maxAge = 30, int $sharedMaxAge = 60): self
    {
        $this->public = [$maxAge, $sharedMaxAge];

        return $this;
    }

    public function send(): void
    {
        $this->prepare();

        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        echo $this->body;
    }

    /**
     * سیاست کش هر پاسخ. سربرگی که خود مسیر گذاشته همیشه مقدم است.
     *
     *  - عمومی و واجد شرایط: public + Vary: Cookie + ETag (و ۳۰۴ اگر تغییری نیست)
     *  - وابسته به نشست:   private, no-store (صفحه‌های شخصی ذخیره نشوند)
     *  - بقیه:             no-cache (هر بار بازبینی؛ پیش‌تر این را خودِ
     *                      session_start می‌فرستاد، حالا که مهمان نشست ندارد اینجاست)
     */
    public function prepare(): self
    {
        $this->applyCachePolicy();

        return $this;
    }

    private function applyCachePolicy(): void
    {
        foreach (array_keys($this->headers) as $key) {
            if (strcasecmp((string) $key, 'Cache-Control') === 0) {
                return;
            }
        }

        $touched = Session::touched();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $eligible = $this->public !== null
            && $this->status === 200
            && ($method === 'GET' || $method === 'HEAD')
            && !$touched;

        if (!$eligible) {
            $this->headers['Cache-Control'] = $touched ? 'private, no-store' : 'no-cache';

            return;
        }

        [$maxAge, $shared] = $this->public;
        $this->headers['Cache-Control'] = "public, max-age=$maxAge, s-maxage=$shared, stale-while-revalidate=30";
        $this->headers['Vary'] = 'Cookie';

        /*
         * ETag روی بدنه، با nonce پوشیده: nonce هر بار عوض می‌شود ولی محتوا نه.
         * اگر مرورگر همین نسخه را دارد، ۳۰۴ بدون بدنه. سربرگ CSP را از ۳۰۴
         * برمی‌داریم: مرورگر سربرگ‌های ۳۰۴ را روی نسخهٔ ذخیره‌شده می‌نشاند و
         * CSP با nonce تازه، اسکریپت‌های درون‌خطیِ بدنهٔ قدیمی را مسدود می‌کرد.
         */
        $etag = 'W/"' . substr(hash('sha256', str_replace(Security::nonce(), '', $this->body)), 0, 20) . '"';
        $this->headers['ETag'] = $etag;

        if (self::etagMatches((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $etag)) {
            $this->status = 304;
            $this->body = '';
            unset($this->headers['Content-Type']);
            if (!headers_sent()) {
                header_remove('Content-Security-Policy');
            }
        }
    }

    /**
     * mod_deflate آپاچی به ETag پسوند «-gzip» می‌چسباند و مرورگر همان را
     * برمی‌گرداند؛ بدون عادی‌سازی، ۳۰۴ پشت gzip هرگز رخ نمی‌داد.
     */
    private static function etagMatches(string $header, string $etag): bool
    {
        if ($header === '') {
            return false;
        }
        $normalize = static fn (string $t): string => preg_replace('/-(gzip|br|deflate)$/', '', trim(preg_replace('#^W/#', '', trim($t)), '"')) ?? '';
        $want = $normalize($etag);
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '*' || $normalize($candidate) === $want) {
                return true;
            }
        }

        return false;
    }
}
