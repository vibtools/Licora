-- Version 5 additive migration only. Existing tables/columns/endpoints are not removed or renamed.

ALTER TABLE api_keys
  ADD COLUMN IF NOT EXISTS app_name VARCHAR(120) DEFAULT NULL AFTER name,
  ADD COLUMN IF NOT EXISTS scope_label VARCHAR(120) DEFAULT NULL AFTER app_name;

ALTER TABLE licenses
  ADD COLUMN IF NOT EXISTS app_scope VARCHAR(120) DEFAULT NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS api_key_id INT(11) DEFAULT NULL AFTER app_scope;

INSERT INTO settings (setting_key, setting_value) VALUES
('license_api_key_binding_enabled','1'),
('license_app_scope_enforcement','strict')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
