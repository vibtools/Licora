#!/usr/bin/env python3
"""Apply the scope-locked Licora v5.2.0 API-v2 delta to the official v5.1.0 baseline.

The script makes only deterministic edits that cannot be represented by ZIP overwrite
semantics. New/replacement files are supplied directly by the delta archive.
"""
from __future__ import annotations

import hashlib
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

BASELINE_BLOBS = {
    ".gitignore": "66d84cf214d31395a9dea1c19a6b943333ee07a0",
    "includes/config.php": "e959f0333300ed63980209b38842a1138ae0c076",
    "config.sample.php": "976068a7749182caa7234ab27c38f54228b8cb4a",
    "database.sql": "95a87d4914f6e0b29f3d9c606b00fdebc5a75ba4",
    "install.php": "fa627c7dab7ee1c9d332679223ed65b411142e91",
    "includes/installation.php": "a14d14ece9678477f82ca2fc8bfe4fd47595be17",
    "admin/includes/navbar.php": "597f4f965f12845f15e4f02159cd97d24a0569d6",
    "admin/license.php": "67cfa488c68a8e010684012bac86696a695212d7",
    "README.md": "e3ca2a1ab4d25e978fcca910cc4bc576585f6832",
    "CHANGELOG.md": "4295b8d76b9c5f7b53a865020e04e956f8f7eedb",
    "SECURITY.md": "c695c6c6a31547521eb09d6131fe7fa20217e11d",
    "docs/CONFIGURATION.md": "c91885b77197b1be811aab5a1f7a5af60afd6afc",
    "docs/RELEASE.md": "54ee78d37d8e3537340dd814c145f84abbf8fb94",
    "docs/ARCHITECTURE.md": "41a80f7a42dc4c09e623346f6c53c63619ea7742",
    "docs/API.md": "01d79c585e03ad2bfb16a7f5468eebf9c2085102",
    "docs/FEATURE_MATRIX.md": "87dfcbbf00bb29c36120aec9cea06df71fbb7ce0",
    "docs/INSTALLATION.md": "ccc2cd74de2ce90e232b5c107ab5d30e906f735d",
    "docs/UPGRADE_GUIDE.md": "21e28c821b0608bb263f72700d167bd35edabf76",
    "REPOSITORY_METADATA.md": "1299b438228cf2a0ded5f72cce52020f6a464edf",
    "tests/compatibility_regression.php": "7672b6f28fa3abbb446238a0701917776187faf6",
    "tests/installer_smoke.php": "831a754203abd7e427670e1f5b8a6a6460f3824a",
    "tests/release_readiness.php": "0f70e37cb09eb16672a36bcf87fef0f211134205",
}


def fail(message: str) -> None:
    raise SystemExit(f"PATCH FAILED: {message}")


def blob_sha(text: str) -> str:
    data = text.replace("\r\n", "\n").replace("\r", "\n").encode("utf-8")
    return hashlib.sha1(b"blob " + str(len(data)).encode("ascii") + b"\0" + data).hexdigest()


def load(rel: str) -> str:
    path = ROOT / rel
    if not path.is_file():
        fail(f"missing baseline file: {rel}")
    return path.read_text(encoding="utf-8")


def save(rel: str, text: str) -> None:
    (ROOT / rel).write_text(text, encoding="utf-8", newline="\n")


def guard(rel: str, text: str, final_marker: str) -> bool:
    if final_marker and final_marker in text:
        return False
    expected = BASELINE_BLOBS[rel]
    actual = blob_sha(text)
    if actual != expected:
        fail(f"{rel} is not the official v5.1.0 baseline and does not contain the v5.2.0 marker: {actual} != {expected}")
    return True


def replace_once(text: str, old: str, new: str, rel: str) -> str:
    count = text.count(old)
    if count != 1:
        fail(f"expected exactly one patch anchor in {rel}, found {count}: {old[:70]!r}")
    return text.replace(old, new, 1)


# .gitignore
rel = ".gitignore"; text = load(rel)
if guard(rel, text, "includes/.licora-v2-signing-private.pem"):
    text = text.rstrip() + """

# Licora API v2 deployment signing material
includes/.licora-v2-signing-private.pem
includes/.licora-v2-signing-public.pem
includes/.licora-v2-signing-private.pem.installing.*
includes/.licora-v2-signing-public.pem.installing.*
"""
    save(rel, text)

