# Licora v5.4.1 Release Commands

Run from a clean Git working copy at the official v5.4.0 baseline after applying the verified v5.4.1 replace-ready delta.

## Local verification

```cmd
python scripts\verify-local.py
```

Git Bash:

```bash
bash scripts/validate.sh
```

Targeted corrective tests:

```cmd
php tests\updater_dom_contract.php
node tests\updater_browser_runtime.js
python tests\updater_builder_contract.py
php tests\updater_manifest.php
php tests\updater_state_machine.php
php tests\updater_failure_recovery.php
php tests\updater_ui_contract.php
php tests\ui_updater_contract.php
```

## Commit and push

```cmd
git add -A
git diff --cached --check
git status
git commit -m "fix: harden updater recovery and v5.3-v5.4 scope integrity in Licora v5.4.1"
git push origin main
```

Wait for the complete GitHub CI matrix, updater MySQL integration and verified source-artifact job to pass.

## Tag and release

```cmd
git status
git pull --ff-only
git tag -a v5.4.1 -m "Licora v5.4.1 - Updater Recovery and Scope Integrity Hotfix"
git push origin v5.4.1
```

The tag-triggered Release workflow must publish:

```text
Licora-5.4.1.zip
Licora-5.4.1.zip.sha256
licora-update-manifest.json
licora-update-manifest.sig
```

The Release workflow also executes `scripts/verify-release-update.php` against the signed exact-tag artifacts before publication.
