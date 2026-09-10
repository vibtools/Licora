"""Complete Tkinter login shell using LicoraAuth without blocking the UI."""
from __future__ import annotations

import tkinter as tk
from tkinter import ttk

from licora_sdk import LicoraAuth, LicenseResult


class LicenseWindow(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.title("License Login")
        self.geometry("480x230")
        self.resizable(False, False)
        self.auth = LicoraAuth(config_module="licensing_public")
        self.protocol("WM_DELETE_WINDOW", self.close)

        frame = ttk.Frame(self, padding=24)
        frame.pack(fill="both", expand=True)
        ttk.Label(frame, text=self.auth.config.app_name, font=("Segoe UI", 16, "bold")).pack(anchor="w")
        ttk.Label(frame, text="Enter your Licora license key").pack(anchor="w", pady=(8, 4))
        self.key = ttk.Entry(frame, width=48, show="•")
        self.key.pack(fill="x")
        self.status = tk.StringVar(value="Ready")
        ttk.Label(frame, textvariable=self.status, wraplength=420).pack(anchor="w", pady=10)
        self.login_button = ttk.Button(frame, text="Activate", command=self.login)
        self.login_button.pack(anchor="e")

        if self.auth.is_licensed():
            self.open_application()
        self.auth.start_background_validation(callback=self.from_worker)

    def login(self) -> None:
        self.login_button.configure(state="disabled")
        self.status.set("Validating license…")
        self.auth.login_async(self.key.get(), callback=self.from_worker)

    def from_worker(self, result: LicenseResult) -> None:
        self.after(0, lambda: self.apply_result(result))

    def apply_result(self, result: LicenseResult) -> None:
        self.status.set(f"{result.code}: {result.message}")
        self.login_button.configure(state="normal")
        if result.snapshot.authorized:
            self.open_application()

    def open_application(self) -> None:
        self.status.set("License active. Open your application's main window here.")

    def close(self) -> None:
        self.auth.close()
        self.destroy()


if __name__ == "__main__":
    LicenseWindow().mainloop()
