# Licora v5.6.1 Release Commands

Run these commands only after applying the reviewed v5.6.1 corrective delta.

## Local verification

```cmd
python scripts\verify-local.py
php tests\dashboard_data_contract.php
git diff --check
git status --short --branch
```

`tests/dashboard_db_integration.php` requires the dedicated MySQL test environment used by GitHub Actions. Do not point it at a normal/production database.

## Recommended feature-branch commit

Stage only the reviewed v5.6.1 corrective paths. Do not use `git add -A`.

```cmd
git status
git diff --check
git add -- .github/workflows/ci.yml BASELINE_v5.6.1.md CHANGELOG.md DASHBOARD_PRODUCTION_UPDATE_INDEX.md README.md RELEASE_COMMANDS_v5.6.1.md RELEASE_NOTES_v5.6.1.md REPOSITORY_METADATA.md ROADMAP.md admin/ajax/dashboard-data.php admin/index.php audit/V5.6.1_DELTA_MANIFEST.md audit/V5.6.1_DELTA_SHA256SUMS.txt audit/V5.6.1_PHASE1_VERIFICATION_AUDIT.md config.sample.php docs/ACTUAL_IMPLEMENTATION_LEDGER.md docs/ARCHITECTURE.md docs/CONFIGURATION.md docs/DASHBOARD_CHANGE_CONTROL.md docs/DASHBOARD_DATA_CONTRACT.md docs/DASHBOARD_PRODUCTION_ROADMAP_2_PHASE.md docs/DASHBOARD_PRODUCTION_VALIDATION_GATES.md docs/DASHBOARD_UPDATE_PHASE_LOG.md docs/ERROR_HANDLING_BASELINE_AND_TARGET.md docs/FEATURE_MATRIX.md docs/INSTALLATION.md docs/RELEASE.md docs/UPGRADE_GUIDE.md includes/config.php includes/dashboard.php includes/installation.php install.php scripts/verify-local.py tests/compatibility_regression.php tests/dashboard_data_contract.php tests/dashboard_db_integration.php tests/installer_smoke.php tests/release_readiness.php tests/updater_state_machine.php update/release-spec.json
git status
git commit -m "fix: verify Dashboard Phase 1 and correct CI contract in Licora v5.6.1"
git push
```

The existing PR #8 should update automatically when the commit is pushed to `feature/v5.6.0-dashboard-phase1`.

## CI stop rule

Run/check PR #8. If the MySQL integration job or any other check fails, stop and inspect that new concrete failure; do not repeatedly rerun unchanged failing jobs.

Do not merge or tag until all required checks are green.

## Tag after merge and final verification

```cmd
git switch main
git pull --ff-only origin main
git tag -a v5.6.1 -m "Licora v5.6.1 - Dashboard Phase 1 Verification Fix"
git push origin v5.6.1
```
