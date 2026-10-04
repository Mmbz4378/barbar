CREATE TABLE loyalty_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    stamps SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    stamps_required SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    redeemed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_loyalty_customer (customer_id),
    CONSTRAINT fk_loyalty_customer FOREIGN KEY (customer_id, salon_id) REFERENCES customers(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    monthly_price BIGINT NOT NULL,
    status ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',
    started_at DATE NOT NULL,
    CONSTRAINT fk_subplan_customer FOREIGN KEY (customer_id, salon_id) REFERENCES customers(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    comment VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviews_salon (salon_id),
    CONSTRAINT fk_reviews_appt FOREIGN KEY (appointment_id, salon_id) REFERENCES appointments(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
