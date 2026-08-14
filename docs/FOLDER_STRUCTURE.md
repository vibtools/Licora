# Folder Structure

```text
.
├── .github/                  GitHub workflows and templates
├── admin/                    Authenticated administration pages and UI assets
│   ├── assets/css/           Admin stylesheet
│   ├── ajax/                 Authenticated updater JSON endpoints
│   ├── assets/js/            Admin interactions, updater client and notification polling
│   ├── assets/css/vibtools/  VibTools v2.1.2 updater UI token foundation
│   └── includes/             Shared admin navigation
├── api/                      License verification endpoints
├── assets/                   Repository banner and screenshot placeholders
├── audit/                    Forensic audit, inventory, diff, and validation evidence
├── cron/                     CLI maintenance scripts; web access denied on Apache
├── docs/                     Architecture, API, deployment, and maintainer guides
├── includes/                 Configuration, database, auth, security, validation, core logic
│   └── updater/              Signed-release updater engine and public verification key
├── scripts/                  Validation, release packaging and update-manifest builder
├── update/                   Signed-release specification metadata
├── tests/                    Non-database smoke tests
├── database.sql              Sanitized public schema and local-development seed
├── install.php               One-time installation wizard
├── index.php                 Restricted-area landing page
└── migration*.sql            Historical/API/updater additive migrations
```

Private runtime configuration belongs in `includes/config.local.php` and must not be committed.

Updater transient staging/backups/locks are created under `includes/.licora-updater/`. This directory is private runtime state, ignored by Git and excluded from release packages.
