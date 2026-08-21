import crypto from 'react-native-quick-crypto';
import { Buffer } from 'buffer';

/**
 * Licora Secure API v2 lifecycle reference for React Native.
 *
 * This developer/test helper creates an ephemeral P-256 credential and
 * deactivates it. Production apps must store the private key and rotated
 * refresh token with platform-secure storage and verify LICORA-V2/RS256
 * access-token signatures using the pinned Licora server public key.
 */

const b64url = (data) => Buffer.from(data).toString('base64').replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
const sha256 = (data) => crypto.createHash('sha256').update(data).digest('hex');
const randomHex = (bytes) => crypto.randomBytes(bytes).toString('hex');

function jwtJti(token) {
  const parts = token.split('.');
  if (parts.length !== 3) throw new Error('Licora returned a malformed access token.');
  const payload = JSON.parse(Buffer.from(parts[1], 'base64url').toString('utf8'));
  return String(payload.jti);
}

export class LicoraV2Client {
  constructor(baseUrl, appId, appVersion = '1.0.0') {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
    this.appId = appId;
    this.appVersion = appVersion;
    const pair = crypto.generateKeyPairSync('ec', {
      namedCurve: 'prime256v1',
      publicKeyEncoding: { type: 'spki', format: 'pem' },
      privateKeyEncoding: { type: 'pkcs8', format: 'pem' },
    });
    this.publicKey = pair.publicKey;
    this.privateKey = pair.privateKey;
    this.deviceId = `rn-${randomHex(16)}`;
  }

  endpoint(name) { return `${this.baseUrl}/api/v2/${name}.php`; }

  headers(url, body, context, accessToken) {
    const timestamp = Math.floor(Date.now() / 1000);
    const nonce = b64url(crypto.randomBytes(18));
    const canonical = ['POST', new URL(url).pathname || '/', timestamp, nonce, sha256(body), context].join('\n');
    const signer = crypto.createSign('SHA256');
    signer.update(canonical, 'utf8'); signer.end();
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
    const body = JSON.stringify(payload);
    const response = await fetch(url, { method: 'POST', headers: this.headers(url, Buffer.from(body, 'utf8'), context, accessToken), body });
    const data = await response.json();
    if (!data.success) throw new Error(`Licora error ${data.code || 'UNKNOWN'} (HTTP ${response.status})`);
    return data;
  }

  activate(licenseKey) { return this.post('activate', { license_key: licenseKey, app_id: this.appId, app_version: this.appVersion, device_id: this.deviceId, device_public_key: this.publicKey }, `activate:${this.appId}`); }
  status(accessToken) { return this.post('status', {}, jwtJti(accessToken), accessToken); }
  refresh(refreshToken) { return this.post('refresh', { refresh_token: refreshToken, app_version: this.appVersion }, `refresh:${sha256(Buffer.from(refreshToken, 'utf8'))}`); }
  deactivate(accessToken) { return this.post('deactivate', {}, jwtJti(accessToken), accessToken); }
}

export async function lifecycleTest({ baseUrl, appId, licenseKey, appVersion = '1.0.0' }) {
  const client = new LicoraV2Client(baseUrl, appId, appVersion);
  let accessToken = '';
  try {
    const activated = await client.activate(licenseKey); accessToken = activated.access_token; let refreshToken = activated.refresh_token; console.log('[PASS] activate');
    await client.status(accessToken); console.log('[PASS] status');
    const refreshed = await client.refresh(refreshToken); accessToken = refreshed.access_token; refreshToken = refreshed.refresh_token; console.log('[PASS] refresh (rotated)');
    await client.status(accessToken); console.log('[PASS] status-after-refresh');
    await client.deactivate(accessToken); accessToken = ''; console.log('[PASS] deactivate');
  } finally {
    if (accessToken) { try { await client.deactivate(accessToken); console.log('[INFO] cleanup deactivate completed'); } catch (_) { console.warn('[WARN] cleanup deactivate failed'); } }
  }
}
