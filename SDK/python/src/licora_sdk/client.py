"""Exact HTTP/device-proof client for Licora Secure API v2."""
from __future__ import annotations

import hashlib
import json
import secrets
import time
from typing import Any
from urllib.parse import urljoin, urlparse

import requests
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.asymmetric import ec

from .config import LicoraConfig
from .crypto import (
    AccessTokenClaims,
    AccessTokenVerifier,
    b64url_encode,
    canonical_request,
    compact_json,
    load_device_key_material,
    load_device_private_key,
)
from .errors import LicoraError, LicoraProtocolError


class LicoraV2Client:
    """Thread-safe-at-manager-level protocol client with pinned token verification."""

    def __init__(
        self,
        config: LicoraConfig,
        *,
        session: requests.Session | None = None,
    ) -> None:
        self.config = config
        self.session = session or requests.Session()
        self._owns_session = session is None
        self.verifier = AccessTokenVerifier(config)

    def close(self) -> None:
        if self._owns_session:
            self.session.close()

    def __enter__(self) -> "LicoraV2Client":
        return self

    def __exit__(self, *_: object) -> None:
        self.close()

    def _url(self, path: str) -> str:
        url = urljoin(self.config.api_base_url + "/", path.lstrip("/"))
        parsed = urlparse(url)
        expected = urlparse(self.config.api_base_url)
        if parsed.scheme != "https" or parsed.netloc != expected.netloc:
            raise LicoraProtocolError(
                "INVALID_CONFIGURATION", "Licora endpoint escaped the configured HTTPS origin."
            )
        return url

    def _proof_headers(
        self,
        *,
        url: str,
        body: bytes,
        context: str,
        private_key_pem: str,
        access_token: str = "",
    ) -> dict[str, str]:
        timestamp = int(time.time())
        nonce = b64url_encode(secrets.token_bytes(24))
        path = urlparse(url).path or "/"
        signature = load_device_private_key(private_key_pem).sign(
            canonical_request("POST", path, timestamp, nonce, body, context),
            ec.ECDSA(hashes.SHA256()),
        )
        headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "User-Agent": f"{self.config.app_name}/{self.config.app_version} LicoraPythonSDK/1.0",
            "X-Licora-Timestamp": str(timestamp),
            "X-Licora-Nonce": nonce,
            "X-Licora-Device-Signature": b64url_encode(signature),
        }
        if access_token:
            headers["Authorization"] = "Bearer " + access_token
        return headers

    def _post(
        self,
        *,
        path: str,
        payload: dict[str, Any],
        context: str,
        private_key_pem: str,
        access_token: str = "",
    ) -> dict[str, Any]:
        url = self._url(path)
        body = compact_json(payload)
        try:
            response = self.session.post(
                url,
                data=body,
                headers=self._proof_headers(
                    url=url,
                    body=body,
                    context=context,
                    private_key_pem=private_key_pem,
                    access_token=access_token,
                ),
                timeout=(
                    self.config.connect_timeout_seconds,
                    self.config.read_timeout_seconds,
                ),
                allow_redirects=False,
                stream=True,
            )
        except requests.RequestException as exc:
            raise LicoraError(
                "NETWORK_ERROR", f"Licora API v2 network request failed: {exc}"
            ) from exc
        try:
            if 300 <= response.status_code < 400:
                raise LicoraProtocolError(
                    "INVALID_SERVER_RESPONSE",
                    "Licora API v2 unexpectedly redirected the request.",
                    http_status=response.status_code,
                )
            content_length = response.headers.get("Content-Length")
            if content_length:
                try:
                    declared_length = int(content_length)
                except (TypeError, ValueError) as exc:
                    raise LicoraProtocolError(
                        "INVALID_SERVER_RESPONSE", "Licora response length is invalid."
                    ) from exc
                if declared_length < 0 or declared_length > self.config.max_response_bytes:
                    raise LicoraProtocolError(
                        "INVALID_SERVER_RESPONSE", "Licora response exceeded the configured limit."
                    )
            chunks: list[bytes] = []
            total = 0
            for chunk in response.iter_content(chunk_size=65536):
                total += len(chunk)
                if total > self.config.max_response_bytes:
                    raise LicoraProtocolError(
                        "INVALID_SERVER_RESPONSE", "Licora response exceeded the configured limit."
                    )
                chunks.append(chunk)
            try:
                parsed = json.loads(b"".join(chunks).decode("utf-8"))
            except Exception as exc:
                raise LicoraProtocolError(
                    "INVALID_SERVER_RESPONSE",
                    "Licora API v2 returned a non-JSON response.",
                    http_status=response.status_code,
                ) from exc
            if not isinstance(parsed, dict):
                raise LicoraProtocolError(
                    "INVALID_SERVER_RESPONSE", "Licora response must be a JSON object."
                )
            request_id = str(parsed.get("request_id", ""))
            if (
                parsed.get("protocol") != self.config.protocol
                or parsed.get("api_version") != self.config.api_version
            ):
                raise LicoraProtocolError(
                    "INVALID_SERVER_RESPONSE",
                    "Licora API v2 protocol marker is invalid.",
                    http_status=response.status_code,
                    request_id=request_id,
                )
            if (
                response.status_code != 200
                or parsed.get("success") is not True
                or parsed.get("code") != "OK"
            ):
                raise LicoraError(
                    str(parsed.get("code") or "LICORA_ERROR"),
                    str(parsed.get("message") or "Licora API v2 request was rejected."),
                    http_status=response.status_code,
                    request_id=request_id,
                )
            return parsed
        except requests.RequestException as exc:
            raise LicoraError(
                "NETWORK_ERROR", "Licora API v2 response transfer failed."
            ) from exc
        finally:
            response.close()

    def verify_access_token(
        self,
        token: str,
        *,
        expected_device_id: str | None = None,
        expected_device_fingerprint: str | None = None,
    ) -> AccessTokenClaims:
        return self.verifier.verify(
            token,
            expected_device_id=expected_device_id,
            expected_device_fingerprint=expected_device_fingerprint,
        )

    def activate(
        self, *, license_key: str, device_id: str, private_key_pem: str
    ) -> dict[str, Any]:
        material = load_device_key_material(private_key_pem)
        result = self._post(
            path=self.config.activate_path,
            payload={
                "license_key": license_key.strip().upper(),
                "app_id": self.config.app_id,
                "app_version": self.config.app_version,
                "device_id": device_id,
                "device_public_key": material.public_key_pem,
            },
            context="activate:" + self.config.app_id,
            private_key_pem=material.private_key_pem,
        )
        access = str(result.get("access_token", ""))
        refresh = str(result.get("refresh_token", ""))
        if not access or not refresh:
            raise LicoraProtocolError(
                "INVALID_SERVER_RESPONSE", "Activation response did not include both tokens."
            )
        claims = self.verify_access_token(
            access,
            expected_device_id=device_id,
            expected_device_fingerprint=material.public_key_fingerprint,
        )
        result["verified_claims"] = claims.raw
        return result

    def status(
        self, *, access_token: str, device_id: str, private_key_pem: str
    ) -> dict[str, Any]:
        material = load_device_key_material(private_key_pem)
        claims = self.verify_access_token(
            access_token,
            expected_device_id=device_id,
            expected_device_fingerprint=material.public_key_fingerprint,
        )
        return self._post(
            path=self.config.status_path,
            payload={},
            context=claims.jti,
            private_key_pem=material.private_key_pem,
            access_token=access_token,
        )

    def refresh(
        self, *, refresh_token: str, device_id: str, private_key_pem: str
    ) -> dict[str, Any]:
        material = load_device_key_material(private_key_pem)
        result = self._post(
            path=self.config.refresh_path,
            payload={
                "refresh_token": refresh_token,
                "app_version": self.config.app_version,
            },
            context="refresh:" + hashlib.sha256(refresh_token.encode("utf-8")).hexdigest(),
            private_key_pem=material.private_key_pem,
        )
        access = str(result.get("access_token", ""))
        rotated = str(result.get("refresh_token", ""))
        if not access or not rotated or rotated == refresh_token:
            raise LicoraProtocolError(
                "INVALID_SERVER_RESPONSE", "Refresh response did not rotate both credentials."
            )
        claims = self.verify_access_token(
            access,
            expected_device_id=device_id,
            expected_device_fingerprint=material.public_key_fingerprint,
        )
        result["verified_claims"] = claims.raw
        return result

    def deactivate(
        self, *, access_token: str, device_id: str, private_key_pem: str
    ) -> dict[str, Any]:
        material = load_device_key_material(private_key_pem)
        claims = self.verify_access_token(
            access_token,
            expected_device_id=device_id,
            expected_device_fingerprint=material.public_key_fingerprint,
        )
        return self._post(
            path=self.config.deactivate_path,
            payload={},
            context=claims.jti,
            private_key_pem=material.private_key_pem,
            access_token=access_token,
        )
