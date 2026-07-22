-- Version 5 App/Scope save hotfix.
-- Additive only: no old table or column is removed, renamed, or modified.
-- Run this once on the connected production database if API Key App Name / Scope Label is not saving.

ALTER TABLE `api_keys`
  ADD COLUMN IF NOT EXISTS `app_name` VARCHAR(120) DEFAULT NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `scope_label` VARCHAR(120) DEFAULT NULL AFTER `app_name`;

ALTER TABLE `licenses`
  ADD COLUMN IF NOT EXISTS `app_scope` VARCHAR(120) DEFAULT NULL AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `api_key_id` INT(11) DEFAULT NULL AFTER `app_scope`;
