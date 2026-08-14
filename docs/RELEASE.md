# Release Guide

## Current release contract

Licora uses semantic version tags. The current release is `5.4.1`; runtime, installer, verifier, release notes, update release specification and GitHub workflow markers must agree before a tag can publish.

Every v5.3.0+ official release intended for the in-app updater consists of four updater-facing assets:

```text
Licora-X.Y.Z.zip
Licora-X.Y.Z.zip.sha256
licora-update-manifest.json
licora-update-manifest.sig
```

The ZIP/checksum are generated from the exact Git ref by `scripts/package-release.sh`. `scripts/build-update-manifest.py` inventories the exact ZIP, records per-file SHA-256 values, package hash/size, commit identity, migration metadata, protected deletion intent and compatibility requirements. GitHub Actions signs the exact manifest bytes with the dedicated repository secret `LICORA_UPDATE_SIGNING_PRIVATE_KEY`; the matching public key is tracked at `includes/updater/update-signing-public.pem`.

## v5.4.1 release specification

v5.4.1 is a no-migration corrective update for the reviewed v5.3.0 updater and v5.4.0 UI baselines:

```json
{
  "version": "5.4.1",
  "minimum_updater": "5.3.0",
  "upgrade_from": ["5.3.0", "5.4.0"],
  "migrations": []
}
```

The release closes updater DOM/runtime, archive, backup/rollback and manifest-builder/runtime verification gaps. It does not alter API, license, device, cron or database contracts.

## v5.4.0 release specification

v5.4.0 is a UI-only direct update from v5.3.0:

```json
{
  "version": "5.4.0",
  "minimum_updater": "5.3.0",
  "upgrade_from": ["5.3.0"],
  "migrations": []
}
```

No database migration is run for this release.

## Mandatory pre-tag gates

```bash
python3 scripts/verify-local.py
bash scripts/validate.sh
php tests/ui_route_contract.php
php tests/ui_form_contract.php
php tests/ui_component_contract.php
php tests/ui_updater_contract.php
php tests/updater_dom_contract.php
node tests/updater_browser_runtime.js
python tests/updater_builder_contract.py
php tests/updater_static.php
php tests/updater_manifest.php
php tests/updater_state_machine.php
php tests/updater_failure_recovery.php
php tests/updater_ui_contract.php
```

Database-backed API v2/admin/updater integration runs in GitHub CI against MySQL 8.4. CI must be green before creating the release tag.

## Signing secret

The updater private key is release infrastructure and must never be committed, bundled into a delta, copied to a hosted Licora application or printed in logs. The same established repository secret remains authoritative:

```bash
gh secret set LICORA_UPDATE_SIGNING_PRIVATE_KEY < /secure/path/Licora_UPDATE_SIGNING_PRIVATE_KEY.pem
```

The release workflow derives the public key from that secret and compares it to the tracked updater public key before signing or publishing.

## Exact local package rehearsal

From a clean committed working tree:

```bash
bash scripts/package-release.sh v5.4.1 v5.4.1
```

The official ZIP is still produced from the exact tag by GitHub Actions.

## v5.4.1 publication

```bash
git add -A
git commit -m "fix: harden updater recovery and v5.3-v5.4 scope integrity in Licora v5.4.1"
git push origin main
# Wait for CI success.
git tag -a v5.4.1 -m "Licora v5.4.1 - Updater Recovery and Scope Integrity Hotfix"
git push origin v5.4.1
```

The Release workflow packages and signs the exact tag, verifies the signature, then runs `scripts/verify-release-update.php` so the exact ZIP/manifest/signature must also pass the installed runtime verifier and archive validator before GitHub publication.

## v5.4.0 publication

```bash
git add -A
git commit -m "feat: migrate Licora to VibTools light component UI in v5.4.0"
git push origin main
# Wait for CI success.
git tag -a v5.4.0 -m "Licora v5.4.0 - VibTools Light Component UI"
git push origin v5.4.0
```

The tag-triggered Release workflow checks the exact tag, re-runs source/database gates, packages the exact tag, builds/signs/verifies the updater manifest and publishes all four assets with `RELEASE_NOTES_v5.4.0.md`. Manual `gh release create` is not part of the normal workflow.

## Package hygiene

Release archives must exclude deployment-private/runtime material including `config.local.php`, encryption/install flags, API v2 signing keys, updater runtime state, update private keys, `.env*`, logs, backups, exports and `.git`. See `scripts/package-release.sh`.

## Future release rule

Every future release intended for one-click installation must update `update/release-spec.json`, declare the exact direct source versions it supports in signed `upgrade_from`, ship every migration required for those supported direct paths, keep `minimum_updater` compatible, and publish the four assets above. A latest release that does not list the installed version in `upgrade_from` is deliberately blocked rather than silently skipping an intermediate migration. Never modify a published manifest/ZIP in place; create a new semantic version.

See [UPDATER.md](UPDATER.md) for the runtime trust/rollback model, [UI_DESIGN_SYSTEM.md](UI_DESIGN_SYSTEM.md) for the v5.4.0 presentation contract, and `RELEASE_COMMANDS_v5.4.1.md` for the current Windows-friendly command sequence.
