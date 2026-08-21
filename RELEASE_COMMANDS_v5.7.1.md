# Licora v5.7.1 — Phase 2 Verification Fix Review / Publication Commands

This file records the reviewed Windows/CMD sequence for the v5.7.1 corrective candidate. It does not authorize GitHub writes by itself.

## Source authority

- Official corrective parent: uploaded `Licora_v5.7.0_Baseline.zip`
- Parent ZIP SHA-256: `e198fda3a90f38ef0d15faeab3f0b2797b92ba98b542cb7f22ac8f01b3bda022`
- Parent embedded Git HEAD: `4b430b77ccc303aebeadc2852bebd3f11f67452a`
- Parent local branch: `feature/v5.7.0-dashboard-phase2`
- Parent GitHub v5.7.0 tag: not published at audit time
- Target: `v5.7.1`
- Replace-ready corrective delta: `37 paths` relative to the uploaded v5.7.0 baseline
- Combined Git publication scope: `47 paths` relative to Git HEAD `4b430b7`
- Delete list: none
- Database migrations: none

## Why Git publication has 47 paths

The uploaded v5.7.0 baseline contains the original Phase 2 implementation as an **uncommitted** 41-path work-tree delta over the published v5.6.1 commit. v5.7.1 then corrects that baseline and adds corrective/version/audit files.

Therefore:

- the delivered v5.7.1 replace-ready delta contains only the **37 paths that differ from the uploaded v5.7.0 baseline**;
- but the Git commit that publishes Phase 2 must include the **full 47-path combined work tree** relative to the current Git HEAD, otherwise original Phase 2 files would be omitted.

Do not use `git add -A`, `git add .` or `git add --all`.

## Apply v5.7.1 corrective delta

Stay on the existing Phase 2 branch:

```cmd
git status --short --branch
```

Expected branch:

```text
feature/v5.7.0-dashboard-phase2
```

Extract `Licora-v5.7.1-Dashboard-Phase2-Verification-Fix-Delta.zip` over the repository root and overwrite matching files.

Then:

```cmd
git diff --check
git status --short
```

The combined status should contain exactly 47 reviewed paths: 32 modified and 15 untracked.

## Exact combined staging command

```cmd
git add -- .github/workflows/ci.yml BASELINE_v5.7.0.md BASELINE_v5.7.1.md CHANGELOG.md DASHBOARD_PRODUCTION_UPDATE_INDEX.md README.md RELEASE_COMMANDS_v5.7.0.md RELEASE_COMMANDS_v5.7.1.md RELEASE_NOTES_v5.7.0.md RELEASE_NOTES_v5.7.1.md REPOSITORY_METADATA.md ROADMAP.md admin/assets/css/admin-ui.css admin/assets/js/dashboard.js admin/index.php audit/V5.7.0_DASHBOARD_PHASE2_AUDIT.md audit/V5.7.0_DELTA_MANIFEST.md audit/V5.7.0_DELTA_SHA256SUMS.txt audit/V5.7.1_DASHBOARD_PHASE2_VERIFICATION_AUDIT.md audit/V5.7.1_DELTA_MANIFEST.md audit/V5.7.1_DELTA_SHA256SUMS.txt config.sample.php docs/ACTUAL_IMPLEMENTATION_LEDGER.md docs/ARCHITECTURE.md docs/CONFIGURATION.md docs/DASHBOARD_CHANGE_CONTROL.md docs/DASHBOARD_DATA_CONTRACT.md docs/DASHBOARD_PRODUCTION_VALIDATION_GATES.md docs/DASHBOARD_UPDATE_PHASE_LOG.md docs/ERROR_HANDLING_BASELINE_AND_TARGET.md docs/FEATURE_MATRIX.md docs/INSTALLATION.md docs/RELEASE.md docs/UI_DESIGN_SYSTEM.md docs/UPGRADE_GUIDE.md includes/config.php includes/installation.php install.php scripts/verify-local.py tests/compatibility_regression.php tests/dashboard_browser_runtime.js tests/dashboard_data_contract.php tests/dashboard_phase2_contract.php tests/installer_smoke.php tests/release_readiness.php tests/updater_state_machine.php update/release-spec.json
```

Then inspect once:

```cmd
git diff --cached --check
git diff --cached --name-status
git status
```

Expected: exactly 47 staged paths and no unstaged Phase 2/v5.7.1 paths.

## Commit / push — only after explicit GitHub-write authorization

```cmd
git commit -m "feat: complete Dashboard Phase 2 with v5.7.1 verification fixes"
git push -u origin feature/v5.7.0-dashboard-phase2
```

Recommended PR title:

```text
Licora v5.7.1 - Dashboard Phase 2 Complete with Verification Fixes
```

Recommended PR body summary:

```text
Implements the approved Dashboard Phase 2 compact/reload-free UI over the published v5.6.1 base and includes the v5.7.1 corrective refresh-lifecycle fixes found during forensic verification. No database migration, file deletion, external API contract change, license/device enforcement change, auth/role change, Cron mutation change, updater protocol change, or shared sidebar/topbar redesign.
```

After push, run the PR CI once. Do not merge while any required gate is pending or failing.

## Tag / release — only after final Phase 2 acceptance and explicit authorization

Do not tag until:

1. PR CI/MySQL/PHP/Windows gates are green;
2. required manual desktop/tablet/mobile staging or production smoke is accepted;
3. PR is merged;
4. local `main` is clean and synchronized.

Then:

```cmd
git switch main
git pull --ff-only origin main
git status --short --branch
git tag -a v5.7.1 -m "Licora v5.7.1 - Dashboard Phase 2 Complete and Verified"
git show --no-patch --decorate v5.7.1
git push origin v5.7.1
```

The tag-triggered release workflow must verify the exact tag, run the database gate, build the exact-tag ZIP/checksum, build/sign/verify the updater manifest, and publish the GitHub Release.
