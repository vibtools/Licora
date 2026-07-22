<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
$allowedOrigin = getenv('LICENSE_ALLOWED_ORIGIN') ?: APP_URL;
if (!empty($_SERVER['HTTP_ORIGIN']) && rtrim($_SERVER['HTTP_ORIGIN'], '/') === rtrim($allowedOrigin, '/')) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-API-KEY, x-api-key, Authorization');

// CORS প্রি-ফ্লাইট রিকোয়েস্ট হ্যান্ডলিং
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// শুধুমাত্র POST রিকোয়েস্ট গ্রহণ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// =============================
// ডিবাগ মোড চালু/বন্ধ
// =============================
$debug_mode = (ENVIRONMENT === 'development');
$debug_log = [];

if ($debug_mode) {
    error_log("===========================");
    error_log("API VERIFY REQUEST STARTED");
    error_log("===========================");
    
    // রিকোয়েস্ট ইনফো লগ করুন
    error_log("Request Time: " . date('Y-m-d H:i:s'));
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
    error_log("Remote IP: " . Security::getClientIP());
    
    $debug_log['request_info'] = [
        'time' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'ip' => Security::getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
    ];
}

// =============================
// রেট লিমিট চেক
// =============================
$ip = Security::getClientIP();
if (!Security::checkRateLimit($ip, 'verify', API_RATE_LIMIT)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again later.']);
    exit();
}

// =============================
// এপিআই কি এক্সট্রাক্ট এবং ভ্যালিডেশন
// =============================
$apiKey = '';

if ($debug_mode) {
    error_log("--- API Key Extraction ---");
    $debug_log['headers_received'] = [];
    
    // সব হেডার লগ করুন
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            error_log("  $key: [redacted]");
            $debug_log['headers_received'][$key] = '[redacted]';
        }
    }
}

// মেথড ১: সরাসরি $_SERVER থেকে নিন
$possible_server_keys = [
    'HTTP_X_API_KEY',
    'HTTP_X_APIKEY', 
    'HTTP_API_KEY',
    'HTTP_APIKEY',
    'HTTP_AUTHORIZATION'
];

foreach ($possible_server_keys as $header) {
    if (!empty($_SERVER[$header])) {
        $apiKey = $_SERVER[$header];
        if ($debug_mode) {
            error_log("Found API Key in \$_SERVER['$header']: " . substr($apiKey, 0, 30) . "...");
        }
        break;
    }
}

// মেথড ২: getallheaders() ব্যবহার করুন
if (empty($apiKey) && function_exists('getallheaders')) {
    $headers = getallheaders();
    if ($debug_mode) {
        error_log("Checking getallheaders(): " . json_encode($headers));
    }
    
    $possible_header_names = [
        'X-API-Key',
        'X-API-KEY',
        'x-api-key',
        'X-APIKEY',
        'X-Api-Key',
        'Authorization'
    ];
    
    foreach ($possible_header_names as $header_name) {
        if (isset($headers[$header_name])) {
            $apiKey = $headers[$header_name];
            if ($debug_mode) {
                error_log("Found API Key in getallheaders()['$header_name']: " . substr($apiKey, 0, 30) . "...");
            }
            break;
        }
    }
    
    // Authorization header থেকে Bearer টোকেন নিন
    if (empty($apiKey) && isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
            $apiKey = $matches[1];
            if ($debug_mode) {
                error_log("Extracted API Key from Authorization Bearer: " . substr($apiKey, 0, 30) . "...");
            }
        }
    }
}

// মেথড ৩: php://input থেকে JSON ডাটা চেক করুন (fallback)
if (empty($apiKey)) {
    $input_data = file_get_contents('php://input');
    if (!empty($input_data)) {
        $json_data = json_decode($input_data, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($json_data['api_key'])) {
            $apiKey = $json_data['api_key'];
            if ($debug_mode) {
                error_log("Found API Key in JSON data: " . substr($apiKey, 0, 30) . "...");
            }
        }
    }
}

// এপিআই কি ক্লিনআপ
$originalApiKey = $apiKey;
$apiKey = trim($apiKey);
$apiKey = preg_replace('/\s+/', '', $apiKey);

