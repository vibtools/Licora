#!/usr/bin/env python3
"""Local source verifier for Licora v5.2.1.

This verifier validates source and tests only. It never creates a Git tag, release,
or GitHub artifact. Release packaging is intentionally owned by GitHub Actions and
scripts/package-release.sh.
"""
from __future__ import annotations

import base64
import hashlib
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "5.2.1"

V1_GIT_BLOBS = {
    "api/verify.php": "4dc549c2afea0772d3f2ffa8b330fd24b8b13ec2",
    "api/check_license.php": "e3bc343489bb384304e003fa25e02d494fd83b8a",
    "includes/functions.php": "778f02afc27d989acd2d58ed28001a5f325f5cda",
    "includes/security.php": "6c2cfd4ce1d10eb02b41ce1a77ebeeb71de05532",
}

REQUIRED = [
    "README.md", "CHANGELOG.md", "SECURITY.md", "REPOSITORY_METADATA.md",
    "RELEASE_NOTES_v5.2.1.md", "RELEASE_COMMANDS_v5.2.1.md",
    "audit/V5.2.1_PHASE02_STEP001_FORENSIC_AUDIT.md", "audit/V5.2.1_DELTA_PATCH_MANIFEST.txt", "audit/V5.2.1_DELTA_FILE_SHA256SUMS.txt",
    "migration-v5.2.0-api-v2.sql", "database.sql", "includes/.htaccess",
    "api/verify.php", "api/check_license.php",
    "api/v2/activate.php", "api/v2/refresh.php", "api/v2/status.php", "api/v2/deactivate.php",
    "includes/v2/V2Exception.php", "includes/v2/V2KeyManager.php", "includes/v2/V2TokenService.php",
    "includes/v2/V2DeviceProof.php", "includes/v2/ApiV2.php", "includes/v2/V2Repository.php", "includes/v2/V2Provisioner.php", "includes/v2/bootstrap.php",
    "admin/client_apps.php", "admin/v2_devices.php",
    "scripts/setup-v2.php", "scripts/verify-local.py", "scripts/validate.sh", "scripts/package-release.sh",
    "tests/api_v1_freeze.php", "tests/api_v2_crypto.php", "tests/api_v2_static.php", "tests/api_v2_db_integration.php",
    "docs/API_V2.md", "docs/API_V2_SECURITY.md", "docs/API_V2_CLIENT_INTEGRATION.md", "docs/API_V2_MIGRATION.md",
    "docs/CONFIGURATION.md", "docs/ARCHITECTURE.md", "docs/RELEASE.md", "docs/INSTALLATION.md", "docs/UPGRADE_GUIDE.md", "docs/FEATURE_MATRIX.md",
    ".github/workflows/ci.yml", ".github/workflows/release.yml",
]

TESTS = [
    "tests/security_smoke.php",
    "tests/compatibility_regression.php",
    "tests/installer_smoke.php",
    "tests/release_readiness.php",
    "tests/api_v1_freeze.php",
    "tests/api_v2_crypto.php",
    "tests/api_v2_static.php",
    "tests/api_v2_db_integration.php",
]


def fail(message: str) -> None:
    raise SystemExit(f"VERIFY FAILED: {message}")


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def git_blob_sha1(path: Path) -> str:
    data = path.read_bytes().replace(b"\r\n", b"\n").replace(b"\r", b"\n")
    return hashlib.sha1(b"blob " + str(len(data)).encode("ascii") + b"\0" + data).hexdigest()


def run(command: list[str]) -> None:
    print("$", subprocess.list2cmdline(command), flush=True)
    subprocess.run(command, cwd=ROOT, check=True)


def workflow_action_refs(text: str, action: str) -> list[str]:
    pattern = re.compile(r"uses:\s*" + re.escape(action) + r"@([^\s#]+)")
    return pattern.findall(text)


def version_tuple(ref: str) -> tuple[int, ...]:
    value = ref[1:] if ref.startswith("v") else ref
    if not re.fullmatch(r"\d+(?:\.\d+)*", value):
        fail(f"workflow action ref is not a numeric version tag: {ref}")
    return tuple(int(part) for part in value.split("."))


def require_action_minimum(text: str, action: str, minimum: tuple[int, ...], label: str) -> None:
    refs = workflow_action_refs(text, action)
    if not refs:
        fail(f"{label} action is missing: {action}")
    for ref in refs:
        current = version_tuple(ref)
        padded_current = current + (0,) * max(0, len(minimum) - len(current))
        padded_minimum = minimum + (0,) * max(0, len(current) - len(minimum))
        if padded_current < padded_minimum:
            fail(f"{label} action {action}@{ref} is below supported minimum {'.'.join(map(str, minimum))}")


print("[1/12] Required source structure")
for rel in REQUIRED:
    if not (ROOT / rel).is_file():
        fail(f"missing required file: {rel}")

print("[2/12] Frozen API v1 implementation")
for rel, expected in V1_GIT_BLOBS.items():
    actual = git_blob_sha1(ROOT / rel)
    if actual != expected:
        fail(f"API v1 drift in {rel}: {actual} != {expected}")

