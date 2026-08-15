<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/security.php';
require_once dirname(__DIR__, 3) . '/includes/v2/V2KeyManager.php';

if (!function_exists('licora_ui_root_url')) {
    function licora_ui_root_url(): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/admin/settings.php');
        $base = rtrim(dirname(dirname($script)), '/\\');
        if ($base === '/' || $base === '.') { $base = ''; }
        return $scheme . '://' . $host . $base . '/';
    }
}

if (!function_exists('licora_ui_endpoints')) {
    function licora_ui_endpoints(): array
    {
        $root = licora_ui_root_url();
        return [
            'Application Root' => $root,
            'API v1 Verify' => $root . 'api/verify.php',
            'API v1 Check License' => $root . 'api/check_license.php',
            'API v2 Activate' => $root . 'api/v2/activate.php',
            'API v2 Refresh' => $root . 'api/v2/refresh.php',
            'API v2 Status' => $root . 'api/v2/status.php',
            'API v2 Deactivate' => $root . 'api/v2/deactivate.php',
        ];
    }
}

if (!function_exists('licora_ui_cron_jobs')) {
    function licora_ui_cron_jobs(): array
    {
        $root = dirname(__DIR__, 3);
        $cleanup = realpath($root . '/cron/cleanup.php') ?: ($root . '/cron/cleanup.php');
        $expiring = realpath($root . '/cron/check_expiring.php') ?: ($root . '/cron/check_expiring.php');
        return [
            'Cleanup' => ['path' => $cleanup, 'command' => 'php ' . $cleanup],
            'Expiry Check' => ['path' => $expiring, 'command' => 'php ' . $expiring],
        ];
    }
}

if (!function_exists('licora_ui_v2_key_status')) {
    function licora_ui_v2_key_status(): array
    {
        $keys = new V2KeyManager();
        $privateConfigured = is_file($keys->privatePath()) && is_readable($keys->privatePath());
        $publicConfigured = is_file($keys->publicPath()) && is_readable($keys->publicPath());
        $fingerprint = '';
        $pairValid = false;
        if ($publicConfigured) {
            try {
                $pem = $keys->publicKeyPem();
                $fingerprint = hash('sha256', $pem);
                if ($privateConfigured) {
                    $keys->assertPairMatches();
                    $pairValid = true;
                }
            } catch (Throwable $e) {
                error_log('API v2 key status inspection failed: ' . $e->getMessage());
            }
        }
        return [
            'key_id' => $keys->keyId(),
            'private_configured' => $privateConfigured,
            'public_configured' => $publicConfigured,
            'pair_valid' => $pairValid,
            'public_fingerprint' => $fingerprint,
        ];
    }
}
