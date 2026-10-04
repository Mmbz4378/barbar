-- سانس‌بندی و استراحت.
--
-- چرا لازم شد: تا الان سالن فقط «ساعت باز» و «ساعت بسته» داشت. ولی
-- آرایشگاه واقعی وسط روز استراحت دارد (ناهار، نماز) و اگر سیستم آن را
-- نداند، مشتری برای ساعتی نوبت می‌گیرد که کسی سر کار نیست.
--
-- سانس هم قابل تنظیم نشده بود: بازهٔ ثابت در کد بود. سالنی که خدمتش
-- ۲۰ دقیقه است نباید سانس ۱۵ دقیقه‌ای ببیند.

ALTER TABLE working_hours
    ADD COLUMN break_start TIME NULL DEFAULT NULL AFTER closes_at,
    ADD COLUMN break_end   TIME NULL DEFAULT NULL AFTER break_start;

ALTER TABLE salons
    ADD COLUMN slot_step_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER theme;
