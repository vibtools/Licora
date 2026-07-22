<?php
class Security {
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return trim((string)$input);
    }

    public static function escape($data) {
        if (is_array($data)) {
            return array_map([self::class, 'escape'], $data);
        }
        return htmlspecialchars((string)$data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword($password, $hash) {
        if (password_verify($password, $hash)) {
            return true;
        }
        // Backward compatible legacy hash support.
        if (preg_match('/^[a-f0-9]{32}$/i', (string)$hash) && hash_equals(strtolower($hash), md5($password))) {
            return true;
        }
        if (preg_match('/^[a-f0-9]{40}$/i', (string)$hash) && hash_equals(strtolower($hash), sha1($password))) {
            return true;
        }
        return false;
    }

    public static function passwordNeedsRehash($hash) {
        return !password_get_info($hash)['algo'] || password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    private static function encryptionKey() {
        $source = ENCRYPTION_KEY ?: hash('sha256', __DIR__ . APP_URL . DB_NAME, true);
        return substr(hash('sha256', $source), 0, 32);
    }

    public static function encrypt($data) {
        $key = self::encryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt((string)$data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($data) {
        if (empty($data)) {
            return '';
        }
        $decoded = base64_decode((string)$data, true);
        if ($decoded === false || strlen($decoded) < 17) {
            return '';
        }
        $key = self::encryptionKey();
        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);
        $plain = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        return $plain === false ? '' : $plain;
    }

    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken(32);
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireCSRFToken($token) {
        if (!self::validateCSRFToken($token)) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }

    public static function getClientIP() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public static function generateDeviceHash() {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            $_SERVER['HTTP_ACCEPT'] ?? '',
            gethostname() ?: php_uname('n')
        ];
        return hash('sha256', implode('|', $components));
    }

    public static function validateLicenseFormat($key) {
        return preg_match('/^[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$/', (string)$key);
    }

    public static function validateAPIKey($apiKey) {
        $db = Database::getInstance();
        $apiKey = preg_replace('/\s+/', '', trim((string)$apiKey));
        if ($apiKey === '') {
            return false;
        }
        $hash = hash('sha256', $apiKey);
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE api_key_hash = :hash AND is_active = 1 AND (expires_at IS NULL OR expires_at = '0000-00-00 00:00:00' OR expires_at >= NOW())");
        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch();
    }

    public static function checkRateLimit($ip, $endpoint, $limit = 100) {
        $db = Database::getInstance();
        $delete = $db->prepare("DELETE FROM rate_limits WHERE last_request < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $delete->execute();
        $check = $db->prepare("SELECT request_count FROM rate_limits WHERE ip_address = :ip AND endpoint = :endpoint AND last_request > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $check->execute([':ip' => $ip, ':endpoint' => $endpoint]);
        $result = $check->fetch();
        if ($result) {
            if ((int)$result['request_count'] >= (int)$limit) {
                return false;
            }
            $update = $db->prepare("UPDATE rate_limits SET request_count = request_count + 1, last_request = NOW() WHERE ip_address = :ip AND endpoint = :endpoint");
            $update->execute([':ip' => $ip, ':endpoint' => $endpoint]);
        } else {
            $insert = $db->prepare("INSERT INTO rate_limits (ip_address, endpoint) VALUES (:ip, :endpoint)");
            $insert->execute([':ip' => $ip, ':endpoint' => $endpoint]);
        }
        return true;
    }
}
?>
