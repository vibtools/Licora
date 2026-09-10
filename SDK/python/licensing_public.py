"""Public, non-secret Licora API v2 configuration.

Copy this file beside your application's entry point and edit the application
identity values. The server signing public key is safe to distribute. Never put
a license key, API v1 key, Admin API key, refresh token, access token, device
private key, or Licora server private signing key in this module.
"""

LICORA_API_BASE_URL = "https://mxflow.shop"
LICORA_API_VERSION = 2
LICORA_PROTOCOL = "licora-api-v2"

# Create this exact App ID in Licora Admin -> Client Apps, then create licenses
# scoped to the same App ID. The SDK refuses to start while this sentinel remains.
LICORA_APP_ID = "replace-with-your-app-id"
LICORA_APP_NAME = "Replace With Your App Name"
LICORA_APP_VERSION = "1.0.0"

LICORA_ACTIVATE_PATH = "/api/v2/activate.php"
LICORA_STATUS_PATH = "/api/v2/status.php"
LICORA_REFRESH_PATH = "/api/v2/refresh.php"
LICORA_DEACTIVATE_PATH = "/api/v2/deactivate.php"

LICORA_SIGNING_KEY_ID = "primary-v1"
LICORA_SIGNING_PUBLIC_KEY_PEM = """-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAtyagxZBg0ZyKPdWvc+KW
jHIjjMHi34yHFh9hOWB/ciMRvDDquyCsIaFEVwE+70w8bwqoUy/aXv0DQUNgBZhU
Y2snjSiRm4V0S/YvDYR+1zFXmVVx9jHT1E29OTSzlz0GFUV+wDx5ErKMZtt+Gns/
r3CF0iADf5FlPnBey7+5jl7gvn5yQYZNztDcAL6WU9QSO0lo2GqCjClGE17yrIdz
0ybr20YiL9rNKaI4PVwFCQuJGhh5bjcmOjZmyt9+8OjxoywOyzWxRSeT669QZBgw
nHJ8vwQftFt4dPhtHih11FOzOmjSqW7+u8R3WkDuKSTA4uyiiLVb/go0bka3g3kO
NfU0NL9gWoN/cy8OBqdWxfA1ZgoX5IeOjVTung/GYNgKALCK98xGA+1wr2wwAItY
coCMzQ9zTDs42l0/Pew9fUyhEgc6jdkCyhRnLUaPq+4HYlQZexUX5TCtbgw4va9o
sbhQ3Mzsy8RlD5noNI10tw85RgjQO9HKK62u4jeaImY/AgMBAAE=
-----END PUBLIC KEY-----
"""
LICORA_SIGNING_PUBLIC_KEY_SHA256 = "e4c15e883f17f89482f3423245d2bf71da64190dba4ea01d39da0a1d88942783"

LICORA_SERVER_TIMEZONE = "Asia/Dhaka"
LICORA_CLOCK_SKEW_SECONDS = 300
LICORA_CONNECT_TIMEOUT_SECONDS = 5.0
LICORA_READ_TIMEOUT_SECONDS = 20.0
LICORA_BACKGROUND_CHECK_SECONDS = 300
LICORA_REFRESH_MARGIN_SECONDS = 90
LICORA_MAX_RESPONSE_BYTES = 1048576
