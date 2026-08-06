# Release Guide

## Versioning

Licora uses semantic version tags. For this release, repository version, installer version, installation flag, stored installed version, and runtime `APP_VERSION` are aligned at `5.1.0`.

The release tag must point to a reviewed commit on `main`. Do not tag a dirty working tree or package uncommitted files.

## Release gate

- [ ] `git status --short` is empty.
- [ ] Pull-request review is complete.
- [ ] GitHub Actions passes for PHP 8.0, 8.1, 8.2, 8.3, and 8.4.
- [ ] `bash scripts/validate.sh` passes locally.
- [ ] Fresh production installation passes with Demo Data unchecked.
- [ ] Fresh demonstration installation passes with Demo Data checked.
- [ ] Existing v5.0.1.1 upgrade passes without running the installer.
- [ ] Admin login, password change, roles, API keys, licenses, devices, logs, settings, exports, backups, and cron entry points are tested.
- [ ] `/api/verify.php` is tested with valid, invalid, expired, suspended, and device-limit scenarios.
- [ ] `database.sql` contains no operational data.
- [ ] No private configuration, API key, password, license key, device identifier, IP address, log, or backup is tracked.
- [ ] `CHANGELOG.md`, `RELEASE_NOTES_v5.1.0.md`, and repository metadata are current.
- [ ] The release ZIP and SHA-256 checksum are inspected.

## Build the release archive

Run from the repository root after committing and tagging:

```bash
bash scripts/package-release.sh v5.1.0 v5.1.0
```

The packager:

1. validates the repository;
2. requires a clean tracked working tree;
3. packages only files tracked by the selected Git ref using `git archive`;
4. writes a SHA-256 checksum next to the ZIP.

Default output:

```text
../Licora-v5.1.0.zip
../Licora-v5.1.0.zip.sha256
```

## Tag and publish

```bash
git switch main
git pull --ff-only origin main
bash scripts/validate.sh
git tag -a v5.1.0 -m "Licora v5.1.0 - Smart Installer and First-Run Wizard"
git push origin v5.1.0
bash scripts/package-release.sh v5.1.0 v5.1.0
gh release create v5.1.0 \
  ../Licora-v5.1.0.zip \
  ../Licora-v5.1.0.zip.sha256 \
  --verify-tag \
  --title "Licora v5.1.0 - Smart Installer and First-Run Wizard" \
  --notes-file RELEASE_NOTES_v5.1.0.md \
  --latest
```

Windows Command Prompt equivalents are provided in `RELEASE_COMMANDS_v5.1.0.md`.

## Post-release verification

- Confirm the GitHub Release is marked Latest.
- Download the published ZIP and compare its SHA-256 checksum.
- Extract it into a disposable directory.
- Confirm private/runtime files are absent.
- Run `bash scripts/validate.sh` from the extracted source.
- Perform one clean browser installation and one API smoke test from the published asset.

## Suggested release metadata

See [REPOSITORY_METADATA.md](../REPOSITORY_METADATA.md).
