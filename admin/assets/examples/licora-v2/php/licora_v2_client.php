<?php
declare(strict_types=1);

/*
 * Licora Secure API v2 lifecycle reference.
 * Requires PHP 8.0+, curl, openssl and json.
 * This test client uses an ephemeral device and deactivates it before exit.
 */

function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function sha256hex(string $raw): string { return hash('sha256', $raw); }
function compact_json(array $value): string {
    if ($value === []) { return '{}'; }
    $json = json_encode($value, JSON_UNESCAPED_SLASHES);
    if ($json === false) { throw new RuntimeException('JSON encoding failed.'); }
    return $json;
}
function jwt_payload(string $token): array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) { throw new RuntimeException('Malformed access token.'); }
    $segment = strtr($parts[1], '-_', '+/');
    $segment .= str_repeat('=', (4 - strlen($segment) % 4) % 4);
    $raw = base64_decode($segment, true);
    $data = $raw === false ? null : json_decode($raw, true);
    if (!is_array($data)) { throw new RuntimeException('Malformed access-token payload.'); }
    return $data;
}
function url_path(string $url): string {
    $path = parse_url($url, PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

final class LicoraV2ReferenceClient {
    private string $baseUrl;
    private string $appId;
    private string $appVersion;
    private string $deviceId;
    private OpenSSLAsymmetricKey $privateKey;
    private string $publicPem;

    public function __construct(string $baseUrl, string $appId, string $appVersion) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->appId = $appId;
        $this->appVersion = $appVersion;
        $this->deviceId = 'php-' . bin2hex(random_bytes(16));
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false) { throw new RuntimeException('Unable to generate P-256 key.'); }
        $details = openssl_pkey_get_details($key);
        if (!is_array($details) || empty($details['key'])) { throw new RuntimeException('Unable to export public key.'); }
        $this->privateKey = $key;
        $this->publicPem = (string)$details['key'];
    }

    private function endpoint(string $name): string { return $this->baseUrl . '/api/v2/' . $name . '.php'; }

    private function headers(string $url, string $body, string $context, ?string $accessToken = null): array {
        $timestamp = time();
        $nonce = b64url(random_bytes(18));
        $canonical = implode("\n", ['POST', url_path($url), (string)$timestamp, $nonce, sha256hex($body), $context]);
        $signature = '';
        if (!openssl_sign($canonical, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) { throw new RuntimeException('Device proof signing failed.'); }
        $headers = [
            'Content-Type: application/json',
            'X-Licora-Timestamp: ' . $timestamp,
            'X-Licora-Nonce: ' . $nonce,
            'X-Licora-Device-Signature: ' . b64url($signature),
        ];
        if ($accessToken !== null && $accessToken !== '') { $headers[] = 'Authorization: Bearer ' . $accessToken; }
        return $headers;
    }

    private function post(string $name, array $payload, string $context, ?string $accessToken = null): array {
        $url = $this->endpoint($name);
        $body = compact_json($payload);
        $ch = curl_init($url);
        if ($ch === false) { throw new RuntimeException('Unable to initialize cURL.'); }
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $this->headers($url, $body, $context, $accessToken), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($raw === false) { $error = curl_error($ch); curl_close($ch); throw new RuntimeException('HTTP request failed: ' . $error); }
        curl_close($ch);
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) { throw new RuntimeException('HTTP ' . $status . ': non-JSON response.'); }
        if (empty($data['success'])) { throw new RuntimeException('Licora error ' . (string)($data['code'] ?? 'UNKNOWN') . ' (HTTP ' . $status . ')'); }
        return $data;
    }

    public function activate(string $licenseKey): array {
        return $this->post('activate', ['license_key'=>$licenseKey,'app_id'=>$this->appId,'app_version'=>$this->appVersion,'device_id'=>$this->deviceId,'device_public_key'=>$this->publicPem], 'activate:' . $this->appId);
    }
    public function status(string $accessToken): array { return $this->post('status', [], (string)(jwt_payload($accessToken)['jti'] ?? ''), $accessToken); }
    public function refresh(string $refreshToken): array { return $this->post('refresh', ['refresh_token'=>$refreshToken,'app_version'=>$this->appVersion], 'refresh:' . hash('sha256', $refreshToken)); }
    public function deactivate(string $accessToken): array { return $this->post('deactivate', [], (string)(jwt_payload($accessToken)['jti'] ?? ''), $accessToken); }
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    [$script, $baseUrl, $appId, $licenseKey, $appVersion] = array_pad($argv, 5, null);
    $appVersion = $appVersion ?: '1.0.0';
    if (!$baseUrl || !$appId || !$licenseKey) { fwrite(STDERR, "Usage: php licora_v2_client.php <base-url> <app-id> <license-key> [app-version]\n"); exit(2); }
    $client = new LicoraV2ReferenceClient($baseUrl, $appId, $appVersion);
    $accessToken = '';
    try {
        $activated = $client->activate($licenseKey); $accessToken = (string)$activated['access_token']; $refreshToken = (string)$activated['refresh_token']; echo "[PASS] activate\n";
        $client->status($accessToken); echo "[PASS] status\n";
        $refreshed = $client->refresh($refreshToken); $accessToken = (string)$refreshed['access_token']; $refreshToken = (string)$refreshed['refresh_token']; echo "[PASS] refresh (rotated)\n";
        $client->status($accessToken); echo "[PASS] status-after-refresh\n";
        $client->deactivate($accessToken); $accessToken = ''; echo "[PASS] deactivate\n";
    } finally {
        if ($accessToken !== '') { try { $client->deactivate($accessToken); echo "[INFO] cleanup deactivate completed\n"; } catch (Throwable $e) { fwrite(STDERR, "[WARN] cleanup deactivate failed\n"); } }
    }
}
