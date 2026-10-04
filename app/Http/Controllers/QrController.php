<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\QrCodeService;

/**
 * برگهٔ QR سالن — برای چسباندن پشت آینه و روی پیشخوان.
 */
final class QrController extends Controller
{
    public function show(Request $request): Response
    {
        $salon = $this->salon();
        $link = absolute_url('s/' . $salon['slug']);
        $qr = new QrCodeService();

        return $this->page('layouts.panel', 'panel.qr.index', [
            'title' => 'کد QR سالن',
            'salon' => $salon,
            'link' => $link,
            // درون‌خطی می‌آید تا صفحه یک درخواست کمتر بزند و چاپ هم
            // بدون اتصال به اینترنت درست دربیاید.
            'qrDataUri' => $qr->available() ? $qr->svgDataUri($link) : null,
            'pngAvailable' => $qr->available() && $qr->isPngAvailable(),
        ]);
    }

    public function svg(Request $request): Response
    {
        return $this->download('svg', 'image/svg+xml');
    }

    public function png(Request $request): Response
    {
        return $this->download('png', 'image/png');
    }

    private function download(string $format, string $mime): Response
    {
        $salon = $this->salon();
        $qr = new QrCodeService();

        if (!$qr->available() || ($format === 'png' && !$qr->isPngAvailable())) {
            return Response::html('این قالب روی این سرور در دسترس نیست.', 501);
        }

        $link = absolute_url('s/' . $salon['slug']);
        $body = $format === 'png' ? $qr->png($link) : $qr->svg($link);

        return new Response($body, 200, [
            'Content-Type' => $mime,
            // نام فایل از اسلاگ می‌آید، نه از نام فارسیِ سالن: نام فایلِ
            // غیرلاتین در برخی مرورگرها و ویندوز خراب می‌شود.
            'Content-Disposition' => 'attachment; filename="reshen-' . $salon['slug'] . '.' . $format . '"',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    /** @return array{id:int,name:string,slug:string} */
    private function salon(): array
    {
        $salon = DB::selectOne('SELECT id, name, slug FROM salons WHERE id = ?', [Auth::salonId()]);

        // میان‌افزار TenantRequired پیش از این تضمین کرده سالن هست؛ این
        // فقط برای حالتی است که سالن بین دو درخواست حذف شده باشد.
        if ($salon === null) {
            throw new \RuntimeException('سالن فعال پیدا نشد.');
        }

        return $salon;
    }
}
