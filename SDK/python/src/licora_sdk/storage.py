"""Fail-closed operating-system-backed storage for Licora session secrets."""
from __future__ import annotations

from abc import ABC, abstractmethod
import base64
import ctypes
from ctypes import wintypes
import hashlib
import json
import os
from pathlib import Path
import threading
from typing import Any

from .config import LicoraConfig
from .errors import LicoraStorageError
from .models import StoredSession


_DPAPI_HEADER = b"LICORA-SDK-DPAPI-V1\n"


class StateStore(ABC):
    """Persistence interface; custom applications may inject an equivalent store."""

    @abstractmethod
    def load(self) -> StoredSession | None:
        raise NotImplementedError

    @abstractmethod
    def save(self, session: StoredSession) -> None:
        raise NotImplementedError

    @abstractmethod
    def clear(self) -> None:
        raise NotImplementedError


def _serialize(session: StoredSession) -> str:
    return json.dumps(
        session.to_dict(), ensure_ascii=False, separators=(",", ":"), sort_keys=True
    )


def _deserialize(value: str) -> StoredSession:
    try:
        parsed = json.loads(value)
        return StoredSession.from_dict(parsed)
    except Exception as exc:
        raise LicoraStorageError(
            "LOCAL_STATE_INVALID", "Stored Licora session is corrupt or incompatible."
        ) from exc


def _atomic_write(path: Path, data: bytes) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name(
        f"{path.name}.tmp.{os.getpid()}.{threading.get_ident()}"
    )
    try:
        with temporary.open("wb") as handle:
            if os.name != "nt":
                os.chmod(temporary, 0o600)
            handle.write(data)
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temporary, path)
        if os.name != "nt":
            os.chmod(path, 0o600)
    except Exception as exc:
        try:
            temporary.unlink(missing_ok=True)
        except OSError:
            pass
        raise LicoraStorageError(
            "LOCAL_PERSISTENCE_ERROR", "Unable to persist the Licora session securely."
        ) from exc


class _DataBlob(ctypes.Structure):
    _fields_ = [
        ("cbData", wintypes.DWORD),
        ("pbData", ctypes.POINTER(ctypes.c_ubyte)),
    ]


def _dpapi_crypt(value: bytes, *, decrypt: bool) -> bytes:
    if os.name != "nt":
        raise LicoraStorageError(
            "SECURE_STORAGE_UNAVAILABLE", "Windows DPAPI is unavailable on this platform."
        )
    source = ctypes.create_string_buffer(value)
    input_blob = _DataBlob(
        len(value), ctypes.cast(source, ctypes.POINTER(ctypes.c_ubyte))
    )
    output_blob = _DataBlob()
    crypt32 = ctypes.windll.crypt32
    kernel32 = ctypes.windll.kernel32
    kernel32.LocalFree.argtypes = [ctypes.c_void_p]
    kernel32.LocalFree.restype = ctypes.c_void_p
    flags = 0x01  # CRYPTPROTECT_UI_FORBIDDEN
    if decrypt:
        crypt32.CryptUnprotectData.argtypes = [
            ctypes.POINTER(_DataBlob),
            ctypes.POINTER(wintypes.LPWSTR),
            ctypes.POINTER(_DataBlob),
            ctypes.c_void_p,
            ctypes.c_void_p,
            wintypes.DWORD,
            ctypes.POINTER(_DataBlob),
        ]
        crypt32.CryptUnprotectData.restype = wintypes.BOOL
        ok = crypt32.CryptUnprotectData(
            ctypes.byref(input_blob), None, None, None, None, flags, ctypes.byref(output_blob)
        )
    else:
        crypt32.CryptProtectData.argtypes = [
            ctypes.POINTER(_DataBlob),
            wintypes.LPCWSTR,
            ctypes.POINTER(_DataBlob),
            ctypes.c_void_p,
            ctypes.c_void_p,
            wintypes.DWORD,
            ctypes.POINTER(_DataBlob),
        ]
        crypt32.CryptProtectData.restype = wintypes.BOOL
        ok = crypt32.CryptProtectData(
            ctypes.byref(input_blob),
            "Licora Secure API v2 Session",
            None,
            None,
            None,
            flags,
            ctypes.byref(output_blob),
        )
    if not ok:
        error = ctypes.get_last_error()
        raise LicoraStorageError(
            "SECURE_STORAGE_ERROR", f"Windows DPAPI operation failed ({error})."
        )
    try:
        return ctypes.string_at(output_blob.pbData, output_blob.cbData)
    finally:
        kernel32.LocalFree(output_blob.pbData)


