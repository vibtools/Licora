# Secure In-App Updater

## Scope

Licora v5.3.0 introduces a Super-Admin-only updater for future official Licora releases. v5.2.2 cannot self-install v5.3.0; v5.3.0 is the one-time bootstrap release. From v5.3.0 onward, standard signed releases can be installed from **Admin → Updates** when server preflight passes.

## Trust chain

```text
vibtools/Licora GitHub Release
  -> licora-update-manifest.json
  -> RSA/SHA-256 signature verification
  -> semantic version / updater compatibility
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

The authenticated updater bootstrap can idempotently provision these tables on an upgraded v5.2.2 deployment when **Updates** is opened. Fresh installations receive the same schema through `database.sql`.

## State machine

```text
fetch_manifest
 -> preflight
 -> download
 -> stage_archive
 -> backup_source
 -> [backup_database when migrations exist]
 -> lock_update
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

When a release contains migrations, Licora also creates a chunked pure-PHP database safety dump. Non-destructive migrations must be explicitly idempotent. A destructive signed migration is rejected unless it declares a signed rollback path. Source replacement uses staged checksums and atomic same-filesystem rename operations.

Backup/runtime data is retained after success so diagnostics and an eligible manual rollback remain available. Operators should still maintain external production backups.

## Critical update lock

The updater lock is activated only for the critical migration/source-apply phase (or earlier for destructive-migration database safety backup). While active, non-updater application/API requests receive HTTP 503 with `Retry-After: 5`. Update, login, and logout recovery routes remain available. Successful completion or rollback releases the lock.

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

Stored settings created idempotently: `updater_auto_check`, `updater_check_interval_seconds`, and `updater_channel`.

## Release specification

`update/release-spec.json` is maintainer-authored source metadata. `scripts/build-update-manifest.py` combines it with the exact release ZIP inventory and exact Git commit to produce the signed manifest payload. Destructive migrations without rollback metadata and non-destructive migrations not explicitly marked idempotent are rejected at build time.
