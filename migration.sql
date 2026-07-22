-- Optional data-hardening migration for license-system-secured-v2.
-- This does not change table/column schema. It removes reversible stored API key copies; API validation continues to use api_key_hash.
UPDATE api_keys SET api_key_encrypted = NULL WHERE api_key_encrypted IS NOT NULL;
