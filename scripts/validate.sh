#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
if command -v python3 >/dev/null 2>&1; then exec python3 scripts/verify-local.py "$@"; fi
if command -v python >/dev/null 2>&1; then exec python scripts/verify-local.py "$@"; fi
if command -v py >/dev/null 2>&1; then exec py -3 scripts/verify-local.py "$@"; fi
echo "Python 3 is required for Licora local verification." >&2
exit 1
