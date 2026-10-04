CREATE TABLE online_payment_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 salon_id BIGINT UNSIGNED NOT NULL,
 appointment_id BIGINT UNSIGNED NOT NULL,
 authority VARCHAR(100) NOT NULL UNIQUE,
 redirect_url VARCHAR(500) NOT NULL,
 amount BIGINT NOT NULL,
 status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
 reference_id VARCHAR(100) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_attempt_appointment (appointment_id,status),
 FOREIGN KEY (appointment_id,salon_id) REFERENCES appointments(id,salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
