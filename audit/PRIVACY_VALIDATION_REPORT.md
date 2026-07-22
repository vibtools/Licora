# Privacy and Archive Safety Validation

**Date:** 2026-07-22

## Uploaded archive safety

- ZIP entries inspected: **59**
- Absolute or traversal paths: **0**
- Unsafe entries: **none**

## Private deployment data regression scan

The scan extracts sensitive-value classes from the untouched original database and checks for exact reappearance anywhere in the public release tree. It reports counts only and never publishes the values.

| Data class | Original unique values checked | Public release matches |
|---|---:|---:|
| License keys | 50 | 0 |
| Non-local IP addresses | 460 | 0 |
| Long nontrivial quoted database values | 344 | 0 |

## Result

**PASS** — no original license key, non-local IP address, or long sensitive database value was found in the GitHub-ready tree.

Raw original database values are intentionally absent from this report, the public static inventory, and the redacted unified diff.
