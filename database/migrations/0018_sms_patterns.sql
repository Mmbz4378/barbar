-- الگوهای پیامکی که در سامانهٔ اپراتور ثبت شده‌اند.
--
-- تا حالا شناسهٔ الگو فقط در `.env` بود، و راهش هم دستی: صاحب سالن متن
-- را از پنل رشن کپی می‌کرد، وارد پنل ملی‌پیامک می‌شد، ثبت می‌کرد،
-- شناسه را برمی‌داشت و در `.env` می‌گذاشت. شش الگو، شش بار، و هر جای
-- این زنجیره که اشتباه می‌شد، پیامک بی‌صدا نمی‌رسید.
--
-- حالا ثبت از داخل برنامه انجام می‌شود و نتیجه‌اش اینجا می‌نشیند. این
-- جدول جای `.env` را نمی‌گیرد — `.env` همچنان تصمیم‌گیرندهٔ نهایی است
-- چون الگو به حسابِ اپراتور بسته است نه به یک سالن — ولی حافظهٔ کار را
-- نگه می‌دارد:
--
--   • چه متنی، با چه شناسه‌ای، کِی ثبت شد
--   • آیا هنوز منتظر تأیید است (تأیید چند روز طول می‌کشد و تا آن موقع
--     ارسال با کد -4 برمی‌گردد)
--
-- بدون این، صاحب سالن بعد از چند روز یادش نمی‌آید چه ثبت کرده و
-- دوباره ثبت می‌کند — که یعنی الگوی تکراری و یک شناسهٔ بی‌استفادهٔ دیگر.

CREATE TABLE sms_pattern_registrations (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider      VARCHAR(30)  NOT NULL COMMENT 'melipayamak یا kavenegar',
    template_code VARCHAR(40)  NOT NULL COMMENT 'کلید الگو در SmsTemplates',
    body_id       VARCHAR(40)  NOT NULL COMMENT 'شناسه‌ای که اپراتور برگرداند',
    title         VARCHAR(120) NOT NULL,
    body          TEXT         NOT NULL COMMENT 'متن دقیقی که ثبت شد، با متغیرهای اپراتور',
    registered_by BIGINT UNSIGNED NULL COMMENT 'کاربری که دکمه را زد',
    registered_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- هر الگو نزد هر اپراتور یک بار. ثبت دوباره، همان ردیف را
    -- به‌روز می‌کند تا شناسه‌های یتیم جمع نشوند.
    UNIQUE KEY uq_provider_template (provider, template_code),

    CONSTRAINT fk_sms_pattern_user
        FOREIGN KEY (registered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
