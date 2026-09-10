# Licora Admin License API SDK

Production-ready, dependency-light PHP and Node.js clients for Licora's scoped server-to-server Admin License Control API.

This SDK is for trusted backend systems such as checkout services, order processors, CRM workers and internal automation. An Admin API secret must never be shipped in a browser bundle, desktop application, mobile application, public repository or client-visible response.

## Package contents

```text
Licora-Admin-API-SDK-v1.0.0/
├── VERSION
├── LICENSE
├── README.md
├── AI_INSTRUCTIONS.md
├── docs/
│   ├── AUTHENTICATION_AND_SIGNING.md
│   ├── API_REFERENCE.md
│   └── SECURITY_AND_OPERATIONS.md
├── php/
│   ├── composer.json
│   ├── config.example.php
│   ├── README.md
│   ├── examples/manage-license.php
│   └── src/
│       ├── LicoraAdminApiException.php
│       └── LicoraAdminClient.php
└── nodejs/
    ├── env.example
    ├── package.json
    ├── README.md
    ├── examples/manage-license.mjs
    └── src/licora-admin-client.mjs
```

## Supported operations

- list applications assigned to the calling API key;
- create an app-bound license with order-level idempotency;
- read one license by `license_id` or `external_order_id`;
- list/filter owned licenses with pagination;
- activate, suspend, extend, ban or soft-delete a license;
- list devices belonging to an owned license;
- revoke one device and its refresh credentials.

The clients generate the required timestamp, nonce, exact-body SHA-256 and HMAC-SHA256 request proof automatically. They validate HTTPS configuration, reject redirects, limit response size, validate the Licora response envelope and expose stable API errors.

## Server preparation

1. Install or upgrade Licora so the Admin License API schema is available.
2. In **Admin → API & Clients → Client Apps**, create or verify the target application.
3. In **Admin → Settings → Admin License API**, create a key.
4. Assign only the applications and scopes the integration requires.
5. Copy the secret when it is displayed. Licora shows the full secret only once.
6. Store the secret in the backend platform's secret manager.

## Configuration

Set these server-side environment variables:

```text
LICORA_ADMIN_API_BASE_URL=https://licenses.example.com/licora
LICORA_ADMIN_API_KEY=licora_admin_live_<64-lowercase-hex-characters>
```

`LICORA_ADMIN_API_BASE_URL` is the deployed Licora root, including its base directory when applicable. Do not add `/api/admin/v1` to it.

## PHP quick start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use VibTools\Licora\AdminApi\LicoraAdminClient;

$client = new LicoraAdminClient(
    (string)getenv('LICORA_ADMIN_API_BASE_URL'),
    (string)getenv('LICORA_ADMIN_API_KEY')
);

$response = $client->createLicense([
    'external_order_id' => 'ORDER-10482',
    'customer_reference' => 'USER-9081',
    'app_id' => 'vibrapilot',
    'validity_hours' => 720,
    'device_limit' => 2,
    'notes' => 'Paid order',
], 'checkout-ORDER-10482');

$license = $response['data'];
```

See [`php/README.md`](php/README.md) for installation and every method.

## Node.js quick start

```js
import { LicoraAdminClient } from './src/licora-admin-client.mjs';

const client = new LicoraAdminClient({
  baseUrl: process.env.LICORA_ADMIN_API_BASE_URL,
  apiKey: process.env.LICORA_ADMIN_API_KEY,
});

const response = await client.createLicense({
  external_order_id: 'ORDER-10482',
  customer_reference: 'USER-9081',
  app_id: 'vibrapilot',
  validity_hours: 720,
  device_limit: 2,
  notes: 'Paid order',
}, { idempotencyKey: 'checkout-ORDER-10482' });

const license = response.data;
```

See [`nodejs/README.md`](nodejs/README.md) for installation and every method.

## Required reading for production

- [`docs/AUTHENTICATION_AND_SIGNING.md`](docs/AUTHENTICATION_AND_SIGNING.md)
- [`docs/API_REFERENCE.md`](docs/API_REFERENCE.md)
- [`docs/SECURITY_AND_OPERATIONS.md`](docs/SECURITY_AND_OPERATIONS.md)
- [`AI_INSTRUCTIONS.md`](AI_INSTRUCTIONS.md) when handing this package to an AI coding agent

## Compatibility

- PHP 8.0 or newer with cURL and JSON extensions.
- Node.js 18 or newer using its built-in Fetch API and `node:crypto`.
- Licora Admin License API protocol `licora-admin-api`, API version `1`.

No database access, browser credential, API v1 key or Secure API v2 device credential is required by this SDK.