if ($debug_mode) {
    error_log("--- API Key Processing ---");
    error_log("Original API Key: [redacted]");
    error_log("Cleaned API Key: [redacted]");
    error_log("API Key Length: " . strlen($apiKey));
    
    $debug_log['api_key_processing'] = [
        'original' => '[redacted]',
        'cleaned' => '[redacted]',
        'length' => strlen($apiKey)
    ];
}

// এপিআই কি ভেরিফিকেশন
if (empty($apiKey)) {
    if ($debug_mode) {
        error_log("API Key is empty after cleaning!");
    }
    
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'API key is required',
        'debug' => $debug_mode ? [
            'message' => 'No API key provided in headers or request body',
            'expected_headers' => ['X-API-Key', 'X-API-KEY', 'Authorization: Bearer <key>'],
            'headers_received' => array_keys($debug_log['headers_received'] ?? [])
        ] : null
    ]);
    exit();
}

// এপিআই কি ভ্যালিডেট করুন
$apiValidation = Security::validateAPIKey($apiKey);

if (!$apiValidation) {
    if ($debug_mode) {
        error_log("API Key validation FAILED!");
        
        // ডাটাবেস থেকে সব এপিআই কি চেক করুন
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT id, name, LEFT(api_key_hash, 20) as hash_prefix FROM api_keys");
            $all_keys = $stmt->fetchAll();
            error_log("All API Keys in database:");
            foreach ($all_keys as $key) {
                error_log("  ID: {$key['id']}, Name: {$key['name']}, Hash: {$key['hash_prefix']}...");
            }
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
        }
    }
    
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid API key',
        'debug' => $debug_mode ? [
            'key_provided' => '[redacted]',
            'key_cleaned' => '[redacted]',
            'key_length' => strlen($apiKey),
            'suggestions' => [
                'Check API key in admin panel',
                'Ensure key is active',
                'Check for whitespace in key',
                'Verify header name is correct'
            ]
        ] : null
    ]);
    exit();
}

// সফল ভ্যালিডেশন
if ($debug_mode) {
    error_log("API Key validation SUCCESS!");
    error_log("API Key ID: " . $apiValidation['id']);
    error_log("API Key Name: " . $apiValidation['name']);
}

// =============================
// রিকোয়েস্ট বডি পড়ুন এবং ভ্যালিডেট করুন
// =============================
$input = file_get_contents('php://input');

if ($debug_mode) {
    error_log("--- Request Body ---");
    error_log("Raw Input: [redacted]");
}

// JSON ডিকোড করুন
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    if ($debug_mode) {
        error_log("JSON Decode Error: " . json_last_error_msg());
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid JSON data: ' . json_last_error_msg(),
        'debug' => $debug_mode ? ['raw_input' => '[redacted]'] : null
    ]);
    exit();
}

if ($debug_mode) {
    error_log("Decoded JSON: [redacted]");
}

// ভ্যালিডেশন
$validator = new Validation();
if (!$validator->validateAPIRequest($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $validator->getFormattedErrors(),
        'debug' => $debug_mode ? ['input_data' => '[redacted]'] : null
    ]);
    exit();
}

// রিকোয়েস্ট ডেটা এক্সট্র্যাক্ট করুন
$license_key = trim($data['license_key'] ?? '');
$device_hash = $data['device_hash'] ?? Security::generateDeviceHash();
$app_id = $data['app_id'] ?? 'default';
$app_version = $data['app_version'] ?? '1.0';

if ($debug_mode) {
    error_log("--- Request Parameters ---");
    error_log("License Key: " . substr($license_key, 0, 8) . "...");
    error_log("Device Hash: [redacted]");
    error_log("App ID: $app_id");
    error_log("App Version: $app_version");
    
    $debug_log['request_params'] = [
        'license_key' => substr($license_key, 0, 8) . '...',
        'device_hash' => '[redacted]',
        'app_id' => $app_id,
        'app_version' => $app_version
    ];
}

