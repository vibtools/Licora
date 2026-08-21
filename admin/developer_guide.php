<?php
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/admin_helpers.php';
require_once 'includes/ui/integration.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentPage = 'developer_guide.php';
$rootUrl = licora_ui_root_url();
$endpoints = licora_ui_endpoints();
$canDownloadPublicKey = AdminHelpers::canDelete();

$languages = [
    'python' => [
        'label' => 'Python', 'icon' => 'bi-code-square',
        'file' => 'assets/examples/licora-v2/python/licora_v2_client.py',
        'install' => 'python -m pip install requests cryptography',
        'run' => 'python licora_v2_client.py --base-url "https://license.example.com" --app-id "my-app" --license-key "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD" --app-version "1.0.0"',
        'focus' => 'requests + cryptography; ephemeral P-256 lifecycle test.',
    ],
    'powershell' => [
        'label' => 'PowerShell / CMD', 'icon' => 'bi-terminal',
        'file' => 'assets/examples/licora-v2/powershell/licora-v2-test.ps1',
        'install' => 'No external PowerShell module required. Windows PowerShell 5.1+ or PowerShell 7+.',
        'run' => '.\\licora-v2-test.ps1 -BaseUrl "https://license.example.com" -AppId "my-app" -LicenseKey "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD" -AppVersion "1.0.0"',
        'focus' => 'One-file Windows connectivity/lifecycle test with built-in .NET crypto.',
    ],
    'c' => [
        'label' => 'C', 'icon' => 'bi-braces',
        'file' => 'assets/examples/licora-v2/c/licora_v2_client.c',
        'install' => 'Dependencies: libcurl, OpenSSL 3.x, cJSON. Link with -lcurl -lssl -lcrypto -lcjson.',
        'run' => './licora_v2_client https://license.example.com my-app AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD 1.0.0',
        'focus' => 'libcurl + OpenSSL + cJSON reference client.',
    ],
    'cpp' => [
        'label' => 'C++', 'icon' => 'bi-braces',
        'file' => 'assets/examples/licora-v2/cpp/licora_v2_client.cpp',
        'install' => 'Dependencies: libcurl, OpenSSL 3.x, nlohmann/json. Link with -lcurl -lssl -lcrypto.',
        'run' => './licora_v2_client https://license.example.com my-app AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD 1.0.0',
        'focus' => 'RAII-oriented libcurl/OpenSSL reference client.',
    ],
    'csharp' => [
        'label' => 'C# / .NET', 'icon' => 'bi-hash',
        'file' => 'assets/examples/licora-v2/csharp/LicoraV2Client.cs',
        'install' => 'Target .NET 8+; no third-party package is required by this reference file.',
        'run' => 'dotnet run -- https://license.example.com my-app AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD 1.0.0',
        'focus' => 'HttpClient + ECDsa P-256 + DER signature format.',
    ],
    'java' => [
        'label' => 'Java', 'icon' => 'bi-cup',
        'file' => 'assets/examples/licora-v2/java/LicoraV2Client.java',
        'install' => 'Java 17+ plus Jackson Databind (com.fasterxml.jackson.core:jackson-databind).',
        'run' => 'java LicoraV2Client https://license.example.com my-app AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD 1.0.0',
        'focus' => 'java.net.http + secp256r1 + SHA256withECDSA.',
    ],
    'flutter' => [
        'label' => 'Flutter', 'icon' => 'bi-phone',
        'file' => 'assets/examples/licora-v2/flutter/licora_v2_client.dart',
        'install' => 'flutter pub add http cryptography',
        'run' => 'Call LicoraV2Client.lifecycleTest(...) from a trusted developer/test screen; do not ship test licenses.',
        'focus' => 'http + cryptography with P-256 raw-to-DER signature conversion.',
    ],
    'react-native' => [
        'label' => 'React Native', 'icon' => 'bi-phone',
        'file' => 'assets/examples/licora-v2/react-native/licoraV2Client.js',
        'install' => 'npm install react-native-quick-crypto buffer',
        'run' => 'await lifecycleTest({ baseUrl, appId, licenseKey, appVersion });',
        'focus' => 'react-native-quick-crypto Node-compatible P-256 proof generation.',
    ],
    'php' => [
        'label' => 'PHP', 'icon' => 'bi-code-square',
        'file' => 'assets/examples/licora-v2/php/licora_v2_client.php',
        'install' => 'PHP 8.0+ with curl, openssl and json extensions.',
        'run' => 'php licora_v2_client.php https://license.example.com my-app AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD 1.0.0',
        'focus' => 'cURL + OpenSSL P-256 client reference.',
    ],
    'node' => [
        'label' => 'Node.js', 'icon' => 'bi-code-square',
        'file' => 'assets/examples/licora-v2/node/licora-v2-client.mjs',
        'install' => 'Node.js 20+; this reference uses built-in fetch and node:crypto only.',
        'run' => 'node licora-v2-client.mjs https://license.example.com my-app AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD 1.0.0',
        'focus' => 'Built-in fetch + node:crypto P-256 reference client.',
    ],
];

