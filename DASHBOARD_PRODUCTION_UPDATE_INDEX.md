# Licora Dashboard Production Update — Documentation Index

## Authority

এই documentation set **Licora v5.5.1 Baseline Freeze** থেকে তৈরি।

- Baseline ZIP SHA-256: `c443b95ad28b8996526d190c5408671c2c405beeff7ff9b8ba7b4ef42b1161d7f`
- Git commit: `2f48ef569e6c532ab0de974a418c644e4ea8423f`
- Git tree: `c9986ba8b22f3d32c3b3d746dc24c7754d6d0132`
- Version: `5.5.1`
- Development implementation status: **PHASE 1 IMPLEMENTED — LOCAL VERIFIER PASS; MANDATORY DB/CI GATE PENDING**
- Planned update phases: **2**
- Target release/version number: **v5.6.0 — Phase 1**

এই delta **documentation-only**। কোনো PHP, JavaScript, CSS, SQL, API contract, database schema, cron behavior, updater behavior বা runtime feature পরিবর্তন করে না।

## Document Map

| Document | Purpose |
|---|---|
| `audit/V5.5.1_DASHBOARD_PRODUCTION_READINESS_FORENSIC_REPORT.md` | Baseline forensic findings, production-readiness gaps, scope boundary |
| `docs/DASHBOARD_PRODUCTION_ROADMAP_2_PHASE.md` | A–Z implementation roadmap, maximum 2 phases |
| `docs/DASHBOARD_UPDATE_PHASE_LOG.md` | Phase completion ledger and continuation point |
| `docs/ERROR_HANDLING_BASELINE_AND_TARGET.md` | Existing error handling + required additions |
| `docs/ACTUAL_IMPLEMENTATION_LEDGER.md` | What actually works now, what becomes working after each phase, what remains |
| `docs/DASHBOARD_DATA_CONTRACT.md` | Exact read-only dashboard data semantics and proposed AJAX response contract |
| `docs/DASHBOARD_PRODUCTION_VALIDATION_GATES.md` | Automated/manual acceptance gates before phase completion/release |
| `docs/DASHBOARD_CHANGE_CONTROL.md` | Freeze rules, decision rules, no-scope-creep rules, rollback discipline |
| `audit/DASHBOARD_PRODUCTION_DOCS_DELTA_MANIFEST.md` | Documentation delta contents and extraction instructions |
| `audit/DASHBOARD_PRODUCTION_DOCS_DELTA_SHA256SUMS.txt` | SHA-256 checksums for every file in this patch |

## Mandatory Working Order

1. Baseline Freeze remains immutable.
2. Read forensic report.
3. Read exact data contract.
4. Implement **Phase 1 only**.
5. Run Phase 1 validation gates.
6. Update `DASHBOARD_UPDATE_PHASE_LOG.md` and `ACTUAL_IMPLEMENTATION_LEDGER.md`.
7. Only after Phase 1 is verified, implement **Phase 2**.
8. Run final production gates.
9. Update logs/ledger.
10. Only then prepare a runtime delta/release.

## Continuation Rule

যে phase `COMPLETE + VERIFIED` নয় সেটিকে completed ধরা যাবে না।  
যে feature source/tests দিয়ে verify হয়নি সেটিকে `ACTUAL WORKING` লেখা যাবে না।
