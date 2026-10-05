<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/**
 * مانیفست PWA — ساخته‌شده در لحظه، نه فایل ثابت.
 *
 * چرا ثابت نیست: دو گروه این برنامه را نصب می‌کنند و انتظارشان یکی
 * نیست.
 *
 *   صاحب سالن و کارکنان از پنل نصب می‌کنند و می‌خواهند اپ با «صف زنده»
 *   باز شود.
 *
 *   مشتری از صفحهٔ اختصاصی سالن نصب می‌کند. اگر اپش با صفحهٔ ورودِ پنل
 *   باز شود، همان بار اول پاکش می‌کند. باید با صفحهٔ همان آرایشگاه باز
 *   شود و اسم و رنگ همان آرایشگاه را هم داشته باشد.
 *
 * پس مانیفست، `?s=<slug>` می‌گیرد و متناسب با آن ساخته می‌شود.
 */
final class ManifestController extends Controller
{
    public function show(Request $request): Response
    {
        $slug = trim((string) $request->query('s', ''));
        $salon = null;

        if ($slug !== '' && preg_match('/^[a-z0-9-]{1,64}$/i', $slug) === 1) {
            $salon = DB::selectOne(
                'SELECT name, slug, theme FROM salons WHERE slug = ? AND is_active = 1',
                [$slug]
            );
        }

        $isSalon = $salon !== null;

        // رنگ نوار بالای اپ و صفحهٔ آغاز: همان زمینهٔ روشن طراحی (--bg).
        // حالت تیره را متای theme-color در خود صفحه به‌روز می‌کند.
        $themeColor = '#f6f6f4';

        $manifest = [
            // id ثابت می‌ماند تا مرورگر نصبِ قبلی را همان اپ بشناسد،
            // نه یک اپ تازه کنار قبلی.
            'id' => $isSalon ? '/s/' . $salon['slug'] : '/panel',
            'name' => $isSalon ? $salon['name'] : brand(),
            'short_name' => $isSalon ? $this->shortName($salon['name']) : $this->shortName(brand()),
            'description' => $isSalon
                ? 'رزرو نوبت در ' . $salon['name']
                : \App\Domain\System\SiteSettings::tagline(),
            'start_url' => $isSalon ? url('s/' . $salon['slug']) : url('panel'),
            'scope' => url(''),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f6f6f4',
            'theme_color' => $themeColor,
            'dir' => 'rtl',
            'lang' => 'fa-IR',
            'categories' => ['lifestyle', 'business'],
            'icons' => $this->icons(),
        ];

        if (!$isSalon) {
            // میان‌بُرها فقط برای پنل معنا دارند — مشتری یک صفحه بیشتر ندارد.
            $manifest['shortcuts'] = [
                [
                    'name' => 'صف زنده',
                    'url' => url('panel'),
                    'icons' => [['src' => $this->icons()[0]['src'], 'sizes' => '192x192']],
                ],
                [
                    'name' => 'رزرو جدید',
                    'url' => url('panel/bookings/new'),
                    'icons' => [['src' => $this->icons()[0]['src'], 'sizes' => '192x192']],
                ],
            ];
        }

        return new Response(
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            [
                'Content-Type' => 'application/manifest+json; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    /**
     * آیکون‌های اپ: اگر مدیر کل لوگو گذاشته، آیکون‌های ساخته‌شده از آن؛
     * وگرنه آیکون پیش‌فرض (با نسخهٔ maskable).
     *
     * @return array<int,array<string,string>>
     */
    private function icons(): array
    {
        $i192 = \App\Domain\System\SiteSettings::iconPath(192);
        $i512 = \App\Domain\System\SiteSettings::iconPath(512);
        if ($i192 !== null && $i512 !== null) {
            return [
                ['src' => url($i192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => url($i512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ];
        }

        return [
            ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => asset('icons/icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
    }

    /**
     * نام کوتاه برای زیر آیکون.
     *
     * اندروید و iOS حدود ۱۲ کاراکتر جا دارند و بقیه را با «…» می‌برند.
     * «آرایشگاه شهاب» بریده می‌شود به «آرایشگاه…» که بی‌فایده است، پس
     * واژهٔ عمومیِ اول را برمی‌داریم و اسم خاص را نگه می‌داریم.
     */
    private function shortName(string $name): string
    {
        $name = trim($name);

        foreach (['سالن زیبایی', 'آرایشگاه زنانه', 'آرایشگاه مردانه', 'آرایشگاه', 'سالن', 'پیرایش', 'باربرشاپ'] as $prefix) {
            if (str_starts_with($name, $prefix . ' ')) {
                $name = trim(substr($name, strlen($prefix) + 1));
                break;
            }
        }

        return mb_substr($name, 0, 12);
    }
}