# Runtime configuration/version.
rel = "includes/config.php"; text = load(rel)
if guard(rel, text, "Licora v5.2.0 API v2 public/runtime configuration"):
    text = replace_once(text, "env_value('APP_VERSION', '5.1.0')", "env_value('APP_VERSION', '5.2.0')", rel)
    anchor = "if (!defined('API_VERSION')) define('API_VERSION', env_value('API_VERSION', 'v1'));"
    block = anchor + """

// Licora v5.2.0 API v2 public/runtime configuration.
// No server signing private key or API-v1 credential is embedded here.
if (!defined('LICENSE_V2_REQUIRE_HTTPS')) define('LICENSE_V2_REQUIRE_HTTPS', env_value('LICENSE_V2_REQUIRE_HTTPS', '1'));
if (!defined('LICENSE_TRUST_PROXY_HEADERS')) define('LICENSE_TRUST_PROXY_HEADERS', env_value('LICENSE_TRUST_PROXY_HEADERS', '0'));
if (!defined('LICENSE_V2_MAX_BODY_BYTES')) define('LICENSE_V2_MAX_BODY_BYTES', (int)env_value('LICENSE_V2_MAX_BODY_BYTES', 32768));
if (!defined('LICENSE_V2_RATE_LIMIT')) define('LICENSE_V2_RATE_LIMIT', (int)env_value('LICENSE_V2_RATE_LIMIT', 300));
if (!defined('LICENSE_V2_CLOCK_SKEW')) define('LICENSE_V2_CLOCK_SKEW', (int)env_value('LICENSE_V2_CLOCK_SKEW', 300));
if (!defined('LICENSE_V2_SIGNING_KEY_ID')) define('LICENSE_V2_SIGNING_KEY_ID', env_value('LICENSE_V2_SIGNING_KEY_ID', 'primary-v1'));
if (!defined('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH')) define('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH', env_value('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH', __DIR__ . '/.licora-v2-signing-private.pem'));
if (!defined('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH')) define('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH', env_value('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH', __DIR__ . '/.licora-v2-signing-public.pem'));
"""
    text = replace_once(text, anchor, block, rel)
    save(rel, text)

rel = "config.sample.php"; text = load(rel)
if guard(rel, text, "LICENSE_V2_SIGNING_PRIVATE_KEY_PATH"):
    text = replace_once(text, "define('APP_VERSION', '5.1.0')", "define('APP_VERSION', '5.2.0')", rel)
    block = """if (!defined('LICENSE_V2_REQUIRE_HTTPS')) define('LICENSE_V2_REQUIRE_HTTPS', '1');
if (!defined('LICENSE_TRUST_PROXY_HEADERS')) define('LICENSE_TRUST_PROXY_HEADERS', '0');
if (!defined('LICENSE_V2_MAX_BODY_BYTES')) define('LICENSE_V2_MAX_BODY_BYTES', 32768);
if (!defined('LICENSE_V2_RATE_LIMIT')) define('LICENSE_V2_RATE_LIMIT', 300);
if (!defined('LICENSE_V2_CLOCK_SKEW')) define('LICENSE_V2_CLOCK_SKEW', 300);
if (!defined('LICENSE_V2_SIGNING_KEY_ID')) define('LICENSE_V2_SIGNING_KEY_ID', 'primary-v1');
if (!defined('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH')) define('LICENSE_V2_SIGNING_PRIVATE_KEY_PATH', __DIR__ . '/.licora-v2-signing-private.pem');
if (!defined('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH')) define('LICENSE_V2_SIGNING_PUBLIC_KEY_PATH', __DIR__ . '/.licora-v2-signing-public.pem');
"""
    text = replace_once(text, "?>", block + "?>", rel)
    save(rel, text)