foreach ($languages as $key => &$language) {
    $full = __DIR__ . '/' . $language['file'];
    $language['source'] = is_file($full) ? (string)file_get_contents($full) : '';
    $language['download_name'] = basename($language['file']);
}
unset($language);

$errorCodes = [
    'INVALID_LICENSE', 'LICENSE_EXPIRED', 'LICENSE_INACTIVE', 'INVALID_APP', 'APP_NOT_ALLOWED',
    'APP_VERSION_UNSUPPORTED', 'DEVICE_LIMIT_REACHED', 'DEVICE_REVOKED', 'DEVICE_KEY_MISMATCH',
    'INVALID_DEVICE_PROOF', 'STALE_REQUEST', 'REPLAY_DETECTED', 'TOKEN_EXPIRED', 'INVALID_REFRESH_TOKEN',
    'REFRESH_TOKEN_REUSED', 'RATE_LIMITED',
];
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Developer Guide - Licora</title>
    <link rel="icon" href="assets/brand/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/admin-ui.css">
</head>
<body class="admin-ui developer-guide-page">
<?php include 'includes/navbar.php'; ?>
<div class="container-fluid admin-shell">
    <div class="page-hero developer-guide-hero">
        <div>
            <h2><i class="bi bi-code-slash"></i> Developer Guide</h2>
            <p>Integrate Licora Secure API v2 into desktop, mobile, server and CLI applications.</p>
        </div>
        <div class="developer-guide-hero-actions">
            <a class="btn btn-outline-secondary btn-sm" href="client_apps.php"><i class="bi bi-boxes"></i> Client Apps</a>
            <?php if ($canDownloadPublicKey): ?>
                <a class="btn btn-outline-secondary btn-sm" href="ajax/v2-public-key.php"><i class="bi bi-download"></i> Server Public Key</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="developer-guide-banner" aria-labelledby="developer-v2-title">
        <div class="developer-guide-banner-icon"><i class="bi bi-shield-check"></i></div>
        <div>
            <div class="developer-guide-eyebrow">Recommended for new integrations</div>
            <h3 id="developer-v2-title">Secure API v2</h3>
            <p>Public clients use an App ID, a device-bound P-256 key and signed request proofs. They never embed Licora's API v1 shared/master credential.</p>
        </div>
        <span class="ui-status ui-status-success">No shared API key</span>
    </section>

    <div class="developer-guide-grid developer-guide-grid-top">
        <section class="card developer-guide-card" aria-labelledby="quick-start-title">
            <div class="card-header"><div><h3 id="quick-start-title"><i class="bi bi-lightning-charge"></i> Quick Start</h3><small>Production client lifecycle</small></div></div>
            <div class="card-body">
                <ol class="developer-guide-steps">
                    <li><span>1</span><div><strong>Create Client App</strong><small>Choose a stable lowercase App ID.</small></div></li>
                    <li><span>2</span><div><strong>Scope a License</strong><small>Create/assign a license to that API v2 App ID.</small></div></li>
                    <li><span>3</span><div><strong>Generate P-256 Device Key</strong><small>Keep the private key on the device; send only the public key.</small></div></li>
                    <li><span>4</span><div><strong>Activate</strong><small>Sign the exact activation JSON bytes and call <code>activate.php</code>.</small></div></li>
                    <li><span>5</span><div><strong>Store Credentials</strong><small>Protect the device private key and refresh token with OS-backed secure storage.</small></div></li>
                    <li><span>6</span><div><strong>Check Status</strong><small>Use Bearer access token + fresh device proof.</small></div></li>
                    <li><span>7</span><div><strong>Refresh</strong><small>Rotate refresh credentials and immediately discard the old refresh token.</small></div></li>
                    <li><span>8</span><div><strong>Deactivate</strong><small>Revoke the device credential when unlinking the installation.</small></div></li>
                </ol>
            </div>
        </section>

        <section class="card developer-guide-card" aria-labelledby="endpoint-title">
            <div class="card-header"><div><h3 id="endpoint-title"><i class="bi bi-diagram-3"></i> This Installation</h3><small>Detected read-only integration endpoints</small></div></div>
            <div class="card-body developer-guide-endpoints">
                <?php foreach (['API v2 Activate','API v2 Refresh','API v2 Status','API v2 Deactivate'] as $name): ?>
                    <div class="developer-guide-endpoint-row">
                        <span><?php echo Security::escape($name); ?></span>
                        <code><?php echo Security::escape((string)$endpoints[$name]); ?></code>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-devguide-copy-text="<?php echo Security::escape((string)$endpoints[$name]); ?>" aria-label="Copy <?php echo Security::escape($name); ?> endpoint"><i class="bi bi-clipboard"></i></button>
                    </div>
                <?php endforeach; ?>
                <div class="developer-guide-note"><i class="bi bi-info-circle"></i><span>Examples use a configurable Base URL. If Licora is installed in a subdirectory, keep that subdirectory in the Base URL so the signed request path exactly matches the URL being called.</span></div>
            </div>
        </section>
    </div>

    <section class="card developer-guide-card developer-guide-proof" aria-labelledby="proof-title">
        <div class="card-header"><div><h3 id="proof-title"><i class="bi bi-fingerprint"></i> Device Proof Contract</h3><small>Sign exactly what you send</small></div></div>
        <div class="card-body developer-guide-proof-grid">
            <div>
                <div class="developer-guide-eyebrow">Canonical string</div>
                <pre id="devguide-canonical"><code>HTTP_METHOD
