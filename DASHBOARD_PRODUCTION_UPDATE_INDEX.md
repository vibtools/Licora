# Licora Dashboard Production Update — Documentation Index

## Authority

এই documentation program v5.5.1 থেকে শুরু হয়েছিল; বর্তমান corrective source authority হলো uploaded **Licora v5.6.0 Official Baseline Freeze**।

- Current baseline ZIP SHA-256: `ba99c6e4fd74c2b59d392c3010b3aefe493390b3b0b0c94bd3c211218f14d597`
- Current baseline Git commit: `5c685636e955422bc70e3bf07694f55d9c7fb1dc`
- Current baseline Git tree: `848801c1785ebba0b2523a34afcf6af3ee05d5d6`
- Current baseline version: `5.6.0`
- Original Phase-program baseline: `v5.5.1 / 2f48ef569e6c532ab0de974a418c644e4ea8423f`
- Development implementation status: **v5.6.1 corrective source locally verified — remote MySQL/CI re-run pending**
- Planned update phases: **2**
- Current corrective target: **v5.6.1 — Phase 1 verification fix**

এই document set Phase 1 runtime implementation এবং v5.6.1 corrective verification state record করে। v5.6.1 কোনো database migration, external API contract change, license/device enforcement change, Cron mutation change, updater protocol change বা Phase 2 polling/UI feature যোগ করে না।

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
