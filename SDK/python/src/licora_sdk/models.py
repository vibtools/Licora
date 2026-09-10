"""Typed public results and private persisted session state."""
from __future__ import annotations

from dataclasses import asdict, dataclass
from enum import Enum
from typing import Any


class LicenseStatus(str, Enum):
    LOGGED_OUT = "logged_out"
    ACTIVE = "active"
    DEGRADED = "degraded"
    VALIDATING = "validating"
    INVALID = "invalid"
    EXPIRED = "expired"
    REVOKED = "revoked"
    ERROR = "error"
    CLOSED = "closed"


@dataclass(frozen=True)
class LicenseSnapshot:
    status: LicenseStatus
    authorized: bool
    code: str
    message: str
    app_id: str
    app_name: str
    user_reference: str
    device_id: str
    device_public_key_fingerprint: str
    license_expires_at: str | None
    access_expires_at: int
    refresh_expires_at: str | None
    last_online_check: int


@dataclass(frozen=True)
class LicenseResult:
    ok: bool
    code: str
    message: str
    snapshot: LicenseSnapshot


@dataclass
class StoredSession:
    schema_version: int = 1
    protocol: str = "licora-api-v2"
    api_base_url: str = ""
    app_id: str = ""
    app_name: str = ""
    device_id: str = ""
    device_private_key_pem: str = ""
    device_public_key_fingerprint: str = ""
    recovery_rotations: int = 0
    license_key: str = ""
    license_key_sha256: str = ""
    user_reference: str = ""
    access_token: str = ""
    refresh_token: str = ""
    access_expires_at: int = 0
    refresh_expires_at: str | None = None
    license_expires_at: str | None = None
    last_online_check: int = 0
    status: str = LicenseStatus.LOGGED_OUT.value
    code: str = "LOGGED_OUT"
    message: str = "No active license session."

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)

    @classmethod
    def from_dict(cls, value: dict[str, Any]) -> "StoredSession":
        if not isinstance(value, dict):
            raise ValueError("stored Licora session must be a JSON object")
        allowed = cls.__dataclass_fields__.keys()
        unknown = set(value) - set(allowed)
        if unknown:
            raise ValueError("stored Licora session contains unsupported fields")
        session = cls(**value)
        if session.schema_version != 1 or session.protocol != "licora-api-v2":
            raise ValueError("stored Licora session schema/protocol is unsupported")
        for name in (
            "api_base_url",
            "app_id",
            "app_name",
            "device_id",
            "device_private_key_pem",
            "device_public_key_fingerprint",
            "license_key",
            "license_key_sha256",
            "user_reference",
            "access_token",
            "refresh_token",
            "status",
            "code",
            "message",
        ):
            if not isinstance(getattr(session, name), str):
                raise ValueError(f"stored Licora session field {name} must be text")
        if not isinstance(session.access_expires_at, int) or not isinstance(
            session.last_online_check, int
        ):
            raise ValueError("stored Licora session timestamps must be integers")
        if not isinstance(session.recovery_rotations, int) or session.recovery_rotations < 0:
            raise ValueError("stored Licora recovery counter is invalid")
        if session.refresh_expires_at is not None and not isinstance(
            session.refresh_expires_at, str
        ):
            raise ValueError("stored refresh expiry must be text or null")
        if session.license_expires_at is not None and not isinstance(
            session.license_expires_at, str
        ):
            raise ValueError("stored license expiry must be text or null")
        return session