# Current installer release identity plus additive API v2 signing-key provisioning.
rel = "includes/installation.php"; text = load(rel)
if guard(rel, text, "licora_installer_prepare_v2_signing_keys"):
    helper_anchor = "if (!function_exists('licora_installer_finalize')) {"
    helper = r"""if (!function_exists('licora_installer_prepare_v2_signing_keys')) {
    function licora_installer_prepare_v2_signing_keys(?string $root): array
    {
        $root = licora_installation_root($root);
        $includes = $root . '/includes';
        $privatePath = $includes . '/.licora-v2-signing-private.pem';
        $publicPath = $includes . '/.licora-v2-signing-public.pem';
        if (is_file($privatePath) || is_file($publicPath)) {
            throw new RuntimeException('API v2 signing key files already exist.');
        }
        $resource = openssl_pkey_new([
            'private_key_bits' => 3072,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            throw new RuntimeException('Unable to generate API v2 signing key.');
        }
        $privatePem = '';
        if (!openssl_pkey_export($resource, $privatePem)) {
            throw new RuntimeException('Unable to export API v2 signing private key.');
        }
        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || empty($details['key'])) {
            throw new RuntimeException('Unable to export API v2 signing public key.');
        }
        $suffix = '.installing.' . bin2hex(random_bytes(6));
        $privateTemporary = $privatePath . $suffix;
        $publicTemporary = $publicPath . $suffix;
        if (file_put_contents($privateTemporary, $privatePem, LOCK_EX) === false) {
            throw new RuntimeException('Unable to prepare API v2 signing private key.');
        }
        @chmod($privateTemporary, 0600);
        if (file_put_contents($publicTemporary, (string)$details['key'], LOCK_EX) === false) {
            @unlink($privateTemporary);
            throw new RuntimeException('Unable to prepare API v2 signing public key.');
        }
        @chmod($publicTemporary, 0644);
        return [
            'private' => $privatePath, 'public' => $publicPath,
            'private_tmp' => $privateTemporary, 'public_tmp' => $publicTemporary,
        ];
    }
}

"""
    text = replace_once(text, helper_anchor, helper + helper_anchor, rel)
    state_anchor = "        $flagTemporary = $flagPath . '.installing.' . bin2hex(random_bytes(6));\n        $configActivated = false;"
    state_block = "        $flagTemporary = $flagPath . '.installing.' . bin2hex(random_bytes(6));\n        $configActivated = false;\n        $v2Keys = null;\n        $v2PrivateActivated = false;\n        $v2PublicActivated = false;"
    text = replace_once(text, state_anchor, state_block, rel)
    text = replace_once(text, "            licora_installer_execute_schema($pdo, $root . '/database.sql');", "            $v2Keys = licora_installer_prepare_v2_signing_keys($root);\n\n            licora_installer_execute_schema($pdo, $root . '/database.sql');", rel)
    activate_anchor = "            if (!@rename($configTemporary, $configPath)) {"
    activate_block = r"""            if (!is_array($v2Keys) || !@rename($v2Keys['private_tmp'], $v2Keys['private'])) {
                throw new RuntimeException('Unable to activate API v2 signing private key.');
            }
            $v2PrivateActivated = true;
            @chmod($v2Keys['private'], 0600);
            if (!@rename($v2Keys['public_tmp'], $v2Keys['public'])) {
                throw new RuntimeException('Unable to activate API v2 signing public key.');
            }
            $v2PublicActivated = true;
            @chmod($v2Keys['public'], 0644);

""" + activate_anchor
    text = replace_once(text, activate_anchor, activate_block, rel)
    catch_anchor = "            @unlink($configTemporary);\n            @unlink($flagTemporary);"
    catch_block = """            @unlink($configTemporary);
            @unlink($flagTemporary);
            if (is_array($v2Keys)) {
                @unlink($v2Keys['private_tmp']);
                @unlink($v2Keys['public_tmp']);
                if ($v2PrivateActivated) { @unlink($v2Keys['private']); }
                if ($v2PublicActivated) { @unlink($v2Keys['public']); }
            }"""
    text = replace_once(text, catch_anchor, catch_block, rel)
    if "5.1.0" not in text: fail("missing v5.1.0 release marker in includes/installation.php")
    text = text.replace("5.1.0", "5.2.0")
    save(rel, text)

rel = "install.php"; text = load(rel)
if guard(rel, text, "5.2.0"):
    if "5.1.0" not in text: fail("missing v5.1.0 release marker in install.php")
    text = text.replace("5.1.0", "5.2.0")
    text = replace_once(text, "Application, encryption, CSRF, and JWT secrets are generated securely. Secret values are never displayed.", "Application, encryption, CSRF, JWT secrets, and the API v2 server signing key pair are generated securely. Secret values are never displayed.", rel)
    text = replace_once(text, "<li>Database password and generated secrets will not be displayed.</li>", "<li>Database password, generated secrets, and the API v2 signing private key will not be displayed.</li>", rel)
    text = replace_once(text, "<p>Licora will initialize the unchanged, existing database schema from <code>database.sql</code>, including its existing indexes, constraints, triggers, and additive migrations.</p>", "<p>Licora will initialize <code>database.sql</code>, preserving the existing API v1 schema and adding only the Secure API v2 tables documented for v5.2.0.</p>", rel)
    text = replace_once(text, '<dt class="col-sm-4">Schema changes</dt><dd class="col-sm-8">None beyond the existing repository schema</dd>', '<dt class="col-sm-4">Schema changes</dt><dd class="col-sm-8">Additive Secure API v2 tables; existing API v1 schema preserved</dd>', rel)
    text = replace_once(text, '<dt class="col-sm-4">Business logic</dt><dd class="col-sm-8">Unchanged</dd>', '<dt class="col-sm-4">Business logic</dt><dd class="col-sm-8">API v1 unchanged; Secure API v2 added separately</dd>', rel)
    text = replace_once(text, "<li>The existing schema and application business logic remain unchanged.</li>", "<li>The existing API v1 schema and behavior remain preserved; Secure API v2 additions are additive.</li>", rel)
    save(rel, text)

