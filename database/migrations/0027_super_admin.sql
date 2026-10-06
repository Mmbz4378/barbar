-- مدیر ارشد: اولین مدیر کل سامانه. فقط او مدیر کل تازه می‌سازد یا برمی‌دارد،
-- روی حساب مدیرهای دیگر کار می‌کند و تنظیمات حساس (ورود، پیامک، پرداخت) را
-- عوض می‌کند. admin_granted_at نشان می‌دهد مدیریت کل از راه خود سامانه داده
-- شده؛ مدیرِ بی‌تاریخ یعنی پرچم مستقیم در دیتابیس عوض شده و داشبورد هشدار می‌دهد.

ALTER TABLE users
    ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_platform_admin,
    ADD COLUMN admin_granted_by BIGINT UNSIGNED NULL AFTER is_super_admin,
    ADD COLUMN admin_granted_at TIMESTAMP NULL AFTER admin_granted_by;

-- مدیرهای کنونی پیش از این نسخه از راه سامانه (نصاب، خط فرمان) ساخته شده‌اند
UPDATE users SET admin_granted_at = CURRENT_TIMESTAMP WHERE is_platform_admin = 1;

-- قدیمی‌ترین مدیر کلِ فعال، مدیر ارشد می‌شود (بعداً از پنل قابل انتقال است)
UPDATE users SET is_super_admin = 1
 WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM users WHERE is_platform_admin = 1 AND is_active = 1) AS first_admin);