print("[3/12] Release/version consistency")
config = read("includes/config.php")
if f"env_value('APP_VERSION', '{VERSION}')" not in config:
    fail("runtime APP_VERSION is not 5.2.1")
for rel in ["config.sample.php", "install.php", "includes/installation.php", "RELEASE_NOTES_v5.2.1.md", "CHANGELOG.md"]:
    if VERSION not in read(rel):
        fail(f"5.2.1 release marker missing from {rel}")

print("[4/12] API v2 protocol/security contract")
v2_endpoint_text = "\n".join(read(f"api/v2/{name}.php") for name in ("activate", "refresh", "status", "deactivate"))
v2_client_code = v2_endpoint_text + "\n" + "\n".join(read("includes/v2/" + name) for name in ("ApiV2.php", "V2DeviceProof.php", "V2Exception.php", "V2KeyManager.php", "V2Repository.php", "V2Provisioner.php", "V2TokenService.php", "bootstrap.php"))
if re.search(r"(?i)\bX-API-Key\b|\bapi_key\b|LICENSE_API_KEY", v2_client_code):
    fail("API v2 public-client implementation contains a shared/master API-key marker")
for marker in [
    "LICENSE_V2_REQUIRE_HTTPS", "REQUEST_TOO_LARGE", "INVALID_CONTENT_TYPE", "X_LICORA_TIMESTAMP",
    "X_LICORA_NONCE", "X_LICORA_DEVICE_SIGNATURE", "Cache-Control", "licora-api-v2",
]:
    if marker not in (read("includes/v2/ApiV2.php") + read("includes/v2/bootstrap.php")):
        fail(f"API v2 request-security marker missing: {marker}")
for marker in ["OPENSSL_KEYTYPE_EC", "prime256v1", "OPENSSL_ALGO_SHA256"]:
    if marker not in read("includes/v2/V2DeviceProof.php"):
        fail(f"device-proof cryptography marker missing: {marker}")
for marker in ["RS256", "LICORA-V2", "token_version", "device_key_fingerprint"]:
    if marker not in read("includes/v2/V2TokenService.php"):
        fail(f"access-token marker missing: {marker}")

for marker in ["assertPairMatches", "matching pair", "allowNonCli"]:
    if marker not in read("includes/v2/V2KeyManager.php"):
        fail(f"signing-key pair validation marker missing: {marker}")
for marker in ["class V2Provisioner", "migrationStatements", "provision(bool", "schema_ready", "key_pair_ready"]:
    if marker not in read("includes/v2/V2Provisioner.php"):
        fail(f"API v2 provisioning marker missing: {marker}")
refresh = read("api/v2/refresh.php")
rate_context_pos = refresh.find("refreshRateLimitContext")
transaction_pos = refresh.find("refreshContextForUpdate")
if rate_context_pos < 0 or transaction_pos < 0 or rate_context_pos > transaction_pos:
    fail("refresh app/device rate-limit context must be consumed before the refresh transaction opens")

print("[5/12] Additive database/migration contract")
migration = read("migration-v5.2.0-api-v2.sql")
for table in ["v2_client_apps", "v2_device_credentials", "v2_refresh_tokens", "v2_used_nonces", "v2_audit_logs"]:
    if f"CREATE TABLE IF NOT EXISTS {table}" not in migration:
        fail(f"missing API v2 table: {table}")
stripped = re.sub(r"--[^\n]*", "", migration)
if re.search(r"\b(?:DROP|TRUNCATE|RENAME)\b", stripped, re.I):
    fail("destructive statement found in API v2 migration")
if "-- Licora v5.2.0 Secure API v2 additive migration." not in read("database.sql"):
    fail("fresh-install database.sql does not contain API v2 additive schema")
# Public seed scope remains limited to existing admin/settings seed categories.
seed_tables = {m.lower() for m in re.findall(r"INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?", read("database.sql"), re.I)}
if seed_tables - {"admin_users", "settings"}:
    fail(f"unexpected public database seed tables: {sorted(seed_tables)}")

print("[6/12] Signing-key and secret hygiene")
for rel in [
    "includes/.licora-v2-signing-private.pem", "includes/.licora-v2-signing-public.pem",
    "includes/.licora-v2-signing-private.pem.installing", "includes/.licora-v2-signing-public.pem.installing",
]:
    if (ROOT / rel).exists():
        fail(f"deployment signing-key material present in repository: {rel}")
gitignore = read(".gitignore")
for marker in ["includes/.licora-v2-signing-private.pem", "includes/.licora-v2-signing-public.pem"]:
    if marker not in gitignore:
        fail(f"API v2 signing-key ignore missing: {marker}")
if "Require all denied" not in read("includes/.htaccess"):
    fail("Apache includes/ deny rule is missing")
