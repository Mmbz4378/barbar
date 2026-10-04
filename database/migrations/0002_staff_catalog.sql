CREATE TABLE staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL COMMENT 'null until they accept an invite / set up login',
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(15) NULL,
    photo_path VARCHAR(255) NULL,
    commission_percent DECIMAL(5,2) NULL COMMENT 'default cut percentage for this barber',
    color VARCHAR(7) NOT NULL DEFAULT '#2563eb' COMMENT 'UI chip color',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_id_salon (id, salon_id),
    INDEX idx_staff_salon (salon_id),
    CONSTRAINT fk_staff_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'nominal fallback duration',
    price BIGINT NOT NULL DEFAULT 0 COMMENT 'Rial, integer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_services_id_salon (id, salon_id),
    INDEX idx_services_salon (salon_id),
    CONSTRAINT fk_services_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-barber override of duration/price for a given service.
CREATE TABLE staff_service (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    duration_minutes SMALLINT UNSIGNED NULL COMMENT 'overrides services.duration_minutes when set',
    price BIGINT NULL COMMENT 'overrides services.price when set',
    UNIQUE KEY uq_staff_service (staff_id, service_id),
    INDEX idx_staff_service_salon (salon_id),
    CONSTRAINT fk_ss_staff FOREIGN KEY (staff_id, salon_id) REFERENCES staff(id, salon_id) ON DELETE CASCADE,
    CONSTRAINT fk_ss_service FOREIGN KEY (service_id, salon_id) REFERENCES services(id, salon_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- weekday: 0 = Saturday (Iranian week start) ... 6 = Friday
CREATE TABLE working_hours (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL COMMENT 'null = salon-wide default hours',
    weekday TINYINT UNSIGNED NOT NULL,
    opens_at TIME NOT NULL,
    closes_at TIME NOT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_wh_salon (salon_id),
    INDEX idx_wh_staff (staff_id),
    CONSTRAINT fk_wh_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE time_offs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    salon_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL COMMENT 'null = whole salon closed',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_timeoff_salon_range (salon_id, starts_at, ends_at),
    CONSTRAINT fk_timeoff_salon FOREIGN KEY (salon_id) REFERENCES salons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE holidays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gregorian_date DATE NOT NULL UNIQUE,
    jalali_label VARCHAR(60) NOT NULL,
    is_official TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
