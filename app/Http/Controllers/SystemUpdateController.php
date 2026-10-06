<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Identity\AdminPolicy;
use App\Domain\System\Updater;
use App\Support\Version;
use Throwable;

/**
 * صفحهٔ «به‌روزرسانی سامانه»: نسخهٔ فعلی و تازه، نصب با یک دکمه،
 * حالت خودکار و بازهٔ شبانه، تاریخچه و بازگردانی دیتابیس.
 */
final class SystemUpdateController extends Controller
{
    public function index(Request $request): Response
    {
        $updater = new Updater();

        return $this->page('layouts.platform', 'system.updates', [
            'title' => 'به‌روزرسانی سامانه',
            'current' => Version::current(),
            'mode' => $updater->mode(),
            'window' => $updater->window(),
            'state' => $updater->state(),
            'available' => $updater->available(),
            'readiness' => $updater->readiness(),
            'history' => $updater->history(),
            'source' => [
                'type' => (string) Config::get('reshen.updates.source', 'github'),
                'repo' => (string) Config::get('reshen.updates.github_repo', ''),
                'url' => (string) Config::get('reshen.updates.manifest_url', ''),
                'channel' => (string) Config::get('reshen.updates.channel', 'stable'),
                'token' => trim((string) Config::get('reshen.updates.github_token', '')) !== '',
                'signed' => trim((string) Config::get('reshen.updates.public_key', '')) !== '',
            ],
        ]);
    }

    public function check(Request $request): Response
    {
        try {
            $release = (new Updater())->check();
        } catch (Throwable $e) {
            return $this->withError('بررسی نسخهٔ تازه ناموفق بود: ' . $e->getMessage(), '/system/updates');
        }

        return $release !== null
            ? $this->withSuccess('نسخهٔ ' . $release['version'] . ' آمادهٔ نصب است.', '/system/updates')
            : $this->withSuccess('سامانه به‌روز است (نسخهٔ ' . Version::current() . ').', '/system/updates');
    }

    public function apply(Request $request): Response
    {
        $updater = new Updater();
        try {
            // همیشه از نو از منبع؛ به مانیفستِ ذخیره‌شده برای نصب اعتماد نمی‌کنیم
            $release = $updater->check();
        } catch (Throwable $e) {
            return $this->withError('دریافت اطلاعات انتشار ناموفق بود: ' . $e->getMessage(), '/system/updates');
        }
        if ($release === null) {
            return $this->withSuccess('نسخهٔ تازه‌تری برای نصب نیست.', '/system/updates');
        }
        $expected = Version::normalize((string) $request->input('version', ''));
        if ($expected !== '' && $expected !== $release['version']) {
            return $this->withError('در این فاصله نسخهٔ ' . $release['version'] . ' منتشر شده است؛ یادداشت‌هایش را ببینید و دوباره بزنید.', '/system/updates');
        }

        $result = $updater->apply($release, 'manual', Auth::id());

        return $result['ok']
            ? $this->withSuccess($result['message'], '/system/updates')
            : $this->withError('به‌روزرسانی انجام نشد: ' . $result['message'], '/system/updates');
    }

    public function settings(Request $request): Response
    {
        $updater = new Updater();
        try {
            $updater->setMode((string) $request->input('mode', 'notify'));
            $updater->setWindow((int) $request->input('window_start', 3), (int) $request->input('window_end', 5));
        } catch (Throwable $e) {
            return $this->withError($e->getMessage(), '/system/updates');
        }

        return $this->withSuccess('تنظیمات به‌روزرسانی ذخیره شد.', '/system/updates');
    }

    public function restoreDatabase(Request $request): Response
    {
        $id = (int) $request->param('id');
        // دیتابیسِ قدیمی فهرستِ مدیرهای قدیمی را هم برمی‌گرداند؛ مدیرِ برداشته‌شده
        // با بازگردانی پشتیبانِ پیش از آن دوباره مدیر می‌شد.
        if (!AdminPolicy::bootstrapAllowed() && ($deny = AdminPolicy::denyUnlessSuper()) !== null) {
            return $this->withError($deny, '/system/updates');
        }
        try {
            (new Updater())->restoreDatabase($id);
            DB::insert('audit_logs', [
                'actor_user_id' => Auth::id(),
                'action' => 'database_restored',
                'subject_type' => 'system_update',
                'subject_id' => $id,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            return $this->withError('بازگردانی دیتابیس انجام نشد: ' . $e->getMessage(), '/system/updates');
        }

        return $this->withSuccess('دیتابیس به وضعیت پیش از آن به‌روزرسانی برگشت.', '/system/updates');
    }
}
