# PHP client

This server-side client supports PHP 8.0 or newer and uses only the cURL and JSON extensions. It generates Licora's HMAC request proof and sends the exact signed bytes.

## Install

Copy the `php` directory into a private backend repository, then run:

```bash
composer install
```

For a package checked into an existing Composer project, merge its PSR-4 mapping or configure it as a path repository. Never put the package or key in a public web directory.

Set secrets in the process environment or a secret manager:

```text
LICORA_ADMIN_API_BASE_URL=https://licenses.example.com/licora
LICORA_ADMIN_API_KEY=licora_admin_live_<64-lowercase-hex-characters>
```

## Create a client

```php
use VibTools\Licora\AdminApi\LicoraAdminClient;

$client = new LicoraAdminClient(
    (string)getenv('LICORA_ADMIN_API_BASE_URL'),
    (string)getenv('LICORA_ADMIN_API_KEY'),
    timeoutMs: 15000,
    maxResponseBytes: 2097152
);
```

## Methods

```php
$client->listAllowedApps();
$client->createLicense($fields, 'checkout-ORDER-10482');
$client->licenseStatus(licenseId: 42);
$client->licenseStatus(externalOrderId: 'ORDER-10482', includeKey: true);
$client->listLicenses(['page' => 1, 'per_page' => 25, 'status' => 'active']);
$client->activateLicense(42);
$client->suspendLicense(42);
$client->extendLicense(42, 720);
$client->banLicense(42, 'Confirmed payment fraud');
$client->deleteLicense(42);
$client->listDevices(42);
$client->revokeDevice(91);
```

Every method returns the complete decoded response envelope. `createLicense` requires one stable idempotency key per logical order. The client performs no automatic retries.

## Error handling

```php
use VibTools\Licora\AdminApi\LicoraAdminApiException;

try {
    $response = $client->licenseStatus(licenseId: 42);
} catch (LicoraAdminApiException $error) {
    error_log(json_encode([
        'code' => $error->getApiCode(),
        'http_status' => $error->getHttpStatus(),
        'request_id' => $error->getRequestId(),
    ]));
}
```

Do not log the API key, signature, full license key or request body. Retry only after classifying the failure according to `../docs/SECURITY_AND_OPERATIONS.md`. A create retry must use the original order payload and idempotency key; the client automatically generates a fresh timestamp, nonce and signature for every attempt.

See `examples/manage-license.php` for a runnable CLI example.
