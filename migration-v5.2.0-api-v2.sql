-- Licora v5.2.0 Secure API v2 additive migration.
-- Existing API v1 tables, columns, endpoints, license format and behavior are unchanged.

CREATE TABLE IF NOT EXISTS v2_client_apps (
  id INT(11) NOT NULL AUTO_INCREMENT,
  app_id VARCHAR(120) NOT NULL,
  display_name VARCHAR(160) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  min_version VARCHAR(64) DEFAULT NULL,
  access_token_ttl INT(11) NOT NULL DEFAULT 3600,
  refresh_token_ttl INT(11) NOT NULL DEFAULT 2592000,
  clock_skew_seconds INT(11) NOT NULL DEFAULT 300,
  rate_limit_per_hour INT(11) NOT NULL DEFAULT 300,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_v2_client_apps_app_id (app_id),
  KEY idx_v2_client_apps_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_device_credentials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  license_id INT(11) NOT NULL,
  app_id VARCHAR(120) NOT NULL,
  device_hash VARCHAR(255) NOT NULL,
  public_key TEXT NOT NULL,
  public_key_fingerprint CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_v2_device_identity (license_id, app_id, device_hash),
  KEY idx_v2_device_license (license_id),
  KEY idx_v2_device_app_status (app_id, status),
  KEY idx_v2_device_fingerprint (public_key_fingerprint),
  CONSTRAINT fk_v2_device_license FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_refresh_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_credential_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  family_id CHAR(32) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  revoked_at DATETIME DEFAULT NULL,
  rotated_to_hash CHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_v2_refresh_hash (token_hash),
  KEY idx_v2_refresh_device (device_credential_id),
  KEY idx_v2_refresh_family (family_id),
  KEY idx_v2_refresh_expiry (expires_at),
  CONSTRAINT fk_v2_refresh_device FOREIGN KEY (device_credential_id) REFERENCES v2_device_credentials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_used_nonces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_credential_id BIGINT UNSIGNED NOT NULL,
  nonce_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_v2_nonce (device_credential_id, nonce_hash),
  KEY idx_v2_nonce_expiry (expires_at),
  CONSTRAINT fk_v2_nonce_device FOREIGN KEY (device_credential_id) REFERENCES v2_device_credentials (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(80) NOT NULL,
  app_id VARCHAR(120) DEFAULT NULL,
  license_id INT(11) DEFAULT NULL,
  device_credential_id BIGINT UNSIGNED DEFAULT NULL,
  request_id CHAR(32) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  details_json LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_v2_audit_event (event_type),
  KEY idx_v2_audit_app (app_id),
  KEY idx_v2_audit_license (license_id),
  KEY idx_v2_audit_device (device_credential_id),
  KEY idx_v2_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
