#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-}"
REF="${2:-${VERSION}}"
OUT="${3:-}"

if [[ -z "$VERSION" ]]; then
  echo "Usage: bash scripts/package-release.sh <vX.Y.Z|X.Y.Z> [git-ref] [output.zip]" >&2
  exit 2
fi
VERSION_NO_V="${VERSION#v}"
ARCHIVE_ROOT="Licora-${VERSION_NO_V}"
if [[ -z "$OUT" ]]; then OUT="$ROOT/../${ARCHIVE_ROOT}.zip"; fi
CHECKSUM_OUT="${OUT}.sha256"
cd "$ROOT"

resolve_python_command() {
  if command -v python3 >/dev/null 2>&1; then PYTHON_CMD=(python3)
  elif command -v python >/dev/null 2>&1; then PYTHON_CMD=(python)
  elif command -v py >/dev/null 2>&1; then PYTHON_CMD=(py -3)
  else echo "Python 3 is required for release validation and packaging." >&2; exit 1; fi
  "${PYTHON_CMD[@]}" -c 'import sys; raise SystemExit(0 if sys.version_info.major == 3 else 1)' || { echo "Python 3 is required." >&2; exit 1; }
}
resolve_python_command

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then echo "Release packaging requires a Git working tree." >&2; exit 1; fi
if ! git rev-parse --verify "${REF}^{commit}" >/dev/null 2>&1; then echo "Unknown release ref: ${REF}" >&2; exit 1; fi
if ! git diff --quiet || ! git diff --cached --quiet; then echo "Tracked working-tree changes are present. Commit or restore them before packaging." >&2; exit 1; fi

REF_VERSION="$(git show "${REF}:includes/config.php" | sed -n "s/.*env_value('APP_VERSION', '\([^']*\)').*/\1/p" | head -n 1)"
if [[ "$REF_VERSION" != "$VERSION_NO_V" ]]; then echo "Release ref version ${REF_VERSION:-unknown} does not match requested ${VERSION_NO_V}." >&2; exit 1; fi

TEMP_DIR="$(mktemp -d)"
cleanup(){ rm -rf "$TEMP_DIR"; }
trap cleanup EXIT
mkdir -p "$TEMP_DIR/source"
git archive "$REF" | tar -x -C "$TEMP_DIR/source"
(
  cd "$TEMP_DIR/source"
  "${PYTHON_CMD[@]}" scripts/verify-local.py
)

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT" "$CHECKSUM_OUT"
git archive --format=zip --prefix="${ARCHIVE_ROOT}/" -o "$OUT" "$REF"

"${PYTHON_CMD[@]}" - "$OUT" "$CHECKSUM_OUT" "$ARCHIVE_ROOT" <<'PY'
from pathlib import Path
import hashlib, sys, zipfile
archive=Path(sys.argv[1]).resolve(); checksum=Path(sys.argv[2]).resolve(); prefix=sys.argv[3].rstrip('/')+'/'
forbidden=(".git/", ".env", "config.local.php", ".licora-encryption.key", ".licora-v2-signing-private.pem", ".licora-v2-signing-public.pem", ".licora-installed", ".licora-updater/", "update-signing-private.pem", "logs/", "backups/", "exports/")
with zipfile.ZipFile(archive) as z:
    names=z.namelist()
    if not names or any(not n.startswith(prefix) for n in names): raise SystemExit("Release archive prefix validation failed")
    bad=[]
    for name in names:
        rel=name[len(prefix):]
        if any(part in rel for part in forbidden): bad.append(rel)
    if bad: raise SystemExit("Forbidden release paths: "+", ".join(sorted(bad)))
digest=hashlib.sha256(archive.read_bytes()).hexdigest()
checksum.write_text(f"{digest}  {archive.name}\n", encoding="ascii")
print(archive); print(checksum); print("SHA-256:", digest)
PY
