# Release Guide

## Versioning

Licora uses semantic version tags. The current maintenance release is `5.2.1`; repository/runtime version markers, installer source version and release documentation must agree before a tag can publish.

The release tag must point to a reviewed commit on `main`. Do not tag a dirty working tree or package uncommitted files.

## Release gate

- [ ] `git status --short` is empty.
- [ ] `python scripts/verify-local.py` passes on the source tree.
- [ ] GitHub CI passes for PHP 8.0, 8.1, 8.2, 8.3 and 8.4.
- [ ] The dedicated MySQL 8.4 API v2 integration job passes.
- [ ] API v1 freeze verification passes.
- [ ] Fresh production installation passes with Demo Data unchecked.
- [ ] Fresh demonstration installation passes with Demo Data checked.
- [ ] Existing/cPanel overwrite upgrade preserves private/runtime files and API v2 provisioning state.
- [ ] Admin login, roles, API keys, licenses, devices, Client Apps, V2 Devices, logs, settings, exports, backups and cron entry points are checked.
- [ ] No private configuration, signing private key, API key, password, license key, device identifier, IP address, log or backup is tracked.
- [ ] `CHANGELOG.md`, `RELEASE_NOTES_v5.2.1.md`, `REPOSITORY_METADATA.md` and release documentation are current.

## Local verification

From the repository root:

```bash
python scripts/verify-local.py
```

`bash scripts/validate.sh` remains the compatibility validation entry point. Local verification validates source; it never creates a tag or publishes a GitHub Release.

## Manual forensic package check

The packager remains available for local/release-forensic inspection after the target commit/tag exists:

```bash
bash scripts/package-release.sh v5.2.1 v5.2.1
```

It validates the exact Git ref, runs the source verifier inside an archive of that ref, creates a prefixed source ZIP with `git archive`, rejects private/runtime paths and writes a SHA-256 checksum.

Default output:

```text
../Licora-5.2.1.zip
../Licora-5.2.1.zip.sha256
```

## Normal GitHub publication

The normal release path is automatic. After the `main` CI run is green, create and push the annotated release tag:

```bash
git tag -a v5.2.1 -m "Licora v5.2.1 - API v2 verification and cPanel upgrade"
git push origin v5.2.1
```

`.github/workflows/release.yml` then:

1. checks out the exact tag;
2. validates tag/source version consistency;
3. runs the full source verifier;
4. runs the dedicated MySQL API v2 integration test;
5. packages the exact tag;
6. generates SHA-256;
7. creates the GitHub Release using `RELEASE_NOTES_v5.2.1.md`;
8. attaches `Licora-5.2.1.zip` and `Licora-5.2.1.zip.sha256`.

Manual `gh release create` is not part of the normal v5.2.1 publication workflow.

## CI source artifact

Normal pushes and pull requests run the PHP 8.0–8.4 validation matrix plus the dedicated MySQL API v2 integration job. After both gates succeed, CI builds a verified source ZIP/checksum artifact for the exact commit. That CI artifact is not a public GitHub Release.

## Post-release verification

- Confirm the `v5.2.1` Release workflow is green.
- Confirm the GitHub Release is marked Latest.
- Download the published ZIP and verify its `.sha256` file.
- Extract it into a disposable directory and confirm private/runtime files are absent.
- Run the source verifier from the extracted source where the local environment supports PHP/Python/Node.
- Perform one clean browser installation and one API v1/API v2 smoke test against a disposable database.

Windows Command Prompt equivalents are provided in `RELEASE_COMMANDS_v5.2.1.md`.
