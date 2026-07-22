-- Version 5 App/Scope hotfix migration.
-- Additive only. No table/column is removed or renamed.

ALTER TABLE api_keys ADD COLUMN IF NOT EXISTS app_name VARCHAR(120) DEFAULT NULL AFTER name;
ALTER TABLE api_keys ADD COLUMN IF NOT EXISTS scope_label VARCHAR(120) DEFAULT NULL AFTER app_name;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS app_scope VARCHAR(120) DEFAULT NULL AFTER notes;
ALTER TABLE licenses ADD COLUMN IF NOT EXISTS api_key_id INT(11) DEFAULT NULL AFTER app_scope;

INSERT INTO settings (setting_key, setting_value) VALUES
('license_api_key_binding_enabled','1'),
('license_app_scope_enforcement','strict')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
