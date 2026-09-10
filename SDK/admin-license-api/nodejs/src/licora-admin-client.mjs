import { createHash, createHmac, randomBytes } from 'node:crypto';

const PROTOCOL = 'licora-admin-api';
const API_VERSION = 1;

export class LicoraAdminApiError extends Error {
  constructor(message, {
    code = 'SDK_ERROR',
    httpStatus = 0,
    requestId = null,
    response = null,
    cause,
  } = {}) {
    super(message, cause === undefined ? undefined : { cause });
    this.name = 'LicoraAdminApiError';
    this.code = code;
    this.httpStatus = httpStatus;
    this.requestId = requestId;
    this.response = response;
  }
}

export class LicoraAdminClient {
  constructor({ baseUrl, apiKey, timeoutMs = 15000, maxResponseBytes = 2097152 } = {}) {
    let parsed;
    try {
      parsed = new URL(baseUrl);
    } catch (cause) {
      throw new LicoraAdminApiError('baseUrl must be a valid HTTPS URL.', {
        code: 'INVALID_CONFIGURATION', cause,
      });
    }
    if (parsed.protocol !== 'https:' || parsed.username || parsed.password || parsed.search || parsed.hash) {
      throw new LicoraAdminApiError(
        'baseUrl must be an HTTPS Licora installation URL without credentials, query or fragment.',
        { code: 'INVALID_CONFIGURATION' },
      );
    }
    if (!/^licora_admin_live_[a-f0-9]{64}$/.test(apiKey ?? '')) {
      throw new LicoraAdminApiError('Invalid Licora Admin API key format.', {
        code: 'INVALID_CONFIGURATION',
      });
    }
    if (!Number.isInteger(timeoutMs) || timeoutMs < 1
        || !Number.isInteger(maxResponseBytes) || maxResponseBytes < 1024) {
      throw new LicoraAdminApiError('Timeout and response-size limits must be positive integers.', {
        code: 'INVALID_CONFIGURATION',
      });
    }

    parsed.pathname = `${parsed.pathname.replace(/\/+$/, '')}/`;
    this.baseUrl = parsed;
    this.apiKey = apiKey;
    this.timeoutMs = timeoutMs;
    this.maxResponseBytes = maxResponseBytes;
  }

  listAllowedApps() {
    return this.#request('GET', 'api/admin/v1/apps/list.php');
  }

