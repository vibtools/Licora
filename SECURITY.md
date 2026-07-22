# Security Policy

## Supported release

The latest tagged public release receives security documentation and best-effort fixes. Older snapshots may be referenced for migration context but are not guaranteed to receive patches.

## Reporting a vulnerability

Send a private report to `support@vib.tools` with:

- Affected file, endpoint, or admin action.
- Reproduction steps using non-production data.
- Expected and observed behavior.
- Security impact and prerequisites.
- Suggested mitigation, when available.

Do not include real license keys, API keys, passwords, device hashes, customer information, or live database exports.

## Response expectations

The maintainer should acknowledge receipt, reproduce the issue, assign severity, prepare a coordinated fix, and publish release notes without exposing unnecessary exploitation detail.

## Deployment warning

This repository contains self-hosted security-sensitive software. A safe production deployment requires HTTPS, private configuration, restricted installer and cron access, least-privilege database credentials, tested backups, log retention, and review of [audit/FORENSIC_AUDIT_REPORT.md](audit/FORENSIC_AUDIT_REPORT.md).

The supplied temporary development account must be changed before any internet exposure.
