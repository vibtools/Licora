"""Public API for the Licora Python SDK."""
from .client import LicoraV2Client
from .config import LicoraConfig
from .crypto import (
    AccessTokenClaims,
    DeviceKeyMaterial,
    generate_device_key_material,
    load_device_key_material,
)
from .decorators import require_license
from .errors import (
    LicenseRequiredError,
    LicoraConfigurationError,
    LicoraError,
    LicoraProtocolError,
    LicoraStorageError,
    is_transient_error,
)
from .manager import LicenseManager, LicoraAuth
from .models import LicenseResult, LicenseSnapshot, LicenseStatus
from .storage import (
    KeyringStateStore,
    MemoryStateStore,
    StateStore,
    WindowsDpapiStateStore,
    default_state_store,
)
from .worker import LicenseValidationWorker

__all__ = [
    "AccessTokenClaims",
    "DeviceKeyMaterial",
    "KeyringStateStore",
    "LicenseManager",
    "LicenseRequiredError",
    "LicenseResult",
    "LicenseSnapshot",
    "LicenseStatus",
    "LicenseValidationWorker",
    "LicoraAuth",
    "LicoraConfig",
    "LicoraConfigurationError",
    "LicoraError",
    "LicoraProtocolError",
    "LicoraStorageError",
    "LicoraV2Client",
    "MemoryStateStore",
    "StateStore",
    "WindowsDpapiStateStore",
    "default_state_store",
    "generate_device_key_material",
    "is_transient_error",
    "load_device_key_material",
    "require_license",
]

__version__ = "1.0.0"
