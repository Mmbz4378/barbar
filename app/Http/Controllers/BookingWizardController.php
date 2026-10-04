<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Booking\BookingGuard;
use App\Domain\Booking\BookingService;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Identity\OtpService;
use App\Domain\Queue\QueueService;
use App\Domain\Staff\StaffRepository;
use App\Support\Clock;
use App\Support\IranMobile;
use App\Support\Jalali;
use App\Support\JalaliCalendar;
use DateTimeImmutable;
use RuntimeException;

/**
 * مسیر رزرو عمومی، بدون نصب و بدون حساب کاربری.
 *
 * ترتیب گام‌ها: روز -> سانس آزاد -> خدمت -> آرایشگر -> نام و شماره.
 *
 * چرا وقت اول می‌آید: چیزی که مشتری را سر دوراهی می‌گذارد «کِی
 * می‌توانم بیایم؟» است، نه «چه خدمتی می‌خواهم» — آن را از قبل
 * می‌داند. پس اول وقت قفل می‌شود، بعد جزئیات.
 *
 * سانس‌ها از طول سانسِ خود سالن ساخته می‌شوند، نه از مدت خدمت، چون
 * موقع نمایششان هنوز خدمتی انتخاب نشده. جا شدن خدمت در سانس را پنل
 * آرایشگاه بررسی می‌کند.
 */
final class BookingWizardController extends Controller
{
    /**
     * چند روز در نوار بالای صفحهٔ رزرو دیده شود.
     *
     * دو هفته: بلندتر از این، نوار افقی آن‌قدر دراز می‌شود که کسی تا
     * تهش نمی‌رود. برای دورتر، تقویم کامل هست.
     */
    private const DAY_STRIP_LENGTH = 14;

    /** گام ۱ — روز و سانس. */
    public function landing(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        // بازگشت به مرحلهٔ زمان، انتخاب روز، ساعت و خدمات را حفظ می‌کند.

        return $this->slotPicker($salon, $request, 1);
    }

    /** ثبت روز و سانسِ انتخاب‌شده و رفتن به گام خدمت. */
    public function chooseSlot(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        $date = (string) $request->input('date', '');
        $time = (string) $request->input('time', '');
        $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$validDate || $validDate->format('Y-m-d') !== $date || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return $this->withError('روز و ساعت را انتخاب کنید.', '/s/' . $salon['slug']);
        }

        $this->setWizard($salon['slug'], ['date' => $date, 'time' => $time]);

