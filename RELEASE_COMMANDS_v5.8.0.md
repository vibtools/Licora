# Licora v5.8.0 Release Commands

Run only after the v5.8.0 implementation branch, remote CI and required manual Developer Guide UI/download smoke gates are complete.

```cmd
cd /d "D:\VibTools_Workspace\02_Websites\01_Licora_Open_Source_Cental_License_System\github_release"
git switch main
git pull --ff-only origin main
git status --short --branch
git rev-parse HEAD
```

Create and inspect the release tag only from the verified merge commit:

```cmd
git tag -a v5.8.0 -m "Licora v5.8.0 - Developer Integration Guide"
git show --no-patch --decorate v5.8.0
git push origin v5.8.0
gh run list --workflow release.yml --limit 3
```

Do not rewrite a published tag or release. The tag-triggered release workflow owns exact-tag packaging, checksum generation, signed updater manifest creation, DB integration gates and GitHub Release publication.
