# Licora v5.5.1 Release Commands

Run these after applying the verified v5.5.1 replace-ready delta to a clean `v5.5.0` working copy.

## Local verification

```cmd
python scripts\verify-local.py
php tests\ui_v550_contract.php
php tests\ui_v551_contract.php
php tests\ui_route_contract.php
php tests\ui_component_contract.php
php tests\ui_form_contract.php
php tests\ui_updater_contract.php
php tests\updater_dom_contract.php
node tests\updater_browser_runtime.js
node tests\sidebar_submenu_runtime.js
python tests\updater_builder_contract.py
git diff --check
git status
```

If Git Bash is installed:

```cmd
D:\App\Git\bin\bash.exe scripts/validate.sh
```

## Commit and push

```cmd
git add -A
git diff --cached --check
git status
git commit -m "fix: polish Settings navigation and About UI in Licora v5.5.1"
git push origin main
```

Do not create the tag until GitHub CI is green.

## Tag and release

```cmd
git pull --ff-only
git tag -a v5.5.1 -m "Licora v5.5.1 - Settings and About UI Hotfix"
git push origin v5.5.1
```

The tag-triggered release workflow must publish:

- `Licora-5.5.1.zip`
- `Licora-5.5.1.zip.sha256`
- `licora-update-manifest.json`
- `licora-update-manifest.sig`
