<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * All business tunables for the queue/ETA engine and messaging rules live
 * here per the product spec (section 8.6 / 11.3) — never as magic numbers
 * scattered through the domain code.
 */
return [

    'ui' => [
        /*
         * خانوادهٔ فونت. «vazirmatn» یا «iranyekan».
         *
         * فایل‌های ایران‌یکان از دارایی محلی کاربر در پروژه قرار گرفته‌اند.
         * وزن‌ها در public/assets/fonts و تعریف فونت در reshen.css است.
         * راهنما: public/assets/fonts/README.md
         */
        'font' => Env::get('UI_FONT', 'iranyekan'),
    ],
    'queue' => [
        // Booked appointments outrank walk-ins from N minutes before to N after their slot.
        'priority_window_minutes' => 10,
        // Cleanup / cigarette buffer between customers.
        'buffer_minutes' => 5,
        // Never estimate "right now" — floor on remaining time for the person in the chair.
        'min_remaining_minutes' => 2,
        // Never promise more than 3 hours out — error is meaningless beyond that.
        'max_horizon_minutes' => 180,
    ],

    'estimation' => [
        // Minimum real samples of (staff, service) before trusting the learned percentile.
        'min_samples_for_learning' => 8,
        // Rolling window of most recent samples used to compute percentiles.
        'rolling_window_samples' => 200,
        // Outlier filtering: durations outside this range are discarded from stats.
        'outlier_min_minutes' => 5,
        // خدمات زنانه (رنگ و لایت، عروس) تا چند ساعت طول می‌کشند؛ سقف ۱۸۰
        // دقیقه همهٔ نمونه‌های واقعی آن‌ها را دور می‌ریخت.
        'outlier_max_minutes' => 480,
        // Customer personal duration factor: needs at least this many visits to activate.
        'customer_factor_min_visits' => 3,
        'customer_factor_min' => 0.7,
        'customer_factor_max' => 1.5,
        // Percentiles used for the promised window (p50 = lower bound, p80 = upper bound).
        'lower_percentile' => 50,
        'upper_percentile' => 80,
        // Fallback nominal duration in minutes when nothing else is known.
        'fallback_minutes' => 30,
    ],

    'display' => [
        // Range wider than this is capped and flagged as "rough estimate".
        'max_window_minutes' => 25,
        'imminent_threshold_minutes' => 15,
        'far_threshold_minutes' => 60,
    ],

    /*
     * پرداخت آنلاین.
     *
     * پیش‌فرض خاموش است چون پلتفرم فعلاً رایگان است (ت-۳۱). کد زرین‌پال
     * ساخته و آزموده شده تا روشن کردنش یک تغییر تنظیمات باشد نه یک
     * پروژه: PAYMENT_DRIVER=zarinpal به‌علاوهٔ شناسهٔ پذیرنده.
     *
     * واقعیتی که در سند معماری هم آمده: اکثر پول در آرایشگاه نقدی و
     * کارت‌به‌کارت است. درگاه آنلاین در این صنف حاشیه است، نه مرکز.
     */
    'booking' => [
        /*
         * تأیید شمارهٔ مشتری با کد پیامکی.
         *
         * پیش‌فرض **خاموش** است، و این یک تصمیم محصولی است نه یک
         * میان‌بُر (ت-۳۵):
         *
         *   هر گام اضافه در رزرو، بخشی از مشتری‌ها را می‌ریزد. کد
         *   تأیید یعنی: منتظر پیامک بمان، از برنامه بیرون برو، کد را
         *   بخوان، برگرد، تایپ کن. در صنفی که رقیبش «زنگ زدن» است،
         *   همین کافی است که مشتری بی‌خیال شود.
         *
         *   مشتری هم به دادهٔ حساسی دسترسی پیدا نمی‌کند؛ فقط نوبت
         *   می‌گیرد. اگر شماره اشتباه باشد، ضررش برای خودش است —
         *   یادآوری دریافت نمی‌کند.
         *
         *   و نکتهٔ عملی: تا وقتی الگوهای پیامک ثبت نشده‌اند، کد اصلاً
         *   نمی‌رسد و رزرو **غیرممکن** است.
         *
         * جای تأیید را محدودیت نرخ می‌گیرد (پایین).
         */
        'verify_phone' => Env::get('BOOKING_VERIFY_PHONE', 'false') === 'true',

        // سقف رزرو برای یک شماره در یک شبانه‌روز
        'max_per_phone_per_day' => 5,

        // سقف رزرو از یک IP در یک ساعت — جلوی ساختن انبوه نوبت با
        // شماره‌های الکی را می‌گیرد
        'max_per_ip_per_hour' => 10,

        // نوبت‌های آیندهٔ هم‌زمانِ یک شماره. بیشتر از این یعنی یا
        // اشتباه است یا سوءاستفاده.
        'max_open_per_phone' => 3,
    ],

    'uploads' => [
        // نسبت به public/ — باید از وب سرو شود، برخلاف storage/ که
        // عمداً بسته است.
        'logos_dir' => 'uploads/logos',
    ],

    'payment' => [
        'driver' => Env::get('PAYMENT_DRIVER', 'disabled'),

        'zarinpal' => [
            'merchant_id' => Env::get('ZARINPAL_MERCHANT_ID', ''),
            'sandbox' => Env::get('ZARINPAL_SANDBOX', 'false') === 'true',
        ],
    ],

    'sms' => [
        'driver' => Env::get('SMS_DRIVER', 'log'),

        /**
         * Approved-template ids, per provider. Iranian carriers will not
         * deliver a free-text OTP over a shared service line, so without a
         * registered template the login code silently never arrives — which
         * is why doc 8.7 says to start the approval paperwork in week zero,
         * not the last week. Leave empty in dev: the `log` driver ignores it.
         */
        /*
         * کلیدِ هر الگو با کدِ همان الگو در SmsTemplates یکی است. اگر
         * خالی بماند، آن پیامک فرستاده نمی‌شود — مگر سالن خط اختصاصی
         * داشته باشد (SMS_DEDICATED_LINE=true).
         *
         * فهرست کامل با متنی که باید در پنل اپراتور ثبت شود، در
         * /panel/sms هست.
         */
        'patterns' => [
            'melipayamak' => [
                'otp' => Env::get('SMS_PATTERN_MELIPAYAMAK_OTP', ''),
                'booking_confirmed' => Env::get('SMS_PATTERN_MELIPAYAMAK_BOOKING_CONFIRMED', ''),
                'booking_cancelled' => Env::get('SMS_PATTERN_MELIPAYAMAK_BOOKING_CANCELLED', ''),
                'reminder_24h' => Env::get('SMS_PATTERN_MELIPAYAMAK_REMINDER_24H', ''),
                'reminder_2h' => Env::get('SMS_PATTERN_MELIPAYAMAK_REMINDER_2H', ''),
                'queue_chair_ready' => Env::get('SMS_PATTERN_MELIPAYAMAK_QUEUE_CHAIR_READY', ''),
                'queue_nearly_up' => Env::get('SMS_PATTERN_MELIPAYAMAK_QUEUE_NEARLY_UP', ''),
                'queue_delayed' => Env::get('SMS_PATTERN_MELIPAYAMAK_QUEUE_DELAYED', ''),
            ],
            'kavenegar' => [
                'otp' => Env::get('SMS_PATTERN_KAVENEGAR_OTP', ''),
                'booking_confirmed' => Env::get('SMS_PATTERN_KAVENEGAR_BOOKING_CONFIRMED', ''),
                'booking_cancelled' => Env::get('SMS_PATTERN_KAVENEGAR_BOOKING_CANCELLED', ''),
                'reminder_24h' => Env::get('SMS_PATTERN_KAVENEGAR_REMINDER_24H', ''),
                'reminder_2h' => Env::get('SMS_PATTERN_KAVENEGAR_REMINDER_2H', ''),
                'queue_chair_ready' => Env::get('SMS_PATTERN_KAVENEGAR_QUEUE_CHAIR_READY', ''),
                'queue_nearly_up' => Env::get('SMS_PATTERN_KAVENEGAR_QUEUE_NEARLY_UP', ''),
                'queue_delayed' => Env::get('SMS_PATTERN_KAVENEGAR_QUEUE_DELAYED', ''),
            ],
        ],

        /*
         * خط اختصاصی، متنِ آزاد را تحویل می‌دهد؛ خط خدماتی (۳۰۰۰، ۲۰۰۰،
         * ۹۸۲۱) نه. پیش‌فرض false است چون اشتباهِ true، پیامک‌ها را
         * بی‌صدا از بین می‌برد.
         */
        'dedicated_line' => Env::get('SMS_DEDICATED_LINE', 'false') === 'true',

        // کیف پیامک فعلاً اعمال نمی‌شود — پلتفرم رایگان است (ت-۲۶).
        'enforce_credit' => Env::get('SMS_ENFORCE_CREDIT', 'false') === 'true',

        // OTP abuse limits. A per-phone cooldown alone is not enough: one
        // attacker cycling many numbers from a single IP never trips it.
        'otp_hourly_limit_phone' => 5,
        'otp_hourly_limit_ip' => 15,

        'max_per_appointment' => 4,
        'quiet_hours_start' => 23,
        'quiet_hours_end' => 8,
        'nearly_up_window_min' => 20,
        'nearly_up_window_max' => 30,
        'delay_threshold_minutes' => 20,
        'reminder_hours_before' => [24, 2],
        'low_balance_thresholds' => [30, 15, 5],
        'emergency_credit' => 100,
    ],

    'discovery' => ['maps_enabled' => Env::get('MAPS_ENABLED', 'true') === 'true'],
];

