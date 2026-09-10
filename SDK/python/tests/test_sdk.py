from __future__ import annotations

import base64
import hashlib
import json
from pathlib import Path
import sys
import time
import types
import unittest

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec, padding, rsa

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "src"))

# The repository-level static suite can run before SDK dependencies are installed.
# Manager/crypto unit tests inject a protocol fake and never perform HTTP. Dedicated
# CI installs the package and exercises imports with the real requests dependency.
try:
    import requests as _requests  # noqa: F401
except ModuleNotFoundError:
    requests_stub = types.ModuleType("requests")

    class _RequestException(Exception):
        pass

    class _Session:
        def close(self) -> None:
            return None

    requests_stub.RequestException = _RequestException
    requests_stub.Session = _Session
    sys.modules["requests"] = requests_stub

from licora_sdk import (  # noqa: E402
    LicoraAuth,
    LicoraConfig,
    LicoraConfigurationError,
    LicoraError,
    MemoryStateStore,
    LicenseStatus,
    require_license,
)
from licora_sdk.client import LicoraV2Client  # noqa: E402
from licora_sdk.crypto import (  # noqa: E402
    AccessTokenClaims,
    AccessTokenVerifier,
    b64url_decode,
    b64url_encode,
    canonical_request,
    generate_device_key_material,
)


LICENSE = "AAAAAAAA-BBBBBBBB-CCCCCCCC-DDDDDDDD"


def config_and_key() -> tuple[LicoraConfig, rsa.RSAPrivateKey]:
    key = rsa.generate_private_key(public_exponent=65537, key_size=3072)
    pem = key.public_key().public_bytes(
        serialization.Encoding.PEM,
        serialization.PublicFormat.SubjectPublicKeyInfo,
    ).decode("ascii")
    values = {
        "LICORA_API_BASE_URL": "https://licenses.example.com",
        "LICORA_API_VERSION": 2,
        "LICORA_PROTOCOL": "licora-api-v2",
        "LICORA_APP_ID": "test-app",
        "LICORA_APP_NAME": "Test App",
        "LICORA_APP_VERSION": "1.2.3",
        "LICORA_ACTIVATE_PATH": "/api/v2/activate.php",
        "LICORA_STATUS_PATH": "/api/v2/status.php",
        "LICORA_REFRESH_PATH": "/api/v2/refresh.php",
        "LICORA_DEACTIVATE_PATH": "/api/v2/deactivate.php",
        "LICORA_SIGNING_KEY_ID": "test-key",
        "LICORA_SIGNING_PUBLIC_KEY_PEM": pem,
        "LICORA_SIGNING_PUBLIC_KEY_SHA256": hashlib.sha256(pem.encode("ascii")).hexdigest(),
        "LICORA_SERVER_TIMEZONE": "Asia/Dhaka",
        "LICORA_CLOCK_SKEW_SECONDS": 300,
        "LICORA_CONNECT_TIMEOUT_SECONDS": 2,
        "LICORA_READ_TIMEOUT_SECONDS": 3,
        "LICORA_BACKGROUND_CHECK_SECONDS": 60,
        "LICORA_REFRESH_MARGIN_SECONDS": 30,
        "LICORA_MAX_RESPONSE_BYTES": 65536,
    }
    return LicoraConfig.from_mapping(values), key


def token(config: LicoraConfig, key: rsa.RSAPrivateKey, device_id: str, fingerprint: str) -> str:
    now = int(time.time())
    header = {"typ": "LICORA-V2", "alg": "RS256", "kid": config.signing_key_id}
    payload = {
        "iss": "licora",
        "aud": config.app_id,
        "app_id": config.app_id,
        "license_id": 4,
        "device_id": device_id,
        "device_credential_id": 9,
        "device_key_fingerprint": fingerprint,
        "iat": now,
        "nbf": now - 5,
        "exp": now + 3600,
        "jti": "jti-test",
        "token_version": 2,
    }
    encoded = [
        b64url_encode(json.dumps(part, separators=(",", ":")).encode("utf-8"))
        for part in (header, payload)
    ]
    signing_input = ".".join(encoded).encode("ascii")
    signature = key.sign(signing_input, padding.PKCS1v15(), hashes.SHA256())
    return ".".join((*encoded, b64url_encode(signature)))


