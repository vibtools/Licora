# Licora v5.4.0 Release Commands

Run from the authenticated Git working copy after applying the verified v5.4.0 source delta.

## Local verification

```cmd
python scripts\verify-local.py
```

Git Bash validation:

```bash
bash scripts/validate.sh
```

Targeted UI contracts:

```cmd
php tests\ui_route_contract.php
php tests\ui_form_contract.php
php tests\ui_component_contract.php
php tests\ui_updater_contract.php
```

## Commit and push

```cmd
git add -A
git diff --cached --check
git status
git commit -m "feat: migrate Licora to VibTools light component UI in v5.4.0"
git push origin main
```

Wait for the full GitHub CI matrix and MySQL integration gate to pass before tagging.

## Tag and release

```cmd
git status
git pull --ff-only
git tag -a v5.4.0 -m "Licora v5.4.0 - VibTools Light Component UI"
git push origin v5.4.0
```

The existing tag-triggered release workflow must publish:

```text
Licora-5.4.0.zip
Licora-5.4.0.zip.sha256
licora-update-manifest.json
licora-update-manifest.sig
```

An installed v5.3.0 instance then upgrades through **Admin → Updates** after signed-manifest verification and preflight pass.
