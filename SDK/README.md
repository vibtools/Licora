# Licora SDKs

Production client SDKs for Licora live in this directory.

- [`python/`](python/) — reusable Python desktop/application SDK with device-bound authentication, secure local session storage, token verification, refresh rotation, background validation and logout/deactivation.
- [`admin-license-api/`](admin-license-api/) — server-side PHP and Node.js SDKs for scoped Admin License API automation, including signing, examples and operational documentation.

The Python SDK is a public-client integration and never uses the API v1 shared key or server-side Admin License API key. The Admin License API SDK is backend-only; its secret must never be embedded in a browser, desktop application or mobile application.
