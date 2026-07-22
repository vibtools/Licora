#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NAME="${1:-vibtools-license-management-system-v5.0.0-github-ready}"
OUT="${2:-$ROOT/../${NAME}.zip}"

cd "$ROOT"
bash scripts/validate.sh

python3 - "$ROOT" "$OUT" "$NAME" <<'PY_PACKAGE_RELEASE'
from pathlib import Path
import sys, zipfile
root = Path(sys.argv[1]).resolve()
out = Path(sys.argv[2]).resolve()
archive_root = Path(sys.argv[3])
exclude_parts = {'.git', '.idea', '.vscode', '__pycache__', 'vendor', 'node_modules'}
exclude_names = {'includes/config.local.php', '.env'}
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(root.rglob('*')):
        if not path.is_file():
            continue
        rel = path.relative_to(root)
        if any(part in exclude_parts for part in rel.parts) or rel.as_posix() in exclude_names:
            continue
        archive.write(path, archive_root / rel)
print(out)
PY_PACKAGE_RELEASE