# Fresh-install schema gets the exact additive migration.
rel = "database.sql"; text = load(rel)
marker = "-- Licora v5.2.0 Secure API v2 additive migration."
if guard(rel, text, marker):
    migration = load("migration-v5.2.0-api-v2.sql").rstrip() + "\n"
    if not migration.startswith(marker): fail("API v2 migration marker mismatch")
    save(rel, text.rstrip("\r\n") + "\n\n" + migration)

# Admin navigation.
rel = "admin/includes/navbar.php"; text = load(rel)
if guard(rel, text, "href=\"client_apps.php\""):
    anchor = """                <li class=\"nav-item\">\n                    <a class=\"nav-link <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>\" href=\"settings.php\">"""
    insertion = """                <li class=\"nav-item\">\n                    <a class=\"nav-link <?php echo $currentPage == 'client_apps.php' ? 'active' : ''; ?>\" href=\"client_apps.php\">\n                        <i class=\"bi bi-boxes\"></i> <span>Client Apps</span>\n                    </a>\n                </li>\n                <li class=\"nav-item\">\n                    <a class=\"nav-link <?php echo $currentPage == 'v2_devices.php' ? 'active' : ''; ?>\" href=\"v2_devices.php\">\n                        <i class=\"bi bi-shield-check\"></i> <span>V2 Devices</span>\n                    </a>\n                </li>\n""" + anchor
    text = replace_once(text, anchor, insertion, rel)
    save(rel, text)

# License page: existing API-v1 selector/logic stays; API-v2 app scope is additive.
rel = "admin/license.php"; text = load(rel)
if guard(rel, text, "$v2AppOptions"):
    anchor = "} catch (Exception $e) { $appOptions = []; }\n\n// লাইসেন্স তৈরি"
    block = """} catch (Exception $e) { $appOptions = []; }
$v2AppOptions = [];
try {
    if (AdminHelpers::tableExists('v2_client_apps')) {
        $v2AppOptions = $db->query("SELECT app_id, display_name FROM v2_client_apps WHERE is_active = 1 ORDER BY display_name, app_id")->fetchAll();
    }
} catch (Exception $e) { $v2AppOptions = []; }
$v2AllowedAppIds = array_values(array_filter(array_map(static function ($row) { return (string)($row['app_id'] ?? ''); }, $v2AppOptions)));

// লাইসেন্স তৈরি"""
    text = replace_once(text, anchor, block, rel)
    single_logic_anchor = "    $license_api_key_id = (int)($_POST['license_api_key_id'] ?? 0);\n    if ($license_api_key_id > 0) {"
    single_logic = "    $license_api_key_id = (int)($_POST['license_api_key_id'] ?? 0);\n    if ($license_api_key_id <= 0 && $app_scope !== '' && !in_array($app_scope, $v2AllowedAppIds, true)) {\n        $error = 'Selected API v2 client application is not active or does not exist';\n        $app_scope = '';\n    }\n    if ($license_api_key_id > 0) {"
    text = replace_once(text, single_logic_anchor, single_logic, rel)
    bulk_logic_anchor = "    $bulkApiKeyId = (int)($_POST['bulk_license_api_key_id'] ?? 0);\n    if ($bulkApiKeyId > 0) {"
    bulk_logic = "    $bulkApiKeyId = (int)($_POST['bulk_license_api_key_id'] ?? 0);\n    if ($bulkApiKeyId <= 0 && $appScope !== '' && !in_array($appScope, $v2AllowedAppIds, true)) {\n        $error = 'Selected API v2 client application is not active or does not exist';\n        $appScope = '';\n    }\n    if ($bulkApiKeyId > 0) {"
    text = replace_once(text, bulk_logic_anchor, bulk_logic, rel)
    hidden = '                                <input type="hidden" name="app_scope" value="">'
    single = """                                <div class=\"mb-3\">\n                                    <label class=\"form-label\">API v2 Client App</label>\n                                    <select class=\"form-select\" name=\"app_scope\" id=\"v2_app_scope\">\n                                        <option value=\"\">No API v2 app scope</option>\n                                        <?php foreach ($v2AppOptions as $v2App): ?>\n                                            <option value=\"<?php echo Security::escape($v2App['app_id']); ?>\"><?php echo Security::escape(($v2App['display_name'] ?? $v2App['app_id']) . ' — ' . $v2App['app_id']); ?></option>\n                                        <?php endforeach; ?>\n                                    </select>\n                                    <small class=\"text-muted\">Used by Secure API v2. If an API v1 key is selected above, the existing v1 binding remains authoritative.</small>\n                                </div>"""
    text = replace_once(text, hidden, single, rel)
    bulk_hidden = '                            <input type="hidden" name="bulk_app_scope" value="">'
    bulk = """                            <div class=\"col-12\">\n                                <label class=\"form-label\">API v2 Client App</label>\n                                <select class=\"form-select\" name=\"bulk_app_scope\" id=\"bulk_v2_app_scope\">\n                                    <option value=\"\">No API v2 app scope</option>\n                                    <?php foreach ($v2AppOptions as $v2App): ?>\n                                        <option value=\"<?php echo Security::escape($v2App['app_id']); ?>\"><?php echo Security::escape(($v2App['display_name'] ?? $v2App['app_id']) . ' — ' . $v2App['app_id']); ?></option>\n                                    <?php endforeach; ?>\n                                </select>\n                                <small class=\"text-muted\">Applies the same API v2 app scope to this bulk batch.</small>\n                            </div>"""
    text = replace_once(text, bulk_hidden, bulk, rel)
    save(rel, text)

