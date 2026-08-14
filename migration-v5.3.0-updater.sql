-- Licora v5.3.0 Secure In-App Updater additive migration.
-- Adds updater persistence only. Existing license/API/device tables and contracts are unchanged.

CREATE TABLE IF NOT EXISTS update_jobs (
  job_uuid CHAR(36) NOT NULL,
  admin_id INT(11) DEFAULT NULL,
  from_version VARCHAR(32) NOT NULL,
  target_version VARCHAR(32) NOT NULL,
  release_tag VARCHAR(64) NOT NULL,
  release_url TEXT DEFAULT NULL,
  manifest_json LONGTEXT DEFAULT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'queued',
  stage VARCHAR(64) NOT NULL DEFAULT 'fetch_manifest',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  context_json LONGTEXT DEFAULT NULL,
  error_code VARCHAR(80) DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  rollback_status VARCHAR(32) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  finished_at DATETIME DEFAULT NULL,
  PRIMARY KEY (job_uuid),
  KEY idx_update_jobs_status (status),
  KEY idx_update_jobs_created (created_at),
  KEY idx_update_jobs_target (target_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS update_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_uuid CHAR(36) NOT NULL,
  level VARCHAR(16) NOT NULL DEFAULT 'info',
  stage VARCHAR(64) DEFAULT NULL,
  event_code VARCHAR(80) DEFAULT NULL,
  message TEXT NOT NULL,
  context_json LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_update_events_job_id (job_uuid, id),
  KEY idx_update_events_created (created_at),
  CONSTRAINT fk_update_events_job FOREIGN KEY (job_uuid) REFERENCES update_jobs (job_uuid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_id VARCHAR(190) NOT NULL,
  release_version VARCHAR(32) NOT NULL,
  checksum CHAR(64) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'running',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  applied_at DATETIME DEFAULT NULL,
  execution_ms INT UNSIGNED DEFAULT NULL,
  error_message TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_app_migrations_id (migration_id),
  KEY idx_app_migrations_release (release_version),
  KEY idx_app_migrations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('updater_auto_check', '1'),
  ('updater_check_interval_seconds', '21600'),
  ('updater_channel', 'stable')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