        return $this->redirect('/s/' . $salon['slug'] . '/services');
    }

    /**
     * منوی خدمات — فهرست خواندنی، بدون شروع رزرو.
     *
     * چرا جدا از صفحهٔ اصلی: آنجا خدمت‌ها چک‌باکس‌اند و هدفشان شروع
     * رزرو است. ولی خیلی از مشتری‌ها اول فقط می‌خواهند بدانند «چی
     * دارید و چند؟» — مخصوصاً وقتی لینک را در اینستاگرام دیده‌اند.
     * با فهرستِ رزرو، سؤالِ قیمت جواب داده نمی‌شود مگر اینکه وارد
     * جریان رزرو شوند.
     *
     * لینکِ جدا یعنی سالن می‌تواند همین را در بیو بگذارد.
     */
    public function menu(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        return $this->page('layouts.booking', 'booking.menu', [
            'title' => 'خدمات ' . $salon['name'],
            'salon' => $salon,
            'services' => (new ServiceRepository())->all((int) $salon['id'], true),
            'liveStatus' => $this->liveStatus((int) $salon['id']),
        ]);
    }

    /** گام ۲ — خدمت. روز و سانس از قبل انتخاب شده‌اند. */
    public function servicesStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['date']) || empty($wizard['time'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        if ($request->method === 'POST') {
            $serviceIds = array_values(array_filter(array_map('intval', (array) $request->input('service_ids', []))));
            if ($serviceIds === []) {
                return $this->withError('حداقل یک خدمت انتخاب کنید.', '/s/' . $salon['slug'] . '/services');
            }
            $serviceIds = array_values(array_unique($serviceIds));
            $repo = new ServiceRepository();
            foreach ($serviceIds as $id) {
                $service = $repo->find((int)$salon['id'],$id);
                if (!$service || !$service['is_active']) return $this->withError('یکی از خدمات انتخاب‌شده در دسترس نیست.','/s/'.$salon['slug'].'/services');
            }
            $this->setWizard($salon['slug'], ['service_ids' => $serviceIds]);

            return $this->redirect('/s/' . $salon['slug'] . '/staff');
        }

        return $this->page('layouts.booking', 'booking.services', [
            'title' => 'انتخاب خدمت',
            'step' => 2,
            'salon' => $salon,
            'services' => (new ServiceRepository())->all((int) $salon['id'], true),
            'slotLabel' => $this->slotLabel($wizard),
            'selected' => $wizard['service_ids'] ?? [],
        ]);
    }

    /** گام ۳ — آرایشگر، فقط آن‌هایی که همان سانس آزادند. */
    public function staffStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['service_ids']) || empty($wizard['date']) || empty($wizard['time'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        $free = $this->staffFreeAtSlot($salon, $wizard);
        if ($free === []) return $this->withError('زمان کافی برای این خدمات وجود ندارد. زمان دیگری انتخاب کن.', '/s/' . $salon['slug']);

        if ($request->method === 'POST') {
            $raw = (string) $request->input('staff_id', '');
            $staffId = $raw === '' ? null : (int) $raw;

            /*
             * انتخاب کاربر دوباره سنجیده می‌شود. فهرست را سرور ساخته،
             * ولی بین نمایش و ارسال ممکن است همان آرایشگر پر شده باشد
             * — و فرمِ دستکاری‌شده هم نباید آرایشگرِ اشغال را جا بیندازد.
             */
            if ($staffId !== null && !isset($free[$staffId])) {
                return $this->withError(
                    'این آرایشگر دیگر در آن ساعت آزاد نیست.',
                    '/s/' . $salon['slug'] . '/staff'
                );
            }

            $this->setWizard($salon['slug'], ['staff_id' => $staffId]);

            return $this->redirect('/s/' . $salon['slug'] . '/phone');
        }

        /*
         * هیچ آرایشگری آزاد نیست یعنی سانس بین گام اول و اینجا پر شده.
         * فرستادن به گام خدمت فایده ندارد؛ باید وقت دیگری بگیرد.
         */
        if ($free === []) {
            return $this->withError(
                'این ساعت همین الان پر شد. لطفاً ساعت دیگری انتخاب کنید.',
                '/s/' . $salon['slug']
            );
        }

        return $this->page('layouts.booking', 'booking.staff', [
            'title' => 'انتخاب آرایشگر',
            'step' => 3,
            'salon' => $salon,
            'staff' => array_values($free),
            'selectedStaffId' => $wizard['staff_id'] ?? null,
            'slotLabel' => $this->slotLabel($wizard),
        ]);
    }

    /**
     * آرایشگرهایی که در سانس انتخاب‌شده آزادند.
     *
     * @return array<int,array> کلید: شناسهٔ آرایشگر
     */
    private function staffFreeAtSlot(array $salon, array $wizard): array
    {
        $booking = new BookingService();
        $salonId = (int) $salon['id'];
        $byTime = $booking->freeSlotsForServices(
            $salonId, null, new DateTimeImmutable($wizard['date']), $wizard['service_ids']
        );

        $ids = $byTime[$wizard['time']] ?? [];
        if ($ids === []) {
            return [];
        }

        $free = [];
        foreach ((new StaffRepository())->all($salonId, true) as $st) {
            if (in_array((int) $st['id'], $ids, true)) {
                $free[(int) $st['id']] = $st;
            }
        }

        return $free;
    }

    /**
     * تقویم و سانس‌های آزاد — هم صفحهٔ اول است، هم صفحهٔ «عوض کردن وقت».
     *
     * سانس‌ها با طول سانسِ سالن ساخته می‌شوند، نه با مدت خدمت، چون در
     * این گام هنوز خدمتی انتخاب نشده.
     */
    private function slotPicker(array $salon, Request $request, int $step): Response
    {
        $salonId = (int) $salon['id'];
        $booking = new BookingService();
        $session = $booking->sessionMinutes($salonId);

        $savedWizard = $this->wizard($salon['slug']);
        $dateParam = (string) $request->query('date', $savedWizard['date'] ?? date('Y-m-d'));
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $dateParam);
        $today = new DateTimeImmutable('today');
        $max = $today->modify('+'.(int)Config::get('reshen.booking.max_days_ahead',30).' days');
        $date = $parsed && $parsed->format('Y-m-d') === $dateParam && $parsed >= $today && $parsed <= $max ? $parsed : $today;
        $dateParam = $date->format('Y-m-d');

        $days = $this->upcomingDays($salonId, $session, $date);

        /*
         * اگر کاربر روزی را انتخاب نکرده و امروز وقتی ندارد، برو روی
         * اولین روزی که دارد.
         *
         * چرا: سالن ساعت ۸ شب دیگر سانسی ندارد، و مشتری‌ای که همان موقع
         * لینک را باز می‌کند با «این روز سانس آزادی ندارد» روبه‌رو
         * می‌شد — درست در لحظه‌ای که تصمیم داشت نوبت بگیرد. حالا صفحه
         * روی نزدیک‌ترین روزِ آزاد باز می‌شود.
         *
         * فقط وقتی روز صراحتاً خواسته نشده باشد: اگر کسی روی «جمعه» زد
         * و جمعه پر بود، باید همان را ببیند، نه اینکه بی‌خبر جای دیگری
         * برود.
         */
        if ($request->query('date') === null && empty($savedWizard['date'])) {
            foreach ($days as $candidate) {
                if ($candidate['available']) {
                    if ($candidate['date'] !== $dateParam) {
                        $dateParam = $candidate['date'];
                        $date = new DateTimeImmutable($dateParam);

                        // فقط نشانهٔ انتخاب جابه‌جا می‌شود. محاسبهٔ دوبارهٔ
                        // نوار یعنی چهارده بار جست‌وجوی سانس آزاد، که روی
                        // هاست اشتراکی حس می‌شود.
                        foreach ($days as $i => $d) {
                            $days[$i]['selected'] = $d['date'] === $dateParam;
                        }
                    }
                    break;
                }
            }
        }

        $slotsByTime = $booking->freeSlots($salonId, null, $date, $session);

        /*
         * ماهی که تقویم نشان می‌دهد. پیش‌فرض ماهِ تاریخ انتخاب‌شده است،
         * ولی کاربر می‌تواند با دکمه‌های قبل/بعد جابه‌جا شود — پس ماه از
         * پارامتر آدرس هم خوانده می‌شود.
         */
        [$todayJy, $todayJm] = Jalali::fromDateTime(new DateTimeImmutable('today'));
        [$dateJy, $dateJm] = Jalali::fromDateTime($date);

        $viewYear = (int) $request->query('jy', (string) $dateJy);
        $viewMonth = (int) $request->query('jm', (string) $dateJm);
        if ($viewMonth < 1 || $viewMonth > 12) {
            $viewMonth = $dateJm;
            $viewYear = $dateJy;
        }

        /*
         * وضعیت هر روزِ ماه: آیا اصلاً وقتی آزاد دارد؟
         *
         * بدون این، تقویم روزهایی را قابل انتخاب نشان می‌دهد که سالن
         * تعطیل است یا همهٔ نوبت‌هایش پر شده — و مشتری بعد از دو کلیک
         * به صفحهٔ خالی می‌رسد. بهتر است همان اول ببیند کدام روزها باز است.
         */
        $dayStates = $this->monthAvailability($salonId, null, $session, $viewYear, $viewMonth);

        return $this->page('layouts.booking', 'booking.slots', [
            'days' => $days,
            'title' => $salon['name'],
            'step' => $step,
            'salon' => $salon,
            'calendar' => JalaliCalendar::month($viewYear, $viewMonth, $dayStates),
            'minMonth' => ['year' => $todayJy, 'month' => $todayJm],
            'selectedDate' => $dateParam,
            'selectedTime' => ($savedWizard['date'] ?? '') === $dateParam ? ($savedWizard['time'] ?? null) : null,
            'selectedDateLabel' => JalaliCalendar::relativeDate($date),
            'slots' => array_keys($slotsByTime),
            'liveStatus' => $this->liveStatus($salonId),
        ]);
    }

    /** برچسب «روز، ساعت» برای نشان دادن وقتِ قفل‌شده در گام‌های بعد. */
    private function slotLabel(array $wizard): ?string
    {
        if (empty($wizard['date']) || empty($wizard['time'])) {
            return null;
        }

        return JalaliCalendar::relativeDate(new DateTimeImmutable($wizard['date']))
            . '، ساعت ' . Clock::hm($wizard['time']);
    }

    /**
     * وضعیت لحظه‌ای سالن برای نمایش در صفحهٔ اول.
     *
     * این جواب سؤالی است که مشتری واقعاً در ذهن دارد: «الان برم یا
     * شلوغه؟» — و چیزی است که رقبا نمی‌توانند داشته باشند، چون دادهٔ
     * لحظه‌ای صف را ندارند.
     *
     * @return array{open:bool,waiting:int,freeNow:int,chairs:int,waitLabel:string}
     */
    private function liveStatus(int $salonId): array
    {
        $snapshot = (new QueueService())->salonSnapshot($salonId);

        $chairs = count($snapshot);
        $waiting = 0;
        $freeNow = 0;
        $soonest = null;

        foreach ($snapshot as $chair) {
            $queue = $chair['queue'] ?? [];
            $waiting += count(array_filter(
                $queue,
                static fn (array $a): bool => ($a['status'] ?? '') === 'queued'
            ));

            if ($queue === []) {
                $freeNow++;
                continue;
            }

            /*
             * سؤال مشتری این است: «اگر الان بیایم، کِی روی صندلی
             * می‌نشینم؟» — نه اینکه نفر آخرِ صف کِی شروع می‌کند.
             *
             * پس باید زمانِ **پایانِ** کار نفر آخر را حساب کرد، نه
             * زمان شروعش. (اولین نسخه شروع را گرفت و نتیجه «۰ دقیقه
             * انتظار» شد درحالی‌که سه نفر در صف بودند.)
             */
            $last = end($queue);
            $startsAt = $last['eta']['start_p50'] ?? null;
            $duration = (int) ($last['eta']['expected_p50'] ?? 30);

            if ($startsAt instanceof DateTimeImmutable) {
                $freeAt = $startsAt->modify('+' . $duration . ' minutes');
                if ($soonest === null || $freeAt < $soonest) {
                    $soonest = $freeAt;
                }
            }
        }

        $label = 'تخمین انتظار در دسترس نیست';
        if ($soonest !== null) {
            $minutes = (int) round(($soonest->getTimestamp() - time()) / 60);
            $label = $minutes <= 5
                ? 'تقریباً بدون انتظار'
                : 'حدود ' . Jalali::toPersianDigits((string) $minutes) . ' دقیقه انتظار';
        }

        return [
            'open' => $this->isOpenNow($salonId),
            'waiting' => $waiting,
            'freeNow' => $freeNow,
            'chairs' => $chairs,
            'waitLabel' => $label,
        ];
    }

    /** آیا الان ساعت کاری است؟ */
    private function isOpenNow(int $salonId): bool
    {
        $now = new DateTimeImmutable();
        $weekday = Jalali::weekday($now);

        $row = DB::selectOne(
            'SELECT 1 AS ok FROM working_hours
              WHERE salon_id = ? AND weekday = ? AND is_closed = 0
                AND ? BETWEEN opens_at AND closes_at
              LIMIT 1',
            [$salonId, $weekday, $now->format('H:i:s')]
        );

        return $row !== null;
    }

    /**
     * برای هر روز ماه، بگو وقت آزاد دارد یا نه.
     *
     * @return array<string,array{available:bool,label:string}>
     */
    /**
     * چند روز آیندهٔ نزدیک، با شمارِ سانس آزاد هرکدام.
     *
     * چرا این و نه تقویم ماهانه: تقویم ماه، سی خانه نشان می‌دهد که
     * بیست‌وچند تایش گذشته و خاکستری است. روی موبایل یعنی یک صفحهٔ
     * کامل اسکرول برای رسیدن به ساعت‌ها — و مشتری‌ای که لینک را از
     * اینستاگرام باز کرده، همان‌جا می‌رود.
     *
     * تقریباً همهٔ رزروها برای امروز تا چند روز آینده‌اند. پس همان‌ها
     * جلوی چشم می‌آیند و تقویم کامل پشت یک دکمه می‌ماند برای کسی که
     * واقعاً ماه بعد را می‌خواهد.
     *
     * شمارِ سانس هم نمایش داده می‌شود چون تصمیم را عوض می‌کند: «۱ سانس»
     * یعنی عجله کن، «۱۲ سانس» یعنی خیالت راحت.
     *
     * @return array<int,array{date:string,label:string,day:string,free:int,available:bool,selected:bool}>
     */
    private function upcomingDays(int $salonId, int $session, DateTimeImmutable $selected): array
    {
        $today = new DateTimeImmutable('today');
        $horizon = (int) Config::get('reshen.booking.max_days_ahead', 30);
        $span = min(self::DAY_STRIP_LENGTH, max(1, $horizon));

        $booking = new BookingService();
        $selectedKey = $selected->format('Y-m-d');
        $days = [];

        for ($i = 0; $i < $span; $i++) {
            $date = $today->modify('+' . $i . ' days');
            $key = $date->format('Y-m-d');
            [, , $jd] = Jalali::fromDateTime($date);
            $free = count($booking->freeSlots($salonId, null, $date, $session));

            /*
             * برچسب کوتاه: «امروز»، «فردا»، «پس‌فردا»، بعد نام روز هفته.
             *
             * relativeDate برای روزهای دورتر «جمعه ۳ مهر» می‌دهد که خودش
             * شمارهٔ روز را دارد — کنار شمارهٔ بزرگ زیرش، همان عدد دو بار
             * تکرار می‌شد.
             */
            $label = $i <= 2
                ? JalaliCalendar::relativeDate($date, $today)
                : Jalali::weekdayName($date);

            $days[] = [
                'date' => $key,
                'label' => $label,
                'day' => fa_num((string) $jd),
                'free' => $free,
                'available' => $free > 0,
                'selected' => $key === $selectedKey,
            ];
        }

        return $days;
    }

    private function monthAvailability(
        int $salonId,
        ?int $staffId,
        int $duration,
        int $jy,
        int $jm
    ): array {
        $today = new DateTimeImmutable('today');
        $horizon = $today->modify('+' . (int) Config::get('reshen.booking.max_days_ahead', 30) . ' days');
        $booking = new BookingService();

        $states = [];
        $daysInMonth = Jalali::daysInJalaliMonth($jy, $jm);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = Jalali::toDateTime($jy, $jm, $day);
            $key = $date->format('Y-m-d');

            if ($date < $today) {
                $states[$key] = ['available' => false, 'label' => 'گذشته'];
                continue;
            }

            if ($date > $horizon) {
                $states[$key] = ['available' => false, 'label' => 'هنوز باز نشده'];
                continue;
            }

            $free = $booking->freeSlots($salonId, $staffId, $date, $duration);
            $states[$key] = $free === []
                ? ['available' => false, 'label' => 'بدون وقت آزاد']
                : ['available' => true, 'label' => ''];
        }

        return $states;
    }

    /** گام ۴ — نام و شماره، و ثبت نهایی. */
    public function phoneStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        if ($salon === null) {
            return Response::html('سالن یافت نشد.', 404);
        }

        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['date']) || empty($wizard['time'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }
        if (empty($wizard['service_ids'])) {
            return $this->redirect('/s/' . $salon['slug'] . '/services');
        }

        if ($request->method === 'POST') {
            $phone = IranMobile::tryParse((string) $request->input('phone', ''));
            if ($phone === null) {
                return $this->withError('شمارهٔ موبایل نامعتبر است.', '/s/' . $salon['slug'] . '/phone');
            }
            $name = mb_substr(trim((string) $request->input('name', '')),0,120);
            $this->setWizard($salon['slug'], ['phone' => $phone->e164, 'name' => $name ?: null]);

            /*
             * پیش‌فرض: بدون کد تأیید، نوبت همین‌جا ثبت می‌شود (ت-۳۵).
             *
             * هر گام اضافه بخشی از مشتری‌ها را می‌ریزد، و کد تأیید یعنی
             * از برنامه بیرون برو و برگرد. جای آن را BookingGuard
             * می‌گیرد.
             */
            if (!Config::get('reshen.booking.verify_phone', false)) {
                return $this->finishBooking($salon, $request);
            }

            $result = (new OtpService())->request($phone, 'booking');
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'خطا در ارسال پیامک', '/s/' . $salon['slug'] . '/phone');
            }

            return $this->redirect('/s/' . $salon['slug'] . '/verify');
        }

        return $this->page('layouts.booking', 'booking.phone', [
            'title' => 'شمارهٔ موبایل',
            'step' => 4,
            'salon' => $salon,
            'savedPhone' => $wizard['phone'] ?? '',
            'savedName' => $wizard['name'] ?? '',
            'needsVerification' => (bool) Config::get('reshen.booking.verify_phone', false),
            'summary' => $this->wizardSummary($salon, $wizard),
        ]);
    }

    public function verifyStep(Request $request): Response
    {
        $salon = $this->salonOrFail((string) $request->param('slug'));
        $wizard = $this->wizard($salon['slug']);
        if (empty($wizard['phone'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        if ($request->method === 'POST') {
            $code = (string) $request->input('code', '');
            $phone = IranMobile::parse($wizard['phone']);
            $result = (new OtpService())->verify($phone, $code, 'booking');
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/s/' . $salon['slug'] . '/verify');
            }

            return $this->finishBooking($salon, $request);
        }

        return $this->page('layouts.booking', 'booking.verify', [
            'title' => 'تأیید کد',
            'salon' => $salon,
            'phone' => $wizard['phone'],
            'debugLine' => OtpService::devHint($wizard['phone']),
        ]);
    }

    /**
     * ثبت نهایی نوبت — مسیر مشترکِ با و بدون کد تأیید.
     *
     * هر دو راه به اینجا می‌رسند تا منطقِ ساخت نوبت یک جا بماند. اگر
     * دو نسخه می‌داشت، روزی یکی‌شان اصلاح می‌شد و دیگری نه.
     */
    /**
     * خلاصهٔ انتخاب‌ها برای آخرین گام.
     *
     * مشتری پیش از دادن شماره باید ببیند چه چیزی را تأیید می‌کند. سه
     * صفحه قبل خدمت را انتخاب کرده و یادش نیست — و اگر اشتباه باشد،
     * پس از ثبت می‌فهمد که دیرِ کار است.
     *
     * @return array<string,string>
     */
    private function wizardSummary(array $salon, array $wizard): array
    {
        if (empty($wizard['date']) || empty($wizard['time'])) {
            return [];
        }

        $summary = [];

        if (!empty($wizard['service_ids'])) {
            $repo = new ServiceRepository();
            $names = [];
            $total = 0;
            foreach ($wizard['service_ids'] as $id) {
                $service = $repo->find((int) $salon['id'], (int) $id);
                if ($service !== null) {
                    $names[] = $service['name'];
                    $total += !empty($wizard['staff_id']) ? $repo->effective((int)$salon['id'],(int)$wizard['staff_id'],(int)$id)['price'] : (int)$service['price'];
                }
            }
            if ($names !== []) {
                $summary['خدمت'] = implode('، ', $names);
                if (!empty($wizard['staff_id'])) $summary['هزینه'] = toman($total);
                else {
                    $free = $this->staffFreeAtSlot($salon,$wizard); $prices=[];
                    foreach ($free as $member) {
                        $sum=0;foreach($wizard['service_ids'] as $sid)$sum+=$repo->effective((int)$salon['id'],(int)$member['id'],(int)$sid)['price'];$prices[]=$sum;
                    }
                    $summary['هزینه'] = $prices ? (min($prices)===max($prices)?toman(min($prices)):toman(min($prices)).' تا '.toman(max($prices))) : toman($total);
                }
            }
        }

        if (!empty($wizard['staff_id'])) {
            $staff = DB::selectOne('SELECT name FROM staff WHERE id = ? AND salon_id = ?', [$wizard['staff_id'], $salon['id']]);
            if ($staff !== null) {
                $summary['آرایشگر'] = $staff['name'];
            }
        }

        $summary['آرایشگر'] ??= 'هر آرایشگر آزاد';
        $durations = [];
        $candidates = !empty($wizard['staff_id']) ? [['id'=>(int)$wizard['staff_id']]] : $this->staffFreeAtSlot($salon, $wizard);
        foreach ($candidates as $candidate) {
            $minutes = 0;
            foreach ($wizard['service_ids'] ?? [] as $serviceId) {
                $minutes += (new ServiceRepository())->effective((int)$salon['id'], (int)$candidate['id'], (int)$serviceId)['duration_minutes'];
            }
            $durations[] = $minutes;
        }
        if ($durations) $summary['مدت تقریبی'] = Jalali::toPersianDigits((string) min($durations)) . (min($durations) !== max($durations) ? ' تا ' . Jalali::toPersianDigits((string) max($durations)) : '') . ' دقیقه';
        $date = new DateTimeImmutable($wizard['date']);
        $summary['زمان'] = JalaliCalendar::relativeDate($date) . '، ساعت ' . Clock::hm($wizard['time']);

        return $summary;
    }

    private function finishBooking(array $salon, Request $request): Response
    {
        $slug = $salon['slug'];
        $wizard = $this->wizard($slug);

        if (empty($wizard['phone']) || empty($wizard['date']) || empty($wizard['time'])) {
            return $this->redirect('/s/' . $slug);
        }

        // حفاظ ضدِ سوءاستفاده — جای کاری که کد تأیید می‌کرد
        $guard = (new BookingGuard())->check((int) $salon['id'], $wizard['phone'], $request->ip());
        if (!$guard['ok']) {
            return $this->withError($guard['error'], '/s/' . $slug . '/phone');
        }

        try {
            $appointment = (new BookingService())->createBooking(
                (int) $salon['id'],
                $wizard['staff_id'] ?? null,
                $wizard['service_ids'],
                new DateTimeImmutable($wizard['date']),
                $wizard['time'],
                $wizard['phone'],
                $wizard['name'] ?? null,
                $request->ip(),
            );
        } catch (RuntimeException $e) {
            /*
             * سانس بین انتخاب و ثبت پر شده. مشتری باید وقت دیگری
             * بگیرد، ولی خدمت و آرایشگرش در نشست می‌ماند تا دوباره
             * انتخابشان نکند.
             */
            return $this->withError($e->getMessage(), '/s/' . $slug);
        }

        Session::forget($this->wizardKey($slug));

        return $this->redirect('/q/' . $appointment['public_token']);
    }

    private function salonOrFail(string $slug): ?array
    {
        return DB::selectOne('SELECT * FROM salons WHERE slug = ? AND is_active = 1', [$slug]);
    }

    private function wizardKey(string $slug): string
    {
        return 'booking_' . $slug;
    }

    private function wizard(string $slug): array
    {
        return Session::get($this->wizardKey($slug), []);
    }

    /** @param string[] $keys */
    private function forgetWizard(string $slug, array $keys): void
    {
        $wizard = $this->wizard($slug);
        foreach ($keys as $k) {
            unset($wizard[$k]);
        }
        Session::put($this->wizardKey($slug), $wizard);
    }

    private function setWizard(string $slug, array $data): void
    {
        Session::put($this->wizardKey($slug), array_merge($this->wizard($slug), $data));
    }
}
