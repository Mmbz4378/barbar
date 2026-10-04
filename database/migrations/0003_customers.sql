-- Deliberate split from `users`: a customer record belongs to one salon,
-- a user identity belongs to the whole platform. See docs section 8.4.
CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(120) NULL,
    phone VARCHAR(15) NULL,
    preferred_staff_id BIGINT UNSIGNED NULL,
    trust_score SMALLINT NOT NULL DEFAULT 100,
    visit_count INT UNSIGNED NOT NULL DEFAULT 0,
    no_show_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_visit_at TIMESTAMP NULL,
    duration_factor DECIMAL(4,2) NULL COMMENT 'personal pace multiplier, clamped 0.7-1.5, active after 3+ visits',
    notes TEXT NULL COMMENT 'free-form barber notebook entry',
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_id_salon (id, salon_id),
    INDEX idx_customers_salon_phone (salon_id, phone),
    INDEX idx_customers_salon_name (salon_id, name),
    CONSTRAINT fk_customers_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Specialty fields a real barber cares about (B03).
CREATE TABLE customer_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
    clipper_size VARCHAR(20) NULL COMMENT 'e.g. "شماره ۲"',
    hair_shape VARCHAR(60) NULL,
    beard_notes VARCHAR(255) NULL,
    skin_sensitivity VARCHAR(255) NULL,
    last_barber_said TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cp_customer FOREIGN KEY (customer_id, salon_id) REFERENCES customers(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    path VARCHAR(255) NOT NULL COMMENT 'unguessable path outside public/, EXIF stripped on upload',
    consented_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cphoto_customer (customer_id),
    CONSTRAINT fk_cphoto_customer FOREIGN KEY (customer_id, salon_id) REFERENCES customers(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
