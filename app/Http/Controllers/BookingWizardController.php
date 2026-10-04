<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Domain\Booking\AvailabilityCache;
use App\Domain\Booking\BookingGuard;
use App\Domain\Booking\BookingPlanner;
use App\Domain\Booking\BookingService;
use App\Domain\Booking\SlotFinder;
use App\Domain\Catalog\ServiceRepository;
use App\Domain\Identity\OtpService;
use App\Domain\Queue\QueueService;
use App\Domain\Salon\SalonRepository;
use App\Support\Audience;
use App\Support\Clock;
use App\Support\IranMobile;
use App\Support\Jalali;
use App\Support\JalaliCalendar;
use App\Support\Now;
use App\Support\SalonContext;
use DateTimeImmutable;
use RuntimeException;

/**
 * مسیر رزرو عمومی، بدون نصب و بدون حساب کاربری.
 *
 * دو ترتیب دارد و هر سالن یکی را انتخاب می‌کند:
 *
 *   اول زمان  — زمان ← خدمت ← فرد ← اطلاعات. برای آرایشگاهی که تقریباً
 *               یک خدمت دارد و سؤال مشتری «کِی؟» است.
 *   اول خدمت — خدمت ← فرد ← زمان ← اطلاعات. برای سالنی که خدمت‌هایش از
 *               بیست دقیقه تا چند ساعت‌اند؛ تا خدمت معلوم نشود هیچ
 *               ساعتی را نمی‌شود قول داد.
 *
 * هر گام فقط وقتی باز می‌شود که گام‌های پیش از آن کامل باشند، و هر
 * انتخاب پیش از ثبت دوباره سنجیده می‌شود — فهرستی که سرور نشان داده
 * ممکن است بین نمایش و ارسال کهنه شده باشد.
 */
final class BookingWizardController extends Controller
{
    /** چند روز در نوار بالای صفحهٔ زمان دیده شود. */
    private const DAY_STRIP_LENGTH = 14;

    private const FLOWS = [
        'time_first' => ['time', 'services', 'staff', 'details'],
        'service_first' => ['services', 'staff', 'time', 'details'],
    ];

    private const STEP_PATHS = ['time' => '/time', 'services' => '/services', 'staff' => '/staff', 'details' => '/phone'];

    /** گام اول مسیرِ همان سالن. */
    public function landing(Request $request): Response
    {
        $salon = $this->salon($request);
        if ($salon === null) {
            return $this->notFound('این سالن پیدا نشد یا فعلاً نوبت نمی‌دهد.');
        }

        return $this->flow($salon)[0] === 'time'
            ? $this->renderTime($salon, $request)
            : $this->servicesStep($request);
    }

    /** نسخهٔ قدیمی فرم زمان به همین نشانی ارسال می‌شد؛ هنوز پذیرفته می‌شود. */
    public function chooseSlot(Request $request): Response
    {
        return $this->timeStep($request);
    }

    public function timeStep(Request $request): Response
    {
        $salon = $this->salon($request);
        if ($salon === null) {
            return $this->notFound('این سالن پیدا نشد یا فعلاً نوبت نمی‌دهد.');
        }
        if (($redirect = $this->guard($salon, 'time')) !== null) {
            return $redirect;
        }

        if ($request->method !== 'POST') {
            return $this->renderTime($salon, $request);
        }

        $date = (string) $request->input('date', '');
        $time = (string) $request->input('time', '');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return $this->withError('روز و ساعت را انتخاب کنید.', $this->stepUrl($salon, 'time') . '?date=' . rawurlencode($date));
        }

        $wizard = $this->wizard($salon);
        $start = new DateTimeImmutable($date . ' ' . $time);
        $planner = new BookingPlanner();

        // در مسیرِ اول-خدمت، ساعت همین‌جا با خدمت‌ها و فردِ انتخاب‌شده سنجیده می‌شود
        if (!empty($wizard['service_ids'])
            && $planner->plan((int) $salon['id'], $wizard['service_ids'], $start, $wizard['staff_id'] ?? null, true) === null) {
            return $this->withError('این ساعت همین حالا پر شد. ساعت دیگری انتخاب کنید.', $this->stepUrl($salon, 'time') . '?date=' . $date);
        }

