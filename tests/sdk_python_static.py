#!/usr/bin/env python3
from __future__ import annotations

import ast
from pathlib import Path
import re
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[1]
SDK = ROOT / "SDK" / "python"
required = [
    "pyproject.toml",
    "requirements.txt",
    "README.md",
    "PYPI_PUBLISHING.md",
    "LICENSE",
    "licensing_public.py",
    "src/licora_sdk/__init__.py",
    "src/licora_sdk/client.py",
    "src/licora_sdk/config.py",
    "src/licora_sdk/crypto.py",
    "src/licora_sdk/errors.py",
    "src/licora_sdk/manager.py",
    "src/licora_sdk/models.py",
    "src/licora_sdk/storage.py",
    "src/licora_sdk/worker.py",
    "src/licora_sdk/decorators.py",
    "examples/headless_app.py",
    "examples/tkinter_login.py",
    "tests/test_sdk.py",
]
for rel in required:
    if not (SDK / rel).is_file():
        raise SystemExit(f"missing Python SDK file: {rel}")

pyproject = (SDK / "pyproject.toml").read_text(encoding="utf-8")
for marker in (
    'name = "licora"',
    'license = "MIT"',
    'license-files = ["LICENSE"]',
    'Documentation = "https://github.com/vibtools/Licora/tree/main/SDK/python"',
):
    if marker not in pyproject:
        raise SystemExit(f"missing PyPI package metadata: {marker}")
project_version = re.search(r'^version = "([0-9]+\.[0-9]+\.[0-9]+)"$', pyproject, re.MULTILINE)
package_init = (SDK / "src" / "licora_sdk" / "__init__.py").read_text(encoding="utf-8")
if project_version is None or f'__version__ = "{project_version.group(1)}"' not in package_init:
    raise SystemExit("Python distribution and import-package versions must match")

for path in sorted(SDK.rglob("*.py")):
    ast.parse(path.read_text(encoding="utf-8"), filename=str(path))

runtime_paths = list((SDK / "src").rglob("*.py")) + [SDK / "licensing_public.py"]
source = "\n".join(path.read_text(encoding="utf-8") for path in runtime_paths)
for forbidden in ("X-API-Key", "ADMIN_API_KEY", "BEGIN RSA PRIVATE KEY", "BEGIN EC PRIVATE KEY"):
    if forbidden in source:
        raise SystemExit(f"forbidden SDK credential marker: {forbidden}")
for marker in (
    "X-Licora-Device-Signature",
    "X-Licora-Timestamp",
    "X-Licora-Nonce",
    "LICORA-V2",
    "RS256",
    "ec.SECP256R1",
    "padding.PKCS1v15",
    "allow_redirects=False",
    "default_state_store",
    "start_background_validation",
):
    if marker not in source:
        raise SystemExit(f"missing Python SDK security/lifecycle marker: {marker}")

config = (SDK / "licensing_public.py").read_text(encoding="utf-8")
if "replace-with-your-app-id" not in config:
    raise SystemExit("public SDK config must require an explicit Licora App ID")
if re.search(r"LICORA_(?:ADMIN_)?API_KEY\s*=", config):
    raise SystemExit("public SDK config must not define shared/admin API keys")

subprocess.run(
    [sys.executable, "-m", "unittest", "discover", "-s", str(SDK / "tests"), "-v"],
    cwd=SDK,
    check=True,
)
print("Licora Python SDK static and unit checks passed.")
