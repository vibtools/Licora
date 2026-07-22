-- VibTools License Management System - sanitized public schema
-- Public release schema. Operational records, credentials, license keys, device fingerprints, IP addresses, and logs have been removed.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `license_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL,
  `api_key_hash` varchar(64) NOT NULL,
  `api_key_encrypted` text DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `app_name` varchar(120) DEFAULT NULL,
  `scope_label` varchar(120) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `rate_limit_per_hour` int(11) DEFAULT 1000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `request_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `api_logs`
--

CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL,
  `api_key_id` int(11) NOT NULL,
  `endpoint` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- --------------------------------------------------------

--
-- Table structure for table `blacklist`
--

CREATE TABLE `blacklist` (
  `id` int(11) NOT NULL,
  `type` enum('device','ip','license') NOT NULL,
  `value` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `banned_by` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `license_id` int(11) NOT NULL,
  `device_hash` varchar(255) NOT NULL,
  `device_info` text DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `last_active` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Triggers `devices`
--
DELIMITER $$
CREATE TRIGGER `update_device_last_active` BEFORE UPDATE ON `devices` FOR EACH ROW SET NEW.last_active = NOW()
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `failed_logins`
--

CREATE TABLE `failed_logins` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `licenses`
--

CREATE TABLE `licenses` (
  `id` int(11) NOT NULL,
  `license_key` varchar(50) NOT NULL,
  `encrypted_key` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `app_scope` varchar(120) DEFAULT NULL,
  `api_key_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `device_limit` int(11) DEFAULT 1,
  `status` enum('active','suspended','expired') DEFAULT 'active',
  `total_devices` int(11) DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Triggers `licenses`
--
DELIMITER $$
CREATE TRIGGER `update_license_timestamp` BEFORE UPDATE ON `licenses` FOR EACH ROW SET NEW.updated_at = NOW()
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `license_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `endpoint` varchar(100) NOT NULL,
  `request_count` int(11) DEFAULT 1,
  `first_request` datetime DEFAULT current_timestamp(),
  `last_request` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_key_hash` (`api_key_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_api_key_hash` (`api_key_hash`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `api_logs`
--
ALTER TABLE `api_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_key_id` (`api_key_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_license_key` (`license_key`);

--
-- Indexes for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blacklist_type` (`type`,`value`),
  ADD KEY `idx_blacklist_expires` (`expires_at`),
  ADD KEY `banned_by` (`banned_by`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_license_device` (`license_id`,`device_hash`),
  ADD KEY `idx_active_devices` (`license_id`,`is_active`,`last_active`),
  ADD KEY `idx_device_hash` (`device_hash`),
  ADD KEY `idx_license_id` (`license_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `failed_logins`
--
ALTER TABLE `failed_logins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_failed_logins` (`ip_address`,`attempt_time`);

--
-- Indexes for table `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`),
  ADD UNIQUE KEY `license_key_2` (`license_key`),
  ADD KEY `idx_license_key` (`license_key`),
  ADD KEY `idx_status_expires` (`status`,`expires_at`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_license_logs` (`license_id`,`created_at`),
  ADD KEY `idx_admin_logs` (`admin_id`,`created_at`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rate_limit` (`ip_address`,`endpoint`,`last_request`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blacklist`
--
ALTER TABLE `blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_logins`
--
ALTER TABLE `failed_logins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_logs`
--
ALTER TABLE `api_logs`
  ADD CONSTRAINT `api_logs_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blacklist`
--
ALTER TABLE `blacklist`
  ADD CONSTRAINT `blacklist_ibfk_1` FOREIGN KEY (`banned_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_ibfk_1` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `licenses`
--
ALTER TABLE `licenses`
  ADD CONSTRAINT `licenses_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`license_id`) REFERENCES `licenses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- Version 4 additive migration only. Existing tables/columns/endpoints are not removed or renamed.

ALTER TABLE admin_users
  ADD COLUMN IF NOT EXISTS role ENUM('super_admin','manager','viewer') NOT NULL DEFAULT 'super_admin' AFTER two_factor_enabled;

ALTER TABLE api_keys
  ADD COLUMN IF NOT EXISTS app_name VARCHAR(120) DEFAULT NULL AFTER name,
  ADD COLUMN IF NOT EXISTS scope_label VARCHAR(120) DEFAULT NULL AFTER app_name;

ALTER TABLE licenses
  ADD COLUMN IF NOT EXISTS app_scope VARCHAR(120) DEFAULT NULL AFTER notes;

CREATE TABLE IF NOT EXISTS audit_trail (
  id INT(11) NOT NULL AUTO_INCREMENT,
  admin_id INT(11) DEFAULT NULL,
  entity_type VARCHAR(60) NOT NULL,
  entity_id INT(11) DEFAULT NULL,
  action VARCHAR(120) NOT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_admin (admin_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
('enable_audit_trail','1'),
('risk_high_device_threshold','5'),
('risk_high_ip_threshold','3'),
('backup_enabled','1'),
('viewer_readonly','1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- -----------------------------------------------------------------------------
-- Public development seed
-- -----------------------------------------------------------------------------
-- Temporary local-only credentials:
--   username: admin
--   password: ChangeMe!2026
-- Change this password immediately and never expose a fresh installation to the
-- public internet with the temporary credential still active.

INSERT INTO admin_users
    (username, password, email, role, failed_attempts, two_factor_enabled, created_at)
VALUES
    ('admin', '$2y$12$wIjygon5jl5PzZHHtrI/NuiOecBAooQv815/JwAyCq0TdC2QRpmkW', 'admin@example.invalid', 'super_admin', 0, 0, NOW())
ON DUPLICATE KEY UPDATE username = VALUES(username);

INSERT INTO settings (setting_key, setting_value) VALUES
    ('system_name', 'License Management System'),
    ('timezone', 'Asia/Dhaka'),
    ('default_license_hours', '24'),
    ('default_device_limit', '1'),
    ('api_rate_limit', '1000'),
    ('log_retention_days', '90'),
    ('maintenance_mode', '0'),
    ('enable_two_factor', '0'),
    ('license_key_prefix', ''),
    ('api_base_url', 'http://localhost/license-system/api/verify.php')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