class WindowsDpapiStateStore(StateStore):
    """Current-Windows-user encrypted, atomic local state file."""

    def __init__(self, path: Path) -> None:
        self.path = Path(path).expanduser().resolve()
        self._lock = threading.RLock()

    def load(self) -> StoredSession | None:
        with self._lock:
            if not self.path.is_file():
                return None
            try:
                payload = self.path.read_bytes()
                if not payload.startswith(_DPAPI_HEADER):
                    raise ValueError("invalid DPAPI state header")
                protected = base64.b64decode(
                    payload[len(_DPAPI_HEADER) :], validate=True
                )
                return _deserialize(_dpapi_crypt(protected, decrypt=True).decode("utf-8"))
            except LicoraStorageError:
                raise
            except Exception as exc:
                raise LicoraStorageError(
                    "LOCAL_STATE_INVALID",
                    "Stored Licora session cannot be decrypted by this Windows user.",
                ) from exc

    def save(self, session: StoredSession) -> None:
        with self._lock:
            raw = _serialize(session).encode("utf-8")
            protected = _dpapi_crypt(raw, decrypt=False)
            _atomic_write(self.path, _DPAPI_HEADER + base64.b64encode(protected))

    def clear(self) -> None:
        with self._lock:
            try:
                self.path.unlink(missing_ok=True)
            except OSError as exc:
                raise LicoraStorageError(
                    "LOCAL_PERSISTENCE_ERROR", "Unable to remove the Licora session."
                ) from exc


class KeyringStateStore(StateStore):
    """macOS/Linux credential-vault state using the system keyring backend."""

    def __init__(self, service_name: str, account_name: str = "session") -> None:
        try:
            import keyring
            from keyring.errors import KeyringError
        except Exception as exc:
            raise LicoraStorageError(
                "SECURE_STORAGE_UNAVAILABLE",
                "Install a supported system keyring backend before using Licora.",
            ) from exc
        backend = keyring.get_keyring()
        if float(getattr(backend, "priority", 0) or 0) <= 0:
            raise LicoraStorageError(
                "SECURE_STORAGE_UNAVAILABLE",
                "No secure operating-system keyring backend is available.",
            )
        self._keyring = keyring
        self._keyring_error = KeyringError
        self.service_name = service_name
        self.account_name = account_name
        self._lock = threading.RLock()

    def load(self) -> StoredSession | None:
        with self._lock:
            try:
                value = self._keyring.get_password(self.service_name, self.account_name)
            except self._keyring_error as exc:
                raise LicoraStorageError(
                    "SECURE_STORAGE_ERROR", "Unable to read the Licora system-keyring session."
                ) from exc
            return _deserialize(value) if value else None

    def save(self, session: StoredSession) -> None:
        with self._lock:
            try:
                self._keyring.set_password(
                    self.service_name, self.account_name, _serialize(session)
                )
            except self._keyring_error as exc:
                raise LicoraStorageError(
                    "SECURE_STORAGE_ERROR", "Unable to write the Licora system-keyring session."
                ) from exc

    def clear(self) -> None:
        with self._lock:
            try:
                self._keyring.delete_password(self.service_name, self.account_name)
            except self._keyring.errors.PasswordDeleteError:
                return
            except self._keyring_error as exc:
                raise LicoraStorageError(
                    "SECURE_STORAGE_ERROR", "Unable to remove the Licora system-keyring session."
                ) from exc


class MemoryStateStore(StateStore):
    """Non-persistent store for automated tests and explicitly ephemeral tools."""

    def __init__(self) -> None:
        self._value: dict[str, Any] | None = None
        self._lock = threading.RLock()

    def load(self) -> StoredSession | None:
        with self._lock:
            return StoredSession.from_dict(dict(self._value)) if self._value else None

    def save(self, session: StoredSession) -> None:
        with self._lock:
            self._value = session.to_dict()

    def clear(self) -> None:
        with self._lock:
            self._value = None


def default_state_store(config: LicoraConfig) -> StateStore:
    """Return a secure platform store; never silently fall back to plaintext."""
    identity = hashlib.sha256(
        f"{config.api_base_url}\n{config.app_id}".encode("utf-8")
    ).hexdigest()[:16]
    if os.name == "nt":
        base = os.environ.get("LOCALAPPDATA") or os.environ.get("APPDATA")
        if not base:
            raise LicoraStorageError(
                "SECURE_STORAGE_UNAVAILABLE", "Windows application-data directory is unavailable."
            )
        path = Path(base) / config.app_name / "Licora" / f"session-{identity}.bin"
        return WindowsDpapiStateStore(path)
    service = f"Licora SDK:{config.app_id}:{identity}"
    return KeyringStateStore(service)
