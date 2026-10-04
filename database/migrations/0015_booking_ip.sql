-- IP سازندهٔ نوبت — برای محدودیت نرخ.
--
-- چرا لازم شد: با برداشتن کد تأیید (ت-۳۵)، دیگر چیزی جلوی اسکریپتی را
-- نمی‌گیرد که با شماره‌های ساختگی صف را پر کند. شمارش بر اساس شماره
-- کافی نیست، چون مهاجم هر بار شمارهٔ تازه می‌سازد.
--
-- IP ذخیره می‌شود نه چیز دیگری: دادهٔ شخصیِ اضافه جمع نمی‌کنیم و همین
-- هم پس از مدتی ارزش نگهداری ندارد.

ALTER TABLE appointments
    ADD COLUMN created_ip VARCHAR(45) NULL DEFAULT NULL AFTER cancel_reason,
    ADD INDEX idx_appt_ip_created (salon_id, created_ip, created_at);
