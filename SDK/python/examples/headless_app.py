"""Framework-neutral Licora SDK example without command-line secret leakage."""
from __future__ import annotations

from getpass import getpass
import signal
import threading

from licora_sdk import LicoraAuth, LicenseResult


stopping = threading.Event()


def report(result: LicenseResult) -> None:
    print(f"[licora] {result.snapshot.status.value}: {result.code} — {result.message}")
    if not result.snapshot.authorized:
        stopping.set()


def main() -> int:
    auth = LicoraAuth(config_module="licensing_public")
    try:
        if not auth.is_licensed():
            license_key = getpass("Licora license key: ")
            result = auth.login(license_key)
            if not result.ok:
                report(result)
                return 1

        auth.start_background_validation(callback=report)
        signal.signal(signal.SIGINT, lambda *_: stopping.set())
        signal.signal(signal.SIGTERM, lambda *_: stopping.set())
        print("Licensed application service is running. Press Ctrl+C to stop.")
        while not stopping.wait(1):
            if not auth.is_licensed():
                return 2
        return 0
    finally:
        auth.close()


if __name__ == "__main__":
    raise SystemExit(main())
