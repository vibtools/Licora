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

    private static function legacyEncryptionKey() {
        $source = ENCRYPTION_KEY ?: hash('sha256', __DIR__ . APP_URL . DB_NAME, true);
        return substr(hash('sha256', $source), 0, 32);
    }

    private static function isUsableEncryptionSecret($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }
        $lower = strtolower($value);
        return strpos($lower, 'your-') === false && strpos($lower, 'change-this') === false;
    }

    private static function readLocalEncryptionKey($keyFile) {
        if (!is_file($keyFile) || !is_readable($keyFile)) {
            return '';
        }
        $value = trim((string)file_get_contents($keyFile));
        return preg_match('/^[a-f0-9]{64}$/i', $value) ? $value : '';
    }

    private static function currentEncryptionSource() {
        $candidates = [
            defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : '',
            defined('CSRF_SECRET') ? CSRF_SECRET : '',
            defined('JWT_SECRET') ? JWT_SECRET : ''
        ];

        foreach ($candidates as $candidate) {
            if (self::isUsableEncryptionSecret($candidate)) {
                return (string)$candidate;
            }
        }

        $keyFile = __DIR__ . '/.licora-encryption.key';
        $existing = self::readLocalEncryptionKey($keyFile);
        if ($existing !== '') {
            return $existing;
        }

        $generated = bin2hex(random_bytes(32));
        $handle = @fopen($keyFile, 'x');
        if ($handle !== false) {
            $written = false;
            try {
                if (!flock($handle, LOCK_EX)) {
                    throw new RuntimeException('Unable to lock local encryption key file.');
                }
                $bytes = fwrite($handle, $generated . PHP_EOL);
                if ($bytes === false || $bytes < strlen($generated)) {
                    throw new RuntimeException('Unable to persist local encryption key.');
                }
                fflush($handle);
                $written = true;
            } finally {
                @flock($handle, LOCK_UN);
                fclose($handle);
                if (!$written) {
                    @unlink($keyFile);
                }
            }
            @chmod($keyFile, 0600);
            return $generated;
        }

        // Another request may have created the key after the exclusive create attempt.
        $existing = self::readLocalEncryptionKey($keyFile);
        if ($existing !== '') {
            return $existing;
        }

        throw new RuntimeException(
            'A secure encryption key is unavailable. Configure LICENSE_ENCRYPTION_KEY or make the includes directory writable once.'
        );
    }

    private static function currentEncryptionKeys() {
        $master = hash('sha256', self::currentEncryptionSource(), true);
        return [
            'encryption' => hash_hmac('sha256', 'licora-encryption-v2', $master, true),
            'authentication' => hash_hmac('sha256', 'licora-authentication-v2', $master, true)
        ];
    }

    public static function encrypt($data) {
        $keys = self::currentEncryptionKeys();
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            (string)$data,
            'AES-256-CBC',
            $keys['encryption'],
            OPENSSL_RAW_DATA,
            $iv
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        $mac = hash_hmac('sha256', $iv . $ciphertext, $keys['authentication'], true);
        return 'v2:' . base64_encode($iv . $mac . $ciphertext);
    }

    public static function decrypt($data) {
        if (empty($data)) {
            return '';
        }

        $data = (string)$data;
        if (strpos($data, 'v2:') === 0) {
            try {
                $decoded = base64_decode(substr($data, 3), true);
                if ($decoded === false || strlen($decoded) < 49) {
                    return '';
                }

                $iv = substr($decoded, 0, 16);
                $mac = substr($decoded, 16, 32);
                $ciphertext = substr($decoded, 48);
                $keys = self::currentEncryptionKeys();
                $expectedMac = hash_hmac('sha256', $iv . $ciphertext, $keys['authentication'], true);
                if (!hash_equals($expectedMac, $mac)) {
                    return '';
                }

                $plain = openssl_decrypt(
                    $ciphertext,
                    'AES-256-CBC',
                    $keys['encryption'],
                    OPENSSL_RAW_DATA,
                    $iv
                );
                return $plain === false ? '' : $plain;
            } catch (Throwable $e) {
                error_log('Versioned decryption failed: ' . $e->getMessage());
                return '';
            }
        }

        // Legacy v1 payload support. Existing encrypted licenses and API keys remain readable.
        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) < 17) {
            return '';
        }
        $key = self::legacyEncryptionKey();
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

    public static function normalizeAPIKeyCredential($credential) {
        $credential = trim((string)$credential);
        if (preg_match('/^Bearer\s+(.+)$/i', $credential, $matches)) {
            $credential = trim($matches[1]);
        }
        return preg_replace('/\s+/', '', $credential);
    }

    public static function validateAPIKey($apiKey) {
        $db = Database::getInstance();
        $apiKey = self::normalizeAPIKeyCredential($apiKey);
        if ($apiKey === '') {
            return false;
        }
        $hash = hash('sha256', $apiKey);
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE api_key_hash = :hash AND is_active = 1 AND (expires_at IS NULL OR expires_at = '0000-00-00 00:00:00' OR expires_at >= NOW())");
        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch();
    }

    private static function checkRateLimitWithoutAdvisoryLock($db, $ip, $endpoint, $limit) {
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

    public static function checkRateLimit($ip, $endpoint, $limit = 100) {
        $db = Database::getInstance();
        $ip = (string)$ip;
        $endpoint = (string)$endpoint;
        $limit = max(1, (int)$limit);
        $lockName = 'licora_rl_' . substr(hash('sha256', $ip . '|' . $endpoint), 0, 48);
        $lockAcquired = false;

        try {
            $lock = $db->prepare("SELECT GET_LOCK(:lock_name, 2)");
            $lock->execute([':lock_name' => $lockName]);
            $lockAcquired = ((int)$lock->fetchColumn() === 1);
        } catch (Throwable $e) {
            error_log('Rate limit advisory lock unavailable; using compatibility fallback: ' . $e->getMessage());
            return self::checkRateLimitWithoutAdvisoryLock($db, $ip, $endpoint, $limit);
        }

        if (!$lockAcquired) {
            return false;
        }

        try {
            $delete = $db->prepare("DELETE FROM rate_limits WHERE last_request < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $delete->execute();

            $check = $db->prepare("
                SELECT COALESCE(SUM(request_count), 0) AS request_count, MIN(id) AS first_id
                FROM rate_limits
                WHERE ip_address = :ip
                  AND endpoint = :endpoint
                  AND last_request > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $check->execute([':ip' => $ip, ':endpoint' => $endpoint]);
            $result = $check->fetch();
            $requestCount = (int)($result['request_count'] ?? 0);

            if ($requestCount >= $limit) {
                return false;
            }

            $firstId = isset($result['first_id']) ? (int)$result['first_id'] : 0;
            if ($firstId > 0) {
                $update = $db->prepare("UPDATE rate_limits SET request_count = request_count + 1, last_request = NOW() WHERE id = :id");
                $update->execute([':id' => $firstId]);
            } else {
                $insert = $db->prepare("INSERT INTO rate_limits (ip_address, endpoint) VALUES (:ip, :endpoint)");
                $insert->execute([':ip' => $ip, ':endpoint' => $endpoint]);
            }

            return true;
        } finally {
            try {
                $release = $db->prepare("SELECT RELEASE_LOCK(:lock_name)");
                $release->execute([':lock_name' => $lockName]);
            } catch (Throwable $e) {
                error_log('Rate limit advisory lock release failed: ' . $e->getMessage());
            }
        }
    }
}
?>
