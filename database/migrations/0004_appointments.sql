-- The pivotal table: booked appointments and walk-ins live in ONE table
-- with a `kind` column. Splitting them would mean two ordering rules,
-- two reports, two bugs — and defeat the whole point of a single queue.
CREATE TABLE appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    public_token CHAR(12) NOT NULL UNIQUE COMMENT 'passwordless "my appointment" link',
    customer_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL COMMENT 'null means "any available" until assigned',
    kind ENUM('booked','walkin') NOT NULL,
    status ENUM('pending','confirmed','queued','in_chair','completed','cancelled','no_show') NOT NULL DEFAULT 'queued',
    scheduled_at TIMESTAMP NULL COMMENT 'promised slot time; null for walk-ins',
    queued_at TIMESTAMP NULL COMMENT 'moment they entered the physical/virtual queue',
    estimated_start_at TIMESTAMP NULL COMMENT 'ETA engine output, p50',
    estimated_start_max_at TIMESTAMP NULL COMMENT 'ETA engine output, p80 upper bound',
    actual_start_at TIMESTAMP NULL COMMENT 'learning engine input',
    actual_end_at TIMESTAMP NULL COMMENT 'learning engine input',
    position_snapshot SMALLINT UNSIGNED NULL COMMENT 'queue position at last recompute, for display',
    cancel_reason VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_appt_id_salon (id, salon_id),
    INDEX idx_appt_salon_status (salon_id, status),
    INDEX idx_appt_salon_staff_status (salon_id, staff_id, status),
    INDEX idx_appt_salon_scheduled (salon_id, scheduled_at),
    INDEX idx_appt_customer (customer_id),
    CONSTRAINT fk_appt_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE,
    CONSTRAINT fk_appt_customer FOREIGN KEY (customer_id, salon_id) REFERENCES customers(id, salon_id),
    CONSTRAINT fk_appt_staff FOREIGN KEY (staff_id, salon_id) REFERENCES staff(id, salon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One appointment can bundle several services (A08).
CREATE TABLE appointment_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    price BIGINT NOT NULL COMMENT 'Rial, snapshot at time of booking',
    duration_minutes SMALLINT UNSIGNED NULL COMMENT 'snapshot of estimate used, for auditing MAE later',
    INDEX idx_ai_appt (appointment_id),
    CONSTRAINT fk_ai_appt FOREIGN KEY (appointment_id, salon_id) REFERENCES appointments(id, salon_id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_service FOREIGN KEY (service_id, salon_id) REFERENCES services(id, salon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The estimation engine's memory: learned percentiles per (staff, service).
CREATE TABLE duration_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    sample_count INT UNSIGNED NOT NULL DEFAULT 0,
    p50_minutes DECIMAL(6,2) NULL,
    p80_minutes DECIMAL(6,2) NULL,
    mean_minutes DECIMAL(6,2) NULL,
    stddev_minutes DECIMAL(6,2) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_duration_stats (staff_id, service_id),
    INDEX idx_ds_salon (salon_id),
    CONSTRAINT fk_ds_staff FOREIGN KEY (staff_id, salon_id) REFERENCES staff(id, salon_id) ON DELETE CASCADE,
    CONSTRAINT fk_ds_service FOREIGN KEY (service_id, salon_id) REFERENCES services(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw sample log backing duration_stats' rolling-window recompute.
CREATE TABLE duration_samples (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    minutes DECIMAL(6,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dsample_lookup (staff_id, service_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE waitlist_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    desired_date DATE NOT NULL,
    desired_window_start TIME NULL,
    desired_window_end TIME NULL,
    status ENUM('waiting','offered','booked','expired','cancelled') NOT NULL DEFAULT 'waiting',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_waitlist_salon_date (salon_id, desired_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
