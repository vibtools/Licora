# Licora v5.2.2 Release Workflow

## Local source verification

From the repository root on Windows Command Prompt:

```bat
python scripts\verify-local.py
```

Or from Git Bash/Linux:

```bash
bash scripts/validate.sh
```

This validates source only. It does not create a tag or GitHub Release. The dedicated MySQL CI gate additionally executes the API v2 Admin UI database integration test.

## Commit and push

```bat
git add -A && git commit -m "fix: align API v2 admin schema detection in Licora v5.2.2" && git push origin main
```

Wait for the normal CI workflow to pass, including PHP 8.0–8.4 validation, API v2 MySQL integration, API v2 Admin UI database regression, and the verified source-artifact job.

## Publish v5.2.2

After `main` is green:

```bat
git tag -a v5.2.2 -m "Licora v5.2.2 - API v2 admin UI schema detection fix" && git push origin v5.2.2
```

The tag-triggered Release workflow validates the exact tag, runs the existing API v2 MySQL integration, builds `Licora-5.2.2.zip`, generates `Licora-5.2.2.zip.sha256`, uses `RELEASE_NOTES_v5.2.2.md`, and publishes both assets to the GitHub Release.

## Existing deployment

Preserve `includes/config.local.php`, installation/encryption/signing key files, database content, logs/backups, and all other runtime/private data. Overwrite only tracked application source. No v5.2.2 database migration or installer rerun is required.