# Compatibility tests: retain old immutable objects, make database.sql additive-proof aware, align current version.
rel = "tests/compatibility_regression.php"; text = load(rel)
if guard(rel, text, "v5.2 database preserves the complete v5.1.0 schema prefix"):
    text = text.replace("    'database.sql' => '6a39d7fbb8a48b51cba1118457465b4c31eea1023d18cbeb803f8f2e84430fa6',\n", "")
    text = text.replace("5.1.0", "5.2.0")
    anchor = "$publicRoutes = ["
    block = """$databaseSource = $read('database.sql');
$v2DatabaseMarker = '-- Licora v5.2.0 Secure API v2 additive migration.';
$v2DatabasePosition = strpos($databaseSource, $v2DatabaseMarker);
$assert($v2DatabasePosition !== false, 'v5.2 database contains the additive API v2 marker');
if ($v2DatabasePosition !== false) {
    $prefix = substr($databaseSource, 0, $v2DatabasePosition);
    $trimmed = rtrim($prefix, "\\r\\n");
    $candidates = [
        hash('sha256', $prefix),
        hash('sha256', $trimmed),
        hash('sha256', $trimmed . "\\n"),
        hash('sha256', str_replace(["\\r\\n", "\\r"], "\\n", $trimmed . "\\n")),
    ];
    $assert(in_array('6a39d7fbb8a48b51cba1118457465b4c31eea1023d18cbeb803f8f2e84430fa6', $candidates, true), 'v5.2 database preserves the complete v5.1.0 schema prefix');
}

$publicRoutes = ["""
    text = replace_once(text, anchor, block, rel)
    save(rel, text)

rel = "tests/installer_smoke.php"; text = load(rel)
if guard(rel, text, "API v2 installer signing key pair is generated"):
    text = text.replace("5.1.0", "5.2.0")
    anchor = "$assert(licora_installation_write_flag($tempRoot, '5.2.0'), 'installation flag written atomically');"
    key_test = r"""$v2InstallerKeys = licora_installer_prepare_v2_signing_keys($tempRoot);
$assert(is_file($v2InstallerKeys['private_tmp']) && is_file($v2InstallerKeys['public_tmp']), 'API v2 installer signing key pair is generated');
$assert(openssl_pkey_get_private((string)file_get_contents($v2InstallerKeys['private_tmp'])) !== false, 'API v2 installer private key is valid');
$assert(openssl_pkey_get_public((string)file_get_contents($v2InstallerKeys['public_tmp'])) !== false, 'API v2 installer public key is valid');
@unlink($v2InstallerKeys['private_tmp']);
@unlink($v2InstallerKeys['public_tmp']);

""" + anchor
    text = replace_once(text, anchor, key_test, rel)
    save(rel, text)

rel = "tests/release_readiness.php"; text = load(rel)
if guard(rel, text, "5.2.0"):
    text = text.replace("5.1.0", "5.2.0")
    text = text.replace("5.1.1", "5.2.1")
    text = text.replace("## [5.2.0] - 2026-08-06", "## [5.2.0] - 2026-08-08")
    save(rel, text)

