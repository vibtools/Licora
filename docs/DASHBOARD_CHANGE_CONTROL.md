# Licora Dashboard Update — Change Control & Scope Freeze

## Purpose

এই document development-এর সময় scope drift, duplicate work এবং accidental architecture change প্রতিরোধ করবে।

## Baseline Freeze

Immutable source authority:

- Licora v5.5.1
- commit `2f48ef569e6c532ab0de974a418c644e4ea8423f`

Never edit the archived baseline ZIP itself.

Implementation work must occur in a separate working copy/branch.

## Two-Phase Limit

This update program has exactly:

1. Phase 1 — Data Truth + Backend Read Model + Error Contract
2. Phase 2 — Compact UI + Reload-Free Refresh + Production Gate

A third phase must not be created silently. Any additional work is either:

- folded into an existing phase by explicit decision, or
- moved to a separate future project.

## Frozen Existing Contracts

Without explicit approval, do not change:

- license key format
- license generation
- license verification semantics
- API v1 external contract
- API v2 protocol/crypto contract
- updater signing/state-machine contract
- installer behavior
- authentication/role semantics
- existing cron mutation semantics
- database schema

## UI Rules

- use shared Licora UI components
- no page-specific stylesheet
- no inline page `<style>`
- preserve sidebar/topbar architecture
- compact geometry consistent with v5.5.x
- responsive behavior required
- no native browser `confirm()` for new important/destructive UI

## Dashboard Backend Rules

- new dashboard refresh path is read-only
- no background maintenance in a dashboard GET
- no secrets in JSON
- no externally accessible unauthenticated dashboard endpoint
- no arbitrary SQL/client-controlled query

## Data Truth Rules

- label must match source data
- production environment is not system health
- file existence is not service running
- cron script existence is not cron execution
- `api_logs` is not all API traffic
- boolean active flag is not automatically real-time presence
- future expiry is not expired

## Error Rules

- public/client error is sanitized
- internal detail goes to log
- no stack/SQL/path leakage
- failed refresh keeps last good data
- never replace unknown data with fabricated zero

## Git Discipline

For each phase:

1. inspect clean state
2. create/continue dedicated feature branch
3. change only scoped files
4. run tests
5. inspect diff
6. update phase log
7. commit only after explicit authorization
8. push only after explicit authorization

## Decision Log

Use this section for any roadmap deviation.

### Decision D-000 — Documentation Baseline

- Status: APPROVED BY USER REQUEST
- Decision: create documentation-only planning delta before implementation
- Runtime change: none

### Decision D-001 — v5.6.1 Phase 1 Verification Corrective

- Status: AUTHORIZED BY USER REQUEST
- Trigger: PR #8 Actions run `32420291770` plus forensic source audit
- Scope: fix Dashboard DB test FK cleanup, restore documented `recent_activity` response parity, require a matching API v2 signing key pair for Dashboard `Ready`, update version/docs/tests to v5.6.1
- Database migration: none
- Phase 2 UI/polling: excluded
- Chrome finding: no Chrome installer/downloader exists in the Licora runtime; no unsupported launcher was added

### Future Decision Template

```text
ID:
DATE:
PHASE:
ISSUE:
OPTIONS:
DECISION:
WHY:
FILES/CONTRACTS AFFECTED:
TEST IMPACT:
APPROVED:
```

## Stop Conditions

Stop implementation and record state if:

- baseline mismatch discovered
- unrelated worktree changes appear
- planned metric cannot be made truthful
- DB migration becomes necessary unexpectedly
- external API contract would change
- updater/installer/security regression appears
- tests expose an undocumented existing defect

Do not hide the conflict by changing unrelated architecture.
