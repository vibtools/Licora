# Licora API v2 Client Integration

This document defines the client contract used by VibraPilot and future Licora v2 clients. It contains no private server credential.

## 1. Register the application

In Licora Admin → **Client Apps**, create a stable App ID such as `vibrapilot`. Create licenses with the same API v2 Client App scope.

## 2. Generate a device key pair

The client generates a P-256 key pair on first use and protects the private key with the operating system's secure key/storage facility. Only the PEM public key is submitted to Licora.

## 3. Activation

POST JSON to `/api/v2/activate.php`:

```json
{
  "license_key": "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD",
  "app_id": "vibrapilot",
  "app_version": "1.0.6.2",
  "device_id": "stable-device-identifier",
  "device_public_key": "-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----\n"
}
```

Required proof headers are `X-Licora-Timestamp`, `X-Licora-Nonce`, and `X-Licora-Device-Signature`. The signature covers the exact raw JSON body SHA-256 and context `activate:<app_id>`.

## 4. Store returned credentials

On success, securely store the refresh token and access token. The response also returns the registered public-key fingerprint and license expiry. Distribute the server public signing key to the client through a trusted channel and pin it in the client configuration. The client should verify every server-signed access token before accepting its claims; the server signing private key never leaves Licora.

## 5. Status/deactivate

`status.php` and `deactivate.php` use a Bearer access token plus device proof. Their JSON body is `{}`. The proof context is the access token JTI.

## 6. Refresh

`refresh.php` requires the current refresh token and current app version so the server can enforce the Client App minimum-version policy on every refresh. The request proof context is:

```text
refresh:<sha256(refresh_token)>
```

On success, discard the old refresh token and persist the newly returned one. Reusing an old refresh token causes the server to revoke its refresh family.

## Client restrictions

- Do not embed or request a Licora API v1 master/shared key for API v2.
- Never send the device private key.
- Do not parse human `message` text for program logic; use the stable `code` field.
- Use HTTPS and verify the server certificate.

## In-app Developer Guide

Licora v5.8.1 verifies the **Admin → API & Clients → Developer Guide** introduced by the v5.8.0 source candidate, which presents this same contract in a compact UI and ships downloadable lifecycle references for the approved Python, PowerShell/CMD, C, C++, C#/.NET, Java, Flutter, React Native, PHP and Node.js targets. The examples sign the exact JSON bytes sent to the API and do not embed a shared API v1 credential.