# README public integration guidance.
rel = "README.md"; text = load(rel)
if guard(rel, text, "## Secure API v2 (v5.2.0)"):
    text = text.replace("Use the full endpoint for new integrations:", "API v1 remains available for trusted/legacy integrations:", 1)
    text = text.replace("`X-API-Key` is the recommended authentication header for the current release. See [docs/API.md](docs/API.md) for endpoint behavior, response fields, and integration warnings.", "`X-API-Key` remains the API v1 authentication header. New desktop/public clients should use Secure API v2, which does not embed a shared Licora API key. See [docs/API.md](docs/API.md) and [docs/API_V2.md](docs/API_V2.md).", 1)
    text = replace_once(text, "| Application version | `APP_VERSION` | `5.1.0` |", "| Application version | `APP_VERSION` | `5.2.0` |", rel)
    text = replace_once(text, "- [v5.1.0 release notes](RELEASE_NOTES_v5.1.0.md)", "- [v5.2.0 release notes](RELEASE_NOTES_v5.2.0.md)\n- [v5.1.0 release notes](RELEASE_NOTES_v5.1.0.md)", rel)
    text = replace_once(text, "- The legacy `/api/check_license.php` endpoint remains unauthenticated for compatibility; new clients should use `/api/verify.php` with an API key.", "- The legacy `/api/check_license.php` endpoint remains unauthenticated for compatibility; new desktop/public clients should use Secure API v2.", rel)
    marker = "## Known limitations"
    section = """## Secure API v2 (v5.2.0)

Licora v5.2.0 adds `/api/v2/activate.php`, `/refresh.php`, `/status.php`, and `/deactivate.php` for public/desktop clients. API v2 uses registered App IDs, P-256 device keys, RS256 server-signed short-lived access tokens, rotating hashed refresh tokens, nonce/timestamp replay protection, and device revocation. API v1 remains unchanged for existing integrations.

Existing deployments apply the additive schema and create deployment signing keys with `php scripts/setup-v2.php`. See [API v2](docs/API_V2.md), [security model](docs/API_V2_SECURITY.md), [client integration](docs/API_V2_CLIENT_INTEGRATION.md), and [migration](docs/API_V2_MIGRATION.md).

"""
    if marker not in text: fail("README Known limitations anchor missing")
    text = text.replace(marker, section + marker, 1)
    save(rel, text)

rel = "CHANGELOG.md"; text = load(rel)
if guard(rel, text, "## [5.2.0] - 2026-08-08"):
    anchor = "## [5.1.0] - 2026-08-06"
    section = """## [5.2.0] - 2026-08-08

### Added

- Added Secure API v2 activation, refresh, status and deactivation endpoints without a desktop shared/master API key.
- Added device-bound P-256 request proofs, RSA-3072/RS256 server-signed access tokens, rotating hashed refresh tokens, nonce replay protection and v2 audit logging.
- Added additive API v2 client-app/device/refresh/nonce/audit database tables and migration/setup tooling.
- Added Client Apps and V2 Devices administration pages and additive API v2 app-scope selection during license creation.
- Added local verification, API v1 freeze checks, API v2 crypto/static/database tests, CI package artifacts and tag-triggered automatic GitHub Releases.

### Compatibility

- Preserved API v1 endpoint implementation and existing v1 license/API-key behavior unchanged.
- Preserved existing license format, license engine, encryption compatibility, cron routes and existing admin workflows outside the API v2 additions.

""" + anchor
    text = replace_once(text, anchor, section, rel)
    save(rel, text)

rel = "SECURITY.md"; text = load(rel)
if guard(rel, text, "## Secure API v2 trust boundary"):
    text = text.rstrip() + """

## Secure API v2 trust boundary

Licora v5.2.0 API v2 does not require a shared server API key in desktop/public clients. Deployment RSA signing private keys remain server-side; clients use per-device P-256 key pairs and verify/use short-lived server-signed credentials. Refresh tokens are stored as hashes server-side and rotate on use. Production API v2 requires HTTPS by default and uses timestamp/nonce request proofs to resist replay.

Never commit `includes/.licora-v2-signing-private.pem`, `includes/.licora-v2-signing-public.pem`, deployment configuration, device private keys, refresh credentials, or live license/customer data. See `docs/API_V2_SECURITY.md`.
"""
    save(rel, text)

rel = "docs/API.md"; text = load(rel)
if guard(rel, text, "## Secure API v2 for desktop/public clients"):
    intro = """# API Reference

## Secure API v2 for desktop/public clients

New desktop/public integrations should use the Secure API v2 endpoints documented in `API_V2.md`. API v2 does not use the shared API v1 `X-API-Key` credential. Existing API v1 integrations remain supported and unchanged.
"""
    if not text.startswith("# API Reference\n"):
        fail("docs/API.md heading mismatch")
    text = intro + text[len("# API Reference\n"):]
    text = text.replace("- `X-API-Key: <key>` — recommended and verified by the current implementation.", "- `X-API-Key: <key>` — API v1 credential for trusted/legacy integrations.", 1)
    text = text.replace("The endpoint advertises `Authorization`, but the forensic audit identified inconsistent Bearer extraction. Use `X-API-Key` until the parser is corrected in a reviewed release.", "API v1 accepts its reviewed API-key credential paths for backward compatibility. This credential model is intentionally not used by API v2 desktop/public clients.", 1)
    save(rel, text)

