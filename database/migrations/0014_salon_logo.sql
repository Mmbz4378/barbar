-- لوگوی سالن (PRD F05).
--
-- فقط نام فایل ذخیره می‌شود نه مسیر کامل: اگر پروژه جابه‌جا شود یا از
-- زیرپوشه به ریشهٔ دامنه برود، مسیرِ ذخیره‌شده می‌شکند ولی نام فایل
-- نمی‌شکند.

ALTER TABLE salons
    ADD COLUMN logo_file VARCHAR(80) NULL DEFAULT NULL AFTER theme;
