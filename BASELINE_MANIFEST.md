# Licora Phase 0 Baseline Manifest

**Manifest date:** `2026-07-23`  
**Baseline label:** `v5.0.1-baseline`  
**Repository:** `vibtools/Licora`  
**Frozen source commit:** `c3db5759ac2539ab1525c73530ef5984b4b73ed6`  
**Repository version:** `5.0.1`  
**Runtime API metadata version:** `2.0`

## Baseline counts

| Metric | Count |
|---|---:|
| Frozen source files before Phase 0 artifacts | `96` |
| Frozen source directories excluding root | `17` |
| Phase 0 artifact files | `4` |
| Total files represented after adding Phase 0 artifacts | `100` |
| New directories created by Phase 0 | `0` |
| Public API endpoints | `2` |
| Admin routes/pages | `12` |
| Logical database tables | `11` |
| Database triggers | `2` |

## Phase 0 documentation generated

```text
BASELINE.md
RELEASE_NOTES_BASELINE.md
BASELINE_CHECKSUMS.sha256
BASELINE_MANIFEST.md
```

## Snapshot coverage

| Requirement | Baseline reference |
|---|---|
| Repository identity and integrity | `BASELINE.md`, Sections 1–2 |
| API snapshot | `BASELINE.md`, Sections 4–5 |
| Database snapshot | `BASELINE.md`, Sections 6–18 |
| Folder snapshot | `BASELINE.md`, Section 19 |
| Feature snapshot | `BASELINE.md`, Sections 20–21 |
| Configuration snapshot | `BASELINE.md`, Sections 22–26 |
| Security baseline | `BASELINE.md`, Sections 27–35 |
| Compatibility contract | `BASELINE.md`, Sections 36–51 |
| Release notes | `RELEASE_NOTES_BASELINE.md` |
| Critical SHA-256 inventory | `BASELINE_CHECKSUMS.sha256` |
| Baseline manifest | `BASELINE_MANIFEST.md` |

## Audit references

```text
audit/FORENSIC_AUDIT_REPORT.md
audit/FINAL_FILE_INVENTORY.csv
audit/PHP_SYMBOL_INVENTORY.md
audit/DEPENDENCY_REPORT.md
audit/PRIVACY_VALIDATION_REPORT.md
audit/VALIDATION_REPORT.md
```

## Release and tag identity

```text
Tag recommendation: v5.0.1-baseline
Release title: Licora v5.0.1-baseline — Immutable Baseline Snapshot
Tag target: c3db5759ac2539ab1525c73530ef5984b4b73ed6
```

## Integrity statement

- Frozen source commit is the application baseline.
- Phase 0 artifacts are documentation/checksum metadata only.
- No PHP, SQL, HTML, CSS or JavaScript runtime file is altered by the generated artifact set.
- No route, API, database table, column, index, foreign key, trigger, license format, encryption format, session key or configuration key is changed.
- Current behavior remains defined by the frozen source commit.

## GitHub integration status

The connected GitHub integration successfully verified repository metadata and current source content. It returned `403 Resource not accessible by integration` when creating a branch reference and does not expose tag/release creation. The immutable tag/release identity is therefore documented but not created by this integration.
