#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
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

echo "[1/9] Checking required files"
required=(
  README.md LICENSE SECURITY.md CONTRIBUTING.md database.sql
  CHANGELOG.md RELEASE_NOTES_v5.0.1.1.md RELEASE_NOTES_v5.1.0.md
  PHASE2_INSTALLER_SUMMARY.md REPOSITORY_METADATA.md RELEASE_COMMANDS_v5.1.0.md
  audit/V5.1.0_RELEASE_DELTA_AUDIT.md audit/V5.1.0_DELTA_PATCH_MANIFEST.txt audit/V5.1.0_DELTA_FILE_SHA256SUMS.txt
  includes/config.php includes/installation.php
  install.php install/index.php
  scripts/package-release.sh
  tests/security_smoke.php tests/compatibility_regression.php tests/installer_smoke.php tests/release_readiness.php
  docs/CONFIGURATION.md docs/RELEASE.md docs/INSTALLER_ARCHITECTURE.md
  docs/FIRST_RUN_GUIDE.md docs/UPGRADE_GUIDE.md docs/DEMO_DATA.md
  docs/FAQ.md docs/COMPATIBILITY_MATRIX.md docs/TROUBLESHOOTING.md
)
for path in "${required[@]}"; do
  test -f "$path" || { echo "Missing required file: $path" >&2; exit 1; }
done

echo "[2/9] PHP syntax"
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find . -type f -name '*.php' -not -path './vendor/*' -print0 | sort -z)

echo "[3/9] Security smoke test"
php tests/security_smoke.php

echo "[4/9] Compatibility regression test"
php tests/compatibility_regression.php

echo "[5/9] Installer smoke test"
php tests/installer_smoke.php

echo "[6/9] Release readiness test"
php tests/release_readiness.php

echo "[7/9] JavaScript syntax"
if command -v node >/dev/null 2>&1; then
  node --check admin/assets/js/admin-ui.js
else
  echo "Node.js not installed; JavaScript syntax check skipped."
fi

echo "[8/9] Public-release marker scan"
"${PYTHON_CMD[@]}" - <<'PY_PUBLIC_MARKER_SCAN'
from pathlib import Path
import base64

# Encoded solely to keep retired deployment identifiers out of the public tree.
encoded_markers = [
    'c2VsaXVtLnNpdGU=',
    'Y3guY29kZXJ2aWIuY29t',
    'UGF5UGFsLUF1dG8=',
    'RG9jSHViQWNCb3Q=',
    'RmFjZUJvb2sgT1RQ',
    'QTlmSzJYN21RNFpQOE42UjJMSkg1RDNDN1dCTUVUWFU=',
]
markers = [base64.b64decode(value) for value in encoded_markers]
violations = []
for path in Path('.').rglob('*'):
    if not path.is_file() or any(part in {'.git', 'audit', 'vendor', 'node_modules'} for part in path.parts):
        continue
    try:
        data = path.read_bytes()
    except OSError:
        continue
    if any(marker in data for marker in markers):
        violations.append(path.as_posix())
if violations:
    raise SystemExit('Private deployment marker detected in: ' + ', '.join(sorted(violations)))
PY_PUBLIC_MARKER_SCAN

echo "[9/9] SQL seed scope"
"${PYTHON_CMD[@]}" - <<'PY_VALIDATE_SQL'
from pathlib import Path
import re
text = Path('database.sql').read_text(encoding='utf-8')
tables = re.findall(r'INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?', text, re.I)
allowed = {'admin_users', 'settings'}
unexpected = sorted(set(tables) - allowed)
if unexpected:
    raise SystemExit(f'Unexpected seeded tables: {unexpected}')
print('Approved seed tables:', ', '.join(sorted(set(tables))))
PY_VALIDATE_SQL

echo "Validation passed."
