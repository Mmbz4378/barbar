ALTER TABLE salons ADD COLUMN neighborhood VARCHAR(100) NULL,
 ADD COLUMN introduction VARCHAR(1000) NULL,
 ADD COLUMN publication_status ENUM('draft','pending','published','rejected') NOT NULL DEFAULT 'draft',
 ADD INDEX idx_salon_discovery (publication_status,is_active,city);
ALTER TABLE services ADD COLUMN image_file VARCHAR(100) NULL;
ALTER TABLE reviews ADD COLUMN moderation_status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',
 ADD INDEX idx_review_public (salon_id,moderation_status);
CREATE TABLE salon_favorites (
 phone VARCHAR(15) NOT NULL,
 salon_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(phone,salon_id),
 FOREIGN KEY(salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE review_reports (
 review_id BIGINT UNSIGNED NOT NULL,
 phone VARCHAR(15) NOT NULL,
 reason VARCHAR(300) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(review_id,phone),
 FOREIGN KEY(review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
