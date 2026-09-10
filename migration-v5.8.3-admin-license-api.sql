-- Licora v5.8.3 scoped Admin License Control API additive migration.
-- Existing API v1/v2, license, device, authentication and updater tables are unchanged.

CREATE TABLE IF NOT EXISTS admin_api_keys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  key_prefix VARCHAR(40) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  allowed_ips TEXT DEFAULT NULL,
  rate_limit_per_hour INT UNSIGNED NOT NULL DEFAULT 300,
  expires_at DATETIME DEFAULT NULL,
  last_used_at DATETIME DEFAULT NULL,
  last_used_ip VARCHAR(45) DEFAULT NULL,
  rotated_at DATETIME DEFAULT NULL,
  created_by INT(11) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_api_key_hash (key_hash),
  KEY idx_admin_api_key_status_expiry (status, expires_at),
  KEY idx_admin_api_key_created_by (created_by),
  CONSTRAINT fk_admin_api_key_creator FOREIGN KEY (created_by) REFERENCES admin_users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_api_key_scopes (
  api_key_id BIGINT UNSIGNED NOT NULL,
  scope_name VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (api_key_id, scope_name),
  KEY idx_admin_api_scope_name (scope_name),
  CONSTRAINT fk_admin_api_scope_key FOREIGN KEY (api_key_id) REFERENCES admin_api_keys (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_api_key_apps (
  api_key_id BIGINT UNSIGNED NOT NULL,
  app_id VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (api_key_id, app_id),
  KEY idx_admin_api_key_app (app_id),
  CONSTRAINT fk_admin_api_app_key FOREIGN KEY (api_key_id) REFERENCES admin_api_keys (id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_api_app_client FOREIGN KEY (app_id) REFERENCES v2_client_apps (app_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_api_license_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  api_key_id BIGINT UNSIGNED NOT NULL,
  license_id INT(11) NOT NULL,
  external_order_id VARCHAR(120) NOT NULL,
  customer_reference VARCHAR(120) DEFAULT NULL,
  request_hash CHAR(64) NOT NULL,
  banned_at DATETIME DEFAULT NULL,
  ban_reason VARCHAR(500) DEFAULT NULL,
  deleted_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_api_order (api_key_id, external_order_id),
  UNIQUE KEY uq_admin_api_order_license (license_id),
  KEY idx_admin_api_order_customer (api_key_id, customer_reference),
  CONSTRAINT fk_admin_api_order_key FOREIGN KEY (api_key_id) REFERENCES admin_api_keys (id) ON DELETE RESTRICT,
  CONSTRAINT fk_admin_api_order_license FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_api_idempotency (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  api_key_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  license_id INT(11) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_api_idempotency (api_key_id, idempotency_key),
  KEY idx_admin_api_idempotency_created (created_at),
  CONSTRAINT fk_admin_api_idempotency_key FOREIGN KEY (api_key_id) REFERENCES admin_api_keys (id) ON DELETE CASCADE,
  CONSTRAINT fk_admin_api_idempotency_license FOREIGN KEY (license_id) REFERENCES licenses (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_api_nonces (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  api_key_id BIGINT UNSIGNED NOT NULL,
  nonce_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_api_nonce (api_key_id, nonce_hash),
  KEY idx_admin_api_nonce_expiry (expires_at),
  CONSTRAINT fk_admin_api_nonce_key FOREIGN KEY (api_key_id) REFERENCES admin_api_keys (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_api_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  api_key_id BIGINT UNSIGNED DEFAULT NULL,
  event_type VARCHAR(80) NOT NULL,
  scope_name VARCHAR(64) DEFAULT NULL,
  request_id CHAR(32) NOT NULL,
  http_method VARCHAR(12) NOT NULL,
  request_path VARCHAR(500) NOT NULL,
  response_code SMALLINT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  details_json LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_api_log_key_created (api_key_id, created_at),
  KEY idx_admin_api_log_event (event_type),
  KEY idx_admin_api_log_request (request_id),
  CONSTRAINT fk_admin_api_log_key FOREIGN KEY (api_key_id) REFERENCES admin_api_keys (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
