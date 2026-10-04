<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Support\Theme;
use App\Support\Version;

/**
 * گالری زندهٔ سیستم طراحی — همهٔ اجزا با همهٔ حالت‌ها در یک صفحه.
 *
 * دادهٔ نمونه ثابت است (بدون «امروز» و ساعت جاری) تا آزمون تصویری CI
 * (tests/ui/visual.spec.mjs) روزبه‌روز تغییر نکند.
 */
final class DesignSystemController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->page('layouts.platform', 'system.design', [
            'title' => 'سیستم طراحی',
            'themes' => Theme::all(),
            'version' => Version::current(),
        ]);
    }
}
