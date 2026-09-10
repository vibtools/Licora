<?php
declare(strict_types=1);

return [
    'base_url' => (string) getenv('LICORA_ADMIN_API_BASE_URL'),
    'api_key' => (string) getenv('LICORA_ADMIN_API_KEY'),
    'timeout_ms' => 15000,
    'max_response_bytes' => 2097152,
];

