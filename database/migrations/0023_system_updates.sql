-- به‌روزرسانی خودکار: تنظیمات سامانه و تاریخچهٔ به‌روزرسانی‌ها

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    from_version VARCHAR(32) NOT NULL,
    to_version VARCHAR(32) NOT NULL,
    status ENUM('running','succeeded','failed','rolled_back') NOT NULL DEFAULT 'running',
    trigger_type ENUM('manual','auto') NOT NULL DEFAULT 'manual',
    actor_user_id BIGINT UNSIGNED NULL,
    message TEXT NULL,
    log_text MEDIUMTEXT NULL,
    backup_path VARCHAR(255) NULL,
    db_backup_file VARCHAR(255) NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    KEY idx_system_updates_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
