# Licora v5.6.1 — Next Source Baseline Freeze

## Freeze Status

- Version: `5.6.1`
- Freeze type: **Source baseline freeze / corrective candidate**
- Parent Official Baseline Freeze: `Licora_v5.6.0_Baseline.zip`
- Parent ZIP SHA-256: `ba99c6e4fd74c2b59d392c3010b3aefe493390b3b0b0c94bd3c211218f14d597`
- Parent Git commit: `5c685636e955422bc70e3bf07694f55d9c7fb1dc`
- Parent Git tree: `848801c1785ebba0b2523a34afcf6af3ee05d5d6`
- Database migration: `NONE`
- Deleted files: `NONE`
- External API v1/v2 contract change: `NONE`
- Phase 2 implementation: `NONE`

## Frozen Corrective Scope

This source freeze contains only the verified Phase 1 corrective scope:

1. foreign-key-safe Dashboard MySQL integration fixture isolation;
2. exact top-level Dashboard `recent_activity` contract parity;
3. API v2 readiness based on complete schema plus valid matching signing key pair;
4. v5.6.1 version/release/CI/documentation alignment;
5. browser-agnostic runtime verification guard after confirming no Chrome installer/downloader exists in Licora source.

## Local Verification

`python3 scripts/verify-local.py` passed after the corrective runtime/test changes. The targeted release-readiness check also passed after final publication-status documentation correction. Final `git diff --check` and delta extraction/hash parity are recorded in the v5.6.1 delta manifest.

Environment-dependent DB tests could not run locally because a dedicated MySQL/MariaDB test engine and `pdo_mysql` are unavailable. The corrected PR #8 MySQL/CI run is therefore the mandatory remote acceptance gate.

## GitHub/Release Status

The source is frozen as v5.6.1 for the next commit. The final commit SHA does not exist until the user commits this exact delta. `v5.6.0` was not published as a GitHub tag/release. Do not merge/tag v5.6.1 until corrected PR #8 CI is green.

Phase 1 remains `IMPLEMENTED / REMOTE DB GATE PENDING` until that evidence exists.
