# Licora v5.8.3 Release Commands

The requested source commit is pushed to `main`. Do not create or push the `v5.8.3` tag until the main-branch CI validation, MySQL integration, Windows contract and candidate-package jobs all pass and release publication is separately authorized.

## Verify main

```bash
git switch main
git pull --ff-only origin main
git status --short --branch
git rev-parse HEAD
python3 scripts/verify-local.py
```

Confirm the GitHub Actions run includes `tests/admin_api_db_integration.php` and completes successfully.

## Tag and publish after authorization

```bash
git tag -a v5.8.3 -m "Licora v5.8.3 - Scoped Admin License Control API"
git show --no-patch --decorate v5.8.3
git push origin v5.8.3
```

The tag-triggered release workflow must build the exact-tag ZIP/checksum, generate and sign the updater manifest, verify the signature/package/migration contract and publish using `RELEASE_NOTES_v5.8.3.md`.
