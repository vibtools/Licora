# Licora v5.8.1 Source Baseline Freeze

## Status

- Parent uploaded baseline: `Licora_v5.8.0_Baseline.zip`
- Parent ZIP SHA-256: `eb718abd13e4bef50654c1ee2730f37f7667abb01e54fc399b0968118bc857bb`
- Parent embedded Git HEAD: `c029fee375895e1384dd18410855da3a38443653`
- Target source version: `5.8.1`
- Release channel: stable candidate
- Database migrations: none
- Delete files: none
- Accepted updater sources: `5.7.1`, `5.8.0`

## Corrective scope

1. Re-audit and preserve the v5.8.0 Developer Integration Guide and ten approved API v2 examples.
2. Correct v5.8.x CI candidate ZIP/manifest version coherence.
3. Correct the two Dashboard device glyphs that used an icon unavailable in Bootstrap Icons 1.8.1.
4. Add targeted regression coverage and v5.8.1 release/documentation identity.

No API/database/license-device/auth/Dashboard-data/Cron/updater-runtime redesign is authorized or included. The reported external Chrome download failure is not attributable to this PHP repository because no Chrome launcher/downloader exists in the baseline; no speculative browser installer is added.

The final baseline ZIP SHA-256 is recorded in the delivery checksum file generated after this source record is frozen.
