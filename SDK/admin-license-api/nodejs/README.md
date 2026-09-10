# Node.js client

This server-side client supports Node.js 18 or newer. It has no runtime dependencies and uses the built-in Fetch API and `node:crypto` for Licora's HMAC request proof.

## Install

Copy the `nodejs` directory into a private backend repository. No `npm install` step is required for runtime dependencies. Never bundle this module or its Admin API key into browser, desktop or mobile code.

Set secrets in the process environment or a secret manager:

```text
LICORA_ADMIN_API_BASE_URL=https://licenses.example.com/licora
LICORA_ADMIN_API_KEY=licora_admin_live_<64-lowercase-hex-characters>
```

## Create a client

```js
import { LicoraAdminClient } from './src/licora-admin-client.mjs';

const client = new LicoraAdminClient({
  baseUrl: process.env.LICORA_ADMIN_API_BASE_URL,
  apiKey: process.env.LICORA_ADMIN_API_KEY,
  timeoutMs: 15000,
  maxResponseBytes: 2097152,
});
```

## Methods

```js
await client.listAllowedApps();
await client.createLicense(fields, { idempotencyKey: 'checkout-ORDER-10482' });
await client.licenseStatus({ licenseId: 42 });
await client.licenseStatus({ externalOrderId: 'ORDER-10482', includeKey: true });
await client.listLicenses({ page: 1, per_page: 25, status: 'active' });
await client.activateLicense(42);
await client.suspendLicense(42);
await client.extendLicense(42, 720);
await client.banLicense(42, 'Confirmed payment fraud');
await client.deleteLicense(42);
await client.listDevices(42);
await client.revokeDevice(91);
```

Every method returns the complete response envelope. `createLicense` requires one stable idempotency key per logical order. The client performs no automatic retries.

## Error handling

```js
import { LicoraAdminApiError } from './src/licora-admin-client.mjs';

try {
  const response = await client.licenseStatus({ licenseId: 42 });
} catch (error) {
  if (error instanceof LicoraAdminApiError) {
    console.error({
      code: error.code,
      httpStatus: error.httpStatus,
      requestId: error.requestId,
    });
  }
}
```

Do not log the API key, signature, full license key or request body. Retry only after classifying the failure according to `../docs/SECURITY_AND_OPERATIONS.md`. A create retry must keep the original order payload and idempotency key; the client generates a fresh timestamp, nonce and signature for every attempt.

Run `node examples/manage-license.mjs` only after setting the two required environment variables.