rel = "docs/FEATURE_MATRIX.md"; text = load(rel)
if guard(rel, text, "| Secure API v2 | Implemented"):
    text = text.replace("| Bearer authentication | Defective | Header is advertised, but extraction paths are inconsistent. Use `X-API-Key`. |", "| API v1 Bearer/API-key authentication | Implemented | Existing reviewed v1 credential normalization remains supported; v1 is unchanged. |", 1)
    row_anchor = "| Full API-key verification | Implemented | `api/verify.php` with key hash lookup and scope binding. |"
    rows = row_anchor + "\n| Secure API v2 | Implemented | Device-bound P-256 request proofs, RS256 server tokens, rotating refresh credentials, replay protection and no desktop shared API key. |\n| API v2 client app management | Implemented | Admin Client Apps page controls App IDs, version floor, TTL and rate policy. |\n| API v2 device revocation | Implemented | Admin V2 Devices page revokes the credential and refresh tokens. |"
    text = replace_once(text, row_anchor, rows, rel)
    save(rel, text)

rel = "docs/CONFIGURATION.md"; text = load(rel)
if guard(rel, text, "## API v2 configuration"):
    text = text.replace("5.1.0", "5.2.0")
    text = text.rstrip() + """

## API v2 configuration

| Purpose | Environment/constant | Default |
|---|---|---|
| Require HTTPS | `LICENSE_V2_REQUIRE_HTTPS` | `1` |
| Trust proxy HTTPS header | `LICENSE_TRUST_PROXY_HEADERS` | `0` |
| Maximum JSON body bytes | `LICENSE_V2_MAX_BODY_BYTES` | `32768` |
| Base v2 request limit/hour | `LICENSE_V2_RATE_LIMIT` | `300` |
| Default clock skew seconds | `LICENSE_V2_CLOCK_SKEW` | `300` |
| Signing key ID | `LICENSE_V2_SIGNING_KEY_ID` | `primary-v1` |
| Signing private-key path | `LICENSE_V2_SIGNING_PRIVATE_KEY_PATH` | `includes/.licora-v2-signing-private.pem` |
| Signing public-key path | `LICENSE_V2_SIGNING_PUBLIC_KEY_PATH` | `includes/.licora-v2-signing-public.pem` |

Signing key files are deployment material, not repository configuration. Generate/validate them with `php scripts/setup-v2.php`.
"""
    save(rel, text)

rel = "docs/ARCHITECTURE.md"; text = load(rel)
if guard(rel, text, "## Secure API v2 architecture"):
    text = text.rstrip() + """

## Secure API v2 architecture

API v2 is additive to the existing server-rendered application and API v1. `/api/v2/*` handlers use the existing PDO connection, license/device/blacklist/rate-limit data and a separate v2 service layer under `includes/v2/`.

```text
Public client -> /api/v2 -> V2 request/proof validation -> V2Repository -> existing licenses/devices + v2 credential tables
                                               |-> V2TokenService -> deployment RSA signing key
```

`V2Repository::activate()` locks the license row before checking/registering a device so concurrent first activations cannot exceed the existing license device limit. Existing API v1 `LicenseSystem::verifyLicense()` is not changed.
"""
    save(rel, text)

rel = "docs/RELEASE.md"; text = load(rel)
if guard(rel, text, "## v5.2 automated publication authority"):
    text = text.replace("v5.1.0", "v5.2.0").replace("5.1.0", "5.2.0")
    text = text.rstrip() + """

## v5.2 automated publication authority

Local verification is `python scripts/verify-local.py` or `bash scripts/validate.sh`. Local verification never publishes a release.

Normal pushes/PRs run PHP 8.0–8.4 verification plus a dedicated MySQL API v2 integration job. After those pass, CI builds a verified source ZIP/checksum workflow artifact.

For the v5.2.0 release, the packager command remains available for forensic/manual inspection:

```bash
bash scripts/package-release.sh v5.2.0 v5.2.0
```

The authoritative publication path is tag-triggered GitHub Actions. Pushing `v5.2.0` runs full verification, packages the exact tag, generates SHA-256 and creates the GitHub Release from `RELEASE_NOTES_v5.2.0.md`. Manual `gh release create` is no longer part of the normal release procedure.
"""
    save(rel, text)

