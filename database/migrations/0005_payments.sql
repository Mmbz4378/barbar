CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    method ENUM('cash','card_to_card','pos','online') NOT NULL,
    amount BIGINT NOT NULL COMMENT 'Rial, total charged',
    tip_amount BIGINT NOT NULL DEFAULT 0,
    gateway_ref VARCHAR(100) NULL,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by_user_id BIGINT UNSIGNED NULL,
    INDEX idx_payments_salon_date (salon_id, paid_at),
    CONSTRAINT fk_payments_appt FOREIGN KEY (appointment_id, salon_id) REFERENCES appointments(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chair settlement for a period: percentage, chair rent, or a mix (R7 —
-- only these three models in phase one; never build a generic rules engine).
CREATE TABLE staff_payouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    model ENUM('percent','chair_rent','hybrid') NOT NULL DEFAULT 'percent',
    gross_sales BIGINT NOT NULL DEFAULT 0,
    commission_percent DECIMAL(5,2) NULL,
    commission_amount BIGINT NOT NULL DEFAULT 0,
    chair_rent_amount BIGINT NOT NULL DEFAULT 0,
    tips_amount BIGINT NOT NULL DEFAULT 0,
    deductions_amount BIGINT NOT NULL DEFAULT 0,
    deductions_note VARCHAR(255) NULL,
    net_amount BIGINT NOT NULL DEFAULT 0,
    status ENUM('draft','issued','paid') NOT NULL DEFAULT 'draft',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payout_period (staff_id, period_start, period_end),
    INDEX idx_payout_salon (salon_id),
    CONSTRAINT fk_payout_staff FOREIGN KEY (staff_id, salon_id) REFERENCES staff(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    price BIGINT NOT NULL DEFAULT 0,
    staff_share_percent DECIMAL(5,2) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_products_salon (salon_id),
    CONSTRAINT fk_products_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
