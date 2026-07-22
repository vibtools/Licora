# Roadmap

Roadmap items are proposals and must be implemented through reviewed, backward-compatible changes.

## Security hardening

- Correct Authorization Bearer parsing and remove secret-adjacent development logs.
- Replace unauthenticated AES-CBC storage with an authenticated encryption format and migration path.
- Move all destructive admin actions from query strings to POST requests.
- Enforce the existing session-timeout method consistently.
- Add explicit installer lock and secure first-admin creation.
- Add configurable security headers and a Content Security Policy.

## Correctness and configuration

- Wire stored maintenance, two-factor, timezone, and API-limit settings into runtime behavior.
- Reconcile the legacy simple verification endpoint with API-key and application-scope policy.
- Correct browser and operating-system detection order.
- Normalize database migrations and remove redundant indexes through a safe migration.

## Quality and operations

- Add disposable-database integration tests.
- Add API contract tests and admin authorization tests.
- Vendor or integrity-pin frontend assets for offline and supply-chain resilience.
- Add structured logs, rotation guidance, and health-check output suitable for monitoring.
- Add container and reverse-proxy examples without making containers mandatory.