class FakeClient:
    def __init__(self, config: LicoraConfig, key: rsa.RSAPrivateKey) -> None:
        self.config = config
        self.key = key
        self.activate_calls = 0
        self.refresh_calls = 0
        self.status_calls = 0
        self.deactivate_calls = 0
        self.status_error: LicoraError | None = None
        self.activation_error_once: LicoraError | None = None
        self.tokens: dict[str, AccessTokenClaims] = {}

    def _issue(self, device_id: str, private_key_pem: str) -> tuple[str, dict]:
        from licora_sdk.crypto import load_device_key_material

        fingerprint = load_device_key_material(private_key_pem).public_key_fingerprint
        access = token(self.config, self.key, device_id, fingerprint)
        claims = AccessTokenVerifier(self.config).verify(
            access,
            expected_device_id=device_id,
            expected_device_fingerprint=fingerprint,
        )
        self.tokens[access] = claims
        return access, claims.raw

    def verify_access_token(self, value: str, **_: object) -> AccessTokenClaims:
        if value not in self.tokens:
            raise LicoraError("TOKEN_EXPIRED", "expired")
        return self.tokens[value]

    def activate(self, *, device_id: str, private_key_pem: str, **_: object) -> dict:
        self.activate_calls += 1
        if self.activation_error_once is not None:
            error, self.activation_error_once = self.activation_error_once, None
            raise error
        access, claims = self._issue(device_id, private_key_pem)
        return {
            "access_token": access,
            "refresh_token": f"refresh-{self.activate_calls}",
            "refresh_expires_at": "2030-01-01 00:00:00",
            "verified_claims": claims,
            "license": {"status": "active", "expires_at": "2030-01-01 00:00:00"},
        }

    def status(self, **_: object) -> dict:
        self.status_calls += 1
        if self.status_error is not None:
            raise self.status_error
        return {"license": {"status": "active", "expires_at": "2030-01-01 00:00:00"}}

    def refresh(self, *, device_id: str, private_key_pem: str, **_: object) -> dict:
        self.refresh_calls += 1
        access, claims = self._issue(device_id, private_key_pem)
        return {
            "access_token": access,
            "refresh_token": f"rotated-{self.refresh_calls}",
            "refresh_expires_at": "2030-01-01 00:00:00",
            "verified_claims": claims,
        }

    def deactivate(self, **_: object) -> dict:
        self.deactivate_calls += 1
        return {"success": True}

    def close(self) -> None:
        return None


class FakeResponse:
    def __init__(self, body: dict, status_code: int = 200) -> None:
        self.status_code = status_code
        self._raw = json.dumps(body, separators=(",", ":")).encode("utf-8")
        self.headers = {
            "Content-Type": "application/json; charset=utf-8",
            "Content-Length": str(len(self._raw)),
        }
        self.closed = False

    def iter_content(self, chunk_size: int = 65536):
        for start in range(0, len(self._raw), chunk_size):
            yield self._raw[start : start + chunk_size]

    def close(self) -> None:
        self.closed = True


class FakeHttpSession:
    def __init__(self, response: FakeResponse) -> None:
        self.response = response
        self.calls: list[dict] = []

    def post(self, url: str, **kwargs: object) -> FakeResponse:
        self.calls.append({"url": url, **kwargs})
        return self.response

    def close(self) -> None:
        return None


class ConfigTests(unittest.TestCase):
    def test_configuration_and_signed_token(self) -> None:
        config, key = config_and_key()
        verified = AccessTokenVerifier(config).verify(
            token(config, key, "device-123456789", "a" * 64),
            expected_device_id="device-123456789",
            expected_device_fingerprint="a" * 64,
        )
        self.assertEqual(verified.raw["app_id"], "test-app")

    def test_placeholder_app_id_fails_closed(self) -> None:
        config, _ = config_and_key()
        values = {
            "LICORA_" + name.upper(): value
            for name, value in config.__dict__.items()
        }
        values["LICORA_APP_ID"] = "replace-with-your-app-id"
        with self.assertRaises(LicoraConfigurationError):
            LicoraConfig.from_mapping(values)

    def test_client_activation_signs_exact_transmitted_body(self) -> None:
        config, server_key = config_and_key()
        material = generate_device_key_material()
        access = token(config, server_key, "device-123456789", material.public_key_fingerprint)
        response = FakeResponse(
            {
                "success": True,
                "protocol": "licora-api-v2",
                "api_version": 2,
                "server_version": "5.8.4",
                "request_id": "req-test",
                "code": "OK",
                "message": "License activated.",
                "server_time": int(time.time()),
                "access_token": access,
                "refresh_token": "refresh-token-value-12345678901234567890",
                "refresh_expires_at": "2030-01-01 00:00:00",
                "license": {
                    "status": "active",
                    "expires_at": "2030-01-01 00:00:00",
                    "device_limit": 1,
                },
            }
        )
        session = FakeHttpSession(response)
        client = LicoraV2Client(config, session=session)
        result = client.activate(
            license_key=LICENSE,
            device_id="device-123456789",
            private_key_pem=material.private_key_pem,
        )
        self.assertEqual(result["verified_claims"]["app_id"], config.app_id)
        call = session.calls[0]
        self.assertEqual(call["url"], "https://licenses.example.com/api/v2/activate.php")
        self.assertFalse(call["allow_redirects"])
        self.assertTrue(call["stream"])
        headers = call["headers"]
        self.assertNotIn("X-API-Key", headers)
        raw = call["data"]
        payload = json.loads(raw)
        self.assertEqual(payload["app_id"], config.app_id)
        canonical = canonical_request(
            "POST",
            "/api/v2/activate.php",
            int(headers["X-Licora-Timestamp"]),
            headers["X-Licora-Nonce"],
            raw,
            "activate:" + config.app_id,
        )
        material_key = serialization.load_pem_public_key(material.public_key_pem.encode("ascii"))
        material_key.verify(
            b64url_decode(headers["X-Licora-Device-Signature"]),
            canonical,
            ec.ECDSA(hashes.SHA256()),
        )
        self.assertTrue(response.closed)

    def test_tampered_access_token_is_rejected(self) -> None:
        config, key = config_and_key()
        signed = token(config, key, "device-123456789", "a" * 64)
        parts = signed.split(".")
        payload = bytearray(base64.urlsafe_b64decode(parts[1] + "=="))
        payload[-2] ^= 1
        parts[1] = b64url_encode(bytes(payload))
        with self.assertRaises(LicoraError):
            AccessTokenVerifier(config).verify(".".join(parts))


