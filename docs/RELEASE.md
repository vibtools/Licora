# Release Guide

## Versioning

Use semantic tags for public releases. Repository release numbers and the historical `APP_VERSION` API value are separate until a compatibility decision explicitly aligns them.

## Checklist

- [ ] All changes are reviewed and feature-preserving or explicitly versioned.
- [ ] `bash scripts/validate.sh` passes.
- [ ] Disposable-database manual validation passes.
- [ ] `database.sql` contains no operational data.
- [ ] No private config, API key, password, license key, device identifier, IP address, or backup is tracked.
- [ ] Migrations and rollback steps are documented.
- [ ] `CHANGELOG.md` and release notes are updated.
- [ ] Security findings are resolved or accurately disclosed.
- [ ] Screenshots are redacted.
- [ ] Archive contents and checksums are verified.

## Package

```bash
bash scripts/package-release.sh v5.0.0
```

The script creates a ZIP outside the source directory, excluding Git metadata, private configuration, caches, and generated archives.

## Suggested release metadata

See [REPOSITORY_METADATA.md](../REPOSITORY_METADATA.md).
