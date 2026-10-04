-- صندوق خروجی پیامک: فرستادن از مسیر درخواست کاربر بیرون می‌رود.
--
-- پیش از این، پیامک تأیید رزرو و اعلان صف درون همان درخواست فرستاده
-- می‌شد، با مهلت ۲۰ ثانیه برای هر اپراتور (و اپراتور پشتیبان پس از آن).
-- زیر هجوم که اپراتورها کند می‌شوند، هر رزرو یک پردازش PHP را تا ۴۰
-- ثانیه نگه می‌داشت و کل سایت می‌خوابید. حالا ردیف با وضعیت «queued»
-- ثبت و پس از رسیدن پاسخ به کاربر فرستاده می‌شود؛ cron هم هرچه مانده را
-- می‌فرستد.
--
--   sending          در حال فرستادن (مال یک پردازش است؛ claim_token)
--   attempts         تعداد تلاش؛ پس از ۳ بار نهایی failed می‌ماند
--   next_attempt_at  زمان تلاش دوباره پس از خطا (فاصلهٔ فزاینده)
--   args_json        پارامترهای مرتب‌شدهٔ الگو برای فرستادن بیرون از درخواست
--   claim_token      تا دو پردازش (وب و cron) یک پیامک را دو بار نفرستند
--   claimed_at       ردیفِ گیرکرده در sending پس از ۱۰ دقیقه به صف برمی‌گردد

ALTER TABLE sms_messages
    MODIFY status ENUM('queued','sending','sent','failed','skipped_quiet_hours','skipped_no_credit','skipped_rate_limit') NOT NULL DEFAULT 'queued',
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN next_attempt_at DATETIME NULL AFTER attempts,
    ADD COLUMN args_json TEXT NULL AFTER body,
    ADD COLUMN claim_token CHAR(16) NULL AFTER next_attempt_at,
    ADD COLUMN claimed_at DATETIME NULL AFTER claim_token,
    ADD INDEX idx_sms_outbox (status, next_attempt_at);
