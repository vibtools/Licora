"""Reusable, UI-safe Licora authentication and license-session manager."""
from __future__ import annotations

from concurrent.futures import Future, ThreadPoolExecutor
from datetime import datetime
import hashlib
import logging
import re
import secrets
import threading
import time
from typing import Callable
from zoneinfo import ZoneInfo

from .client import LicoraV2Client
from .config import LicoraConfig
from .crypto import generate_device_key_material, load_device_key_material
from .errors import (
    AUTHORITATIVE_DENIAL_CODES,
    DEVICE_RECOVERY_CODES,
    TOKEN_ERROR_CODES,
    LicoraError,
    LicoraProtocolError,
    LicoraStorageError,
    is_transient_error,
)
from .models import LicenseResult, LicenseSnapshot, LicenseStatus, StoredSession
from .storage import StateStore, default_state_store


_LICENSE_KEY_RE = re.compile(r"^[A-Z0-9]{8}(?:-[A-Z0-9]{8}){3}$")
ResultCallback = Callable[[LicenseResult], None]
ClientFactory = Callable[[LicoraConfig], LicoraV2Client]


class LicoraAuth:
    """Own the complete Licora v2 client lifecycle for one application.

    Network methods are synchronous and safe to call from a worker thread. GUI
    applications can use ``login_async``/``validate_async`` or the built-in
    background validator to avoid blocking their event loop.
    """

    def __init__(
        self,
        config: LicoraConfig | None = None,
        *,
        config_module: str = "licensing_public",
        state_store: StateStore | None = None,
        client_factory: ClientFactory | None = None,
    ) -> None:
        self.config = config or LicoraConfig.from_module(config_module)
        self.store = state_store or default_state_store(self.config)
        self._client_factory = client_factory or (lambda value: LicoraV2Client(value))
        self._client: LicoraV2Client | None = None
        self._state_lock = threading.RLock()
        self._network_lock = threading.Lock()
        self._logout_done = threading.Event()
        self._logout_done.set()
        self._generation = 0
        self._closed = False
        self._executor = ThreadPoolExecutor(
            max_workers=1, thread_name_prefix="licora-auth"
        )
        self._worker = None
        self._logout_threads: set[threading.Thread] = set()
        stored = self.store.load()
        self._session = stored or self._empty_session()
        self._validate_loaded_session()

    def _empty_session(self) -> StoredSession:
        return StoredSession(
            api_base_url=self.config.api_base_url,
            app_id=self.config.app_id,
            app_name=self.config.app_name,
        )

    def _validate_loaded_session(self) -> None:
        with self._state_lock:
            state = self._session
            if (
                state.api_base_url != self.config.api_base_url
                or state.app_id != self.config.app_id
            ):
                raise LicoraStorageError(
                    "LOCAL_STATE_APP_MISMATCH",
                    "Stored Licora session belongs to a different server or App ID.",
                )
            if state.device_private_key_pem:
                material = load_device_key_material(state.device_private_key_pem)
                if (
                    state.device_public_key_fingerprint
                    and state.device_public_key_fingerprint
                    != material.public_key_fingerprint
                ):
                    raise LicoraStorageError(
                        "LOCAL_STATE_INVALID", "Stored Licora device-key fingerprint mismatch."
                    )
                state.device_private_key_pem = material.private_key_pem
                state.device_public_key_fingerprint = material.public_key_fingerprint
            if state.license_key:
                expected = hashlib.sha256(state.license_key.encode("utf-8")).hexdigest()
                if state.license_key_sha256 not in {"", expected}:
                    raise LicoraStorageError(
                        "LOCAL_STATE_INVALID", "Stored Licora license-key digest mismatch."
                    )
                state.license_key_sha256 = expected
            if state.access_token and not self._local_token_valid_locked():
                state.access_token = ""
                state.access_expires_at = 0
                state.status = LicenseStatus.INVALID.value
                state.code = "TOKEN_EXPIRED"
                state.message = "Stored access token requires online renewal."
                self._persist_locked()

    def _ensure_open(self) -> None:
        if self._closed:
            raise LicoraError("SDK_CLOSED", "LicoraAuth is closed.")

    def _get_client(self) -> LicoraV2Client:
        with self._state_lock:
            if self._client is None:
                self._client = self._client_factory(self.config)
            return self._client

    def _persist_locked(self) -> None:
        self.store.save(self._session)

    def _ensure_identity_locked(self) -> None:
        if self._session.device_private_key_pem and self._session.device_id:
            material = load_device_key_material(self._session.device_private_key_pem)
        else:
            material = generate_device_key_material()
            self._session.device_id = secrets.token_hex(12)
        self._session.device_private_key_pem = material.private_key_pem
        self._session.device_public_key_fingerprint = material.public_key_fingerprint
        self._persist_locked()

    def _rotate_device_locked(self) -> None:
        self._session.device_id = secrets.token_hex(12)
        self._session.recovery_rotations += 1
        self._clear_tokens_locked()
        self._persist_locked()

    def _clear_tokens_locked(self) -> None:
        self._session.access_token = ""
        self._session.refresh_token = ""
        self._session.access_expires_at = 0
        self._session.refresh_expires_at = None

    def _clear_license_locked(self) -> None:
        self._clear_tokens_locked()
        self._session.license_key = ""
        self._session.license_key_sha256 = ""
        self._session.user_reference = ""
        self._session.license_expires_at = None
        self._session.last_online_check = 0

    def _local_token_valid_locked(self) -> bool:
        state = self._session
        if not (
            state.license_key
            and state.access_token
            and state.device_id
            and state.device_private_key_pem
        ):
            return False
        if hashlib.sha256(state.license_key.encode("utf-8")).hexdigest() != state.license_key_sha256:
            return False
        try:
            claims = self._get_client().verify_access_token(
                state.access_token,
                expected_device_id=state.device_id,
                expected_device_fingerprint=state.device_public_key_fingerprint,
            )
        except Exception:
            return False
        state.access_expires_at = claims.expires_at
        now = int(time.time())
        license_expiry = self._license_expiry_epoch_locked()
        return claims.expires_at > now and (license_expiry is None or license_expiry > now)

    def _license_expiry_epoch_locked(self) -> int | None:
        value = self._session.license_expires_at
        if not value:
            return None
        try:
            normalized = value.strip().replace("Z", "+00:00")
            parsed = datetime.fromisoformat(normalized)
            if parsed.tzinfo is None:
                parsed = parsed.replace(tzinfo=ZoneInfo(self.config.server_timezone))
            return int(parsed.timestamp())
        except Exception:
            return -1

    def _snapshot_locked(self) -> LicenseSnapshot:
        authorized = self._local_token_valid_locked()
        try:
            status = LicenseStatus(self._session.status)
        except ValueError:
            status = LicenseStatus.ERROR
        if not self._session.license_key:
            status = LicenseStatus.LOGGED_OUT
            authorized = False
        elif not authorized and status in {LicenseStatus.ACTIVE, LicenseStatus.DEGRADED}:
            status = LicenseStatus.EXPIRED
        return LicenseSnapshot(
            status=status,
            authorized=authorized,
            code=self._session.code,
            message=self._session.message,
            app_id=self.config.app_id,
            app_name=self.config.app_name,
            user_reference=self._session.user_reference,
            device_id=self._session.device_id,
            device_public_key_fingerprint=self._session.device_public_key_fingerprint,
            license_expires_at=self._session.license_expires_at,
            access_expires_at=self._session.access_expires_at,
            refresh_expires_at=self._session.refresh_expires_at,
            last_online_check=self._session.last_online_check,
        )

    def snapshot(self) -> LicenseSnapshot:
        with self._state_lock:
            return self._snapshot_locked()

    def is_licensed(self) -> bool:
        return self.snapshot().authorized

    def _result_locked(self, ok: bool, code: str, message: str) -> LicenseResult:
        self._session.code = code
        self._session.message = message
        return LicenseResult(ok, code, message, self._snapshot_locked())

    def _commit_online_locked(
        self,
        generation: int,
        *,
        access_token: str,
        refresh_token: str,
        access_expires_at: int,
        refresh_expires_at: str | None,
        license_expires_at: str | None,
        message: str,
    ) -> LicenseResult | None:
        if self._generation != generation:
            return None
        self._session.access_token = access_token
        self._session.refresh_token = refresh_token
        self._session.access_expires_at = access_expires_at
        self._session.refresh_expires_at = refresh_expires_at
        self._session.license_expires_at = license_expires_at or self._session.license_expires_at
        self._session.last_online_check = int(time.time())
        self._session.status = LicenseStatus.ACTIVE.value
        self._session.code = "OK"
        self._session.message = message
        self._persist_locked()
        return LicenseResult(True, "OK", message, self._snapshot_locked())

    def _error_result(self, generation: int, error: LicoraError) -> LicenseResult:
        with self._state_lock:
            if self._generation != generation:
                return self._result_locked(False, "SESSION_CHANGED", "License session changed locally.")
            if is_transient_error(error.code) and self._local_token_valid_locked():
                self._session.status = LicenseStatus.DEGRADED.value
                self._session.code = error.code
                self._session.message = (
                    "Online license check is temporarily unavailable; the verified access token remains valid."
                )
                self._persist_locked()
                return LicenseResult(
                    True,
                    error.code,
                    self._session.message,
                    self._snapshot_locked(),
                )
            if error.code in {"LICENSE_EXPIRED"}:
                status = LicenseStatus.EXPIRED
            elif error.code in DEVICE_RECOVERY_CODES:
                status = LicenseStatus.REVOKED
            elif error.code in AUTHORITATIVE_DENIAL_CODES:
                status = LicenseStatus.INVALID
            else:
                status = LicenseStatus.ERROR
            if error.code in AUTHORITATIVE_DENIAL_CODES or error.code in DEVICE_RECOVERY_CODES:
                self._clear_tokens_locked()
            self._session.status = status.value
            self._session.code = error.code
            self._session.message = error.message
            self._persist_locked()
            return LicenseResult(False, error.code, error.message, self._snapshot_locked())

    @staticmethod
    def _license_expiry(response: dict) -> str | None:
        license_info = response.get("license")
        if not isinstance(license_info, dict) or license_info.get("status") != "active":
            raise LicoraProtocolError(
                "INVALID_SERVER_RESPONSE", "Licora response omitted the active license state."
            )
        value = license_info.get("expires_at")
        if not isinstance(value, str) or not value.strip():
            raise LicoraProtocolError(
                "INVALID_SERVER_RESPONSE", "Licora response omitted the license expiry."
            )
        return value.strip()

    def login(self, license_key: str, user_reference: str = "") -> LicenseResult:
        self._ensure_open()
        normalized = str(license_key or "").strip().upper()
        if not _LICENSE_KEY_RE.fullmatch(normalized):
            with self._state_lock:
                return self._result_locked(
                    False, "INVALID_LICENSE_FORMAT", "Enter a valid Licora license key."
                )
        wait_seconds = self.config.connect_timeout_seconds + self.config.read_timeout_seconds + 5
        if not self._logout_done.wait(wait_seconds):
            with self._state_lock:
                return self._result_locked(
                    False,
                    "LOGOUT_IN_PROGRESS",
                    "Previous license logout is still finishing. Retry shortly.",
                )

        with self._network_lock:
            client = self._get_client()
            with self._state_lock:
                self._generation += 1
                generation = self._generation
                self._ensure_identity_locked()
                previous = StoredSession.from_dict(self._session.to_dict())
                same_license = previous.license_key == normalized and bool(previous.license_key)
                if not same_license:
                    self._clear_tokens_locked()
                    self._session.license_expires_at = None
                    self._session.last_online_check = 0
                self._session.license_key = normalized
                self._session.license_key_sha256 = hashlib.sha256(
                    normalized.encode("utf-8")
                ).hexdigest()
                self._session.user_reference = str(user_reference or "").strip()[:255]
                self._session.status = LicenseStatus.VALIDATING.value
                self._session.code = "VALIDATING"
                self._session.message = "Validating license with Licora."
                self._persist_locked()
                device_id = self._session.device_id
                private_key = self._session.device_private_key_pem
                fingerprint = self._session.device_public_key_fingerprint
                access = self._session.access_token if same_license else ""
                refresh = self._session.refresh_token if same_license else ""

            if (
                not same_license
                and previous.access_token
                and previous.device_private_key_pem
                and previous.device_id
            ):
                try:
                    client.deactivate(
                        access_token=previous.access_token,
                        device_id=previous.device_id,
                        private_key_pem=previous.device_private_key_pem,
                    )
                except Exception:
                    logging.info("Previous Licora session could not be deactivated.", exc_info=True)

            if access:
                try:
                    claims = client.verify_access_token(
                        access,
                        expected_device_id=device_id,
                        expected_device_fingerprint=fingerprint,
                    )
                    if (
                        refresh
                        and claims.expires_at
                        <= int(time.time()) + self.config.refresh_margin_seconds
                    ):
                        raise LicoraError(
                            "TOKEN_EXPIRED", "Access token is inside the proactive refresh window."
                        )
                    current = client.status(
                        access_token=access,
                        device_id=device_id,
                        private_key_pem=private_key,
                    )
                    with self._state_lock:
                        result = self._commit_online_locked(
                            generation,
                            access_token=access,
                            refresh_token=refresh,
                            access_expires_at=claims.expires_at,
                            refresh_expires_at=self._session.refresh_expires_at,
                            license_expires_at=self._license_expiry(current),
                            message="Secure license session verified.",
                        )
                    if result is not None:
                        return result
                    return self._session_changed_cleanup(client, access, device_id, private_key)
                except LicoraError as error:
                    if error.code not in TOKEN_ERROR_CODES | DEVICE_RECOVERY_CODES:
                        return self._error_result(generation, error)
                    with self._state_lock:
                        if self._generation != generation:
                            return self._result_locked(
                                False, "SESSION_CHANGED", "License session changed locally."
                            )
                        self._session.access_token = ""
                        self._session.access_expires_at = 0
                        if error.code in DEVICE_RECOVERY_CODES:
                            self._session.refresh_token = ""
                            self._session.refresh_expires_at = None
                            refresh = ""
                        self._persist_locked()
                    access = ""

            if refresh:
                try:
                    renewed = client.refresh(
                        refresh_token=refresh,
                        device_id=device_id,
                        private_key_pem=private_key,
                    )
                    new_access = str(renewed["access_token"])
                    new_refresh = str(renewed["refresh_token"])
                    claims = renewed["verified_claims"]
                    with self._state_lock:
                        stale_refresh = self._generation != generation
                        if not stale_refresh:
                            self._session.access_token = new_access
                            self._session.refresh_token = new_refresh
                            self._session.access_expires_at = int(claims["exp"])
                            self._session.refresh_expires_at = renewed.get("refresh_expires_at")
                            self._persist_locked()
                    if stale_refresh:
                        return self._session_changed_cleanup(
                            client, new_access, device_id, private_key
                        )
                except LicoraError as error:
                    with self._state_lock:
                        if self._generation != generation:
                            return self._result_locked(
                                False, "SESSION_CHANGED", "License session changed locally."
                            )
                        self._clear_tokens_locked()
                        self._persist_locked()
                    if error.code in AUTHORITATIVE_DENIAL_CODES:
                        return self._error_result(generation, error)
                else:
                    try:
                        current = client.status(
                            access_token=new_access,
                            device_id=device_id,
                            private_key_pem=private_key,
                        )
                        with self._state_lock:
                            result = self._commit_online_locked(
                                generation,
                                access_token=new_access,
                                refresh_token=new_refresh,
                                access_expires_at=int(claims["exp"]),
                                refresh_expires_at=renewed.get("refresh_expires_at"),
                                license_expires_at=self._license_expiry(current),
                                message="Secure license session refreshed.",
                            )
                        if result is not None:
                            return result
                        return self._session_changed_cleanup(
                            client, new_access, device_id, private_key
                        )
                    except LicoraError as error:
                        if is_transient_error(error.code):
                            return self._error_result(generation, error)
                        with self._state_lock:
                            if self._generation == generation:
                                self._clear_tokens_locked()
                                self._persist_locked()
                        if error.code in AUTHORITATIVE_DENIAL_CODES:
                            return self._error_result(generation, error)

            def activate(active_device_id: str) -> dict:
                return client.activate(
                    license_key=normalized,
                    device_id=active_device_id,
                    private_key_pem=private_key,
                )

            try:
                try:
                    activated = activate(device_id)
                except LicoraError as first_error:
                    if first_error.code not in {"DEVICE_KEY_MISMATCH", "DEVICE_REVOKED"}:
                        raise
                    with self._state_lock:
                        if self._generation != generation:
                            return self._result_locked(
                                False, "SESSION_CHANGED", "License session changed locally."
                            )
                        self._rotate_device_locked()
                        device_id = self._session.device_id
                    activated = activate(device_id)
                new_access = str(activated["access_token"])
                new_refresh = str(activated["refresh_token"])
                claims = activated["verified_claims"]
                with self._state_lock:
                    result = self._commit_online_locked(
                        generation,
                        access_token=new_access,
                        refresh_token=new_refresh,
                        access_expires_at=int(claims["exp"]),
                        refresh_expires_at=activated.get("refresh_expires_at"),
                        license_expires_at=self._license_expiry(activated),
                        message="License activated securely.",
                    )
                if result is not None:
                    return result
                return self._session_changed_cleanup(
                    client, new_access, device_id, private_key
                )
            except LicoraError as error:
                return self._error_result(generation, error)
            except Exception:
                logging.exception("Unexpected Licora validation failure")
                return self._error_result(
                    generation,
                    LicoraError("UNEXPECTED_ERROR", "License validation failed unexpectedly."),
                )

    def _session_changed_cleanup(
        self,
        client: LicoraV2Client,
        access_token: str,
        device_id: str,
        private_key_pem: str,
    ) -> LicenseResult:
        try:
            client.deactivate(
                access_token=access_token,
                device_id=device_id,
                private_key_pem=private_key_pem,
            )
        except Exception:
            logging.info("Stale Licora activation cleanup failed.", exc_info=True)
        with self._state_lock:
            return self._result_locked(
                False, "SESSION_CHANGED", "License session changed locally."
            )

    def validate(self) -> LicenseResult:
        self._ensure_open()
        with self._state_lock:
            license_key = self._session.license_key
            user_reference = self._session.user_reference
            if not license_key:
                return self._result_locked(
                    False, "NOT_LOGGED_IN", "No license is available for validation."
                )
        return self.login(license_key, user_reference)

    def logout(self, *, wait: bool = False) -> LicenseResult:
        self._ensure_open()
        with self._state_lock:
            self._generation += 1
            generation = self._generation
            previous = StoredSession.from_dict(self._session.to_dict())
            self._clear_license_locked()
            self._session.status = LicenseStatus.LOGGED_OUT.value
            self._session.code = "LOGGED_OUT"
            self._session.message = "License session cleared locally."
            self._persist_locked()
            needs_remote = bool(
                previous.access_token
                and previous.device_id
                and previous.device_private_key_pem
            )
            if needs_remote:
                self._logout_done.clear()

        immediate = LicenseResult(True, "LOGGED_OUT", "License session cleared locally.", self.snapshot())
        if not needs_remote:
            self._logout_done.set()
            return immediate

        def remote_logout() -> LicenseResult:
            deactivated = False
            try:
                with self._network_lock:
                    self._get_client().deactivate(
                        access_token=previous.access_token,
                        device_id=previous.device_id,
                        private_key_pem=previous.device_private_key_pem,
                    )
                    deactivated = True
            except Exception:
                logging.info("Licora remote deactivation did not complete.", exc_info=True)
            finally:
                with self._state_lock:
                    if (
                        deactivated
                        and self._generation == generation
                        and not self._session.license_key
                    ):
                        self._rotate_device_locked()
                    if (
                        deactivated
                        and self._generation == generation
                        and not self._session.license_key
                    ):
                        self._session.message = "License session deactivated and cleared."
                        self._persist_locked()
                        result = LicenseResult(
                            True, "LOGGED_OUT", self._session.message, self._snapshot_locked()
                        )
                    else:
                        result = immediate
                self._logout_done.set()
            return result

        if wait:
            return remote_logout()

        def run_and_discard() -> None:
            try:
                remote_logout()
            finally:
                with self._state_lock:
                    self._logout_threads.discard(threading.current_thread())

        thread = threading.Thread(
            target=run_and_discard, name="licora-logout", daemon=True
        )
        with self._state_lock:
            self._logout_threads.add(thread)
        thread.start()
        return immediate

    @staticmethod
    def _attach_callback(future: Future[LicenseResult], callback: ResultCallback | None) -> None:
        if callback is None:
            return

        def deliver(done: Future[LicenseResult]) -> None:
            try:
                callback(done.result())
            except Exception:
                logging.exception("Licora asynchronous callback failed")

        future.add_done_callback(deliver)

    def login_async(
        self,
        license_key: str,
        user_reference: str = "",
        *,
        callback: ResultCallback | None = None,
    ) -> Future[LicenseResult]:
        self._ensure_open()
        future = self._executor.submit(self.login, license_key, user_reference)
        self._attach_callback(future, callback)
        return future

    def validate_async(
        self, *, callback: ResultCallback | None = None
    ) -> Future[LicenseResult]:
        self._ensure_open()
        future = self._executor.submit(self.validate)
        self._attach_callback(future, callback)
        return future

    def logout_async(
        self, *, callback: ResultCallback | None = None
    ) -> Future[LicenseResult]:
        self._ensure_open()
        future = self._executor.submit(self.logout, wait=True)
        self._attach_callback(future, callback)
        return future

    def start_background_validation(
        self,
        *,
        interval_seconds: int | None = None,
        callback: ResultCallback | None = None,
        run_immediately: bool = False,
    ):
        self._ensure_open()
        from .worker import LicenseValidationWorker

        with self._state_lock:
            if self._worker is not None and self._worker.is_running:
                return self._worker
            self._worker = LicenseValidationWorker(
                self,
                interval_seconds=interval_seconds or self.config.background_check_seconds,
                callback=callback,
                run_immediately=run_immediately,
            )
            self._worker.start()
            return self._worker

    def stop_background_validation(self, timeout: float = 5.0) -> None:
        with self._state_lock:
            worker = self._worker
            self._worker = None
        if worker is not None:
            worker.stop(timeout=timeout)

    def close(self) -> None:
        if self._closed:
            return
        self.stop_background_validation()
        self._closed = True
        self._executor.shutdown(wait=False, cancel_futures=True)
        with self._state_lock:
            client = self._client
            self._client = None
        if client is not None:
            client.close()

    def __enter__(self) -> "LicoraAuth":
        return self

    def __exit__(self, *_: object) -> None:
        self.close()


LicenseManager = LicoraAuth
