<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Cron;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Support\Version;
use Throwable;

/**
 * پاسخ سلامت برای به‌روزرسان و پایش بیرونی.
 *
 * بدون توکن فقط «ok» می‌دهد؛ نسخه و پاک‌کردن کش opcache فقط با توکن
 * کرون (که از APP_KEY ساخته می‌شود) — تا کسی از بیرون نتواند کش را
 * مدام خالی کند یا نسخه را برای حمله شناسایی کند.
 */
final class HealthController extends Controller
{
    public function show(Request $request): Response
    {
        $trusted = Cron::tokenMatches((string) $request->query('token', ''));
        if ($trusted && $request->query('reset') === '1' && function_exists('opcache_reset')) {
            @opcache_reset();
        }

        $dbOk = true;
        try {
            DB::selectOne('SELECT 1 AS ok');
        } catch (Throwable) {
            $dbOk = false;
        }

        $body = ['ok' => $dbOk];
        if ($trusted) {
            $body['version'] = Version::current();
            $body['php'] = PHP_VERSION;
        }

        return new Response(json_encode($body), $dbOk ? 200 : 503, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
