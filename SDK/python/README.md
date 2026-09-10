# Licora Python SDK

Ready-to-use Python integration for Licora Secure API v2. The SDK is framework-neutral and can be copied into desktop, CLI, service, Tkinter, PySide/PyQt or other Python applications.

## Included lifecycle

- exact App ID binding to an existing active Licora Client App;
- P-256 device key generation and signed request proof;
- pinned RSA-3072/RS256 access-token verification;
- secure local session persistence (Windows DPAPI; macOS/Linux system keyring);
- stable device identity across application upgrades;
- login/activation, status, one-time refresh rotation and automatic recovery;
- synchronous and non-blocking asynchronous methods;
- periodic background license validation with callbacks;
- immediate local logout plus server-side device deactivation;
- locally guarded application functions through `require_license`;
- fail-closed configuration, storage, protocol and token validation.

The SDK never uses or stores a Licora API v1 key, Admin License API key, or server private signing key.

## 1. Install

Copy `SDK/python` into your application repository, then install it in the application's virtual environment:

```bash
python -m pip install ./SDK/python
```

For editable development:

```bash
python -m pip install -e ./SDK/python
```

## 2. Configure Licora

1. In Licora Admin → **Client Apps**, create/enable an App ID.
2. Create the license with that exact API v2 Client App selected.
3. Copy `licensing_public.py` beside your application entry point.
4. Set `LICORA_APP_ID`, `LICORA_APP_NAME`, `LICORA_APP_VERSION`, base URL, signing key ID, public key and its SHA-256 fingerprint.

`LICORA_APP_ID` must exactly match both the Client App and the license `app_scope`. The supplied public key is the current `mxflow.shop` deployment key used by VibraPilot. Replace the URL/key/fingerprint together when integrating another Licora deployment.

Never put a user license key or any private/administrative credential in `licensing_public.py`.

## 3. Minimal login integration

```python
from licora_sdk import LicoraAuth

auth = LicoraAuth(config_module="licensing_public")

result = auth.login(user_entered_license_key, user_reference="customer-42")
if not result.ok:
    show_error(result.code, result.message)
else:
    open_main_window()

auth.start_background_validation(callback=on_license_result)
```

For a GUI, keep network I/O off the UI thread:

```python
def completed(result):
    window.after(0, lambda: apply_license_result(result))

auth.login_async(license_entry.get(), callback=completed)
```

Callbacks run on SDK worker threads. Tkinter, Qt and other single-threaded UI frameworks must marshal UI updates to their event loop.

## 4. Startup restore

Construction restores the encrypted session automatically:

```python
auth = LicoraAuth()
snapshot = auth.snapshot()

if snapshot.authorized:
    open_main_window()
else:
    show_license_login()

auth.start_background_validation(run_immediately=True, callback=on_license_result)
```

`snapshot.authorized` is true only while the locally verified, device-bound access token is unexpired. A transient network failure can produce `degraded` status, but authorization never extends past the signed token expiry.

## 5. Protect licensed functions

```python
from licora_sdk import require_license

@require_license(auth)
def run_paid_feature():
    perform_work()
```

Also re-check `auth.is_licensed()` before starting long-running or high-value work. The background interval controls how quickly server-side suspension/revocation is observed while online.

## 6. Logout

```python
auth.logout_async(callback=on_logout_complete)
```

Local license/tokens are removed before remote I/O. The P-256 identity remains protected for safe lifecycle recovery. After confirmed server deactivation, the SDK rotates the permanently revoked device ID so a one-device license can log in again.

Call `auth.close()` during application shutdown.

## Public surface

| API | Purpose |
|---|---|
| `LicoraAuth.login()` / `login_async()` | Activate or restore/refresh a license session. |
| `LicoraAuth.validate()` / `validate_async()` | Perform an online license check and refresh/recovery when required. |
| `LicoraAuth.snapshot()` | Read a secret-free immutable UI/status model. |
| `LicoraAuth.is_licensed()` | Fast local verification of the current signed token. |
| `start_background_validation()` | Run periodic online validation without blocking the UI. |
| `logout()` / `logout_async()` | Clear locally and deactivate the server device session. |
| `require_license()` | Guard a Python function with current authorization. |
| `LicoraV2Client` | Lower-level protocol client for advanced integrations. |
| `StateStore` | Injection point for an application-specific secure vault. |

## Security behavior

- Redirects are rejected and endpoints cannot escape the configured HTTPS origin.
- Exact compact JSON bytes are signed and sent unchanged.
- Every request uses a fresh nonce and timestamp.
- Every access token is checked for type, algorithm, key ID, signature, issuer, audience/App ID, token version, device ID, device-key fingerprint, times and JTI.
- Refresh tokens are treated as one-shot. An ambiguous refresh result is discarded locally before activation recovery.
- Windows stores the entire state as a DPAPI-protected atomic file for the current user. macOS/Linux use an available system keyring; the SDK refuses plaintext fallback.
- `LicenseSnapshot` never exposes the license key, access token, refresh token, or device private key.

See [`examples/tkinter_login.py`](examples/tkinter_login.py) for a complete GUI login flow and [`examples/headless_app.py`](examples/headless_app.py) for a service/CLI pattern.
