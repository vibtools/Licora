# Changelog

All notable public-release changes are recorded here. Historical project notes remain in the original `CHANGELOG-v*.txt` files.

## [Unreleased]

### Planned

- Resolve the security and correctness items listed in the forensic audit through separate reviewed pull requests.

## [5.0.0-github-ready] - 2026-07-22

### Added

- Professional repository documentation, contribution policy, support policy, security policy, roadmap, issue templates, pull-request template, validation scripts, smoke tests, and CI workflow.
- Architecture, API, configuration, database, deployment-security, development, build, release, maintenance, migration, folder-structure, coding-standard, troubleshooting, and feature-matrix documentation.
- Complete forensic audit package, original file inventory, static inventory, change report, validation report, and unified source diff.
- Apache deny rule for the CLI-only `cron/` directory.

### Security

- Removed 2,746 operational database rows from the public schema, including license keys, API-key material, encrypted key copies, device fingerprints, IP addresses, request logs, and administrative activity.
- Replaced deployment-specific domains with neutral local defaults.
- Added private-configuration ignore rules and deployment hardening guidance.

### Changed

- Replaced the short legacy README with a complete open-source project guide.
- Converted `database.sql` from a deployment data dump to a sanitized schema and minimal local-development seed.

### Removed

- Historical generated PHP lint output files and the non-executable tree-note file named `licensesystem`. These files had no runtime references or functional impact.

### Compatibility

- Application feature code was not removed, disabled, or simplified.
- No PHP class, function, endpoint, admin page, migration, stylesheet, or JavaScript behavior was intentionally changed during repository preparation.
