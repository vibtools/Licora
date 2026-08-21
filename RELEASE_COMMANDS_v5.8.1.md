# Licora v5.8.1 Release Commands

Run only after the v5.8.1 verification delta is applied and Git/CI scope has been reviewed.

## Local branch publication

```cmd
git diff --check
git status --short
```

Stage only the explicit paths supplied with the final delta audit. Do not use `git add .`, `git add -A` or `git add --all`.

After staged-scope review:

```cmd
git diff --cached --check
git diff --cached --name-status
git commit -m "feat: complete Developer Guide with v5.8.1 verification fixes"
git push -u origin feature/v5.8.0-developer-guide
```

Create/review the PR against `main`. Merge/tag/release require separate explicit authorization.

## Tagged release after merge and authorization

```cmd
git switch main
git pull --ff-only origin main
git status --short --branch
git rev-parse HEAD
git tag -a v5.8.1 -m "Licora v5.8.1 - Developer Integration Guide Complete and Verified"
git show --no-patch --decorate v5.8.1
git push origin v5.8.1
```

The tag-triggered `release.yml` must build/sign/publish the exact tag.
