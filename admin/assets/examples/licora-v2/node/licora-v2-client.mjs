#!/usr/bin/env node
/**
 * Licora Secure API v2 lifecycle reference.
 *
 * Ephemeral developer/test flow: activate -> status -> refresh -> status ->
 * deactivate. Production apps must persist the P-256 private key and rotated
 * refresh token securely and verify LICORA-V2/RS256 tokens with the pinned
 * server public key before trusting token claims locally.
 */
import {
  createHash,
  createSign,
  generateKeyPairSync,
  randomBytes,
} from 'node:crypto';

const b64url = (buffer) => Buffer.from(buffer).toString('base64').replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
const sha256 = (value) => createHash('sha256').update(value).digest('hex');
const compact = (value) => Buffer.from(JSON.stringify(value), 'utf8');

function decodeJwtPayload(token) {
  const parts = token.split('.');
  if (parts.length !== 3) throw new Error('Licora returned a malformed access token');
  return JSON.parse(Buffer.from(parts[1], 'base64url').toString('utf8'));
}

class LicoraV2Client {
  constructor(baseUrl, appId, appVersion) {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
    this.appId = appId;
    this.appVersion = appVersion;
    const keys = generateKeyPairSync('ec', {
      namedCurve: 'prime256v1',
      publicKeyEncoding: { type: 'spki', format: 'pem' },
      privateKeyEncoding: { type: 'pkcs8', format: 'pem' },
    });
    this.publicKey = keys.publicKey;
    this.privateKey = keys.privateKey;
    this.deviceId = `node-${randomBytes(16).toString('hex')}`;
  }

  endpoint(name) { return `${this.baseUrl}/api/v2/${name}.php`; }

  headers(url, body, context, accessToken = '') {
    const timestamp = Math.floor(Date.now() / 1000);
    const nonce = b64url(randomBytes(18));
    const path = new URL(url).pathname || '/';
    const canonical = ['POST', path, timestamp, nonce, sha256(body), context].join('\n');
    const signer = createSign('SHA256');
    signer.update(canonical, 'utf8');
    signer.end();
    const signature = signer.sign({ key: this.privateKey, dsaEncoding: 'der' });
    return {
      'Content-Type': 'application/json',
      'X-Licora-Timestamp': String(timestamp),
      'X-Licora-Nonce': nonce,
      'X-Licora-Device-Signature': b64url(signature),
      ...(accessToken ? { Authorization: `Bearer ${accessToken}` } : {}),
    };
  }

  async post(name, payload, context, accessToken = '') {
    const url = this.endpoint(name);
    const body = compact(payload);
    const response = await fetch(url, { method: 'POST', headers: this.headers(url, body, context, accessToken), body });
    const data = await response.json().catch(() => { throw new Error(`HTTP ${response.status}: non-JSON response`); });
    if (!data.success) throw new Error(`Licora error ${data.code || 'UNKNOWN'} (HTTP ${response.status})`);
    return data;
  }

  activate(licenseKey) {
    return this.post('activate', {
      license_key: licenseKey,
      app_id: this.appId,
      app_version: this.appVersion,
      device_id: this.deviceId,
      device_public_key: this.publicKey,
    }, `activate:${this.appId}`);
  }

  status(accessToken) {
    return this.post('status', {}, String(decodeJwtPayload(accessToken).jti), accessToken);
  }

  refresh(refreshToken) {
    return this.post('refresh', { refresh_token: refreshToken, app_version: this.appVersion }, `refresh:${sha256(refreshToken)}`);
  }

  deactivate(accessToken) {
    return this.post('deactivate', {}, String(decodeJwtPayload(accessToken).jti), accessToken);
  }
}

async function main() {
  const [baseUrl, appId, licenseKey, appVersion = '1.0.0'] = process.argv.slice(2);
  if (!baseUrl || !appId || !licenseKey) {
    console.error('Usage: node licora-v2-client.mjs <base-url> <app-id> <license-key> [app-version]');
    process.exitCode = 2;
    return;
  }
  const client = new LicoraV2Client(baseUrl, appId, appVersion);
  let accessToken = '';
  try {
    const activated = await client.activate(licenseKey);
    accessToken = activated.access_token;
    let refreshToken = activated.refresh_token;
    console.log('[PASS] activate', activated.code);
    console.log('[PASS] status', (await client.status(accessToken)).code);
    const refreshed = await client.refresh(refreshToken);
    accessToken = refreshed.access_token;
    refreshToken = refreshed.refresh_token;
    console.log('[PASS] refresh', refreshed.code, 'rotated refresh token');
    console.log('[PASS] status-after-refresh', (await client.status(accessToken)).code);
    await client.deactivate(accessToken);
    accessToken = '';
    console.log('[PASS] deactivate');
  } finally {
    if (accessToken) {
      try { await client.deactivate(accessToken); console.log('[INFO] cleanup deactivate completed'); }
      catch (error) { console.warn('[WARN] cleanup deactivate failed:', error.message); }
    }
  }
}

if (import.meta.url === `file://${process.argv[1]}`) main().catch((error) => { console.error(error.message); process.exitCode = 1; });

export { LicoraV2Client };
