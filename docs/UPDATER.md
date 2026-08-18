# Secure In-App Updater

## Scope

Licora v5.3.0 introduces a Super-Admin-only updater for future official Licora releases. v5.2.2 cannot self-install v5.3.0; v5.3.0 is the one-time bootstrap release. From v5.3.0 onward, standard signed releases can be installed from **Admin → Updates** when server preflight passes.

## Trust chain

```text
vibtools/Licora GitHub Release
  -> licora-update-manifest.json
  -> RSA/SHA-256 signature verification
  -> semantic version / signed source-version compatibility
  -> release ZIP SHA-256
  -> archive path/symlink/root validation
  -> per-file SHA-256 inventory
  -> staged source
  -> backup / migration / apply / post-verify
```

The updater never accepts a user-supplied arbitrary release URL. The dedicated updater signing key is separate from API v2 server-signing keys.

## Release assets

Every self-installable release publishes:

- `Licora-X.Y.Z.zip`
- `Licora-X.Y.Z.zip.sha256`
- `licora-update-manifest.json`
- `licora-update-manifest.sig`

The release workflow requires repository secret `LICORA_UPDATE_SIGNING_PRIVATE_KEY` and refuses publication if the derived public key does not match `includes/updater/update-signing-public.pem`.

## Protected deployment data

The updater rejects signed-manifest attempts to overwrite/delete private or runtime paths such as:

- `includes/config.local.php`
- `includes/.licora-encryption.key`
- `includes/.licora-installed`
- API v2 private/public deployment signing files
- updater runtime storage under `includes/.licora-updater/`
- updater private signing keys
- `.env*`
- `logs/`, `backups/`, `exports/`, `.git/`

Only paths explicitly listed in the signed release manifest may be installed. Deletions must also be explicitly signed in `delete_files`.

## Persistent state

`migration-v5.3.0-updater.sql` adds three additive tables:

- `update_jobs` — durable stage/progress/error/rollback state.
- `update_events` — ordered live event stream for the VibTools log modal.
- `app_migrations` — unique migration IDs, release/checksum/status/timing ledger.

The authenticated updater bootstrap can idempotently provision these tables on an upgraded v5.2.2 deployment when **Updates** is opened. Fresh installations receive the same schema through `database.sql`. The updater migration is additive to Licora and therefore requires the existing core `settings` table with a unique `setting_key`; an incomplete base schema is rejected with `UPDATER_BASE_SCHEMA_MISSING` instead of leaking a raw PDO/MySQL error.

## State machine

```text
fetch_manifest
 -> preflight
 -> download
 -> stage_archive
 -> backup_source
 -> [lock_update -> backup_database when migrations exist]
 -> [lock_update when no migration backup is needed]
 -> migrate
 -> apply_files
 -> post_verify
 -> cleanup
 -> success
```

Critical-stage failures transition to:

```text
rollback_migrations
 -> rollback_source
 -> rollback_finalize
 -> rolled_back
```

Each browser-driven `update-step.php` request advances only a bounded unit of work. The job persists in MySQL, so closing/reloading the page does not lose state; reopening Updates resumes a running job. A per-job filesystem step lock prevents two tabs from advancing the same job simultaneously.

## cPanel/VPS preflight

Install remains disabled unless mandatory checks pass:

- compatible PHP and updater versions
- OpenSSL and PDO MySQL
- ZipArchive
- cURL or verified HTTPS stream transport
- dedicated update public key
- updater runtime storage writable
- every target/deletion path writable
- adequate disk space
- no APP_VERSION environment pin that would prevent runtime version transition

No shell, Git, SSH, Composer, Python, `exec()`, `shell_exec()`, or `chmod 777` is required by the production updater runtime.

## Backups and rollback

Before source mutation, every installed path that will change or be deleted is copied to the private updater runtime directory. Newly introduced target paths are recorded so rollback can remove them.

When a release contains migrations, Licora also creates a chunked pure-PHP database safety dump. Because the dump spans resumable HTTP requests, the critical updater lock is acquired before the dump begins so normal Licora writes cannot produce an internally inconsistent backup. Non-destructive migrations must be explicitly idempotent. A destructive signed migration is rejected unless it declares a signed rollback path. Source replacement uses staged checksums and atomic same-filesystem rename operations.

Backup/runtime data is retained after success so diagnostics and an eligible manual rollback remain available. Operators should still maintain external production backups.

## Critical update lock

The updater lock is activated for the critical migration/source-apply phase and, whenever a release carries migrations, before the chunked database safety backup. While active, non-updater application/API requests receive HTTP 503 with `Retry-After: 5`. Update, login, and logout recovery routes remain available. Lock metadata is atomically published; corrupt/truncated metadata is removed during request boot and orphaned terminal-job locks are recovered from the Update Center. Successful completion or rollback releases the lock.

## VibTools UI

The Update Center is the first Licora feature using the approved VibTools Web UI v2.1.2 foundation tokens. The production adapter copies the foundation token/type/spacing/surface/motion files and does not import the whole Sandbox CSS/JS.

