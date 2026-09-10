"""Cryptographic primitives matching Licora Secure API v2 exactly."""
from __future__ import annotations

import base64
from dataclasses import dataclass
import hashlib
import json
import time
from typing import Any

from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec, padding, rsa

from .config import LicoraConfig
from .errors import LicoraProtocolError


@dataclass(frozen=True)
class DeviceKeyMaterial:
    private_key_pem: str
    public_key_pem: str
    public_key_fingerprint: str


@dataclass(frozen=True)
class AccessTokenClaims:
    raw: dict[str, Any]

    @property
    def jti(self) -> str:
        return str(self.raw["jti"])

    @property
    def expires_at(self) -> int:
        return int(self.raw["exp"])


def b64url_encode(value: bytes) -> str:
    return base64.urlsafe_b64encode(value).rstrip(b"=").decode("ascii")


def b64url_decode(value: str) -> bytes:
    if not value or any(
        char not in "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_"
        for char in value
    ):
        raise LicoraProtocolError("INVALID_TOKEN", "Token encoding is invalid.")
    padded = value + "=" * ((4 - len(value) % 4) % 4)
    try:
        return base64.b64decode(
            padded.replace("-", "+").replace("_", "/"), validate=True
        )
    except Exception as exc:
        raise LicoraProtocolError("INVALID_TOKEN", "Token encoding is invalid.") from exc


def compact_json(value: dict[str, Any]) -> bytes:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":")).encode("utf-8")


def canonical_request(
    method: str,
    path: str,
    timestamp: int,
    nonce: str,
    body: bytes,
    context: str,
) -> bytes:
    return "\n".join(
        (
            method.upper(),
            path,
            str(timestamp),
            nonce,
            hashlib.sha256(body).hexdigest().lower(),
            context,
        )
    ).encode("utf-8")


def _public_pem(key: ec.EllipticCurvePublicKey) -> str:
    return key.public_bytes(
        serialization.Encoding.PEM,
        serialization.PublicFormat.SubjectPublicKeyInfo,
    ).decode("ascii")


def generate_device_key_material() -> DeviceKeyMaterial:
    key = ec.generate_private_key(ec.SECP256R1())
    private_pem = key.private_bytes(
        serialization.Encoding.PEM,
        serialization.PrivateFormat.PKCS8,
        serialization.NoEncryption(),
    ).decode("ascii")
    public_pem = _public_pem(key.public_key())
    return DeviceKeyMaterial(
        private_key_pem=private_pem,
        public_key_pem=public_pem,
        public_key_fingerprint=hashlib.sha256(public_pem.encode("ascii")).hexdigest(),
    )


def load_device_key_material(private_key_pem: str) -> DeviceKeyMaterial:
    try:
        key = serialization.load_pem_private_key(private_key_pem.encode("ascii"), None)
    except Exception as exc:
        raise LicoraProtocolError("INVALID_DEVICE_KEY", "Stored device key is invalid.") from exc
    if not isinstance(key, ec.EllipticCurvePrivateKey) or not isinstance(
        key.curve, ec.SECP256R1
    ):
        raise LicoraProtocolError("INVALID_DEVICE_KEY", "Stored device key is not P-256.")
    normalized_private = key.private_bytes(
        serialization.Encoding.PEM,
        serialization.PrivateFormat.PKCS8,
        serialization.NoEncryption(),
    ).decode("ascii")
    public_pem = _public_pem(key.public_key())
    return DeviceKeyMaterial(
        private_key_pem=normalized_private,
        public_key_pem=public_pem,
        public_key_fingerprint=hashlib.sha256(public_pem.encode("ascii")).hexdigest(),
    )


def load_device_private_key(private_key_pem: str) -> ec.EllipticCurvePrivateKey:
    material = load_device_key_material(private_key_pem)
    key = serialization.load_pem_private_key(material.private_key_pem.encode("ascii"), None)
    if not isinstance(key, ec.EllipticCurvePrivateKey):
        raise LicoraProtocolError("INVALID_DEVICE_KEY", "Stored device key is invalid.")
    return key


class AccessTokenVerifier:
    def __init__(self, config: LicoraConfig) -> None:
        self._config = config
        try:
            key = serialization.load_pem_public_key(
                config.signing_public_key_pem.encode("ascii")
            )
        except Exception as exc:
            raise LicoraProtocolError(
                "INVALID_CONFIGURATION", "Pinned Licora public key is invalid."
            ) from exc
        if not isinstance(key, rsa.RSAPublicKey) or key.key_size < 3072:
            raise LicoraProtocolError(
                "INVALID_CONFIGURATION",
                "Pinned Licora signing key must be RSA-3072 or stronger.",
            )
        self._key = key

    def verify(
        self,
        token: str,
        *,
        expected_device_id: str | None = None,
        expected_device_fingerprint: str | None = None,
        now: int | None = None,
    ) -> AccessTokenClaims:
        parts = token.split(".")
        if len(parts) != 3:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token structure is invalid.")
        try:
            header = json.loads(b64url_decode(parts[0]).decode("utf-8"))
            payload = json.loads(b64url_decode(parts[1]).decode("utf-8"))
            signature = b64url_decode(parts[2])
        except LicoraProtocolError:
            raise
        except Exception as exc:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token JSON is invalid.") from exc
        if not isinstance(header, dict) or not isinstance(payload, dict):
            raise LicoraProtocolError("INVALID_TOKEN", "Access token payload is invalid.")
        if (
            header.get("typ") != "LICORA-V2"
            or header.get("alg") != "RS256"
            or header.get("kid") != self._config.signing_key_id
        ):
            raise LicoraProtocolError("INVALID_TOKEN", "Access token header is invalid.")
        try:
            self._key.verify(
                signature,
                (parts[0] + "." + parts[1]).encode("ascii"),
                padding.PKCS1v15(),
                hashes.SHA256(),
            )
        except InvalidSignature as exc:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token signature is invalid.") from exc
        except Exception as exc:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token verification failed.") from exc

        required = {
            "iss",
            "aud",
            "app_id",
            "license_id",
            "device_id",
            "device_credential_id",
            "device_key_fingerprint",
            "iat",
            "nbf",
            "exp",
            "jti",
            "token_version",
        }
        if not required.issubset(payload):
            raise LicoraProtocolError("INVALID_TOKEN", "Access token claims are incomplete.")
        if payload.get("iss") != "licora" or int(payload.get("token_version", 0)) != 2:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token issuer/version is invalid.")
        if payload.get("aud") != self._config.app_id or payload.get("app_id") != self._config.app_id:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token audience is invalid.")
        if expected_device_id is not None and payload.get("device_id") != expected_device_id:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token device is invalid.")
        if (
            expected_device_fingerprint is not None
            and payload.get("device_key_fingerprint") != expected_device_fingerprint
        ):
            raise LicoraProtocolError("INVALID_TOKEN", "Access token device key is invalid.")
        if not isinstance(payload.get("jti"), str) or not payload["jti"]:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token JTI is invalid.")
        try:
            iat, nbf, exp = (int(payload[name]) for name in ("iat", "nbf", "exp"))
        except Exception as exc:
            raise LicoraProtocolError("INVALID_TOKEN", "Access token timestamps are invalid.") from exc
        current = int(time.time()) if now is None else int(now)
        skew = self._config.clock_skew_seconds
        if iat > current + skew or nbf > current + skew:
            raise LicoraProtocolError("TOKEN_NOT_YET_VALID", "Access token is not yet valid.")
        if exp <= current:
            raise LicoraProtocolError("TOKEN_EXPIRED", "Access token has expired.")
        return AccessTokenClaims(dict(payload))
