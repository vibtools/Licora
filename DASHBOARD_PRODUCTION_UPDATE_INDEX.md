# Licora Dashboard Production Update — Documentation Index

## Authority

এই documentation program v5.5.1 থেকে শুরু হয়েছিল; বর্তমান immutable source authority হলো uploaded **Licora v5.7.0 Official Baseline Freeze**।

- Current baseline ZIP SHA-256: `e198fda3a90f38ef0d15faeab3f0b2797b92ba98b542cb7f22ac8f01b3bda022`
- Current baseline embedded Git HEAD: `4b430b77ccc303aebeadc2852bebd3f11f67452a`
- Current baseline version: `5.7.0`
- Original Phase-program baseline: `v5.5.1 / 2f48ef569e6c532ab0de974a418c644e4ea8423f`
- Development implementation status: **v5.7.1 Phase 2 corrective source + local verification PASS — remote CI/live acceptance gates pending**
- Planned update phases: **2**
- Current target: **v5.7.1 — Phase 2 verification corrective candidate**

এই document set Phase 1 verified foundation, v5.7.0 Phase 2 source baseline এবং v5.7.1 corrective verification candidate record করে। Phase 2 Dashboard presentation/browser behavior পরিবর্তন করে, কিন্তু database schema, external API contracts, license/device enforcement, authentication/roles, Cron mutation behavior, updater protocol এবং shared sidebar/topbar architecture অপরিবর্তিত রাখে।

## Document Map

| Document | Purpose |
|---|---|
| `audit/V5.5.1_DASHBOARD_PRODUCTION_READINESS_FORENSIC_REPORT.md` | Baseline forensic findings, production-readiness gaps, scope boundary |
| `docs/DASHBOARD_PRODUCTION_ROADMAP_2_PHASE.md` | A–Z implementation roadmap, maximum 2 phases |
| `docs/DASHBOARD_UPDATE_PHASE_LOG.md` | Phase completion ledger and continuation point |
| `docs/ERROR_HANDLING_BASELINE_AND_TARGET.md` | Existing error handling + required additions |
| `docs/ACTUAL_IMPLEMENTATION_LEDGER.md` | What actually works now, what becomes working after each phase, what remains |
| `docs/DASHBOARD_DATA_CONTRACT.md` | Exact implemented read-only dashboard data semantics and AJAX response contract |
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
