# Licora v5.3.0 — Secure In-App Update Center

**Release type:** Minor feature release  
**Stable base:** `v5.2.2`  
**Database migration:** Additive updater persistence only (`update_jobs`, `update_events`, `app_migrations`)  
**API v1/v2 contracts:** Unchanged

## Summary

Licora v5.3.0 adds the first production-grade in-app update subsystem. v5.2.2 deployments still require this one final manual source upgrade. After v5.3.0 is installed, future signed Licora releases can be detected, preflighted, downloaded, verified, staged, backed up, migrated, installed, verified, and rolled back from **Admin → Updates** without repeatedly uploading release ZIPs through cPanel.

The Update Center is the first Licora surface implemented against the approved **VibTools Web UI v2.1.2** design foundation. Existing Licora admin pages are intentionally not redesigned in this release.

## Added

- Super-Admin-only **Admin → Updates** page and global update-available badge.
- Cached GitHub stable-release discovery locked to `vibtools/Licora`.
- Dedicated updater RSA public key and signed `licora-update-manifest.json` release contract.
- GitHub release assets: release ZIP, SHA-256, signed update manifest, and manifest signature.
- Mandatory signature, semantic-version, package SHA-256, per-file SHA-256, path, protected-file, symlink, and archive-root verification.
- cPanel/VPS preflight for PHP version, OpenSSL, PDO MySQL, ZipArchive, HTTPS transport, writable source/runtime storage, disk space, updater compatibility, and APP_VERSION pinning.
- Persistent/resumable update state machine backed by `update_jobs`.
- Incremental real update event stream backed by `update_events`.
- Migration ledger backed by `app_migrations`, with signed migration checksums and destructive-migration rollback requirements.
- Chunked source rollback backup and chunked pure-PHP database safety backup when release migrations are present.
- Staging-first archive extraction and atomic same-filesystem source replacement.
- Critical update lock that temporarily returns 503 for non-updater requests only while schema/source changes are being applied.
- Automatic rollback scheduling after critical-stage failures.
- Retained source/database backups and downloadable sanitized updater diagnostics.
- VibTools live log modal with search, severity filter, Copy Logs, Download Diagnostics, visible/total count, pin-to-bottom, progress/stage display, Esc close, and resume after page reload.
- Release/CI regression coverage for updater manifest security, state machine, UI contract, archive rejection, and MySQL persistence.
- Signed `upgrade_from` source-version compatibility contract so unsupported direct version jumps are blocked before migration/source mutation.
- Controlled base-schema prerequisite validation for the core `settings` table used by updater settings and the coordinator mutex.
- Atomic critical-lock metadata publication plus corrupt-lock recovery so malformed lock metadata cannot indefinitely strand normal requests in HTTP 503.
- Consistent migration safety backups by entering the critical application lock before any chunked database dump.
- Release-builder/runtime parity checks for duplicate package paths and protocol-v1 updater control-file deletion.
- Correct `updater_auto_check=0` semantics: automatic checks stay offline until a Super Admin explicitly forces a check.

## Pre-release CI hardening

The initial v5.3.0 `main` CI run exposed a test-fixture mismatch: the API v2 integration fixture intentionally created a minimal database without the core `settings` table, while the updater migration correctly expected a normal Licora base schema. The updater DB test now owns its prerequisites, verifies the missing-base-schema failure mode explicitly, creates a production-compatible minimal `settings` table, and then verifies updater schema seeding, coordinator locking, jobs/events/migration persistence, and corrupt-lock recovery. This is a pre-release CI/test-isolation correction; no API v1/v2 or license/device contract changes are involved.

## Security model

The updater accepts release metadata only from the pinned official `vibtools/Licora` GitHub repository and does not expose an arbitrary ZIP/URL installer. A GitHub release is installable only when its manifest is signed by the private key corresponding to the dedicated public updater key bundled with Licora. API v2 signing keys are not reused.

Private deployment/runtime material remains protected and is never a normal release target, including `includes/config.local.php`, encryption/install flags, API v2 signing keys, updater runtime storage, `.env*`, logs, backups, and exports.

## One-time release-signing setup

Before tagging v5.3.0, repository maintainers must configure the GitHub Actions secret `LICORA_UPDATE_SIGNING_PRIVATE_KEY` with the private key corresponding to `includes/updater/update-signing-public.pem`. The release workflow validates that the secret key matches the tracked public key before signing or publishing the updater manifest.

## Upgrade from v5.2.2

1. Back up the database and all private/runtime files.
2. Apply the v5.3.0 tracked-source delta while preserving private/runtime files.
3. Sign in as Super Admin and open **Updates**. The additive updater schema initializes through the shared authenticated updater bootstrap if it is not already present.
4. Verify updater preflight/UI and existing Licora API/admin regression behavior.
5. Configure the GitHub update-signing private-key secret before publishing the v5.3.0 tag/release.

No existing license, API v1, API v2, device, cron, settings, authentication, or license-format behavior is removed or replaced by this release.