// =============================
// লাইসেন্স ভেরিফাই
// =============================
// লাইসেন্স ভেরিফাই
if ($debug_mode) {
    error_log("--- License Verification ---");
    error_log("License Key: " . substr($license_key, 0, 8) . "...");
    error_log("Device Hash: [redacted]");
}

$system = new LicenseSystem();
$result = $system->verifyLicense($license_key, $device_hash, $apiValidation['id'] ?? null);

if ($debug_mode) {
    error_log("Verification Result: [redacted]");
    if (!$result['success']) {
        error_log("Verification Error: " . ($result['message'] ?? 'Unknown error'));
    }
}

// =============================
// এপিআই ব্যবহার লগ
// =============================
try {
    $db = Database::getInstance();
    
    // last_used_at আপডেট
    $stmt = $db->prepare("
        UPDATE api_keys 
        SET last_used_at = NOW(),
            request_count = request_count + 1
        WHERE api_key_hash = :hash
    ");
    $stmt->execute([':hash' => hash('sha256', $apiKey)]);
    
    // এপিআই কল লগ
    $stmt = $db->prepare("
        INSERT INTO api_logs 
        (api_key_id, endpoint, license_key, ip_address, user_agent, response_code, created_at) 
        VALUES (:api_key_id, :endpoint, :license_key, :ip_address, :user_agent, :response_code, NOW())
    ");
    
    $response_code = $result['success'] ? 200 : 400;
    
    $stmt->execute([
        ':api_key_id' => $apiValidation['id'],
        ':endpoint' => 'verify',
        ':license_key' => $license_key,
        ':ip_address' => Security::getClientIP(),
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ':response_code' => $response_code
    ]);
    
} catch (Exception $e) {
    if ($debug_mode) {
        error_log("Error logging API call: " . $e->getMessage());
    }
    // লগিং এররে হলেও মূল রেসপন্স দিতে হবে
}

// =============================
// রেসপন্স প্রস্তুত
// =============================
if ($result['success']) {
    $response = [
        'success' => true,
        'license' => [
            'key' => $result['license']['license_key'],
            'expires' => $result['license']['expires_at'],
            'device_limit' => $result['license']['device_limit'],
            'devices_used' => $result['license']['total_devices'],
            'status' => $result['license']['status'],
            'created_at' => $result['license']['created_at']
        ],
        'device_hash' => $device_hash,
        'timestamp' => date('c'),
        'server_time' => time(),
        'server_version' => APP_VERSION,
        'message' => $result['message'] ?? 'License valid'
    ];
    
    // এক্সপায়ারেশন কাছাকাছি থাকলে ওয়ার্নিং
    $expires = strtotime($result['license']['expires_at']);
    $days_left = floor(($expires - time()) / 86400);
    
    if ($days_left < 7) {
        $response['warning'] = "License expires in {$days_left} days";
        $response['days_remaining'] = $days_left;
    }
    
    // ডিবাগ ইনফো যোগ করুন
    if ($debug_mode) {
        $response['debug'] = [
            'verification_result' => $result,
            'api_key_id' => $apiValidation['id'],
            'request_id' => uniqid('req_', true),
            'processing_time_ms' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) * 1000
        ];
    }
    
} else {
    $response = [
        'success' => false,
        'message' => $result['message'],
        'timestamp' => date('c'),
        'server_version' => APP_VERSION
    ];
    
    // ডিবাগ ইনফো যোগ করুন
    if ($debug_mode) {
        $response['debug'] = [
            'verification_result' => $result,
            'api_key_id' => $apiValidation['id'],
            'request_id' => uniqid('req_', true)
        ];
    }
}

// =============================
// ফাইনাল রেসপন্স
// =============================
if ($debug_mode) {
    error_log("--- Final Response ---");
    error_log("Response generated");
    error_log("===========================");
    error_log("API VERIFY REQUEST COMPLETE");
    error_log("===========================");
    
    // ডিবাগ লগ রেসপন্সে যোগ করুন
    if (isset($response['debug'])) {
        $response['debug']['log'] = $debug_log;
    }
}

// রেসপন্স প্রিন্ট করুন
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>