rel = "docs/INSTALLATION.md"; text = load(rel)
if guard(rel, text, "## Secure API v2 installation"):
    text = text.replace("Licora v5.1.0 provides a first-run installer", "Licora v5.2.0 provides a first-run installer", 1)
    # The exact v5.1.0 sentence is replaced rather than broadly rewriting installer guidance.
    text = replace_once(text, "The installer executes the existing `database.sql` schema and migrations. No new table, column, index, constraint, trigger, or migration is introduced by v5.1.0.", "The installer executes `database.sql`, including the additive Secure API v2 schema. Existing v1 tables, columns, indexes, constraints, triggers, routes, and license behavior remain preserved.", rel)
    text = replace_once(text, "- `includes/.licora-installed`", "- `includes/.licora-installed`\n- `includes/.licora-v2-signing-private.pem`\n- `includes/.licora-v2-signing-public.pem`", rel)
    text = replace_once(text, "The flag stores only product, version, and installation timestamp. It contains no database password, application key, encryption key, administrator password, or generated token.", "The flag stores only product, version, and installation timestamp. It contains no database password, application key, encryption key, administrator password, generated token, or API v2 private key. The API v2 private signing key remains server-side deployment material and is never displayed by the installer.", rel)
    text = replace_once(text, "4. Back up `includes/.licora-encryption.key` when present.", "4. Back up `includes/.licora-encryption.key` when present.\n5. Back up the API v2 signing key pair when present; loss of the private key invalidates access tokens and requires an intentional signing-key rotation.\n", rel).replace("5. Back up `includes/.licora-installed`.\n6. Follow", "6. Back up `includes/.licora-installed`.\n7. Follow", 1).replace("7. Restore secure private configuration", "8. Restore secure private configuration", 1)
    text = text.replace("The v5.1.0 wizard replaces that temporary row", "The v5.2.0 wizard replaces that temporary row", 1)
    text = text.replace("## v5.1.0 production-readiness checks", "## v5.2.0 production-readiness checks", 1)
    text = text.replace("Licora defines no dedicated upload, cache, or storage directory. v5.1.0 does not introduce one.", "Licora defines no dedicated upload, cache, or storage directory. v5.2.0 does not introduce one.", 1)
    text = text.rstrip() + """

## Secure API v2 installation

Fresh v5.2.0 wizard installations generate the deployment RSA-3072 API v2 signing key pair automatically and create the additive v2 tables through `database.sql`. The private key is never shown in the UI.

For an existing Licora deployment upgraded from v5.1.0, preserve all existing private files, replace source, then run:

```bash
php scripts/setup-v2.php
```

This applies only `migration-v5.2.0-api-v2.sql` and generates the signing key pair only when neither key file exists. It refuses a partial key-pair state rather than silently replacing deployment identity.
"""
    save(rel, text)

rel = "docs/UPGRADE_GUIDE.md"; text = load(rel)
if guard(rel, text, "## v5.1.0 to v5.2.0 Secure API v2 upgrade"):
    text = replace_once(text, "v5.0.1 -> v5.0.1.1 -> v5.1.0", "v5.0.1 -> v5.0.1.1 -> v5.1.0 -> v5.2.0", rel)
    text = text.rstrip() + """

## v5.1.0 to v5.2.0 Secure API v2 upgrade

The v5.1.0 first-run installer remains a historical fresh-install baseline. Existing v5.1.0 deployments upgrade in place and must not rerun the installer.

1. Back up the database, `includes/config.local.php`, `includes/.licora-encryption.key` when present, installation flag, and any existing API v2 signing files.
2. Replace application source with v5.2.0 while preserving all private/runtime files.
3. Run `python scripts/verify-local.py` to verify source before changing the database.
4. Run `php scripts/setup-v2.php` once. It applies the additive API v2 migration and creates the deployment signing key pair only when absent.
5. Register a Client App in `admin/client_apps.php`.
6. Bind test licenses to the exact API v2 App ID using the new API v2 Client App selector.
7. Verify activation, refresh, status, deactivation, device limit, revocation, API v1 regression, admin, cron and existing encrypted-data behavior.

`migration-v5.2.0-api-v2.sql` creates only the five `v2_*` tables. It does not drop, rename, or replace existing v1 tables. API v1 endpoints and their shared API-key behavior remain unchanged for compatibility.
"""
    save(rel, text)

rel = "REPOSITORY_METADATA.md"; text = load(rel)
if guard(rel, text, "## v5.2.0 release"):
    text = text.replace("authenticated license validation, API-key/application binding, device controls", "authenticated license validation, Secure API v2 device-bound clients, API-key/application binding, device controls", 1)
    text = text.rstrip() + """

## v5.2.0 release

- **Tag:** `v5.2.0`
- **Title:** `Licora v5.2.0 — Secure API v2`
- **Release notes:** `RELEASE_NOTES_v5.2.0.md`
- **Assets:** automatically generated `Licora-5.2.0.zip` and `Licora-5.2.0.zip.sha256`
- **Publication:** tag-triggered `.github/workflows/release.yml`

Licora v5.2.0 adds a desktop/public-client API that does not require embedding a shared Licora API key, while preserving API v1 compatibility.
"""
    save(rel, text)

# Update existing current-version regression tests only. Historical v5.1 docs remain historical.
rel = "tests/installer_smoke.php"; text = load(rel)
if "5.2.0" not in text:
    fail("installer smoke patch did not apply")
rel = "tests/release_readiness.php"; text = load(rel)
if "RELEASE_NOTES_v5.2.0.md" not in text:
    fail("release readiness patch did not apply")

print("Licora v5.2.0 Step-001 deterministic baseline edits applied.")
print("Running local verifier...")
subprocess.run([sys.executable, str(ROOT / "scripts" / "verify-local.py")], cwd=ROOT, check=True)
