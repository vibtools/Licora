# Licora v5.2.0 Release Workflow

Licora v5.2.0 removes the previous manual ZIP/checksum/GitHub Release publication sequence.

## Local source verification

From the repository root:

```bat
python scripts\verify-local.py
```

or from Git Bash:

```bash
bash scripts/validate.sh
```

This validates local source only. It does not create a tag, release ZIP, or GitHub Release.

## Push and CI

Commit and push normally. GitHub Actions automatically runs PHP 8.0–8.4 validation, the API v2 MySQL integration test, and a source-package job. When all required jobs pass, the CI run contains a verified ZIP/checksum artifact.

## Publish v5.2.0

After `main` is green, create and push the annotated tag:

```bat
git tag -a v5.2.0 -m "Licora v5.2.0 - Secure API v2" && git push origin v5.2.0
```

The tag-triggered Release workflow automatically:

1. Checks out the exact tag.
2. Confirms tag and source version consistency.
3. Runs full source verification.
4. Runs the API v2 MySQL integration test.
5. Builds `Licora-5.2.0.zip` from the exact Git tag.
6. Generates `Licora-5.2.0.zip.sha256`.
7. Creates the GitHub Release using `RELEASE_NOTES_v5.2.0.md`.
8. Uploads the ZIP and checksum as release assets.

Do not manually create or upload replacement assets for the same tag unless recovering from a documented workflow failure.
