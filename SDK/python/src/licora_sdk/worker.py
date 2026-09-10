"""Periodic background license validation without blocking application UI threads."""
from __future__ import annotations

import logging
import threading
from typing import TYPE_CHECKING, Callable

from .models import LicenseResult

if TYPE_CHECKING:
    from .manager import LicoraAuth


class LicenseValidationWorker:
    def __init__(
        self,
        auth: "LicoraAuth",
        *,
        interval_seconds: int,
        callback: Callable[[LicenseResult], None] | None = None,
        run_immediately: bool = False,
    ) -> None:
        if not 60 <= int(interval_seconds) <= 86400:
            raise ValueError("background validation interval must be 60-86400 seconds")
        self.auth = auth
        self.interval_seconds = int(interval_seconds)
        self.callback = callback
        self.run_immediately = bool(run_immediately)
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None

    @property
    def is_running(self) -> bool:
        return self._thread is not None and self._thread.is_alive()

    def start(self) -> None:
        if self.is_running:
            return
        self._stop.clear()
        self._thread = threading.Thread(
            target=self._run, name="licora-validation", daemon=True
        )
        self._thread.start()

    def _run(self) -> None:
        first = True
        while not self._stop.is_set():
            if (first and self.run_immediately) or not first:
                try:
                    result = self.auth.validate()
                    if self.callback is not None:
                        self.callback(result)
                except Exception:
                    logging.exception("Licora background validation failed")
            first = False
            if self._stop.wait(self.interval_seconds):
                break

    def trigger(self) -> None:
        """Schedule an early check by restarting the wait cycle immediately."""
        if not self.is_running:
            self.run_immediately = True
            self.start()
            return
        # A separate one-shot async validation avoids mutating the worker's stop event.
        self.auth.validate_async(callback=self.callback)

    def stop(self, timeout: float = 5.0) -> None:
        self._stop.set()
        thread = self._thread
        if thread is not None and thread is not threading.current_thread():
            thread.join(max(0.0, float(timeout)))
        self._thread = None
