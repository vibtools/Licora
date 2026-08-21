# Licora v5.8.2 Release Commands

Run only after the v5.8.2 hotfix delta is applied and the exact staged scope is reviewed.

## Local branch publication

```cmd
git switch main
git pull --ff-only origin main
git status --short --branch
git switch -c hotfix/v5.8.2-device-icons
```

Apply the replace-ready delta, then:

```cmd
git diff --check
git status --short
```

Stage only the explicit paths supplied with the final delta audit. Do not use `git add .`, `git add -A` or `git add --all`.

After staged-scope review:

```cmd
git diff --cached --check
git diff --cached --name-status
git commit -m "fix: restore device icons across admin UI in v5.8.2"
git push -u origin hotfix/v5.8.2-device-icons
```

Create/review the PR against `main`. Merge/tag/release require separate explicit authorization.

## Tagged release after merge and authorization

```cmd
git switch main
git pull --ff-only origin main
git status --short --branch
git rev-parse HEAD
git tag -a v5.8.2 -m "Licora v5.8.2 - Global Device Icon Compatibility Hotfix"
git show --no-patch --decorate v5.8.2
git push origin v5.8.2
```

The tag-triggered `release.yml` must build, sign and publish the exact tag.
