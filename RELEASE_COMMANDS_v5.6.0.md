# Licora v5.6.0 Release Commands

Run after applying the verified v5.6.0 Phase 1 replace-ready delta to a clean `v5.5.1` working copy.

## Local verification

```cmd
python scripts\verify-local.py
php tests\dashboard_data_contract.php
php tests\dashboard_db_integration.php
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

`tests/dashboard_db_integration.php` requires the same dedicated MySQL test environment used by CI (`LICORA_V2_TEST_ALLOW_SCHEMA_RESET=1` plus the `LICORA_TEST_DB_*` variables). Without that explicit test DB, it reports a skip rather than touching a normal database.

## Commit and push

Stage only the reviewed v5.6.0 Phase 1 paths; do not use `git add -A` on a mixed worktree.

```cmd
git diff --check
git status
git commit -m "feat: add truthful dashboard read model and data endpoint in Licora v5.6.0"
git push -u origin feature/v5.6.0-dashboard-phase1
```

Do not create the tag until the reviewed changes are on the intended release branch/main and GitHub CI is green.

## Tag and release

```cmd
git pull --ff-only
git tag -a v5.6.0 -m "Licora v5.6.0 - Dashboard Data Truth and Read Model"
git push origin v5.6.0
```

The tag-triggered release workflow must publish:

- `Licora-5.6.0.zip`
- `Licora-5.6.0.zip.sha256`
- `licora-update-manifest.json`
- `licora-update-manifest.sig`
