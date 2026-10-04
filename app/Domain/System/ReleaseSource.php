<?php

declare(strict_types=1);

namespace App\Domain\System;

use App\Core\Config;
use App\Support\Version;
use RuntimeException;

/**
 * از کجا بفهمیم نسخهٔ تازه چیست و بسته را از کجا بگیریم.
 *
 * هر انتشار یک «مانیفست» دارد (manifest.json کنار بستهٔ ZIP) که GitHub
 * Action با tools/build-release.php می‌سازد:
 *
 *   {format:1, version, tag, released_at, min_php, notes,
 *    package:{file, url, sha256, size}, signature}
 *
 * دو منبع:
 *   github — Releases یک مخزن. مخزن عمومی بدون API (محدودیت نرخ API روی
 *            IP مشترک هاست زود پر می‌شود)؛ مخزن خصوصی یا کانال beta با API.
 *   url    — یک نشانی ثابت که مانیفست را برمی‌گرداند (سرور انتشار خودتان).
 */
final class ReleaseSource
{
    public const SIGNATURE_CONTEXT = 'reshen-release:1:';

    public function __construct(private readonly HttpFetcher $http = new HttpFetcher())
    {
    }

    /**
     * آخرین انتشار منتشرشده، یا null اگر هیچ انتشاری نیست.
     *
     * @return array{version:string,tag:string,released_at:?string,min_php:string,notes:string,package_file:string,package_url:string,package_headers:array,sha256:string,size:int,signature:?string,prerelease:bool}|null
     */
    public function latest(): ?array
    {
        $source = (string) Config::get('reshen.updates.source', 'github');
        if ($source === 'url') {
            $url = trim((string) Config::get('reshen.updates.manifest_url', ''));
            if ($url === '') {
                throw new RuntimeException('UPDATE_MANIFEST_URL تنظیم نشده است.');
            }
            $manifest = $this->fetchJson($url, []);

            return $manifest === null ? null : $this->normalize($manifest, [], null);
        }

        $repo = trim((string) Config::get('reshen.updates.github_repo', ''));
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
            throw new RuntimeException('UPDATE_GITHUB_REPO معتبر نیست (owner/repo).');
        }
        $token = trim((string) Config::get('reshen.updates.github_token', ''));
        $beta = Config::get('reshen.updates.channel', 'stable') === 'beta';

        if ($token === '' && !$beta) {
            $manifest = $this->fetchJson("https://github.com/{$repo}/releases/latest/download/manifest.json", []);

            return $manifest === null ? null : $this->normalize($manifest, [], $repo);
        }

