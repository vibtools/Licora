# Licora v5.3.0 Release Commands

Run from a clean repository based on the reviewed v5.2.2 baseline after applying the approved v5.3.0 delta.

## 1. Verify

```bash
python3 scripts/verify-local.py
bash scripts/validate.sh
```

With a disposable MySQL 8.x database configured through `LICORA_TEST_DB_*` and `LICENSE_DB_*`:

```bash
export LICORA_V2_TEST_ALLOW_SCHEMA_RESET=1
php tests/api_v2_db_integration.php
php tests/admin_v2_ui_db_integration.php
php tests/updater_db_integration.php
```

## 2. Review scope

```bash
git status
git diff --check
git diff --stat v5.2.2..HEAD
```

Before commit, compare the working tree against `v5.2.2` and confirm that changes belong only to the approved Secure In-App Update Center, release/version synchronization, tests, CI/release signing, and documentation.

## 3. Commit and push

```bash
git add -A
git commit -m "feat: add secure in-app updater in Licora v5.3.0"
git push origin main
```

Wait for the GitHub `CI` workflow to pass on `main`.

## 4. Configure the dedicated update signing secret

The private updater signing key must never be committed.

Using GitHub CLI from the secure machine that holds the approved private key:

```bash
gh secret set LICORA_UPDATE_SIGNING_PRIVATE_KEY < Licora_v5.3.0_UPDATE_SIGNING_PRIVATE_KEY.pem
```

The release workflow derives the public key from this secret and compares it byte-for-byte (DER form) with `includes/updater/update-signing-public.pem`. A mismatch fails the release before publication.

## 5. Tag and release

```bash
git tag -a v5.3.0 -m "Licora v5.3.0 - Secure In-App Update Center"
git push origin v5.3.0
```

The tag-triggered Release workflow must pass source verification, API v2/Admin UI/updater MySQL integration, exact-tag packaging, updater manifest generation, private/public signing-key match, signature self-verification, and GitHub Release publication.

Expected release assets:

```text
Licora-5.3.0.zip
Licora-5.3.0.zip.sha256
licora-update-manifest.json
licora-update-manifest.sig
```

## 6. Final verification

```bash
git status
git tag --points-at HEAD
```

Verify on GitHub that `v5.3.0` targets the intended commit and all release assets were produced by the successful Release workflow.
