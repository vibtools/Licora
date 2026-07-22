# Release Checklist

## Automated gate

- [x] Archive paths inspected for traversal risk.
- [x] Every original file inventoried with SHA-256.
- [x] Every PHP file syntax-checked.
- [x] Local JavaScript syntax-checked.
- [x] Security smoke test passed.
- [x] Public-release deployment-marker scan passed.
- [x] SQL seed table scope passed.
- [x] Original API endpoint files preserved byte-for-byte.
- [x] No original application PHP file deleted.

## Required manual integration gate

- [ ] Import `database.sql` into a disposable MySQL 8 and MariaDB environment.
- [ ] Complete `install.php`, verify `config.local.php`, then remove/disable the installer.
- [ ] Rotate the temporary administrator password.
- [ ] Configure a high-entropy `LICENSE_ENCRYPTION_KEY` before creating API keys.
- [ ] Create, edit, suspend, activate, blacklist, export, and delete a license.
- [ ] Create/update/toggle/delete an API key and test app/scope binding.
- [ ] Verify `X-API-Key` requests through `/api/verify.php`.
- [ ] Verify the legacy `/api/check_license.php` contract or block it at the proxy.
- [ ] Exercise device register, limit, revoke/logout, blacklist, and delete paths.
- [ ] Verify super-admin, manager, and viewer permissions.
- [ ] Verify logs, audit trail, CSV exports, SQL backup, and health page.
- [ ] Run both cron scripts from CLI and verify web requests are denied.
- [ ] Validate Apache and Nginx denial rules, HTTPS, cookies, CORS, and backups.
- [ ] Capture sanitized screenshots without identifiers or credentials.

## GitHub publishing gate

- [ ] Create `vibtools/license-management-system` with default branch `main`.
- [ ] Apply description and topics from `REPOSITORY_METADATA.md`.
- [ ] Enable Issues, Discussions as desired, private vulnerability reporting, and branch protection.
- [ ] Require CI before merge.
- [ ] Create and sign tag `v5.0.0`.
- [ ] Attach the validated ZIP and release notes.
- [ ] Add the project to the VibTools hub catalog after publication.