        return $this->viaApi($repo, $token, $beta);
    }

    private function viaApi(string $repo, string $token, bool $beta): ?array
    {
        $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $res = $this->http->get("https://api.github.com/repos/{$repo}/releases?per_page=20", $headers);
        if ($res['status'] === 404) {
            throw new RuntimeException('مخزن پیدا نشد یا توکن به آن دسترسی ندارد.');
        }
        if ($res['status'] !== 200) {
            throw new RuntimeException('پاسخ GitHub API: HTTP ' . $res['status']);
        }
        $releases = json_decode($res['body'], true);
        if (!is_array($releases)) {
            throw new RuntimeException('پاسخ GitHub API قابل خواندن نبود.');
        }

        $best = null;
        foreach ($releases as $release) {
            if (!is_array($release) || !empty($release['draft']) || (!$beta && !empty($release['prerelease']))) {
                continue;
            }
            $tag = (string) ($release['tag_name'] ?? '');
            if (!Version::isValid($tag)) {
                continue;
            }
            if ($best === null || Version::compare($tag, (string) $best['tag_name']) > 0) {
                $best = $release;
            }
        }
        if ($best === null) {
            return null;
        }

        $assets = [];
        foreach ((array) ($best['assets'] ?? []) as $asset) {
            $assets[(string) ($asset['name'] ?? '')] = (string) ($asset['url'] ?? '');
        }
        if (empty($assets['manifest.json'])) {
            throw new RuntimeException('انتشار ' . $best['tag_name'] . ' فایل manifest.json ندارد.');
        }
        $assetHeaders = ['Accept: application/octet-stream'];
        if ($token !== '') {
            $assetHeaders[] = 'Authorization: Bearer ' . $token;
        }
        $manifest = $this->fetchJson($assets['manifest.json'], $assetHeaders);
        if ($manifest === null) {
            throw new RuntimeException('manifest.json انتشار ' . $best['tag_name'] . ' خوانده نشد.');
        }
        $file = (string) ($manifest['package']['file'] ?? '');
        if ($file === '' || empty($assets[$file])) {
            throw new RuntimeException('بستهٔ ' . $file . ' در انتشار ' . $best['tag_name'] . ' نیست.');
        }
        $manifest['package']['url'] = $assets[$file];
        $manifest['prerelease'] = !empty($best['prerelease']);

        return $this->normalize($manifest, $assetHeaders, null);
    }

    private function fetchJson(string $url, array $headers): ?array
    {
        $res = $this->http->get($url, $headers, 500_000);
        if ($res['status'] === 404) {
            return null;
        }
        if ($res['status'] !== 200) {
            throw new RuntimeException('دریافت مانیفست ناموفق بود (HTTP ' . $res['status'] . ').');
        }
        $data = json_decode($res['body'], true);
        if (!is_array($data)) {
            throw new RuntimeException('مانیفست انتشار JSON معتبر نیست.');
        }

        return $data;
    }

    /**
     * اعتبارسنجی و یکدست‌سازی مانیفست.
     *
     * @param string|null $repo اگر داده شود، نشانی بسته باید از Releases همین مخزن باشد
     */
    private function normalize(array $m, array $packageHeaders, ?string $repo): array
    {
        if ((int) ($m['format'] ?? 0) !== 1) {
            throw new RuntimeException('قالب مانیفست پشتیبانی نمی‌شود؛ ابتدا نسخهٔ فعلی را دستی به‌روز کنید.');
        }
        $version = Version::normalize((string) ($m['version'] ?? ''));
        if (!Version::isValid($version)) {
            throw new RuntimeException('شمارهٔ نسخه در مانیفست معتبر نیست.');
        }
        $sha = strtolower((string) ($m['package']['sha256'] ?? ''));
        if (!preg_match('/^[0-9a-f]{64}$/', $sha)) {
            throw new RuntimeException('چکیدهٔ sha256 بسته در مانیفست نیست.');
        }
        $url = (string) ($m['package']['url'] ?? '');
        HttpFetcher::assertAllowedUrl($url);
        if ($repo !== null && !str_starts_with($url, "https://github.com/{$repo}/releases/download/")) {
            throw new RuntimeException('نشانی بسته به انتشارهای همین مخزن اشاره نمی‌کند.');
        }
        $file = basename((string) ($m['package']['file'] ?? 'package.zip'));

        return [
            'version' => $version,
            'tag' => (string) ($m['tag'] ?? 'v' . $version),
            'released_at' => isset($m['released_at']) ? (string) $m['released_at'] : null,
            'min_php' => (string) ($m['min_php'] ?? '8.1.0'),
            'notes' => mb_substr((string) ($m['notes'] ?? ''), 0, 20000),
            'package_file' => $file,
            'package_url' => $url,
            'package_headers' => $packageHeaders,
            'sha256' => $sha,
            'size' => (int) ($m['package']['size'] ?? 0),
            'signature' => isset($m['signature']) && $m['signature'] !== '' ? (string) $m['signature'] : null,
            'prerelease' => (bool) ($m['prerelease'] ?? Version::isPrerelease($version)),
        ];
    }

    /**
     * امضای Ed25519 روی «زمینه + نسخه + sha256» — هم بسته را تأیید می‌کند
     * و هم نمی‌گذارد بستهٔ امضاشدهٔ قدیمی با شمارهٔ نسخهٔ تازه جا زده شود.
     */
    public static function signatureMessage(string $version, string $sha256): string
    {
        return self::SIGNATURE_CONTEXT . Version::normalize($version) . ':' . strtolower($sha256);
    }

    public static function verifySignature(array $release, string $publicKeyB64): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('افزونهٔ sodium برای بررسی امضا فعال نیست.');
        }
        $key = base64_decode(trim($publicKeyB64), true);
        $sig = base64_decode((string) ($release['signature'] ?? ''), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, self::signatureMessage($release['version'], $release['sha256']), $key);
    }
}
