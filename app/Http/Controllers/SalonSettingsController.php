<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\HolidayRepository;
use App\Domain\Salon\SalonRepository;
use App\Domain\Salon\WorkingHoursRepository;
use App\Domain\Staff\StaffRepository;
use App\Domain\Staff\TimeOffRepository;
use App\Support\Audience;
use App\Support\Clock;
use App\Support\ImageUpload;
use App\Support\Jalali;
use App\Support\Str;
use App\Support\Theme;

/**
 * تنظیمات سالن در چهار بخش: مشخصات، ساعت کاری، قوانین رزرو، تعطیلی و مرخصی.
 *
 * تعطیلات رسمی سراسری فقط به‌دست مدیر پلتفرم تغییر می‌کند. پیش از این
 * هر صاحب سالنی می‌توانست به جدول مشترک تعطیلات چیزی اضافه یا از آن حذف
 * کند و آن تغییر برای همهٔ سالن‌ها اعمال می‌شد. تعطیلی اختصاصی هر سالن
 * حالا یک «بستن کل روز» در time_offs همان سالن است.
 */
final class SalonSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $salonId = (int) Auth::salonId();

        return $this->page('layouts.panel', 'panel.settings.index', [
            'title' => 'تنظیمات سالن',
            'salon' => (new SalonRepository())->find($salonId),
            'hours' => (new WorkingHoursRepository())->salonDefaults($salonId),
            'holidays' => (new HolidayRepository())->upcoming(12),
            'timeOffs' => (new TimeOffRepository())->upcoming($salonId),
            'staffList' => (new StaffRepository())->all($salonId, true),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $current = (new SalonRepository())->find($salonId) ?? [];
        $errors = [];

        $name = mb_substr(trim((string) $request->input('name', '')), 0, 150);
        if ($name === '') {
            $errors['name'] = 'نام سالن را وارد کنید.';
        }
        $audience = (string) $request->input('audience', $current['audience'] ?? 'men');
        if (!array_key_exists($audience, Audience::options())) {
            $errors['audience'] = 'نوع سالن را انتخاب کنید.';
        }
        $slugError = null;
        $slug = $this->cleanSlug((string) $request->input('slug', ''), $salonId, $slugError);
        if ($slugError !== null) {
            $errors['slug'] = $slugError;
        }
        if ($errors !== []) {
            return $this->invalid($request, $errors, '/panel/settings#profile');
        }

        $fields = [
            'name' => $name,
            'audience' => $audience,
            'city' => mb_substr(trim((string) $request->input('city', '')), 0, 80) ?: null,
            'address' => mb_substr(trim((string) $request->input('address', '')), 0, 255) ?: null,
            'phone' => preg_replace('/[^\d+]/', '', Jalali::fromPersianDigits(trim((string) $request->input('phone', '')))) ?: null,
            'theme' => Theme::resolve((string) $request->input('theme', '')),
        ];
        if ($slug !== null) {
            $fields['slug'] = $slug;
        }
        // تغییر اطلاعات عمومی سالنِ منتشرشده، بازبینی دوباره می‌خواهد ولی سالن را از فهرست برنمی‌دارد
        foreach (['name', 'city', 'address', 'phone'] as $public) {
            if (($current[$public] ?? null) !== $fields[$public] && ($current['publication_status'] ?? '') === 'rejected') {
                $fields['publication_status'] = 'draft';
                break;
            }
        }

        DB::update('salons', $fields, 'id = :id', ['id' => $salonId]);
        SalonRepository::forget($salonId);
        Auth::setSalon($salonId);

        $logoMessage = $this->handleLogo($request, $salonId, $current['logo_file'] ?? null);
        if ($logoMessage !== null) {
            return $this->withError($logoMessage, '/panel/settings#profile');
        }

        return $this->withSuccess('مشخصات سالن ذخیره شد.', '/panel/settings#profile');
    }

    public function updateHours(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $repo = new WorkingHoursRepository();
        $problems = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $closed = $request->input("closed_$weekday") !== null;
            $opens = Clock::fromParts($request->input("opens_{$weekday}_h"), $request->input("opens_{$weekday}_m")) ?? '09:00';
            $closes = Clock::fromParts($request->input("closes_{$weekday}_h"), $request->input("closes_{$weekday}_m")) ?? '21:00';
            if (!$closed && $closes <= $opens) {
                $problems[] = \App\Support\JalaliCalendar::WEEKDAY_NAMES[$weekday];
                $closed = true;
            }
            $repo->setSalonDay(
                $salonId,
                $weekday,
                $opens,
                $closes,
                $closed,
                Clock::fromParts($request->input("break_start_{$weekday}_h"), $request->input("break_start_{$weekday}_m")),
                Clock::fromParts($request->input("break_end_{$weekday}_h"), $request->input("break_end_{$weekday}_m")),
            );
        }

        $step = int_input($request->input('slot_step_minutes')) ?? 15;
        DB::update('salons', ['slot_step_minutes' => max(5, min(120, $step))], 'id = :id', ['id' => $salonId]);
        SalonRepository::forget($salonId);

        if ($problems !== []) {
            return $this->withError('ساعت پایان ' . implode('، ', $problems) . ' پیش از شروع بود؛ آن روز تعطیل ذخیره شد. دوباره بررسی کنید.', '/panel/settings#hours');
        }

        return $this->withSuccess('ساعت کاری و سانس‌بندی ذخیره شد.', '/panel/settings#hours');
    }

    public function updateRules(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $card = preg_replace('/\D/', '', Jalali::fromPersianDigits((string) $request->input('deposit_card_number', ''))) ?? '';
        if ($card !== '' && strlen($card) !== 16) {
            return $this->invalid($request, ['deposit_card_number' => 'شمارهٔ کارت باید ۱۶ رقم باشد.'], '/panel/settings#rules');
        }

        DB::update('salons', [
            'booking_flow' => $request->input('booking_flow') === 'service_first' ? 'service_first' : 'time_first',
            'booking_horizon_days' => max(1, min(365, int_input($request->input('booking_horizon_days')) ?? 30)),
            'min_notice_minutes' => max(0, min(10080, int_input($request->input('min_notice_minutes')) ?? 0)),
            'cancel_notice_minutes' => max(0, min(10080, int_input($request->input('cancel_notice_minutes')) ?? 0)),
            'observe_official_holidays' => $request->input('observe_official_holidays') === '1' ? 1 : 0,
            'deposit_card_number' => $card !== '' ? $card : null,
            'deposit_card_holder' => mb_substr(trim((string) $request->input('deposit_card_holder', '')), 0, 120) ?: null,
            'deposit_hold_minutes' => max(15, min(4320, int_input($request->input('deposit_hold_minutes')) ?? 120)),
        ], 'id = :id', ['id' => $salonId]);
        SalonRepository::forget($salonId);

        return $this->withSuccess('قوانین رزرو ذخیره شد.', '/panel/settings#rules');
    }

    /** مرخصی یک نفر یا بستن کل سالن (روزِ کامل یا چند ساعت، یک یا چند روز). */
    public function addTimeOff(Request $request): Response
    {
        $salonId = (int) Auth::salonId();
        $date = jalali_date_from_request($request, 'off_date');
        if ($date === null) {
            return $this->withError('تاریخ را کامل انتخاب کنید.', '/panel/settings#closures');
        }
        $until = jalali_date_from_request($request, 'off_until') ?? $date;
        if ($until < $date) {
            return $this->withError('تاریخ پایان پیش از تاریخ شروع است.', '/panel/settings#closures');
        }

        $allDay = $request->input('off_all_day') === '1';
        $from = $allDay ? null : Clock::fromParts($request->input('off_from_h'), $request->input('off_from_m'));
        $to = $allDay ? null : Clock::fromParts($request->input('off_to_h'), $request->input('off_to_m'));
        if (!$allDay && ($from === null || $to === null)) {
            return $this->withError('برای مرخصی ساعتی، ساعت شروع و پایان را انتخاب کنید.', '/panel/settings#closures');
        }

        $start = new \DateTimeImmutable($date . ' ' . ($from ?? '00:00') . ':00');
        $end = $to !== null
            ? new \DateTimeImmutable($until . ' ' . $to . ':00')
            : (new \DateTimeImmutable($until . ' 00:00:00'))->modify('+1 day');

        $rawStaff = (string) $request->input('off_staff_id', '');
        $staffId = $rawStaff === '' ? null : (int) $rawStaff;

        $repo = new TimeOffRepository();
        $error = $repo->add($salonId, $staffId, $start, $end, mb_substr(trim((string) $request->input('off_reason', '')), 0, 150));
        if ($error !== null) {
            return $this->withError($error, '/panel/settings#closures');
        }

        $clashes = $repo->clashingAppointments($salonId, $staffId, $start, $end);

        return $clashes !== []
            ? $this->withSuccess('ثبت شد. توجه: ' . fa_num(count($clashes)) . ' نوبت در این بازه ثبت شده؛ از «رزروها» با مشتری‌ها هماهنگ یا لغوشان کنید.', '/panel/settings#closures')
            : $this->withSuccess('بازه بسته شد و در رزرو آنلاین نمایش داده نمی‌شود.', '/panel/settings#closures');
    }

    public function removeTimeOff(Request $request): Response
    {
        (new TimeOffRepository())->remove((int) Auth::salonId(), (int) $request->param('id'));

        return $this->withSuccess('بازه دوباره باز شد.', '/panel/settings#closures');
    }

    /**
     * نشانی عمومی سالن. ورودی خالی یعنی «دست نزن»، وگرنه QRهای چاپ‌شده
     * با هر ذخیره از کار می‌افتادند.
     */
    private function cleanSlug(string $raw, int $salonId, ?string &$error): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $slug = substr(Str::slug($raw), 0, 60);
        if ($slug === '') {
            $error = 'نشانی باید دست‌کم یک حرف انگلیسی یا رقم داشته باشد.';

            return null;
        }
        $current = DB::selectOne('SELECT slug FROM salons WHERE id = ?', [$salonId]);
        if ($current !== null && $current['slug'] === $slug) {
            return null;
        }
        if (DB::selectOne('SELECT id FROM salons WHERE slug = ? AND id <> ?', [$slug, $salonId]) !== null) {
            $error = 'این نشانی قبلاً گرفته شده؛ یکی دیگر انتخاب کنید.';

            return null;
        }

        return $slug;
    }

    private function handleLogo(Request $request, int $salonId, ?string $current): ?string
    {
        $dir = BASE_PATH . '/public/' . Config::get('reshen.uploads.logos_dir', 'uploads/logos');

        if ($request->input('remove_logo') !== null) {
            ImageUpload::delete($dir, $current);
            DB::update('salons', ['logo_file' => null], 'id = :id', ['id' => $salonId]);
            SalonRepository::forget($salonId);

            return null;
        }

        $result = ImageUpload::saveImage($request->file('logo'), $dir, 'logo');
        if ($result['error'] !== null) {
            return $result['error'];
        }
        if (!$result['ok']) {
            return null;
        }
        ImageUpload::delete($dir, $current);
        DB::update('salons', ['logo_file' => $result['path']], 'id = :id', ['id' => $salonId]);
        SalonRepository::forget($salonId);

        return null;
    }
}
