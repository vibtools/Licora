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
