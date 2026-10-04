CREATE TABLE plans (
    code VARCHAR(30) PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    monthly_price BIGINT NOT NULL,
    max_seats INT UNSIGNED NULL COMMENT 'null = unlimited',
    extra_seat_price BIGINT NULL,
    sms_gift_monthly INT UNSIGNED NOT NULL DEFAULT 0,
    has_waitlist TINYINT(1) NOT NULL DEFAULT 0,
    has_payout TINYINT(1) NOT NULL DEFAULT 0,
    has_loyalty TINYINT(1) NOT NULL DEFAULT 0,
    has_advanced_reports TINYINT(1) NOT NULL DEFAULT 0,
    has_multi_branch TINYINT(1) NOT NULL DEFAULT 0,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    plan_code VARCHAR(30) NOT NULL,
    amount BIGINT NOT NULL,
    status ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pinvoice_salon (salon_id),
    CONSTRAINT fk_pinvoice_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    delta INT NOT NULL COMMENT 'positive = top-up, negative = consumption',
    balance_after INT NOT NULL,
    reason VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_smswallet_salon (salon_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
