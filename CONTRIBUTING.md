# Contributing

Contributions should preserve existing behavior unless the pull request explicitly fixes a reproduced defect or introduces a documented feature with migration and rollback instructions.

## Before opening a pull request

1. Create an issue for behavior changes, schema changes, or security-sensitive design work.
2. Work from a dedicated branch.
3. Keep secrets, production SQL dumps, license keys, API keys, device identifiers, IP addresses, and customer logs out of commits.
4. Run `bash scripts/validate.sh`.
5. Describe affected endpoints, admin pages, database tables, roles, and backward-compatibility impact.
6. Add tests or a reproducible manual validation procedure.

## Pull-request expectations

A pull request should include:

- Problem statement and scope.
- Exact files and functions changed.
- Before/after behavior.
- Security impact.
- Database migration and rollback steps, when applicable.
- Validation evidence.
- Screenshot evidence with sensitive values redacted.

## Feature-preservation rule

Do not remove or silently weaken license validation, device limits, authentication, authorization, audit logging, API-key binding, migrations, exports, or backup behavior. Deprecations require a documented transition period.

## Coding conventions

Follow [docs/CODING_STANDARDS.md](docs/CODING_STANDARDS.md). Prefer small, reviewable patches over broad refactors. Existing mixed Bengali/English comments may be clarified, but functional meaning must remain intact.

## Security reports

Do not open public issues for exploitable vulnerabilities. Follow [SECURITY.md](SECURITY.md).
