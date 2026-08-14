# Licora v5.4.1 — Updater Recovery and Scope Integrity Hotfix

**Release type:** Corrective hotfix
**Stable base:** `v5.4.0`
**Database migration:** None
**API v1/v2 contracts:** Unchanged
**License/device/cron contracts:** Unchanged

## Summary

Licora v5.4.1 closes defects and verification gaps found by a full forensic comparison of the v5.3.0 Secure In-App Updater scope and the v5.4.0 VibTools Light UI scope against the published v5.4.0 baseline.

The release fixes the first real in-app-upgrade blocker: the updater JavaScript referenced `#update-log-title` while the page rendered `#licora-update-log-title`. A job was therefore created successfully but the browser controller crashed before opening the real log modal and starting `update-step.php`, leaving the job resumable at `fetch_manifest / 1%`. The mismatch existed since v5.3.0 and was exposed by the first normal v5.3.0 → v5.4.0 upgrade.

## Fixed

- Corrected the live updater log-title DOM selector and added a complete JavaScript ↔ HTML updater DOM contract test.
- Added browser-runtime execution coverage so a syntactically valid but DOM-incompatible updater controller cannot pass verification again.
- Distinguishes active (`running`, `rollback_running`) jobs from retained terminal jobs so a completed/failed job does not incorrectly block a later preflight in the same page session.
- Replaced browser-native `confirm()` dialogs with a real Licora Light reusable confirmation component for install and rollback actions.
- Added controlled startup validation for required updater DOM elements instead of raw null-property JavaScript failures.
- Changed the PHP-stream package-download fallback to bounded file streaming instead of buffering the full release ZIP in memory.
- Hardened archive validation for directory traversal, symlinks, unsupported special entries and case-colliding file paths before extraction.
- Hardened source rollback backups with persisted SHA-256 inventory, checked writes and verified restore copies. Rollback remains compatible with in-flight jobs created by the v5.3.0/v5.4.0 backup format.
- Hardened database safety-dump writes so short/failed writes cannot be marked as a completed backup.
- Aligned manifest-builder and runtime validation for protected paths, delete/package overlap, case collisions, duplicate migration IDs and explicit idempotency requirements.
- Added an exact release-artifact runtime verifier. The tag workflow now re-verifies the signed manifest and extracts/verifies the exact release ZIP through the same runtime verifier used by installed Licora before publishing.

## Scope integrity

The forensic audit found no fake updater job engine, fake runtime deployment logs or shipped `AI Diagnostics` implementation. `update_jobs`, `update_events`, signed manifests, preflight, staging, source apply, rollback and diagnostics are real backend paths. The optional installer demo-data feature remains an intentional pre-existing installer option and is not updater/UI fakery.

The v5.4.0 sidebar/component migration is retained. Primary navigation remains sidebar-only, the product stays on the Licora Light theme, and page-specific design CSS is still prohibited by the component contract.

## Compatibility

The signed v5.4.1 release specification accepts both reviewed updater baselines:

```json
"upgrade_from": ["5.3.0", "5.4.0"],
"migrations": []
```

No database change is required. A deployment that already has a `running` v5.3.0 → v5.4.0 job must resume that existing job rather than create a second job. The forensic distribution includes a minimal browser-controller rescue overlay for that pre-critical incident; it does not alter database job rows manually.

## Explicitly unchanged / still documented limitations

- Automatic full SQL restore is not introduced; database safety dumps remain operator recovery artifacts.
- Automatic retention garbage collection for updater history/backups is not introduced.
- Signing-key rotation and beta/dev update channels remain future protocol work.
- API v1, Secure API v2, licensing, devices, settings semantics, authentication/roles and cron behavior are unchanged.
