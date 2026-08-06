# Licora v5.1.0 Release Commands for Windows

These commands assume the repository is located at:

```text
D:\VibTools_Workspace\02_Websites\01_Licora_Open_Source_Cental_License_System\github_release
```

## 1. Preserve the current v5.1.1 branch work

Run before extracting the v5.1.0 delta patch:

```bat
cd /d D:\VibTools_Workspace\02_Websites\01_Licora_Open_Source_Cental_License_System\github_release

git status
git stash push -u -m "backup-before-v5.1.0-final-release"
git fetch origin --prune --tags
git switch main
git pull --ff-only origin main
git switch -c release/v5.1.0-final
```

Extract the delta ZIP into the repository root and allow overwrite/replace.

Do not extract the patch while still on `feature/v5.1.1-quality-stability`.

## 2. Validate the patched source

From Git Bash:

```bash
bash scripts/validate.sh
```

From Command Prompt:

```bat
git diff --check
git status --short
```

Complete the manual release gate described in `docs/RELEASE.md` before tagging.

## 3. Review and commit

```bat
git status
git diff --stat
git diff --check
git add -A
git diff --cached --check
git diff --cached --stat
git commit -m "release: finalize Licora v5.1.0 installer"
git push -u origin release/v5.1.0-final
```

## 4. Create and merge the pull request

With GitHub CLI:

```bat
gh pr create ^
  --base main ^
  --head release/v5.1.0-final ^
  --title "release: Licora v5.1.0 Smart Installer" ^
  --body-file RELEASE_NOTES_v5.1.0.md

gh pr checks --watch
gh pr merge --merge
```

If branch protection requires a PR number, use:

```bat
gh pr view --web
```

After merge:

```bat
git switch main
git pull --ff-only origin main
git status
git log --oneline --decorate -10
```

## 5. Run final validation on `main`

From Git Bash:

```bash
bash scripts/validate.sh
```

Confirm the exact version markers:

```bat
git grep -n "5.1.0" -- includes/config.php includes/installation.php install.php config.sample.php CHANGELOG.md RELEASE_NOTES_v5.1.0.md
```

## 6. Create the annotated tag

```bat
git tag -a v5.1.0 -m "Licora v5.1.0 - Smart Installer and First-Run Wizard"
git show --stat --oneline v5.1.0
git push origin v5.1.0
```

## 7. Build the release ZIP and checksum

Run from Git Bash:

```bash
bash scripts/package-release.sh v5.1.0 v5.1.0
```

Expected files in the parent directory:

```text
Licora-v5.1.0.zip
Licora-v5.1.0.zip.sha256
```

## 8. Publish the GitHub Release

From Command Prompt, adjust the asset paths if your repository parent differs:

```bat
gh release create v5.1.0 ^
  ..\Licora-v5.1.0.zip ^
  ..\Licora-v5.1.0.zip.sha256 ^
  --verify-tag ^
  --title "Licora v5.1.0 - Smart Installer and First-Run Wizard" ^
  --notes-file RELEASE_NOTES_v5.1.0.md ^
  --latest
```

## 9. Verify the published release

```bat
gh release view v5.1.0 --web
git status
git tag --sort=-v:refname
```

Download the published ZIP, verify its checksum, extract it into a disposable folder, run `bash scripts/validate.sh`, and complete one fresh installation plus one authenticated `/api/verify.php` test.

## Recovery of the preserved branch work

The original v5.1.1 work remains in the stash and its remote branch. To inspect the stash later:

```bat
git stash list
```

Do not apply that stash onto the v5.1.0 release branch.
