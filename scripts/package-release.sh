#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="${1:-v5.1.0}"
REF="${2:-$VERSION}"
ARCHIVE_ROOT="Licora-${VERSION#v}"
OUT="${3:-$ROOT/../${ARCHIVE_ROOT}.zip}"
CHECKSUM_OUT="${OUT}.sha256"

cd "$ROOT"

resolve_python_command() {
  if command -v python3 >/dev/null 2>&1; then
    PYTHON_CMD=(python3)
  elif command -v python >/dev/null 2>&1; then
    PYTHON_CMD=(python)
  elif command -v py >/dev/null 2>&1; then
    PYTHON_CMD=(py -3)
  else
    echo "Python 3 is required for release validation and packaging." >&2
    exit 1
  fi

  if ! "${PYTHON_CMD[@]}" -c 'import sys; raise SystemExit(0 if sys.version_info.major == 3 else 1)'; then
    echo "The selected Python command is not Python 3." >&2
    exit 1
  fi
}

resolve_python_command

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Release packaging requires a Git working tree." >&2
  exit 1
fi

if ! git rev-parse --verify "${REF}^{commit}" >/dev/null 2>&1; then
  echo "Unknown release ref: ${REF}" >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Tracked working-tree changes are present. Commit or restore them before packaging." >&2
  exit 1
fi

TEMP_DIR="$(mktemp -d)"
cleanup() {
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

mkdir -p "$TEMP_DIR/source"
git archive "$REF" | tar -x -C "$TEMP_DIR/source"
(
  cd "$TEMP_DIR/source"
  bash scripts/validate.sh
)

mkdir -p "$(dirname "$OUT")"
rm -f "$OUT" "$CHECKSUM_OUT"
git archive --format=zip --prefix="${ARCHIVE_ROOT}/" -o "$OUT" "$REF"

"${PYTHON_CMD[@]}" - "$OUT" "$CHECKSUM_OUT" <<'PY_CHECKSUM'
from pathlib import Path
import hashlib
import sys

archive = Path(sys.argv[1]).resolve()
checksum_file = Path(sys.argv[2]).resolve()
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
checksum_file.write_text(f"{digest}  {archive.name}\n", encoding="ascii")
print(archive)
print(checksum_file)
PY_CHECKSUM
