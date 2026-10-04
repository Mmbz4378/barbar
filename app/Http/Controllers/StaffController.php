<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Identity\UserRepository;
use App\Domain\Staff\StaffRepository;
use App\Support\IranMobile;

final class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        $repo = new StaffRepository();
        $staff = $repo->all(Auth::salonId());

        return $this->page('layouts.panel', 'panel.staff.index', [
            'title' => 'آرایشگرها',
            'staff' => $staff,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->page('layouts.panel', 'panel.staff.form', ['title' => 'آرایشگر جدید', 'staff' => null]);
    }

    public function store(Request $request): Response
    {
        $salonId = Auth::salonId();
        $name = trim((string) $request->input('name', ''));
        $phoneRaw = trim((string) $request->input('phone', ''));
        $commission = $request->input('commission_percent');

        if ($name === '') {
            return $this->withError('نام آرایشگر را وارد کنید.', '/panel/staff/create');
        }

        $userId = null;
        if ($phoneRaw !== '') {
            $phone = IranMobile::tryParse($phoneRaw);
            if ($phone === null) {
                return $this->withError('شمارهٔ موبایل نامعتبر است.', '/panel/staff/create');
            }
            $user = (new UserRepository())->findOrCreate($phone);
            $userId = (int) $user['id'];

            $existingMembership = DB::selectOne(
                'SELECT id FROM salon_user WHERE salon_id = ? AND user_id = ?',
                [$salonId, $userId]
            );
            if (!$existingMembership) {
                DB::insert('salon_user', ['salon_id' => $salonId, 'user_id' => $userId, 'role' => 'staff']);
            }
        }

        $repo = new StaffRepository();
        $repo->create($salonId, [
            'user_id' => $userId,
            'name' => $name,
            'phone' => $phoneRaw !== '' ? IranMobile::parse($phoneRaw)->e164 : null,
            'commission_percent' => $commission !== '' && $commission !== null ? (float) $commission : null,
            'color' => (string) $request->input('color', '#2563eb'),
        ]);

        return $this->withSuccess('آرایشگر اضافه شد.', '/panel/staff');
    }

    public function edit(Request $request): Response
    {
        $repo = new StaffRepository();
        $staff = $repo->find(Auth::salonId(), (int) $request->param('id'));
        if ($staff === null) {
            return $this->withError('یافت نشد.', '/panel/staff');
        }

        return $this->page('layouts.panel', 'panel.staff.form', ['title' => 'ویرایش آرایشگر', 'staff' => $staff]);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new StaffRepository();
        $repo->update(Auth::salonId(), $id, [
            'name' => trim((string) $request->input('name', '')),
            'commission_percent' => $request->input('commission_percent') !== '' ? (float) $request->input('commission_percent') : null,
            'color' => (string) $request->input('color', '#2563eb'),
        ]);

        return $this->withSuccess('تغییرات ذخیره شد.', '/panel/staff');
    }

    public function toggle(Request $request): Response
    {
        $id = (int) $request->param('id');
        $repo = new StaffRepository();
        $staff = $repo->find(Auth::salonId(), $id);
        if ($staff !== null) {
            $repo->setActive(Auth::salonId(), $id, !((bool) $staff['is_active']));
        }

        return $this->redirect('/panel/staff');
    }
}