encoded_markers = [
    "c2VsaXVtLnNpdGU=", "Y3guY29kZXJ2aWIuY29t", "UGF5UGFsLUF1dG8=", "RG9jSHViQWNCb3Q=",
    "RmFjZUJvb2sgT1RQ", "QTlmSzJYN21RNFpQOE42UjJMSkg1RDNDN1dCTUVUWFU=",
]
markers = [base64.b64decode(x) for x in encoded_markers]
violations: list[str] = []
for path in ROOT.rglob("*"):
    if not path.is_file() or any(part in {".git", "audit", "vendor", "node_modules", "release"} for part in path.parts):
        continue
    try:
        data = path.read_bytes()
    except OSError:
        continue
    if any(marker in data for marker in markers):
        violations.append(path.relative_to(ROOT).as_posix())
if violations:
    fail("private deployment marker detected in: " + ", ".join(sorted(violations)))
private_key_markers = [
    base64.b64decode(value) for value in (
        "LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0t",
        "LS0tLS1CRUdJTiBSU0EgUFJJVkFURSBLRVktLS0tLQ==",
        "LS0tLS1CRUdJTiBFQyBQUklWQVRFIEtFWS0tLS0t",
    )
]
private_key_files: list[str] = []
for path in ROOT.rglob("*"):
    if not path.is_file() or any(part in {".git", "vendor", "node_modules", "release"} for part in path.parts):
        continue
    try:
        data = path.read_bytes()
    except OSError:
        continue
    if any(marker in data for marker in private_key_markers):
        private_key_files.append(path.relative_to(ROOT).as_posix())
if private_key_files:
    fail("private-key PEM material detected in repository: " + ", ".join(sorted(private_key_files)))

print("[7/12] Admin/UI integration")
nav = read("admin/includes/navbar.php")
for marker in ["client_apps.php", "v2_devices.php", "Client Apps", "V2 Devices"]:
    if marker not in nav:
        fail(f"API v2 navigation marker missing: {marker}")
license_ui = read("admin/license.php")
for marker in ["$v2AppOptions", "$v2AllowedAppIds", "API v2 Client App", "bulk_v2_app_scope", "Selected API v2 client application is not active or does not exist"]:
    if marker not in license_ui:
        fail(f"license/API v2 app-scope UI marker missing: {marker}")

client_apps = read("admin/client_apps.php")
for marker in ["V2Provisioner", "initialize_v2", "Initialize API v2", "authenticated admin UI"]:
    if marker not in client_apps:
        fail(f"cPanel/admin API v2 provisioning marker missing: {marker}")
setup_v2 = read("scripts/setup-v2.php")
if "V2Provisioner" not in setup_v2:
    fail("CLI API v2 setup does not use the shared provisioner")

print("[8/12] GitHub CI/release automation")
ci = read(".github/workflows/ci.yml")
release = read(".github/workflows/release.yml")
for text, label in [(ci, "CI"), (release, "release")]:
    require_action_minimum(text, "actions/checkout", (6,), label)
    require_action_minimum(text, "actions/setup-python", (6,), label)
    require_action_minimum(text, "actions/setup-node", (6,), label)
    require_action_minimum(text, "shivammathur/setup-php", (2, 37, 2), label)
require_action_minimum(ci, "actions/upload-artifact", (6,), "CI")
for marker in ["mysql-integration", "scripts/package-release.sh"]:
    if marker not in ci:
        fail(f"CI automation marker missing: {marker}")
for marker in ["tags:", "contents: write", "gh release create", "--verify-tag", "RELEASE_NOTES_${GITHUB_REF_NAME}.md", "scripts/package-release.sh"]:
    if marker not in release:
        fail(f"release automation marker missing: {marker}")

print("[9/12] PHP syntax")
php = shutil.which("php")
if not php:
    fail("PHP CLI is required")
for path in sorted(ROOT.rglob("*.php")):
    if any(part in {"vendor", "release"} for part in path.parts):
        continue
    run([php, "-l", str(path)])

print("[10/12] Regression/security tests")
for rel in TESTS:
    if not (ROOT / rel).is_file():
        fail(f"missing test: {rel}")
    run([php, rel])

print("[11/12] JavaScript syntax")
node = shutil.which("node")
js = ROOT / "admin/assets/js/admin-ui.js"
if node and js.is_file():
    run([node, "--check", str(js)])
else:
    print("Node.js not installed; JavaScript syntax check skipped locally.")

print("[12/12] Documentation and release packaging contract")
for rel in ["docs/API_V2.md", "docs/API_V2_SECURITY.md", "docs/API_V2_CLIENT_INTEGRATION.md", "docs/API_V2_MIGRATION.md"]:
    text = read(rel)
    if "API v2" not in text and "API V2" not in text:
        fail(f"API v2 documentation marker missing: {rel}")
packager = read("scripts/package-release.sh")
for marker in ["git archive", "scripts/verify-local.py", "sha256", ".licora-v2-signing-private.pem"]:
    if marker not in packager:
        fail(f"release packaging marker missing: {marker}")

print("Licora v5.2.1 local verification passed.")
