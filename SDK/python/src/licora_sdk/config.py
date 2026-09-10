"""Validated public configuration loader for Licora Secure API v2."""
from __future__ import annotations

from dataclasses import dataclass
import hashlib
import importlib
import re
from types import ModuleType
from typing import Any, Mapping
from urllib.parse import urlparse
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from .errors import LicoraConfigurationError


_APP_ID_RE = re.compile(r"^[a-z0-9][a-z0-9._-]{1,118}[a-z0-9]$")
_VERSION_RE = re.compile(r"^[0-9A-Za-z][0-9A-Za-z._+-]{0,63}$")
_SHA256_RE = re.compile(r"^[0-9a-f]{64}$")
_PLACEHOLDER_IDS = {"replace-with-your-app-id", "change-me", "your-app-id"}


def _text(values: Mapping[str, Any], name: str) -> str:
    value = values.get(name)
    if not isinstance(value, str) or not value.strip():
        raise LicoraConfigurationError(
            "INVALID_CONFIGURATION", f"{name} must be a non-empty string."
        )
    return value.strip()


def _number(
    values: Mapping[str, Any], name: str, *, minimum: float, maximum: float
) -> float:
    value = values.get(name)
    if isinstance(value, bool):
        raise LicoraConfigurationError("INVALID_CONFIGURATION", f"{name} is invalid.")
    try:
        parsed = float(value)
    except (TypeError, ValueError) as exc:
        raise LicoraConfigurationError(
            "INVALID_CONFIGURATION", f"{name} must be numeric."
        ) from exc
    if not minimum <= parsed <= maximum:
        raise LicoraConfigurationError(
            "INVALID_CONFIGURATION",
            f"{name} must be between {minimum:g} and {maximum:g}.",
        )
    return parsed


def _endpoint(values: Mapping[str, Any], name: str) -> str:
    path = _text(values, name)
    parsed = urlparse(path)
    if (
        not path.startswith("/api/v2/")
        or not path.endswith(".php")
        or parsed.scheme
        or parsed.netloc
        or parsed.query
        or parsed.fragment
    ):
        raise LicoraConfigurationError(
            "INVALID_CONFIGURATION",
            f"{name} must be an absolute Licora /api/v2/*.php path.",
        )
    return path


@dataclass(frozen=True)
class LicoraConfig:
    api_base_url: str
    api_version: int
    protocol: str
    app_id: str
    app_name: str
    app_version: str
    activate_path: str
    status_path: str
    refresh_path: str
    deactivate_path: str
    signing_key_id: str
    signing_public_key_pem: str
    signing_public_key_sha256: str
    server_timezone: str
    clock_skew_seconds: int
    connect_timeout_seconds: float
    read_timeout_seconds: float
    background_check_seconds: int
    refresh_margin_seconds: int
    max_response_bytes: int

    @classmethod
    def from_module(cls, module: str | ModuleType = "licensing_public") -> "LicoraConfig":
        source = importlib.import_module(module) if isinstance(module, str) else module
        values = {name: getattr(source, name) for name in dir(source) if name.startswith("LICORA_")}
        return cls.from_mapping(values)

    @classmethod
    def from_mapping(cls, values: Mapping[str, Any]) -> "LicoraConfig":
        base_url = _text(values, "LICORA_API_BASE_URL").rstrip("/")
        parsed = urlparse(base_url)
        if (
            parsed.scheme != "https"
            or not parsed.netloc
            or parsed.username
            or parsed.password
            or parsed.query
            or parsed.fragment
        ):
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION",
                "LICORA_API_BASE_URL must be an absolute HTTPS URL without credentials, query, or fragment.",
            )

        try:
            api_version = int(values.get("LICORA_API_VERSION"))
        except (TypeError, ValueError) as exc:
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION", "LICORA_API_VERSION must be 2."
            ) from exc
        protocol = _text(values, "LICORA_PROTOCOL")
        if api_version != 2 or protocol != "licora-api-v2":
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION", "The SDK supports only Licora Secure API v2."
            )

        app_id = _text(values, "LICORA_APP_ID").lower()
        if app_id in _PLACEHOLDER_IDS or not _APP_ID_RE.fullmatch(app_id):
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION",
                "Set LICORA_APP_ID to the exact active App ID created in Licora.",
            )
        app_name = _text(values, "LICORA_APP_NAME")
        app_version = _text(values, "LICORA_APP_VERSION")
        if not _VERSION_RE.fullmatch(app_version):
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION", "LICORA_APP_VERSION has an invalid format."
            )

        pem = _text(values, "LICORA_SIGNING_PUBLIC_KEY_PEM") + "\n"
        if "PRIVATE KEY" in pem or "-----BEGIN PUBLIC KEY-----" not in pem:
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION",
                "LICORA_SIGNING_PUBLIC_KEY_PEM must contain only the server public key.",
            )
        fingerprint = _text(values, "LICORA_SIGNING_PUBLIC_KEY_SHA256").lower()
        if not _SHA256_RE.fullmatch(fingerprint):
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION",
                "LICORA_SIGNING_PUBLIC_KEY_SHA256 must be a 64-character SHA-256 digest.",
            )
        if hashlib.sha256(pem.encode("ascii")).hexdigest() != fingerprint:
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION", "Pinned Licora signing-key fingerprint mismatch."
            )

        server_timezone = _text(values, "LICORA_SERVER_TIMEZONE")
        try:
            ZoneInfo(server_timezone)
        except ZoneInfoNotFoundError as exc:
            raise LicoraConfigurationError(
                "INVALID_CONFIGURATION", "LICORA_SERVER_TIMEZONE is not a valid IANA timezone."
            ) from exc

        return cls(
            api_base_url=base_url,
            api_version=api_version,
            protocol=protocol,
            app_id=app_id,
            app_name=app_name,
            app_version=app_version,
            activate_path=_endpoint(values, "LICORA_ACTIVATE_PATH"),
            status_path=_endpoint(values, "LICORA_STATUS_PATH"),
            refresh_path=_endpoint(values, "LICORA_REFRESH_PATH"),
            deactivate_path=_endpoint(values, "LICORA_DEACTIVATE_PATH"),
            signing_key_id=_text(values, "LICORA_SIGNING_KEY_ID"),
            signing_public_key_pem=pem,
            signing_public_key_sha256=fingerprint,
            server_timezone=server_timezone,
            clock_skew_seconds=int(
                _number(values, "LICORA_CLOCK_SKEW_SECONDS", minimum=30, maximum=3600)
            ),
            connect_timeout_seconds=_number(
                values, "LICORA_CONNECT_TIMEOUT_SECONDS", minimum=1, maximum=120
            ),
            read_timeout_seconds=_number(
                values, "LICORA_READ_TIMEOUT_SECONDS", minimum=1, maximum=300
            ),
            background_check_seconds=int(
                _number(values, "LICORA_BACKGROUND_CHECK_SECONDS", minimum=60, maximum=86400)
            ),
            refresh_margin_seconds=int(
                _number(values, "LICORA_REFRESH_MARGIN_SECONDS", minimum=15, maximum=3600)
            ),
            max_response_bytes=int(
                _number(values, "LICORA_MAX_RESPONSE_BYTES", minimum=4096, maximum=8388608)
            ),
        )
