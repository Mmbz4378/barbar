<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Salon\HolidayRepository;
use App\Domain\Salon\WorkingHoursRepository;
use App\Domain\Staff\StaffRepository;
use App\Domain\Staff\TimeOffRepository;
use App\Support\Clock;
use App\Support\ImageUpload;
use App\Support\Jalali;
use App\Support\Str;
use App\Support\Theme;

final class SalonSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $salonId = Auth::salonId();
        $salon = DB::selectOne('SELECT * FROM salons WHERE id = ?', [$salonId]);
        $hours = (new WorkingHoursRepository())->salonDefaults($salonId);
        $holidayRepo = new HolidayRepository();
        $upcomingHolidays = $holidayRepo->upcoming(8);

        return $this->page('layouts.panel', 'panel.settings.index', [
            'title' => 'تنظیمات سالن',
            'salon' => $salon,
            'hours' => $hours,
            'weekdayNames' => ['شنبه', 'یک‌شنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'],
            'holidays' => $upcomingHolidays,
            'currentJalaliYear' => Jalali::fromDateTime(new \DateTimeImmutable())[0],
            'timeOffs' => (new TimeOffRepository())->upcoming($salonId),
            'staffList' => (new StaffRepository())->all($salonId, true),
        ]);
    }

    /**
     * ثبت مرخصی یا بستنِ یک بازه.
     *
     * تاریخ و ساعت از کامپوننت‌های شمسیِ خودمان می‌آید، نه از ورودی
     * تاریخِ مرورگر.
     */
    public function addTimeOff(Request $request): Response
    {
        $salonId = Auth::salonId();

        // رشتهٔ Y-m-d برمی‌گرداند، نه شیء تاریخ
        $date = jalali_date_from_request($request, 'off_date');
        if ($date === null) {
            return $this->withError('تاریخ را کامل انتخاب کنید.', '/panel/settings');
        }

        $from = Clock::fromParts($request->input('off_from_h'), $request->input('off_from_m'));
        $to = Clock::fromParts($request->input('off_to_h'), $request->input('off_to_m'));

        /*
         * ساعت خالی یعنی «کل روز». روزِ کامل را با ۰۰:۰۰ تا ۲۴:۰۰
         * می‌بندیم، نه ۰۰:۰۰ تا ۲۳:۵۹ — وگرنه نوبتِ ۲۳:۵۹ از تورِ
         * مرخصی رد می‌شد.
         */
        $start = new \DateTimeImmutable($date . ' ' . ($from ?? '00:00') . ':00');
        $end = $to !== null
            ? new \DateTimeImmutable($date . ' ' . $to . ':00')
            : (new \DateTimeImmutable($date . ' 00:00:00'))->modify('+1 day');

        $rawStaff = (string) $request->input('off_staff_id', '');
        $staffId = $rawStaff === '' ? null : (int) $rawStaff;

        $repo = new TimeOffRepository();
        $error = $repo->add($salonId, $staffId, $start, $end, trim((string) $request->input('off_reason', '')));
        if ($error !== null) {
            return $this->withError($error, '/panel/settings');
        }

        $clashes = $repo->clashingAppointments($salonId, $staffId, $start, $end);
        if ($clashes !== []) {
            return $this->withSuccess(
                'ثبت شد. توجه: ' . Jalali::toPersianDigits((string) count($clashes))
                    . ' نوبت در این بازه ثبت شده که باید خبرشان کنید.',
                '/panel/settings'
            );
        }

        return $this->withSuccess('بازه بسته شد.', '/panel/settings');
    }

    public function removeTimeOff(Request $request): Response
    {
        (new TimeOffRepository())->remove(Auth::salonId(), (int) $request->param('id'));

        return $this->withSuccess('بازه باز شد.', '/panel/settings');
    }

    public function updateProfile(Request $request): Response
    {
        $salonId = Auth::salonId();

        $slugError = null;
        $slug = $this->cleanSlug((string) $request->input('slug', ''), $salonId, $slugError);

        $fields = [
            'name' => trim((string) $request->input('name', '')),
            'city' => trim((string) $request->input('city', '')) ?: null,
            'address' => trim((string) $request->input('address', '')) ?: null,
            'phone' => trim((string) $request->input('phone', '')) ?: null,
            // Theme::resolve مقدار ناشناخته را به پیش‌فرض برمی‌گرداند، پس
            // چیزی جز پالت‌های تعریف‌شده در دیتابیس نمی‌نشیند.
            'theme' => Theme::resolve((string) $request->input('theme', '')),
        ];

        if ($fields['name']==='') return $this->withError('نام سالن را وارد کنید.','/panel/settings');
        $currentProfile = DB::selectOne('SELECT * FROM salons WHERE id=?',[$salonId]);
        foreach (['name','city','address','phone'] as $publicField) {
            if (($currentProfile[$publicField] ?? null) !== $fields[$publicField]) {$fields['publication_status']='draft';break;}
        }
        if ($slug !== null) {
            $fields['slug'] = $slug;
        }

        DB::update('salons', $fields, 'id = :id', ['id' => $salonId]);

        $logoMessage = $this->handleLogo($request);

        // نشست را تازه کن وگرنه پنل تا ورود بعدی رنگ قبلی را نشان می‌دهد
        Auth::setSalon(Auth::salonId());

        if ($logoMessage !== null) {
            return $this->withError($logoMessage, '/panel/settings');
        }

        if ($slugError !== null) {
            return $this->withError($slugError, '/panel/settings');
        }

        return $this->withSuccess('اطلاعات سالن ذخیره شد.', '/panel/settings');
    }

    /**
     * نشانی عمومی سالن — بخشی که در لینک دیده می‌شود.
     *
     * چرا اصلاً قابل ویرایش است: این نشانی روی QR پشت آینه چاپ می‌شود
     * و در واتساپ فرستاده می‌شود. هنگام ثبت‌نام از روی نام سالن ساخته
     * می‌شود، ولی حرف‌نویسی فارسی هیچ‌وقت دقیق نیست — «سالن» می‌شود
     * saln — و صاحب سالن باید بتواند درستش کند.
     *
     * تغییر ندادن هم یک تصمیم است: ورودی خالی یعنی «دست نزن»، وگرنه
     * هر بار ذخیرهٔ فرمِ پروفایل، لینک را عوض می‌کرد و QRهای چاپ‌شده
     * از کار می‌افتادند.
     *
     * @param string|null $error پیام خطا، اگر نشانی پذیرفته نشد
     */
    private function cleanSlug(string $raw, int $salonId, ?string &$error): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $slug = Str::slug($raw);

        if ($slug === '') {
            $error = 'نشانی عمومی باید دست‌کم یک حرف انگلیسی یا رقم داشته باشد.';

            return null;
        }

        $current = DB::selectOne('SELECT slug FROM salons WHERE id = ?', [$salonId]);
        if ($current !== null && $current['slug'] === $slug) {
            return null;
        }

        $taken = DB::selectOne(
            'SELECT id FROM salons WHERE slug = ? AND id <> ?',
            [$slug, $salonId]
        );
        if ($taken !== null) {
            $error = 'این نشانی قبلاً گرفته شده. یکی دیگر انتخاب کنید.';

            return null;
        }

        return $slug;
    }

    /**
     * لوگو: آپلود تازه یا حذف.
     *
     * پیام خطا برمی‌گرداند یا null. بقیهٔ فرم جدا ذخیره شده، پس
     * مشکل لوگو نباید نام و آدرسِ درست را هم دور بریزد — کاربر فرم را
     * پر کرده و اگر همه‌چیز برگردد، دوباره پرش می‌کند بی‌آنکه بفهمد چرا.
     */
    private function handleLogo(Request $request): ?string
    {
        $salonId = Auth::salonId();
        $dir = BASE_PATH . '/public/' . Config::get('reshen.uploads.logos_dir', 'uploads/logos');
        $current = DB::selectOne('SELECT logo_file FROM salons WHERE id = ?', [$salonId])['logo_file'] ?? null;

        if ($request->input('remove_logo') !== null) {
            ImageUpload::delete($dir, $current);
            DB::update('salons', ['logo_file' => null], 'id = :id', ['id' => $salonId]);

            return null;
        }

        $result = ImageUpload::saveImage($request->file('logo'), $dir, 'logo');

        if ($result['error'] !== null) {
            return $result['error'];
        }

        if (!$result['ok']) {
            return null; // چیزی آپلود نشده — عادی است
        }

        // فایل قبلی بعد از موفقیتِ فایل تازه پاک می‌شود، نه قبلش: اگر
        // ذخیره شکست بخورد، سالن بدون لوگو نمی‌ماند.
        ImageUpload::delete($dir, $current);
        DB::update('salons', ['logo_file' => $result['path']], 'id = :id', ['id' => $salonId]);

        return null;
    }

    public function updateHours(Request $request): Response
    {
        $repo = new WorkingHoursRepository();
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $closed = $request->input("closed_$weekday") !== null;

            // ساعت از دو <select> می‌آید (انتخابگر فارسی جایگزین
            // <input type="time"> شده). اگر چیزی نیامد، پیش‌فرض می‌نشیند
            // تا یک فرمِ ناقص، ساعت کاری روز را صفر نکند.
            $opens = Clock::fromParts(
                $request->input("opens_{$weekday}_h"),
                $request->input("opens_{$weekday}_m")
            ) ?? '09:00';
            $closes = Clock::fromParts(
                $request->input("closes_{$weekday}_h"),
                $request->input("closes_{$weekday}_m")
            ) ?? '21:00';

            // استراحت اختیاری است: نبودنش null می‌ماند، نه ۰۰:۰۰.
            $breakStart = Clock::fromParts(
                $request->input("break_start_{$weekday}_h"),
                $request->input("break_start_{$weekday}_m")
            );
            $breakEnd = Clock::fromParts(
                $request->input("break_end_{$weekday}_h"),
                $request->input("break_end_{$weekday}_m")
            );

            $repo->setSalonDay(Auth::salonId(), $weekday, $opens, $closes, $closed, $breakStart, $breakEnd);
        }

        // طول سانس: بین ۵ تا ۱۲۰ دقیقه. صفر، حلقهٔ تولید سانس را
        // بی‌نهایت می‌کند؛ SlotFinder هم حفاظ دارد ولی بهتر است مقدار
        // معیوب اصلاً ذخیره نشود.
        $step = (int) $request->input('slot_step_minutes', 15);
        DB::update('salons', ['slot_step_minutes' => max(5, min(120, $step ?: 15))],
            'id = :id', ['id' => Auth::salonId()]);

        return $this->withSuccess('ساعت کاری و سانس‌بندی ذخیره شد.', '/panel/settings');
    }

    public function seedHolidays(Request $request): Response
    {
        $year = (int) $request->input('jalali_year');
        (new HolidayRepository())->seedFixedHolidaysForYear($year);

        return $this->withSuccess('تعطیلات رسمی ثابت اضافه شد.', '/panel/settings');
    }

    public function addHoliday(Request $request): Response
    {
        // تاریخ از انتخابگر شمسی می‌آید (سه فیلد)، نه از ورودی میلادی.
        $date = jalali_date_from_request($request, 'date');
        $label = trim((string) $request->input('label', '')) ?: 'تعطیل';

        if ($date === null) {
            return $this->withError('تاریخ تعطیلی معتبر نبود.', '/panel/settings');
        }

        (new HolidayRepository())->add($date, $label);

        return $this->withSuccess('تعطیلی اضافه شد.', '/panel/settings');
    }

    public function removeHoliday(Request $request): Response
    {
        (new HolidayRepository())->remove((int) $request->param('id'));

        return $this->withSuccess('حذف شد.', '/panel/settings');
    }
}
