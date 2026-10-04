-- رشن — ساختار کامل دیتابیس (بدون داده)
-- روش استفاده: در phpMyAdmin یک دیتابیس با collation utf8mb4_unicode_ci بسازید و این فایل را Import کنید.
-- (جایگزین اجرای  php tools/migrate.php  است — هر دو به یک نتیجه می‌رسند.)


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `appointment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `price` bigint(20) NOT NULL COMMENT 'Rial, snapshot at time of booking',
  `duration_minutes` smallint(5) unsigned DEFAULT NULL COMMENT 'snapshot of estimate used, for auditing MAE later',
  PRIMARY KEY (`id`),
  KEY `idx_ai_appt` (`appointment_id`),
  KEY `fk_ai_appt` (`appointment_id`,`salon_id`),
  KEY `fk_ai_service` (`service_id`,`salon_id`),
  CONSTRAINT `fk_ai_appt` FOREIGN KEY (`appointment_id`, `salon_id`) REFERENCES `appointments` (`id`, `salon_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_service` FOREIGN KEY (`service_id`, `salon_id`) REFERENCES `services` (`id`, `salon_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `public_token` char(12) NOT NULL COMMENT 'passwordless "my appointment" link',
  `customer_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null means "any available" until assigned',
  `kind` enum('booked','walkin') NOT NULL,
  `status` enum('pending','confirmed','queued','in_chair','completed','cancelled','no_show') NOT NULL DEFAULT 'queued',
  `scheduled_at` timestamp NULL DEFAULT NULL COMMENT 'promised slot time; null for walk-ins',
  `queued_at` timestamp NULL DEFAULT NULL COMMENT 'moment they entered the physical/virtual queue',
  `estimated_start_at` timestamp NULL DEFAULT NULL COMMENT 'ETA engine output, p50',
  `estimated_start_max_at` timestamp NULL DEFAULT NULL COMMENT 'ETA engine output, p80 upper bound',
  `actual_start_at` timestamp NULL DEFAULT NULL COMMENT 'learning engine input',
  `actual_end_at` timestamp NULL DEFAULT NULL COMMENT 'learning engine input',
  `position_snapshot` smallint(5) unsigned DEFAULT NULL COMMENT 'queue position at last recompute, for display',
  `cancel_reason` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_token` (`public_token`),
  UNIQUE KEY `uq_appt_id_salon` (`id`,`salon_id`),
  KEY `idx_appt_salon_status` (`salon_id`,`status`),
  KEY `idx_appt_salon_staff_status` (`salon_id`,`staff_id`,`status`),
  KEY `idx_appt_salon_scheduled` (`salon_id`,`scheduled_at`),
  KEY `idx_appt_customer` (`customer_id`),
  KEY `fk_appt_customer` (`customer_id`,`salon_id`),
  KEY `fk_appt_staff` (`staff_id`,`salon_id`),
  CONSTRAINT `fk_appt_customer` FOREIGN KEY (`customer_id`, `salon_id`) REFERENCES `customers` (`id`, `salon_id`),
  CONSTRAINT `fk_appt_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appt_staff` FOREIGN KEY (`staff_id`, `salon_id`) REFERENCES `staff` (`id`, `salon_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `subject_type` varchar(80) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `meta_json` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_salon` (`salon_id`),
  KEY `idx_audit_actor` (`actor_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) NOT NULL COMMENT 'unguessable path outside public/, EXIF stripped on upload',
  `consented_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cphoto_customer` (`customer_id`),
  KEY `fk_cphoto_customer` (`customer_id`,`salon_id`),
  CONSTRAINT `fk_cphoto_customer` FOREIGN KEY (`customer_id`, `salon_id`) REFERENCES `customers` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `clipper_size` varchar(20) DEFAULT NULL COMMENT 'e.g. "شماره ۲"',
  `hair_shape` varchar(60) DEFAULT NULL,
  `beard_notes` varchar(255) DEFAULT NULL,
  `skin_sensitivity` varchar(255) DEFAULT NULL,
  `last_barber_said` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_id` (`customer_id`),
  KEY `fk_cp_customer` (`customer_id`,`salon_id`),
  CONSTRAINT `fk_cp_customer` FOREIGN KEY (`customer_id`, `salon_id`) REFERENCES `customers` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(120) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `preferred_staff_id` bigint(20) unsigned DEFAULT NULL,
  `trust_score` smallint(6) NOT NULL DEFAULT 100,
  `visit_count` int(10) unsigned NOT NULL DEFAULT 0,
  `no_show_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_visit_at` timestamp NULL DEFAULT NULL,
  `duration_factor` decimal(4,2) DEFAULT NULL COMMENT 'personal pace multiplier, clamped 0.7-1.5, active after 3+ visits',
  `notes` text DEFAULT NULL COMMENT 'free-form barber notebook entry',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_customers_id_salon` (`id`,`salon_id`),
  KEY `idx_customers_salon_phone` (`salon_id`,`phone`),
  KEY `idx_customers_salon_name` (`salon_id`,`name`),
  CONSTRAINT `fk_customers_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `duration_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `duration_samples` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `minutes` decimal(6,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dsample_lookup` (`staff_id`,`service_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `duration_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `duration_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `sample_count` int(10) unsigned NOT NULL DEFAULT 0,
  `p50_minutes` decimal(6,2) DEFAULT NULL,
  `p80_minutes` decimal(6,2) DEFAULT NULL,
  `mean_minutes` decimal(6,2) DEFAULT NULL,
  `stddev_minutes` decimal(6,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_duration_stats` (`staff_id`,`service_id`),
  KEY `idx_ds_salon` (`salon_id`),
  KEY `fk_ds_staff` (`staff_id`,`salon_id`),
  KEY `fk_ds_service` (`service_id`,`salon_id`),
  CONSTRAINT `fk_ds_service` FOREIGN KEY (`service_id`, `salon_id`) REFERENCES `services` (`id`, `salon_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ds_staff` FOREIGN KEY (`staff_id`, `salon_id`) REFERENCES `staff` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gregorian_date` date NOT NULL,
  `jalali_label` varchar(60) NOT NULL,
  `is_official` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gregorian_date` (`gregorian_date`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `loyalty_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty_cards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `stamps` smallint(5) unsigned NOT NULL DEFAULT 0,
  `stamps_required` smallint(5) unsigned NOT NULL DEFAULT 10,
  `redeemed_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loyalty_customer` (`customer_id`),
  KEY `fk_loyalty_customer` (`customer_id`,`salon_id`),
  CONSTRAINT `fk_loyalty_customer` FOREIGN KEY (`customer_id`, `salon_id`) REFERENCES `customers` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `otp_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `otp_codes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(15) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `purpose` varchar(30) NOT NULL DEFAULT 'login',
  `ip_address` varchar(45) DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_phone` (`phone`,`purpose`),
  KEY `idx_otp_ip_created` (`ip_address`,`created_at`),
  KEY `idx_otp_phone_created` (`phone`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `method` enum('cash','card_to_card','pos','online') NOT NULL,
  `amount` bigint(20) NOT NULL COMMENT 'Rial, total charged',
  `tip_amount` bigint(20) NOT NULL DEFAULT 0,
  `gateway_ref` varchar(100) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payments_salon_date` (`salon_id`,`paid_at`),
  KEY `fk_payments_appt` (`appointment_id`,`salon_id`),
  CONSTRAINT `fk_payments_appt` FOREIGN KEY (`appointment_id`, `salon_id`) REFERENCES `appointments` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `code` varchar(30) NOT NULL,
  `name` varchar(60) NOT NULL,
  `monthly_price` bigint(20) NOT NULL,
  `max_seats` int(10) unsigned DEFAULT NULL COMMENT 'null = unlimited',
  `extra_seat_price` bigint(20) DEFAULT NULL,
  `sms_gift_monthly` int(10) unsigned NOT NULL DEFAULT 0,
  `has_waitlist` tinyint(1) NOT NULL DEFAULT 0,
  `has_payout` tinyint(1) NOT NULL DEFAULT 0,
  `has_loyalty` tinyint(1) NOT NULL DEFAULT 0,
  `has_advanced_reports` tinyint(1) NOT NULL DEFAULT 0,
  `has_multi_branch` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `plan_code` varchar(30) NOT NULL,
  `amount` bigint(20) NOT NULL,
  `status` enum('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pinvoice_salon` (`salon_id`),
  CONSTRAINT `fk_pinvoice_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `price` bigint(20) NOT NULL DEFAULT 0,
  `staff_share_percent` decimal(5,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_products_salon` (`salon_id`),
  CONSTRAINT `fk_products_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL COMMENT '1-5',
  `comment` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_reviews_salon` (`salon_id`),
  KEY `fk_reviews_appt` (`appointment_id`,`salon_id`),
  CONSTRAINT `fk_reviews_appt` FOREIGN KEY (`appointment_id`, `salon_id`) REFERENCES `appointments` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salon_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salon_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` enum('owner','manager','staff','reception') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_salon_user` (`salon_id`,`user_id`),
  KEY `fk_salon_user_user` (`user_id`),
  CONSTRAINT `fk_salon_user_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_salon_user_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL COMMENT 'used in public URL reshen.ir/s/{slug}',
  `name` varchar(150) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `cover_path` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `map_lat` decimal(10,7) DEFAULT NULL,
  `map_lng` decimal(10,7) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `plan_code` varchar(30) NOT NULL DEFAULT 'trial',
  `seats` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'number of chairs on the plan',
  `sms_credit` int(11) NOT NULL DEFAULT 0,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Tehran',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `duration_minutes` smallint(5) unsigned NOT NULL DEFAULT 30 COMMENT 'nominal fallback duration',
  `price` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Rial, integer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_services_id_salon` (`id`,`salon_id`),
  KEY `idx_services_salon` (`salon_id`),
  CONSTRAINT `fk_services_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sms_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `appointment_id` bigint(20) unsigned DEFAULT NULL,
  `to_phone` varchar(15) NOT NULL,
  `template_code` varchar(40) NOT NULL,
  `body` text NOT NULL,
  `is_critical` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'critical sms are never blocked by low credit',
  `provider` varchar(30) NOT NULL DEFAULT 'log',
  `provider_ref` varchar(100) DEFAULT NULL,
  `status` enum('queued','sent','failed','skipped_quiet_hours','skipped_no_credit','skipped_rate_limit') NOT NULL DEFAULT 'queued',
  `error_message` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sms_salon_date` (`salon_id`,`created_at`),
  KEY `idx_sms_appt` (`appointment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sms_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL COMMENT 'e.g. queue_nearly_up, queue_delayed, reminder_24h',
  `body` text NOT NULL COMMENT 'placeholders like {name}, {staff}, {minutes}, {link}',
  `is_promotional` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'promotional sms are cut first when credit is low',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sms_wallet_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sms_wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `delta` int(11) NOT NULL COMMENT 'positive = top-up, negative = consumption',
  `balance_after` int(11) NOT NULL,
  `reason` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_smswallet_salon` (`salon_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null until they accept an invite / set up login',
  `name` varchar(120) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `commission_percent` decimal(5,2) DEFAULT NULL COMMENT 'default cut percentage for this barber',
  `color` varchar(7) NOT NULL DEFAULT '#2563eb' COMMENT 'UI chip color',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_id_salon` (`id`,`salon_id`),
  KEY `idx_staff_salon` (`salon_id`),
  CONSTRAINT `fk_staff_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_payouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_payouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `model` enum('percent','chair_rent','hybrid') NOT NULL DEFAULT 'percent',
  `gross_sales` bigint(20) NOT NULL DEFAULT 0,
  `commission_percent` decimal(5,2) DEFAULT NULL,
  `commission_amount` bigint(20) NOT NULL DEFAULT 0,
  `chair_rent_amount` bigint(20) NOT NULL DEFAULT 0,
  `tips_amount` bigint(20) NOT NULL DEFAULT 0,
  `deductions_amount` bigint(20) NOT NULL DEFAULT 0,
  `deductions_note` varchar(255) DEFAULT NULL,
  `net_amount` bigint(20) NOT NULL DEFAULT 0,
  `status` enum('draft','issued','paid') NOT NULL DEFAULT 'draft',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payout_period` (`staff_id`,`period_start`,`period_end`),
  KEY `idx_payout_salon` (`salon_id`),
  KEY `fk_payout_staff` (`staff_id`,`salon_id`),
  CONSTRAINT `fk_payout_staff` FOREIGN KEY (`staff_id`, `salon_id`) REFERENCES `staff` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `staff_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_service` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `duration_minutes` smallint(5) unsigned DEFAULT NULL COMMENT 'overrides services.duration_minutes when set',
  `price` bigint(20) DEFAULT NULL COMMENT 'overrides services.price when set',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_service` (`staff_id`,`service_id`),
  KEY `idx_staff_service_salon` (`salon_id`),
  KEY `fk_ss_staff` (`staff_id`,`salon_id`),
  KEY `fk_ss_service` (`service_id`,`salon_id`),
  CONSTRAINT `fk_ss_service` FOREIGN KEY (`service_id`, `salon_id`) REFERENCES `services` (`id`, `salon_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ss_staff` FOREIGN KEY (`staff_id`, `salon_id`) REFERENCES `staff` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscription_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `monthly_price` bigint(20) NOT NULL,
  `status` enum('active','paused','cancelled') NOT NULL DEFAULT 'active',
  `started_at` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_subplan_customer` (`customer_id`,`salon_id`),
  CONSTRAINT `fk_subplan_customer` FOREIGN KEY (`customer_id`, `salon_id`) REFERENCES `customers` (`id`, `salon_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `time_offs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `time_offs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null = whole salon closed',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `reason` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_timeoff_salon_range` (`salon_id`,`starts_at`,`ends_at`),
  CONSTRAINT `fk_timeoff_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `phone` varchar(15) NOT NULL COMMENT 'E.164 normalized, e.g. +989121234567',
  `name` varchar(120) DEFAULT NULL,
  `is_platform_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `waitlist_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `waitlist_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `desired_date` date NOT NULL,
  `desired_window_start` time DEFAULT NULL,
  `desired_window_end` time DEFAULT NULL,
  `status` enum('waiting','offered','booked','expired','cancelled') NOT NULL DEFAULT 'waiting',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_waitlist_salon_date` (`salon_id`,`desired_date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `working_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `working_hours` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salon_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL COMMENT 'null = salon-wide default hours',
  `weekday` tinyint(3) unsigned NOT NULL,
  `opens_at` time NOT NULL,
  `closes_at` time NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_wh_salon` (`salon_id`),
  KEY `idx_wh_staff` (`staff_id`),
  CONSTRAINT `fk_wh_salon` FOREIGN KEY (`salon_id`) REFERENCES `salons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- وضعیت مهاجرت‌های اعمال‌شده، تا migrate.php دوباره اجرایشان نکند:

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
INSERT INTO `schema_migrations` VALUES (1,'0001_identity.sql','2026-09-19 16:35:57'),(2,'0002_staff_catalog.sql','2026-09-19 16:35:57'),(3,'0003_customers.sql','2026-09-19 16:35:57'),(4,'0004_appointments.sql','2026-09-19 16:35:57'),(5,'0005_payments.sql','2026-09-19 16:35:57'),(6,'0006_messaging.sql','2026-09-19 16:35:57'),(7,'0007_loyalty_reviews.sql','2026-09-19 16:35:57'),(8,'0008_platform.sql','2026-09-19 16:35:57'),(9,'0009_otp_rate_limit.sql','2026-09-19 17:33:15'),(10,'0010_fix_otp_expiry.sql','2026-09-19 17:33:40');
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

