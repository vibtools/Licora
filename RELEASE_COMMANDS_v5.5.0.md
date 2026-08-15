# Licora v5.5.0 Release Commands

Run these only after applying the verified v5.5.0 replace-ready delta to a clean `v5.4.1` working copy.

## Local verification

```cmd
python scripts\verify-local.py
php tests\ui_v550_contract.php
php tests\ui_form_contract.php
php tests\ui_route_contract.php
php tests\ui_component_contract.php
php tests\ui_updater_contract.php
php tests\updater_dom_contract.php
node tests\updater_browser_runtime.js
python tests\updater_builder_contract.py
git diff --check
git status
git diff --stat
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
git commit -m "feat: refine VibTools compact UI and branding in Licora v5.5.0"
git push origin main
```

Do not tag until the pushed commit's complete GitHub CI is green, including the Windows Python builder portability job and MySQL integration job.

## Tag after CI is green

```cmd
git status
git pull --ff-only
git tag -a v5.5.0 -m "Licora v5.5.0 - VibTools Compact Light UI"
git push origin v5.5.0
```

The tag-triggered Release workflow must produce and verify:

- `Licora-5.5.0.zip`
- `Licora-5.5.0.zip.sha256`
- `licora-update-manifest.json`
- `licora-update-manifest.sig`
