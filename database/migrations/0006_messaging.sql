CREATE TABLE sms_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE COMMENT 'e.g. queue_nearly_up, queue_delayed, reminder_24h',
    body TEXT NOT NULL COMMENT 'placeholders like {name}, {staff}, {minutes}, {link}',
    is_promotional TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'promotional sms are cut first when credit is low'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NULL,
    to_phone VARCHAR(15) NOT NULL,
    template_code VARCHAR(40) NOT NULL,
    body TEXT NOT NULL,
    is_critical TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'critical sms are never blocked by low credit',
    provider VARCHAR(30) NOT NULL DEFAULT 'log',
    provider_ref VARCHAR(100) NULL,
    status ENUM('queued','sent','failed','skipped_quiet_hours','skipped_no_credit','skipped_rate_limit') NOT NULL DEFAULT 'queued',
    error_message VARCHAR(255) NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_salon_date (salon_id, created_at),
    INDEX idx_sms_appt (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
