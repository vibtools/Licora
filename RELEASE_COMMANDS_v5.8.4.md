# Licora v5.8.4 Release Commands

Run only after the v5.8.4 source commit is on `main` and all required GitHub Actions checks are green.

## Required release contract

- `update/release-spec.json` accepts both `5.8.2` and `5.8.3`.
- The signed manifest carries the additive/idempotent `v5.8.3.scoped-admin-license-api` migration.
- The release workflow builds and verifies the website ZIP, signed updater manifest, Python package artifacts and Admin SDK archive from the exact tag.

## Tag and publish

```bash
git fetch origin
git switch main
git pull --ff-only origin main
python3 scripts/verify-local.py
git status --short
git tag -a v5.8.4 -m "Licora v5.8.4 - Admin Automation and Production SDKs"
git push origin v5.8.4
```

The tag-triggered Release workflow rebuilds and verifies the exact source archive, signs the updater manifest and publishes all canonical website and SDK assets. Do not tag a different commit or publish a manually assembled archive.