The real-time modal is adapted from `Sandbox/pages/logs-modal.html` and supports actual updater events, search, severity filter, Copy Logs, Download Diagnostics, pin-to-bottom, progress/stage state, visible/total line count, responsive layout, and Esc close. The Sandbox's demo-only **AI Diagnostics** control is intentionally not shipped as a fake feature.

## Configuration

Environment constants:

- `LICORA_UPDATE_REPOSITORY` — pinned to `vibtools/Licora`; any non-official override is rejected by the updater.
- `LICORA_UPDATE_CHECK_INTERVAL` — default `21600` seconds.
- `LICORA_UPDATE_HTTP_TIMEOUT` — updater HTTPS timeout.
- `LICORA_UPDATE_MAX_PACKAGE_BYTES` — maximum release package size.
- `LICORA_UPDATE_PUBLIC_KEY_PATH` — dedicated public verification key path.
- optional `LICORA_GITHUB_TOKEN` — GitHub API token for deployments that need higher API limits; never shown in UI.

Stored settings created idempotently: `updater_auto_check`, `updater_check_interval_seconds`, and `updater_channel`. In protocol v1, `updater_channel` is a reserved marker fixed to `stable`; there is no selectable beta/dev channel. Setting `updater_auto_check=0` suppresses automatic outbound GitHub checks while still allowing an explicit Super Admin **Check for Updates** request.

## Release specification

`update/release-spec.json` is maintainer-authored source metadata. `scripts/build-update-manifest.py` combines it with the exact release ZIP inventory and exact Git commit to produce the signed manifest payload. The spec must declare a non-empty signed `upgrade_from` list; the installed version must appear in that list before a release can be preflighted or installed. This prevents an unsupported direct jump from silently skipping required intermediate migration history. Protocol v1 also rejects deletion of updater control files during a self-update. Destructive migrations without rollback metadata and non-destructive migrations not explicitly marked idempotent are rejected at build time.

## Protocol v1 operational limits

- The retained SQL file is a **database safety backup**, not an automatic full-database restore engine. Automatic rollback restores source and executes signed migration rollback files when supplied; operators must retain external production backups.
- Successful-job runtime backups/events are intentionally retained for diagnostics and manual rollback. Automated retention/garbage collection is not yet implemented, so operators should monitor `includes/.licora-updater/` disk usage until a later maintenance feature adds policy-driven cleanup.
- Update-signing key rotation does not yet implement a dual-key transition protocol. A future key-rotation release requires an explicitly designed trust-transition procedure rather than silently replacing the bundled verification key.
- The UI currently follows GitHub's latest stable release. Maintainers must publish self-installable releases with a compatible signed `upgrade_from` contract (and cumulative required migrations where direct jumps are supported).

## v5.4.0 UI-only signed update

v5.4.0 is the first normal release intended to exercise the v5.3.0 bootstrap updater. Its signed release specification accepts only `5.3.0` as the direct source version and declares no database migration. The update changes the Licora presentation layer to the shared VibTools Light component system while leaving updater signing, manifest validation, job/event persistence, preflight, staging, backup, file apply and rollback semantics unchanged.

**Operational correction:** the first real v5.3.0 → v5.4.0 browser-driven install exposed a pre-existing v5.3.0 JavaScript/HTML live-log title ID mismatch. The backend job remained resumable at the pre-critical `fetch_manifest` stage, but the browser controller stopped before driving steps. v5.4.1 corrects that runtime DOM contract and adds tests that execute the browser controller against the rendered ID inventory.

## v5.4.1 corrective updater integrity

v5.4.1 is a no-migration corrective release. It fixes the latent live-log DOM selector defect that could create an update job but stop the browser before `driveJob()` began calling `update-step.php`. The updater now validates its required DOM contract at startup, distinguishes terminal jobs from active jobs, and uses the shared Licora Light confirmation component instead of browser-native confirmation UI.

The backend trust path is also tightened without changing protocol identity: PHP-stream package downloads are bounded to disk, archive directory/symlink/special-entry and case-collision checks run before extraction, source/database backup writes are checked, v5.4.1-created source backups retain SHA-256 inventory, and the release builder/runtime enforce matching protected-path/deletion/migration rules. The tag workflow runs `scripts/verify-release-update.php` against the exact signed release artifacts before publication.

The signed release accepts direct source versions `5.3.0` and `5.4.0`. A deployment with an already-running older target job must resume that job rather than create another. The corrective forensic distribution provides a minimal browser-controller rescue overlay for the observed pre-critical `fetch_manifest / 1%` incident.

## v5.5.0 presentation-only updater integration

v5.5.0 does not change updater protocol v1, signing, job/event persistence, preflight, staging, file application, rollback or migration handling. The Update Center receives compact light presentation refinements, a themed scrollbar and explicit manual-check feedback when Licora is already current or when a newer stable release is available.

The signed v5.5.0 release specification accepts only `5.4.1` and declares no database migration or deleted files.

## v5.5.1 UI-only release integration

v5.5.1 does not change updater protocol v1, signing, persistence, staging, file application, rollback or migration behavior. Its signed release specification accepts exactly `5.5.0`, declares no migrations and deletes no files.
