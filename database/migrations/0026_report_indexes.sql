-- گزارش‌های سراسری مدیر کل (همهٔ سالن‌ها با هم) بر اساس تاریخ فیلتر می‌کنند؛
-- ایندکس‌های موجود همه با salon_id شروع می‌شوند و برای بازهٔ زمانیِ کل
-- سامانه به کار نمی‌آیند. بدون این‌ها، گزارش با رشد داده کل جدول را می‌خواند.

ALTER TABLE appointments ADD INDEX idx_appt_scheduled (scheduled_at, status);
ALTER TABLE payments ADD INDEX idx_payments_paid (paid_at);
ALTER TABLE customers ADD INDEX idx_customers_created (created_at);
ALTER TABLE sms_messages ADD INDEX idx_sms_created (created_at, status);
ALTER TABLE users ADD INDEX idx_users_created (created_at);
ALTER TABLE salons ADD INDEX idx_salons_created (created_at);