class ManagerTests(unittest.TestCase):
    def setUp(self) -> None:
        self.config, key = config_and_key()
        self.client = FakeClient(self.config, key)
        self.store = MemoryStateStore()
        self.auth = LicoraAuth(
            self.config,
            state_store=self.store,
            client_factory=lambda _: self.client,
        )

    def tearDown(self) -> None:
        self.auth.close()

    def test_login_validate_guard_and_logout(self) -> None:
        result = self.auth.login(LICENSE, "customer-7")
        self.assertTrue(result.ok)
        self.assertTrue(self.auth.is_licensed())
        self.assertEqual(result.snapshot.status, LicenseStatus.ACTIVE)
        old_device = result.snapshot.device_id

        @require_license(self.auth)
        def feature() -> str:
            return "ok"

        self.assertEqual(feature(), "ok")
        self.assertTrue(self.auth.validate().ok)
        logged_out = self.auth.logout(wait=True)
        self.assertTrue(logged_out.ok)
        self.assertFalse(self.auth.is_licensed())
        self.assertNotEqual(self.auth.snapshot().device_id, old_device)
        self.assertEqual(self.client.deactivate_calls, 1)

    def test_refresh_rotation_and_transient_degraded_state(self) -> None:
        self.assertTrue(self.auth.login(LICENSE).ok)
        self.client.tokens.clear()
        refreshed = self.auth.validate()
        self.assertTrue(refreshed.ok)
        self.assertEqual(self.client.refresh_calls, 1)
        self.client.status_error = LicoraError("NETWORK_ERROR", "offline")
        degraded = self.auth.validate()
        self.assertTrue(degraded.ok)
        self.assertEqual(degraded.snapshot.status, LicenseStatus.DEGRADED)
        self.assertTrue(degraded.snapshot.authorized)

    def test_refresh_success_status_network_failure_keeps_rotated_token(self) -> None:
        self.assertTrue(self.auth.login(LICENSE).ok)
        self.client.tokens.clear()
        self.client.status_error = LicoraError("NETWORK_ERROR", "offline")
        degraded = self.auth.validate()
        self.assertTrue(degraded.ok)
        self.assertEqual(degraded.snapshot.status, LicenseStatus.DEGRADED)
        self.assertEqual(self.client.refresh_calls, 1)
        self.assertEqual(self.client.activate_calls, 1)
        self.assertTrue(self.auth.is_licensed())

    def test_license_expiry_caps_local_authorization(self) -> None:
        self.assertTrue(self.auth.login(LICENSE).ok)
        stored = self.store.load()
        self.assertIsNotNone(stored)
        stored.license_expires_at = "2000-01-01 00:00:00"
        self.store.save(stored)
        self.auth.close()
        self.auth = LicoraAuth(
            self.config,
            state_store=self.store,
            client_factory=lambda _: self.client,
        )
        self.assertFalse(self.auth.is_licensed())

    def test_revoked_device_rotates_once(self) -> None:
        self.client.activation_error_once = LicoraError("DEVICE_REVOKED", "revoked")
        result = self.auth.login(LICENSE)
        self.assertTrue(result.ok)
        self.assertEqual(self.client.activate_calls, 2)
        self.assertEqual(self.store.load().recovery_rotations, 1)

    def test_invalid_format_never_calls_server(self) -> None:
        result = self.auth.login("invalid")
        self.assertFalse(result.ok)
        self.assertEqual(result.code, "INVALID_LICENSE_FORMAT")
        self.assertEqual(self.client.activate_calls, 0)


if __name__ == "__main__":
    unittest.main()
