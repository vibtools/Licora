"""Stable exceptions and error classification for the Licora SDK."""
from __future__ import annotations


class LicoraError(RuntimeError):
    """Base error with a stable machine-readable code."""

    def __init__(
        self,
        code: str,
        message: str,
        *,
        http_status: int | None = None,
        request_id: str = "",
    ) -> None:
        super().__init__(message)
        self.code = str(code or "LICORA_ERROR").upper()
        self.message = str(message or "Licora request failed.")
        self.http_status = http_status
        self.request_id = str(request_id or "")


class LicoraConfigurationError(LicoraError):
    """Public SDK configuration is missing, unsafe, or inconsistent."""


class LicoraProtocolError(LicoraError):
    """The server response or a signed access token violates the v2 contract."""


class LicoraStorageError(LicoraError):
    """Secure operating-system-backed session persistence failed."""


class LicenseRequiredError(LicoraError):
    """Raised by a guarded application function without an active license."""


TRANSIENT_ERROR_CODES = frozenset(
    {
        "NETWORK_ERROR",
        "INVALID_SERVER_RESPONSE",
        "RATE_LIMITED",
        "API_V2_NOT_READY",
        "INTERNAL_ERROR",
    }
)

TOKEN_ERROR_CODES = frozenset(
    {"TOKEN_EXPIRED", "TOKEN_NOT_YET_VALID", "INVALID_TOKEN"}
)

DEVICE_RECOVERY_CODES = frozenset(
    {"DEVICE_KEY_MISMATCH", "DEVICE_REVOKED", "INVALID_DEVICE_PROOF"}
)

AUTHORITATIVE_DENIAL_CODES = frozenset(
    {
        "INVALID_LICENSE",
        "LICENSE_EXPIRED",
        "LICENSE_INACTIVE",
        "INVALID_APP",
        "APP_NOT_ALLOWED",
        "APP_VERSION_UNSUPPORTED",
        "DEVICE_LIMIT_REACHED",
        "ACCESS_DENIED",
    }
)


def is_transient_error(code: str) -> bool:
    return str(code or "").upper() in TRANSIENT_ERROR_CODES
