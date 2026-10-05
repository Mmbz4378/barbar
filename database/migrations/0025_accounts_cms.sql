-- حساب‌های رمزدار، تاریخچهٔ ورود، صفحه‌های محتوایی (نسخهٔ ۱۵)
--
-- ورود به پنل تا امروز فقط با کد پیامکی بود. حالا هر کاربر پنل می‌تواند
-- نام کاربری و رمز هم داشته باشد؛ کد پیامکی راه دوم می‌ماند.
--
--   username              اختیاری و یکتا (بدون حساسیت به حروف بزرگ و کوچک)
--   password_hash         password_hash() PHP؛ خالی یعنی هنوز رمزی تعیین نشده
--   must_change_password  رمزی که مدیر داده؛ در اولین ورود باید عوض شود
--   is_active             مسدودشده با ۰؛ نشست‌های باز همان لحظه بسته می‌شوند
--   auth_version          با تغییر رمز یا مسدودی بالا می‌رود و نشست‌های قبلی
--                         را بی‌اعتبار می‌کند
--   failed_logins         تلاش‌های ناموفقِ پیاپی؛ پس از سقف، locked_until

ALTER TABLE users
    ADD COLUMN username VARCHAR(40) NULL AFTER phone,
    ADD COLUMN password_hash VARCHAR(255) NULL AFTER name,
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash,
    ADD COLUMN password_changed_at TIMESTAMP NULL AFTER must_change_password,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_platform_admin,
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_active,
    ADD COLUMN failed_logins SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER auth_version,
    ADD COLUMN locked_until TIMESTAMP NULL AFTER failed_logins,
    ADD UNIQUE KEY uq_users_username (username);

-- هر تلاش ورود، موفق یا ناموفق: سقف تلاش، تاریخچهٔ «حساب من» و گزارش مدیر.
CREATE TABLE login_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    identifier VARCHAR(80) NULL,
    method ENUM('password','otp','link','reset') NOT NULL,
    success TINYINT(1) NOT NULL,
    reason VARCHAR(40) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(200) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_user (user_id, created_at),
    INDEX idx_login_ip (ip_address, created_at),
    INDEX idx_login_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- صفحه‌های محتوایی سایت (درباره، قوانین، حریم خصوصی، …) — /p/{slug}
CREATE TABLE pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title VARCHAR(150) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(300) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    show_in_footer TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- گزارش رویدادهای کل سامانه با فیلتر زمان و نوع
ALTER TABLE audit_logs
    ADD INDEX idx_audit_created (created_at),
    ADD INDEX idx_audit_action (action, created_at);
