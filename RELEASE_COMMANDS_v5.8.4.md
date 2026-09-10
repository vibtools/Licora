# Licora v5.8.4 Release Commands

Run only after the v5.8.4 source commit is on `main` and all required GitHub Actions checks are green.

```bash
git fetch origin
git switch main
git pull --ff-only origin main
python3 scripts/verify-local.py
git status --short
git tag -a v5.8.4 -m "Licora v5.8.4 - Production Python SDK"
git push origin v5.8.4
```

The tag-triggered Release workflow rebuilds and verifies the exact source archive, signs the updater manifest and publishes the canonical release assets. Do not tag a different commit or publish a manually assembled archive.