  createLicense(license, { idempotencyKey } = {}) {
    if (typeof idempotencyKey !== 'string') {
      throw new LicoraAdminApiError('createLicense requires an Idempotency-Key.', {
        code: 'INVALID_CONFIGURATION',
      });
    }
    return this.#request('POST', 'api/admin/v1/licenses/create.php', {
      body: license,
      idempotencyKey,
    });
  }

  licenseStatus({ licenseId, externalOrderId, includeKey = false } = {}) {
    return this.#request('GET', 'api/admin/v1/licenses/status.php', {
      query: {
        license_id: licenseId,
        external_order_id: externalOrderId,
        include_key: includeKey ? 'true' : undefined,
      },
    });
  }

  listLicenses(filters = {}) {
    return this.#request('GET', 'api/admin/v1/licenses/list.php', { query: filters });
  }

  licenseAction(licenseId, action, fields = {}) {
    return this.#request('POST', 'api/admin/v1/licenses/action.php', {
      body: { ...fields, license_id: licenseId, action },
    });
  }

  activateLicense(licenseId) {
    return this.licenseAction(licenseId, 'activate');
  }

  suspendLicense(licenseId) {
    return this.licenseAction(licenseId, 'suspend');
  }

  extendLicense(licenseId, additionalHours) {
    return this.licenseAction(licenseId, 'extend', { additional_hours: additionalHours });
  }

  banLicense(licenseId, reason) {
    return this.licenseAction(licenseId, 'ban', { reason });
  }

  deleteLicense(licenseId) {
    return this.licenseAction(licenseId, 'delete');
  }

  listDevices(licenseId) {
    return this.#request('GET', 'api/admin/v1/devices/list.php', {
      query: { license_id: licenseId },
    });
  }

  revokeDevice(credentialId) {
    return this.#request('POST', 'api/admin/v1/devices/revoke.php', {
      body: { credential_id: credentialId },
    });
  }

  async #request(method, endpoint, { query = {}, body, idempotencyKey } = {}) {
    if (idempotencyKey !== undefined
        && !/^[A-Za-z0-9._:-]{8,120}$/.test(idempotencyKey)) {
      throw new LicoraAdminApiError('Invalid Idempotency-Key format.', {
        code: 'INVALID_CONFIGURATION',
      });
    }

    const url = new URL(endpoint.replace(/^\/+/, ''), this.baseUrl);
    for (const [name, value] of Object.entries(query)) {
      if (value !== undefined && value !== null) url.searchParams.append(name, String(value));
    }
    const target = `${url.pathname}${url.search}`;
    let requestBody = '';
    if (body !== undefined) {
      try {
        requestBody = JSON.stringify(body);
      } catch (cause) {
        throw new LicoraAdminApiError('Unable to encode the request body.', {
          code: 'INVALID_REQUEST_BODY', cause,
        });
      }
      if (requestBody === undefined) {
        throw new LicoraAdminApiError('The request body is not JSON serializable.', {
          code: 'INVALID_REQUEST_BODY',
        });
      }
    }

    const timestamp = String(Math.floor(Date.now() / 1000));
    const nonce = randomBytes(16).toString('hex');
    const bodyHash = createHash('sha256').update(requestBody, 'utf8').digest('hex');
    const canonical = `${method}\n${target}\n${timestamp}\n${nonce}\n${bodyHash}`;
    const signature = createHmac('sha256', this.apiKey).update(canonical, 'utf8').digest('hex');
    const headers = {
      Accept: 'application/json',
      Authorization: `Bearer ${this.apiKey}`,
      'X-Licora-Timestamp': timestamp,
      'X-Licora-Nonce': nonce,
      'X-Licora-Signature': signature,
    };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (idempotencyKey !== undefined) headers['Idempotency-Key'] = idempotencyKey;

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    let response;
    try {
      response = await fetch(url, {
        method,
        headers,
        body: body === undefined ? undefined : requestBody,
        redirect: 'manual',
        signal: controller.signal,
      });
    } catch (cause) {
      clearTimeout(timer);
      const message = cause?.name === 'AbortError' ? 'Licora request timed out.' : 'Licora network request failed.';
      throw new LicoraAdminApiError(message, { code: 'NETWORK_ERROR', cause });
    }

    let responseText;
    try {
      if (response.status >= 300 && response.status < 400) {
        throw new LicoraAdminApiError('Licora redirect responses are rejected.', {
          code: 'INVALID_SERVER_RESPONSE', httpStatus: response.status,
        });
      }
      responseText = await this.#readLimitedBody(response);
    } catch (cause) {
      if (cause instanceof LicoraAdminApiError) throw cause;
      if (controller.signal.aborted) {
        throw new LicoraAdminApiError('Licora request timed out.', {
          code: 'NETWORK_ERROR', httpStatus: response.status, cause,
        });
      }
      throw new LicoraAdminApiError('Unable to read the Licora response.', {
        code: 'INVALID_SERVER_RESPONSE', httpStatus: response.status, cause,
      });
    } finally {
      clearTimeout(timer);
    }
    let decoded;
    try {
      decoded = JSON.parse(responseText);
    } catch (cause) {
      throw new LicoraAdminApiError('Licora returned invalid JSON.', {
        code: 'INVALID_SERVER_RESPONSE', httpStatus: response.status, cause,
      });
    }
    if (!decoded || typeof decoded !== 'object' || Array.isArray(decoded)
        || decoded.protocol !== PROTOCOL || decoded.api_version !== API_VERSION
        || typeof decoded.success !== 'boolean' || typeof decoded.code !== 'string'
        || typeof decoded.request_id !== 'string') {
      throw new LicoraAdminApiError('Licora returned an invalid response envelope.', {
        code: 'INVALID_SERVER_RESPONSE', httpStatus: response.status,
      });
    }

    if (!response.ok || decoded.success !== true) {
      throw new LicoraAdminApiError(
        typeof decoded.message === 'string' ? decoded.message : 'Licora Admin API request failed.',
        {
          code: decoded.code,
          httpStatus: response.status,
          requestId: decoded.request_id,
          response: decoded,
        },
      );
    }
    return decoded;
  }

  async #readLimitedBody(response) {
    const declaredLength = Number(response.headers.get('content-length'));
    if (Number.isFinite(declaredLength) && declaredLength > this.maxResponseBytes) {
      await response.body?.cancel();
      throw new LicoraAdminApiError('Licora response exceeded the configured size limit.', {
        code: 'INVALID_SERVER_RESPONSE', httpStatus: response.status,
      });
    }
    if (!response.body) return '';

    const chunks = [];
    let totalBytes = 0;
    const reader = response.body.getReader();
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      totalBytes += value.byteLength;
      if (totalBytes > this.maxResponseBytes) {
        await reader.cancel();
        throw new LicoraAdminApiError('Licora response exceeded the configured size limit.', {
          code: 'INVALID_SERVER_RESPONSE', httpStatus: response.status,
        });
      }
      chunks.push(value);
    }

    const merged = new Uint8Array(totalBytes);
    let offset = 0;
    for (const chunk of chunks) {
      merged.set(chunk, offset);
      offset += chunk.byteLength;
    }
    return new TextDecoder('utf-8', { fatal: true }).decode(merged);
  }
}
