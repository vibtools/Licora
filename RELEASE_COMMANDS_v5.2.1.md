# Licora v5.2.1 Release Workflow

## Local source verification

From the repository root on Windows Command Prompt:

```bat
python scripts\verify-local.py
```

Or from Git Bash/Linux:

```bash
bash scripts/validate.sh
```

This validates source only. It does not create a tag or GitHub Release.

## Commit and push

```bat
git add -A && git commit -m "fix: harden Licora v5.2.1 API v2 verification and cPanel upgrade" && git push origin main
```

Wait for the normal CI workflow to pass, including PHP 8.0–8.4, API v2 MySQL integration, and the verified source-artifact job.

## Publish v5.2.1

After `main` is green:

```bat
git tag -a v5.2.1 -m "Licora v5.2.1 - API v2 verification and cPanel upgrade" && git push origin v5.2.1
```

The tag-triggered Release workflow automatically validates the exact tag, runs API v2 MySQL integration, builds `Licora-5.2.1.zip`, generates `Licora-5.2.1.zip.sha256`, uses `RELEASE_NOTES_v5.2.1.md`, and publishes both assets to the GitHub Release.

## Existing cPanel deployment

Preserve `includes/config.local.php`, installation/encryption/signing key files, database content, logs/backups, and other runtime/private data. Extract the source update with overwrite enabled. Then open **Admin → Client Apps → Initialize API v2** if API v2 is not ready. CLI-capable deployments may instead run:

```bat
php scripts\setup-v2.php
```
