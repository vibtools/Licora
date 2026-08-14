# Release Guide

## Current release contract

Licora uses semantic version tags. The current updater-bootstrap release is `5.3.0`; runtime, installer, verifier, release notes, update release specification and GitHub workflow markers must agree before a tag can publish.

A v5.3.0+ official release consists of four updater-facing assets:

```text
Licora-X.Y.Z.zip
Licora-X.Y.Z.zip.sha256
licora-update-manifest.json
licora-update-manifest.sig
```

The ZIP/checksum are generated from the exact Git ref by `scripts/package-release.sh`. `scripts/build-update-manifest.py` inventories the exact ZIP, records per-file SHA-256 values, package hash/size, commit identity, migration metadata, protected deletion intent and compatibility requirements. GitHub Actions signs the exact manifest bytes with the dedicated repository secret `LICORA_UPDATE_SIGNING_PRIVATE_KEY`; the matching public key is tracked at `includes/updater/update-signing-public.pem`.

## Mandatory pre-tag gates

```bash
python3 scripts/verify-local.py
bash scripts/validate.sh
php tests/updater_static.php
php tests/updater_manifest.php
php tests/updater_state_machine.php
php tests/updater_failure_recovery.php
php tests/updater_ui_contract.php
```

Database-backed API v2/admin/updater integration runs in GitHub CI against MySQL 8.4. CI must be green before creating the release tag.

## One-time v5.3.0 signing secret

The update private key is release infrastructure and must never be committed, bundled into a delta, copied to the hosted Licora application or printed in logs. Configure it once from a secured local file:

```bash
gh secret set LICORA_UPDATE_SIGNING_PRIVATE_KEY < /secure/path/Licora_v5.3.0_UPDATE_SIGNING_PRIVATE_KEY.pem
```

The release workflow derives the public key from that secret and compares it byte-for-byte (DER) with the tracked updater public key before it is allowed to sign/publish. A mismatched/missing secret fails the release.

## Exact local package rehearsal

From a clean committed working tree, the exact v5.3.0 source archive can be rehearsed with:

```bash
bash scripts/package-release.sh v5.3.0 v5.3.0
```

The official ZIP is still produced from the exact tag by GitHub Actions.

## v5.3.0 publication

```bash
git add -A
git commit -m "feat: add secure in-app updater in Licora v5.3.0"
git push origin main
# Wait for CI success.
git tag -a v5.3.0 -m "Licora v5.3.0 - Secure In-App Update Center"
git push origin v5.3.0
```

The tag-triggered Release workflow checks the exact tag, re-runs source and database gates, packages the exact tag, builds/signs/verifies the updater manifest, and publishes all four assets with `RELEASE_NOTES_v5.3.0.md`. Manual `gh release create` is not part of the normal workflow.

## Package hygiene

Release archives must exclude deployment-private/runtime material including `config.local.php`, encryption/install flags, API v2 signing keys, updater runtime state, update private keys, `.env*`, logs, backups, exports and `.git`. See `scripts/package-release.sh`.

## Future release rule

Every future release intended for one-click installation must update `update/release-spec.json`, ship signed migration metadata when applicable, keep its `minimum_updater` compatible with the installed updater or provide an intermediate release, and publish the four assets above. Never modify a published manifest/ZIP in place; create a new semantic version.

See [UPDATER.md](UPDATER.md) for the runtime trust/rollback model and `RELEASE_COMMANDS_v5.3.0.md` for the Windows-friendly command sequence.
