-- مخاطب سالن، دسته‌بندی خدمات، مهارت کارکنان و قوانین رزرو هر سالن.
--
-- چرا یک‌جا: این‌ها با هم معنا دارند. سالن بانوان بدون «مهارت» کار
-- نمی‌کند (ناخن‌کار رنگ نمی‌کند)، و بدون «بیعانه» و «حداقل فاصله تا
-- نوبت» همان سالن نمی‌تواند نوبت چهارساعتهٔ عروس را آنلاین بدهد.
--
-- همهٔ ستون‌های تازه پیش‌فرضی دارند که رفتار سالن‌های فعلی را عوض
-- نمی‌کند: مخاطب «مردانه»، ترتیب رزرو «اول زمان»، بدون بیعانه، و هر
-- آرایشگر همهٔ خدمات را انجام می‌دهد.

ALTER TABLE salons
    ADD COLUMN audience ENUM('men','women','unisex') NOT NULL DEFAULT 'men' AFTER name,
    ADD COLUMN booking_flow ENUM('time_first','service_first') NOT NULL DEFAULT 'time_first' AFTER slot_step_minutes,
    ADD COLUMN booking_horizon_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER booking_flow,
    ADD COLUMN min_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER booking_horizon_days,
    ADD COLUMN cancel_notice_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER min_notice_minutes,
    ADD COLUMN observe_official_holidays TINYINT(1) NOT NULL DEFAULT 1 AFTER cancel_notice_minutes,
    ADD COLUMN deposit_card_number VARCHAR(24) NULL AFTER observe_official_holidays,
    ADD COLUMN deposit_card_holder VARCHAR(120) NULL AFTER deposit_card_number,
    ADD COLUMN deposit_hold_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 120 AFTER deposit_card_holder,
    ADD COLUMN min_price BIGINT NULL COMMENT 'denormalized for discovery' AFTER deposit_hold_minutes,
    ADD COLUMN rating_avg DECIMAL(3,2) NULL COMMENT 'denormalized for discovery' AFTER min_price,
    ADD COLUMN rating_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER rating_avg,
    ADD INDEX idx_salon_discovery_audience (publication_status, is_active, audience, city);

CREATE TABLE service_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    visual VARCHAR(20) NOT NULL DEFAULT 'haircut' COMMENT 'visual key, see ServiceVisual',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_salon (salon_id, sort_order),
    CONSTRAINT fk_category_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE services
    ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER salon_id,
    ADD COLUMN audience ENUM('all','men','women') NOT NULL DEFAULT 'all' AFTER description,
    ADD COLUMN buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER duration_minutes,
    ADD COLUMN price_type ENUM('fixed','from') NOT NULL DEFAULT 'fixed' AFTER price,
    ADD COLUMN deposit_amount BIGINT NULL COMMENT 'Rial; null = no deposit' AFTER price_type,
    ADD COLUMN online_booking TINYINT(1) NOT NULL DEFAULT 1 AFTER deposit_amount,
    ADD INDEX idx_services_salon_active (salon_id, is_active, price),
    ADD CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL;

ALTER TABLE staff
    ADD COLUMN title VARCHAR(80) NULL AFTER name,
    ADD COLUMN bio VARCHAR(300) NULL AFTER title,
    ADD COLUMN accepts_online TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active;

-- ردیفی با is_offered = 0 یعنی «این نفر این خدمت را انجام نمی‌دهد».
-- نبودِ ردیف همان رفتار قبلی است: همه همه‌چیز را انجام می‌دهند.
ALTER TABLE staff_service
    ADD COLUMN is_offered TINYINT(1) NOT NULL DEFAULT 1 AFTER price;

ALTER TABLE working_hours
    ADD INDEX idx_wh_lookup (salon_id, staff_id, weekday);

ALTER TABLE customer_preferences
    ADD COLUMN hair_type VARCHAR(60) NULL AFTER hair_shape,
    ADD COLUMN color_formula VARCHAR(255) NULL AFTER hair_type,
    ADD COLUMN skin_type VARCHAR(60) NULL AFTER skin_sensitivity,
    ADD COLUMN allergies VARCHAR(255) NULL AFTER skin_type,
    ADD COLUMN nail_notes VARCHAR(255) NULL AFTER allergies;

ALTER TABLE appointments
    ADD COLUMN group_token CHAR(12) NULL COMMENT 'links sequential multi-specialist parts of one booking' AFTER public_token,
    ADD COLUMN customer_note VARCHAR(300) NULL AFTER cancelled_by,
    ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL COMMENT 'null = online booking' AFTER customer_note,
    ADD COLUMN deposit_amount BIGINT NOT NULL DEFAULT 0 AFTER created_by_user_id,
    ADD COLUMN hold_expires_at DATETIME NULL COMMENT 'pending deposit deadline' AFTER deposit_amount,
    ADD INDEX idx_appt_group (group_token),
    ADD INDEX idx_appt_salon_end (salon_id, actual_end_at),
    ADD INDEX idx_appt_salon_queued (salon_id, queued_at),
    ADD INDEX idx_appt_hold (status, hold_expires_at);

ALTER TABLE appointment_items
    ADD COLUMN buffer_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER duration_minutes;

ALTER TABLE payments
    ADD COLUMN kind ENUM('settlement','deposit') NOT NULL DEFAULT 'settlement' AFTER appointment_id,
    ADD COLUMN discount_amount BIGINT NOT NULL DEFAULT 0 AFTER tip_amount,
    ADD INDEX idx_payments_appt (appointment_id, kind);

ALTER TABLE time_offs
    ADD INDEX idx_timeoff_salon_end (salon_id, ends_at);

-- آمار کشف از روی دادهٔ فعلی پر می‌شود؛ از این به بعد با هر تغییر خدمت
-- یا تأیید نظر به‌روز می‌شود (App\Domain\Salon\SalonStats).
UPDATE salons s
   SET s.min_price = (SELECT MIN(v.price) FROM services v WHERE v.salon_id = s.id AND v.is_active = 1);

UPDATE salons s
  JOIN (SELECT salon_id, AVG(rating) AS avg_rating, COUNT(*) AS total
          FROM reviews WHERE moderation_status = 'published' GROUP BY salon_id) r
    ON r.salon_id = s.id
   SET s.rating_avg = r.avg_rating, s.rating_count = r.total;