        $changes = ['date' => $date, 'time' => $time];
        if ($this->flowName($salon) === 'time_first') {
            // فرد انتخابی دوباره سنجیده می‌شود، چون ممکن است ساعت تازه آزاد نباشد
            $changes['staff_chosen'] = false;
        }
        $this->setWizard($salon, $changes);

        return $this->redirect($this->nextUrl($salon, 'time'));
    }

    /**
     * منوی خدمات — فهرست خواندنی، بدون شروع رزرو.
     *
     * لینکِ جدا یعنی سالن می‌تواند همین را در بیو بگذارد.
     */
    public function menu(Request $request): Response
    {
        $salon = $this->salon($request);
        if ($salon === null) {
            return $this->notFound('این سالن پیدا نشد یا فعلاً نوبت نمی‌دهد.');
        }

        // منو برای همه یکی است و فرمی ندارد؛ کمی بلندتر کش می‌شود.
        return $this->page('layouts.booking', 'booking.menu', [
            'title' => 'خدمات ' . $salon['name'],
            'salon' => $salon,
            'groups' => (new ServiceRepository())->grouped((int) $salon['id'], true),
        ])->publicCache(60, 120);
    }

    public function servicesStep(Request $request): Response
    {
        $salon = $this->salon($request);
        if ($salon === null) {
            return $this->notFound('این سالن پیدا نشد یا فعلاً نوبت نمی‌دهد.');
        }
        if (($redirect = $this->guard($salon, 'services')) !== null) {
            return $redirect;
        }

        $salonId = (int) $salon['id'];
        $wizard = $this->wizard($salon);
        $planner = new BookingPlanner();

        if ($request->method === 'POST') {
            $serviceIds = BookingPlanner::normalizeIds((array) $request->input('service_ids', []));
            if (($error = $planner->validate($salonId, $serviceIds, true)) !== null) {
                return $this->withError($error, $this->stepUrl($salon, 'services'));
            }

            $changes = ['service_ids' => $serviceIds, 'staff_chosen' => false];
            if ($this->flowName($salon) === 'service_first' && ($wizard['service_ids'] ?? []) !== $serviceIds) {
                // مدت عوض شده؛ ساعتِ قبلی دیگر تضمینی ندارد
                $changes['time'] = null;
            }
            $this->setWizard($salon, $changes);

            return $this->redirect($this->nextUrl($salon, 'services'));
        }

        $services = (new ServiceRepository())->grouped($salonId, true);

        return $this->page('layouts.booking', 'booking.services', [
            'wide' => true,
            'title' => 'انتخاب خدمت',
            'salon' => $salon,
            'stepper' => $this->stepper($salon, 'services'),
            'groups' => $services,
            'selected' => $wizard['service_ids'] ?? [],
            'context' => $this->contextChips($salon, $wizard, 'services'),
        ]);
    }

    public function staffStep(Request $request): Response
    {
        $salon = $this->salon($request);
        if ($salon === null) {
            return $this->notFound('این سالن پیدا نشد یا فعلاً نوبت نمی‌دهد.');
        }
        if (($redirect = $this->guard($salon, 'staff')) !== null) {
            return $redirect;
        }

        $salonId = (int) $salon['id'];
        $wizard = $this->wizard($salon);
        $planner = new BookingPlanner();
        $serviceIds = $wizard['service_ids'];

        if (($error = $planner->validate($salonId, $serviceIds, true)) !== null) {
            $this->setWizard($salon, ['service_ids' => [], 'staff_chosen' => false]);

            return $this->withError($error, $this->stepUrl($salon, 'services'));
        }

        $choices = $this->staffChoices($salon, $wizard);

        /*
         * هیچ‌کس به‌تنهایی همهٔ خدمات را انجام نمی‌دهد: انتخاب فرد معنا
         * ندارد و برنامه بین چند نفر چیده می‌شود. این گام بی‌صدا رد
         * می‌شود و گام بعد توضیح می‌دهد چه کسی چه بخشی را انجام می‌دهد.
         */
        if ($choices['multi']) {
            $this->setWizard($salon, ['staff_id' => null, 'staff_chosen' => true]);

            return $this->redirect($this->nextUrl($salon, 'staff'));
        }

        if ($choices['staff'] === []) {
            $this->setWizard($salon, ['time' => null]);

            return $this->withError('برای این خدمات در ساعت انتخابی کسی آزاد نیست. ساعت دیگری انتخاب کنید.', $this->stepUrl($salon, 'time'));
        }

        if ($request->method === 'POST') {
            $raw = (string) $request->input('staff_id', '');
            $staffId = $raw === '' ? null : (int) $raw;
            if ($staffId !== null && !isset($choices['staff'][$staffId])) {
                return $this->withError('این فرد دیگر برای انتخاب شما در دسترس نیست. دوباره انتخاب کنید.', $this->stepUrl($salon, 'staff'));
            }

            $changes = ['staff_id' => $staffId, 'staff_chosen' => true];
            if ($this->flowName($salon) === 'service_first' && ($wizard['staff_id'] ?? null) !== $staffId) {
                $changes['time'] = null;
            }
            $this->setWizard($salon, $changes);

            return $this->redirect($this->nextUrl($salon, 'staff'));
        }

        return $this->page('layouts.booking', 'booking.staff', [
            'wide' => true,
            'title' => Audience::term('staff_question'),
            'salon' => $salon,
            'stepper' => $this->stepper($salon, 'staff'),
            'staff' => array_values($choices['staff']),
            'prices' => $choices['prices'],
            'selectedStaffId' => !empty($wizard['staff_chosen']) || isset($wizard['staff_id']) ? ($wizard['staff_id'] ?? null) : null,
            'context' => $this->contextChips($salon, $wizard, 'staff'),
            'timeKnown' => !empty($wizard['time']),
        ]);
    }

    /** گام آخر — مرور، شماره و ثبت نهایی. */
    public function phoneStep(Request $request): Response
    {
        $salon = $this->salon($request);
        if ($salon === null) {
            return $this->notFound('این سالن پیدا نشد یا فعلاً نوبت نمی‌دهد.');
        }
        if (($redirect = $this->guard($salon, 'details')) !== null) {
            return $redirect;
        }

        $wizard = $this->wizard($salon);
        $plan = $this->currentPlan($salon, $wizard);
        if ($plan === null) {
            $this->setWizard($salon, ['time' => null, 'staff_chosen' => $this->flowName($salon) === 'service_first' ? ($wizard['staff_chosen'] ?? false) : false]);

            return $this->withError('ساعت انتخابی دیگر آزاد نیست. لطفاً ساعت دیگری انتخاب کنید.', $this->stepUrl($salon, 'time') . '?date=' . ($wizard['date'] ?? ''));
        }

        if ($request->method === 'POST') {
            $phone = IranMobile::tryParse((string) $request->input('phone', ''));
            $name = mb_substr(trim((string) $request->input('name', '')), 0, 120);
            $note = mb_substr(trim((string) $request->input('note', '')), 0, 300);
            $this->setWizard($salon, ['name' => $name ?: null, 'note' => $note ?: null]);

            if ($phone === null) {
                Session::flash('field_errors', ['phone' => 'شمارهٔ موبایل را کامل وارد کنید؛ مثل ۰۹۱۲۳۴۵۶۷۸۹.']);

                return $this->withError('شمارهٔ موبایل نامعتبر است.', $this->stepUrl($salon, 'details'));
            }
            $this->setWizard($salon, ['phone' => $phone->e164]);

            if (!Config::get('reshen.booking.verify_phone', false)) {
                return $this->finishBooking($salon, $request);
            }

            $result = (new OtpService())->request($phone, 'booking');
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'ارسال پیامک ممکن نشد. کمی بعد دوباره تلاش کنید.', $this->stepUrl($salon, 'details'));
            }

            return $this->redirect('/s/' . $salon['slug'] . '/verify');
        }

        return $this->page('layouts.booking', 'booking.phone', [
            'wide' => true,
            'title' => 'مرور و ثبت نوبت',
            'salon' => $salon,
            'stepper' => $this->stepper($salon, 'details'),
            'plan' => $plan,
            'wizard' => $wizard,
            'needsVerification' => (bool) Config::get('reshen.booking.verify_phone', false),
            'depositActive' => $plan['deposit'] > 0 && trim((string) $salon['deposit_card_number']) !== '',
            'cancelNotice' => (new SlotFinder())->settings((int) $salon['id'])['cancel_notice'],
            'editLinks' => [
                'services' => $this->stepUrl($salon, 'services'),
                'staff' => $this->stepUrl($salon, 'staff'),
                'time' => $this->stepUrl($salon, 'time') . '?date=' . ($wizard['date'] ?? ''),
            ],
        ]);
    }

    public function verifyStep(Request $request): Response
    {
        $salon = $this->salon($request);
        if ($salon === null) {
            return $this->notFound('این سالن پیدا نشد یا فعلاً نوبت نمی‌دهد.');
        }

        $wizard = $this->wizard($salon);
        if (empty($wizard['phone'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        if ($request->method === 'POST') {
            $phone = IranMobile::parse($wizard['phone']);
            $result = (new OtpService())->verify($phone, (string) $request->input('code', ''), 'booking');
            if (!$result['ok']) {
                return $this->withError($result['error'] ?? 'کد نامعتبر است.', '/s/' . $salon['slug'] . '/verify');
            }

            return $this->finishBooking($salon, $request);
        }

        return $this->page('layouts.booking', 'booking.verify', [
            'wide' => true,
            'title' => 'تأیید شماره',
            'salon' => $salon,
            'stepper' => $this->stepper($salon, 'details'),
            'phone' => $wizard['phone'],
            'debugLine' => OtpService::devHint($wizard['phone']),
        ]);
    }

    private function finishBooking(array $salon, Request $request): Response
    {
        $wizard = $this->wizard($salon);
        if (empty($wizard['phone']) || empty($wizard['date']) || empty($wizard['time']) || empty($wizard['service_ids'])) {
            return $this->redirect('/s/' . $salon['slug']);
        }

        // حفاظ ضدِ سوءاستفاده — جای کاری که کد تأیید می‌کرد
        $guard = (new BookingGuard())->check((int) $salon['id'], $wizard['phone'], $request->ip());
        if (!$guard['ok']) {
            return $this->withError($guard['error'], $this->stepUrl($salon, 'details'));
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
                ['online' => true, 'note' => $wizard['note'] ?? null],
            );
        } catch (RuntimeException $e) {
            /*
             * ساعت بین انتخاب و ثبت پر شده. خدمت و فرد در نشست می‌مانند
             * تا مشتری فقط ساعت را دوباره انتخاب کند.
             */
            $this->setWizard($salon, ['time' => null]);

            return $this->withError($e->getMessage(), $this->stepUrl($salon, 'time') . '?date=' . $wizard['date']);
        }

        Session::forget($this->wizardKey($salon));
        Session::flash('booked', true);

        return $this->redirect('/q/' . $appointment['public_token']);
    }

    // ─── گام زمان ─────────────────────────────────────────────────────

    private function renderTime(array $salon, Request $request): Response
    {
        $salonId = (int) $salon['id'];
        $wizard = $this->wizard($salon);
        $planner = new BookingPlanner();
        $serviceFirst = $this->flowName($salon) === 'service_first';
        $serviceIds = $serviceFirst ? ($wizard['service_ids'] ?? []) : [];
        $staffId = $serviceFirst ? ($wizard['staff_id'] ?? null) : null;

        /*
         * دسترسی‌پذیری برای نوار روزها، روز انتخاب‌شده و همهٔ روزهای تقویم
         * حساب می‌شود. از AvailabilityCache می‌گذرد تا هم تکرار نوار و تقویم در
         * همین درخواست حذف شود و هم بازدیدکننده‌های هم‌زمان یک محاسبه را تقسیم
         * کنند. فقط نمایش است؛ ثبت نوبت همیشه زیر قفل از نو حساب می‌شود.
         */
        $flowKey = md5((string) json_encode([$serviceFirst, array_values($serviceIds), $staffId]));
        $finder = static function (DateTimeImmutable $date, bool $firstOnly) use ($planner, $salonId, $serviceFirst, $serviceIds, $staffId, $flowKey): array {
            return AvailabilityCache::remember(
                $salonId,
                $date->format('Y-m-d') . ':' . ($firstOnly ? 1 : 0) . ':' . $flowKey,
                static fn (): array => $serviceFirst
                    ? $planner->availableTimes($salonId, $serviceIds, $date, $staffId, true, $firstOnly)
                    : $planner->openTimes($salonId, $date, true, $firstOnly)
            );
        };

        $today = Now::today();
        $horizon = (new SlotFinder())->settings($salonId)['horizon'];
        $max = $today->modify('+' . $horizon . ' days');

        $dateParam = (string) $request->query('date', $wizard['date'] ?? '');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $dateParam);
        $explicit = $parsed && $parsed->format('Y-m-d') === $dateParam && $parsed >= $today && $parsed <= $max;
        $date = $explicit ? $parsed : $today;

        // نوار روزها: فقط «دارد یا ندارد» — شمارش کامل هر روز گران است
        $days = [];
        $span = min(self::DAY_STRIP_LENGTH, $horizon + 1);
        for ($i = 0; $i < $span; $i++) {
            $day = $today->modify('+' . $i . ' days');
            [, , $jd] = Jalali::fromDateTime($day);
            $days[] = [
                'date' => $day->format('Y-m-d'),
                'label' => $i <= 2 ? JalaliCalendar::relativeDate($day, $today) : Jalali::weekdayName($day),
                'day' => fa_num((string) $jd),
                'month' => JalaliCalendar::MONTHS[Jalali::fromDateTime($day)[1]],
                'available' => $finder($day, true) !== [],
            ];
        }

        /*
         * اگر روزی صراحتاً خواسته نشده و امروز وقتی ندارد، اولین روزِ
         * دارای وقت باز می‌شود — مشتری‌ای که ساعت نه شب لینک را باز
         * می‌کند نباید با «امروز وقت آزاد ندارد» شروع کند.
         */
        if (!$explicit) {
            foreach ($days as $d) {
                if ($d['available']) {
                    $date = new DateTimeImmutable($d['date']);
                    break;
                }
            }
        }
        $dateKey = $date->format('Y-m-d');
        foreach ($days as $i => $d) {
            $days[$i]['selected'] = $d['date'] === $dateKey;
        }

        $slots = $finder($date, false);

        [$todayJy, $todayJm] = Jalali::fromDateTime($today);
        [$dateJy, $dateJm] = Jalali::fromDateTime($date);
        $viewYear = (int) $request->query('jy', (string) $dateJy);
        $viewMonth = (int) $request->query('jm', (string) $dateJm);
        if ($viewMonth < 1 || $viewMonth > 12 || $viewYear < $todayJy - 1 || $viewYear > $todayJy + 2) {
            [$viewYear, $viewMonth] = [$dateJy, $dateJm];
        }

        $dayStates = [];
        for ($d = 1, $n = Jalali::daysInJalaliMonth($viewYear, $viewMonth); $d <= $n; $d++) {
            $day = Jalali::toDateTime($viewYear, $viewMonth, $d);
            $key = $day->format('Y-m-d');
            if ($day < $today) {
                $dayStates[$key] = ['available' => false, 'label' => 'گذشته'];
            } elseif ($day > $max) {
                $dayStates[$key] = ['available' => false, 'label' => 'هنوز باز نشده'];
            } else {
                $dayStates[$key] = $finder($day, true) === []
                    ? ['available' => false, 'label' => 'بدون وقت آزاد']
                    : ['available' => true, 'label' => ''];
            }
        }

        $selectedTime = ($wizard['date'] ?? '') === $dateKey ? ($wizard['time'] ?? null) : null;

        return $this->page('layouts.booking', 'booking.slots', [
            'wide' => true,
            'title' => $serviceFirst ? 'انتخاب زمان' : $salon['name'],
            'salon' => $salon,
            'stepper' => $this->stepper($salon, 'time'),
            'days' => $days,
            'calendar' => JalaliCalendar::month($viewYear, $viewMonth, $dayStates),
            'minMonth' => ['year' => $todayJy, 'month' => $todayJm],
            'selectedDate' => $dateKey,
            'selectedTime' => in_array($selectedTime, $slots, true) ? $selectedTime : null,
            'selectedDateLabel' => JalaliCalendar::relativeDate($date),
            'slots' => $slots,
            'liveStatus' => $serviceFirst ? null : $this->liveStatus($salonId),
            'context' => $this->contextChips($salon, $wizard, 'time'),
            'formAction' => $this->stepUrl($salon, 'time'),
            'baseUrl' => $this->stepUrl($salon, 'time'),
            'isFirstStep' => !$serviceFirst,
        ]);
    }

    /**
     * وضعیت لحظه‌ای سالن: «الان برم یا شلوغه؟»
     *
     * @return array{open:bool,waiting:int,freeNow:int,chairs:int,waitLabel:string}
     */
    /**
     * «الان باز است و N صندلی خالی دارد» — نمایشی و تقریبی.
     *
     * از همان کش نمایشِ وقت‌های آزاد می‌گذرد: هر تغییر صف (نسخهٔ sched)
     * باطلش می‌کند و حداکثر یک دقیقه کهنه می‌ماند؛ در هجوم، بازدیدکننده‌های
     * هم‌زمان یک عکس از صف را تقسیم می‌کنند به‌جای اینکه هرکدام صف را بسازند.
     */
    private function liveStatus(int $salonId): array
    {
        return AvailabilityCache::remember($salonId, 'live', fn (): array => $this->computeLiveStatus($salonId));
    }

    private function computeLiveStatus(int $salonId): array
    {
        $snapshot = (new QueueService())->salonSnapshot($salonId);

        $waiting = 0;
        $freeNow = 0;
        $soonest = null;

        foreach ($snapshot as $chair) {
            $queue = $chair['queue'] ?? [];
            $waiting += count(array_filter($queue, static fn (array $a): bool => ($a['status'] ?? '') === 'queued'));

            if ($queue === []) {
                $freeNow++;
                continue;
            }

            // پایانِ کار نفر آخر، نه شروعش — «اگر الان بیایم کِی می‌نشینم؟»
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

        $label = '';
        if ($soonest !== null) {
            $minutes = (int) round(($soonest->getTimestamp() - time()) / 60);
            $label = $minutes <= 5 ? 'تقریباً بدون انتظار' : 'حدود ' . Jalali::toPersianDigits((string) $minutes) . ' دقیقه انتظار';
        }

        return [
            'open' => (new SalonRepository())->isOpenNow($salonId),
            'waiting' => $waiting,
            'freeNow' => $freeNow,
            'chairs' => count($snapshot),
            'waitLabel' => $label,
        ];
    }

    // ─── گام فرد ──────────────────────────────────────────────────────

    /**
     * @return array{multi:bool,staff:array<int,array>,prices:array<int,array{price:int,minutes:int}>}
     */
    private function staffChoices(array $salon, array $wizard): array
    {
        $salonId = (int) $salon['id'];
        $planner = new BookingPlanner();
        $serviceIds = $wizard['service_ids'];
        $capable = $planner->capableStaff($salonId, $serviceIds, true);

        if ($capable === []) {
            return ['multi' => true, 'staff' => [], 'prices' => []];
        }

        $timeKnown = !empty($wizard['date']) && !empty($wizard['time']);
        $start = $timeKnown ? new DateTimeImmutable($wizard['date'] . ' ' . $wizard['time']) : null;
        $matrix = (new ServiceRepository())->matrix($salonId);
        $services = new ServiceRepository();

        $staff = [];
        $prices = [];
        foreach ($capable as $id) {
            if ($start !== null && $planner->plan($salonId, $serviceIds, $start, $id, true) === null) {
                continue;
            }
            $staff[$id] = $matrix['staff'][$id];
            $price = 0;
            $minutes = 0;
            foreach ($serviceIds as $serviceId) {
                $e = $services->effective($salonId, $id, (int) $serviceId);
                $price += $e['price'];
                $minutes += $e['duration_minutes'];
            }
            $prices[$id] = ['price' => $price, 'minutes' => $minutes];
        }

        return ['multi' => false, 'staff' => $staff, 'prices' => $prices];
    }

    // ─── برنامه و خلاصه ───────────────────────────────────────────────

    private function currentPlan(array $salon, array $wizard): ?array
    {
        if (empty($wizard['date']) || empty($wizard['time']) || empty($wizard['service_ids'])) {
            return null;
        }

        return (new BookingPlanner())->plan(
            (int) $salon['id'],
            $wizard['service_ids'],
            new DateTimeImmutable($wizard['date'] . ' ' . $wizard['time']),
            $wizard['staff_id'] ?? null,
            true,
        );
    }

    /**
     * انتخاب‌های قبلی، بالای هر گام — با لینک تغییر.
     *
     * @return array<int,array{label:string,value:string,href:string}>
     */
    private function contextChips(array $salon, array $wizard, string $current): array
    {
        $flow = $this->flow($salon);
        $position = array_search($current, $flow, true);
        $chips = [];

        foreach (array_slice($flow, 0, (int) $position) as $step) {
            if ($step === 'time' && !empty($wizard['date']) && !empty($wizard['time'])) {
                $chips[] = [
                    'label' => 'زمان',
                    'value' => JalaliCalendar::relativeDate(new DateTimeImmutable($wizard['date'])) . '، ساعت ' . Clock::hm($wizard['time']),
                    'href' => $this->stepUrl($salon, 'time') . '?date=' . $wizard['date'],
                ];
            }
            if ($step === 'services' && !empty($wizard['service_ids'])) {
                $matrix = (new ServiceRepository())->matrix((int) $salon['id']);
                $names = array_filter(array_map(static fn ($id) => $matrix['services'][(int) $id]['name'] ?? null, $wizard['service_ids']));
                $chips[] = ['label' => 'خدمت', 'value' => implode('، ', $names), 'href' => $this->stepUrl($salon, 'services')];
            }
            if ($step === 'staff' && !empty($wizard['staff_chosen'])) {
                $matrix = (new ServiceRepository())->matrix((int) $salon['id']);
                $name = isset($wizard['staff_id']) ? ($matrix['staff'][(int) $wizard['staff_id']]['name'] ?? null) : null;
                $chips[] = ['label' => Audience::term('staff'), 'value' => $name ?? Audience::term('staff_any'), 'href' => $this->stepUrl($salon, 'staff')];
            }
        }

        return $chips;
    }

    /** @return array<int,array{key:string,label:string,state:string}> */
    private function stepper(array $salon, string $current): array
    {
        $labels = [
            'time' => 'زمان',
            'services' => 'خدمت',
            'staff' => Audience::term('staff'),
            'details' => Config::get('reshen.booking.verify_phone', false) ? 'تأیید' : 'اطلاعات',
        ];
        $flow = $this->flow($salon);
        $position = (int) array_search($current, $flow, true);

        $steps = [];
        foreach ($flow as $i => $step) {
            $steps[] = [
                'key' => $step,
                'label' => $labels[$step],
                'state' => $i < $position ? 'done' : ($i === $position ? 'current' : 'todo'),
                'href' => $i < $position ? $this->stepUrl($salon, $step) : null,
            ];
        }

        return $steps;
    }

    // ─── مسیر و نشست ──────────────────────────────────────────────────

    private function salon(Request $request): ?array
    {
        $salon = (new SalonRepository())->findActiveBySlug((string) $request->param('slug'));
        SalonContext::set($salon);

        return $salon;
    }

    private function flowName(array $salon): string
    {
        return (new SlotFinder())->settings((int) $salon['id'])['flow'];
    }

    /** @return string[] */
    private function flow(array $salon): array
    {
        return self::FLOWS[$this->flowName($salon)];
    }

    private function stepUrl(array $salon, string $step): string
    {
        // گام اول هر مسیر همان نشانی کوتاه سالن است که روی QR چاپ شده
        if ($this->flow($salon)[0] === $step && $step === 'time') {
            return '/s/' . $salon['slug'];
        }

        return '/s/' . $salon['slug'] . self::STEP_PATHS[$step];
    }

    private function nextUrl(array $salon, string $step): string
    {
        $flow = $this->flow($salon);
        $next = $flow[(int) array_search($step, $flow, true) + 1] ?? 'details';

        return $this->stepUrl($salon, $next);
    }

    /**
     * اگر گام‌های پیش از این کامل نیستند، به اولین گام ناقص برگرد.
     */
    private function guard(array $salon, string $step): ?Response
    {
        $wizard = $this->wizard($salon);
        foreach ($this->flow($salon) as $candidate) {
            if ($candidate === $step) {
                return null;
            }
            if (!$this->complete($candidate, $wizard)) {
                return $this->redirect($this->stepUrl($salon, $candidate));
            }
        }

        return null;
    }

    private function complete(string $step, array $wizard): bool
    {
        return match ($step) {
            'time' => !empty($wizard['date']) && !empty($wizard['time']),
            'services' => !empty($wizard['service_ids']),
            'staff' => !empty($wizard['staff_chosen']),
            default => false,
        };
    }

    private function wizardKey(array $salon): string
    {
        return 'booking_' . $salon['slug'];
    }

    private function wizard(array $salon): array
    {
        $wizard = Session::get($this->wizardKey($salon), []);

        return is_array($wizard) ? $wizard : [];
    }

    private function setWizard(array $salon, array $data): void
    {
        Session::put($this->wizardKey($salon), array_merge($this->wizard($salon), $data));
    }
}
