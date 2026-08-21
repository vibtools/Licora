# Licora v5.7.0 — Phase 2 Review / Publication Commands

This file records the reviewed Windows/CMD sequence for the v5.7.0 Dashboard Phase 2 delta. It does not authorize GitHub writes by itself.

## Preconditions

- Official parent baseline: published `v5.6.1`
- Parent commit: `4b430b77ccc303aebeadc2852bebd3f11f67452a`
- Parent release ZIP SHA-256: `0ca0ad76b5c0091912aa441fcac4c033a54bac630d6c1a7255ac5b2b75db5493`
- Apply the replace-ready delta only to a clean checkout of the reviewed parent baseline/main.
- Stop on any unexpected file, error, failed check or merge conflict.

## Create the Phase 2 branch

```cmd
git switch main
git pull --ff-only origin main
git status --short --branch
git switch -c feature/v5.7.0-dashboard-phase2
```

After extracting the reviewed delta into the repository root:

```cmd
git diff --check
git status --short
```

Only the reviewed v5.7.0 delta paths should appear.

## Exact staging command

Never use `git add -A`, `git add .` or `git add --all` for this release.

```cmd
git add -- .github/workflows/ci.yml BASELINE_v5.7.0.md CHANGELOG.md DASHBOARD_PRODUCTION_UPDATE_INDEX.md README.md RELEASE_COMMANDS_v5.7.0.md RELEASE_NOTES_v5.7.0.md REPOSITORY_METADATA.md ROADMAP.md admin/assets/css/admin-ui.css admin/assets/js/dashboard.js admin/index.php audit/V5.7.0_DASHBOARD_PHASE2_AUDIT.md audit/V5.7.0_DELTA_MANIFEST.md audit/V5.7.0_DELTA_SHA256SUMS.txt config.sample.php docs/ACTUAL_IMPLEMENTATION_LEDGER.md docs/ARCHITECTURE.md docs/CONFIGURATION.md docs/DASHBOARD_CHANGE_CONTROL.md docs/DASHBOARD_DATA_CONTRACT.md docs/DASHBOARD_PRODUCTION_VALIDATION_GATES.md docs/DASHBOARD_UPDATE_PHASE_LOG.md docs/ERROR_HANDLING_BASELINE_AND_TARGET.md docs/FEATURE_MATRIX.md docs/INSTALLATION.md docs/RELEASE.md docs/UI_DESIGN_SYSTEM.md docs/UPGRADE_GUIDE.md includes/config.php includes/installation.php install.php scripts/verify-local.py tests/compatibility_regression.php tests/dashboard_browser_runtime.js tests/dashboard_data_contract.php tests/dashboard_phase2_contract.php tests/installer_smoke.php tests/release_readiness.php tests/updater_state_machine.php update/release-spec.json
```

Then inspect exactly what is staged:

```cmd
git diff --cached --check
git diff --cached --name-status
git status
```

## Commit / push — only after explicit GitHub-write authorization

```cmd
git commit -m "feat: add reload-free compact Dashboard in Licora v5.7.0"
git push -u origin feature/v5.7.0-dashboard-phase2
```

Create/review a PR targeting `main`, then run the required CI once. Do not merge on a failing/pending gate.

## Tag / release — only after Phase 2 final acceptance and explicit authorization

Do not tag until the PR is merged, `main` is clean/synchronized, remote CI is green, and the required manual production/staging Dashboard smoke is accepted.

```cmd
git switch main
git pull --ff-only origin main
git status --short --branch
git tag -a v5.7.0 -m "Licora v5.7.0 - Compact Dashboard and Reload-Free Refresh"
git push origin v5.7.0
```

The tag-triggered release workflow must then build/verify the exact-tag ZIP, run the required database gates, build/sign the update manifest and publish the GitHub Release.
