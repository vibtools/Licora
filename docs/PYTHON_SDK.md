# Python Secure API v2 SDK

Licora v5.8.4 ships an optional, reusable Python 3.10+ public-client SDK under [`SDK/python`](../SDK/python). It follows the production integration contract used by VibraPilot while remaining framework-neutral.

## Integration contract

1. Create or enable the target application under **Admin → Client Apps**.
2. Assign licenses to that exact API v2 App ID.
3. Copy `SDK/python` into the Python application's repository and install it with `python -m pip install ./SDK/python`.
4. Copy `SDK/python/licensing_public.py` beside the application entry point.
5. Set the HTTPS server URL, exact App ID/name/version, signing key ID, RSA public key and public-key fingerprint.
6. Use `LicoraAuth` for login, restore, validation, background checks and logout.

Only public values belong in `licensing_public.py`. Never distribute an API v1 key, Admin License API key, updater key, server signing private key or a user license key in application source.

## Minimal use

```python
from licora_sdk import LicoraAuth, require_license

auth = LicoraAuth(config_module="licensing_public")
result = auth.login(user_entered_license_key)

@require_license(auth)
def open_paid_feature():
    ...

auth.start_background_validation(callback=handle_license_result)
```

GUI applications should use `login_async`, `validate_async` and `logout_async`, then marshal callbacks back onto the GUI event loop. The complete public API, failure semantics, storage behavior and headless/Tkinter examples are documented in [`SDK/python/README.md`](../SDK/python/README.md).

## Security boundary

The SDK implements the existing `/api/v2/activate.php`, `status.php`, `refresh.php` and `deactivate.php` protocol. It does not add or change a Licora endpoint. It generates a local P-256 device identity, signs the exact transmitted JSON, verifies pinned `LICORA-V2`/`RS256` tokens, stores session secrets through an OS-protected backend and never exposes secrets through status snapshots.