REQUEST_PATH
TIMESTAMP
NONCE
BODY_SHA256
CONTEXT</code></pre>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-devguide-copy-target="devguide-canonical"><i class="bi bi-clipboard"></i> Copy canonical format</button>
            </div>
            <div class="developer-guide-proof-rules">
                <div><strong>Algorithm</strong><span>ECDSA P-256 + SHA-256; send the DER signature as Base64URL without padding.</span></div>
                <div><strong>Activation context</strong><code>activate:&lt;app_id&gt;</code></div>
                <div><strong>Refresh context</strong><code>refresh:&lt;sha256(refresh_token)&gt;</code></div>
                <div><strong>Status / Deactivate</strong><span>Use the access-token <code>jti</code> as proof context.</span></div>
                <div><strong>Headers</strong><span><code>X-Licora-Timestamp</code>, <code>X-Licora-Nonce</code>, <code>X-Licora-Device-Signature</code></span></div>
            </div>
        </div>
    </section>

    <section class="card developer-guide-card developer-guide-languages" aria-labelledby="languages-title">
        <div class="card-header developer-guide-language-header">
            <div><h3 id="languages-title"><i class="bi bi-code-square"></i> Language Examples</h3><small>Copy-ready lifecycle references; downloadable files are shipped with Licora.</small></div>
        </div>
        <div class="developer-guide-tabs" role="tablist" aria-label="Integration language">
            <?php $first = true; foreach ($languages as $key => $language): ?>
                <button type="button" class="developer-guide-tab<?php echo $first ? ' active' : ''; ?>" role="tab" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" data-devguide-tab="<?php echo Security::escape($key); ?>">
                    <i class="bi <?php echo Security::escape($language['icon']); ?>"></i><?php echo Security::escape($language['label']); ?>
                </button>
            <?php $first = false; endforeach; ?>
        </div>
        <?php $first = true; foreach ($languages as $key => $language): ?>
            <section class="developer-guide-language-panel" role="tabpanel" data-devguide-panel="<?php echo Security::escape($key); ?>"<?php echo $first ? '' : ' hidden'; ?>>
                <div class="developer-guide-language-summary">
                    <div><h4><?php echo Security::escape($language['label']); ?></h4><p><?php echo Security::escape($language['focus']); ?></p></div>
                    <a class="btn btn-primary btn-sm" href="<?php echo Security::escape($language['file']); ?>" download="<?php echo Security::escape($language['download_name']); ?>"><i class="bi bi-download"></i> Download Example</a>
                </div>
                <div class="developer-guide-command-grid">
                    <div><span>Requirements / Install</span><pre id="install-<?php echo Security::escape($key); ?>"><code><?php echo Security::escape($language['install']); ?></code></pre><button type="button" class="btn btn-sm btn-outline-secondary" data-devguide-copy-target="install-<?php echo Security::escape($key); ?>"><i class="bi bi-clipboard"></i> Copy</button></div>
                    <div><span>Run / Use</span><pre id="run-<?php echo Security::escape($key); ?>"><code><?php echo Security::escape($language['run']); ?></code></pre><button type="button" class="btn btn-sm btn-outline-secondary" data-devguide-copy-target="run-<?php echo Security::escape($key); ?>"><i class="bi bi-clipboard"></i> Copy</button></div>
                </div>
                <details class="developer-guide-source">
                    <summary><span><i class="bi bi-file-earmark-code"></i> Full reference source</span><span><?php echo number_format(strlen($language['source'])); ?> bytes</span></summary>
                    <div class="developer-guide-source-toolbar"><button type="button" class="btn btn-sm btn-outline-secondary" data-devguide-copy-target="source-<?php echo Security::escape($key); ?>"><i class="bi bi-clipboard"></i> Copy Full Source</button></div>
                    <pre id="source-<?php echo Security::escape($key); ?>"><code><?php echo Security::escape($language['source']); ?></code></pre>
                </details>
            </section>
        <?php $first = false; endforeach; ?>
    </section>

    <div class="developer-guide-grid">
        <section class="card developer-guide-card" aria-labelledby="security-title">
            <div class="card-header"><div><h3 id="security-title"><i class="bi bi-shield-lock"></i> Production Security Checklist</h3><small>Required client trust boundaries</small></div></div>
            <div class="card-body developer-guide-security-grid">
                <div class="developer-guide-security-block is-good"><strong>Always</strong><ul><li>Use HTTPS and validate the server certificate.</li><li>Generate a P-256 private key on the device and keep it private.</li><li>Use a new nonce and current timestamp for every request.</li><li>Rotate and replace refresh tokens after every successful refresh.</li><li>Pin the trusted Licora API v2 server public signing key before trusting access-token claims.</li><li>Use stable machine <code>code</code> values for application logic.</li></ul></div>
                <div class="developer-guide-security-block is-bad"><strong>Never</strong><ul><li>Embed an API v1 shared/master key in desktop/mobile/public code.</li><li>Send the device private key to Licora.</li><li>Disable TLS certificate verification.</li><li>Reuse a used refresh token.</li><li>Store refresh credentials in plain text when OS secure storage exists.</li><li>Parse human-readable <code>message</code> strings for control flow.</li></ul></div>
            </div>
            <div class="developer-guide-note"><i class="bi bi-key"></i><span>The downloadable examples are lifecycle/reference clients and intentionally generate an ephemeral test device, then deactivate it. Production applications must persist their device private key and rotated refresh credential in platform-secure storage. If a production app consumes JWT claims locally, verify the <code>LICORA-V2</code>/<code>RS256</code> signature with the pinned server public key before trusting those claims.</span></div>
        </section>

        <section class="card developer-guide-card" aria-labelledby="errors-title">
            <div class="card-header"><div><h3 id="errors-title"><i class="bi bi-exclamation-diamond"></i> Stable Error Codes</h3><small>Branch on <code>code</code>, not <code>message</code></small></div></div>
            <div class="card-body developer-guide-errors">
                <?php foreach ($errorCodes as $code): ?><code><?php echo Security::escape($code); ?></code><?php endforeach; ?>
            </div>
        </section>
    </div>

    <section class="card developer-guide-card developer-guide-legacy" aria-labelledby="legacy-title">
        <div class="card-header"><div><h3 id="legacy-title"><i class="bi bi-server"></i> Legacy / Trusted Server API v1</h3><small>Backward compatibility only</small></div></div>
        <div class="card-body">
            <p>Existing trusted server-side integrations may continue using <code>POST /api/verify.php</code> with the reviewed API v1 <code>X-API-Key</code> credential. Do not copy that shared credential into a distributed desktop, mobile, JavaScript bundle or other public client. New public integrations should use Secure API v2 above.</p>
            <div class="developer-guide-links"><a href="api_keys.php"><i class="bi bi-key-fill"></i> API Keys</a><a href="client_apps.php"><i class="bi bi-boxes"></i> Client Apps</a><a href="v2_devices.php"><i class="bi bi-shield-check"></i> V2 Devices</a></div>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-ui.js"></script>
<script src="assets/js/developer-guide.js"></script>
</body>
</html>
