# Folder Structure

```text
.
├── .github/                  GitHub workflows and templates
├── admin/                    Authenticated administration pages and UI assets
│   ├── assets/css/           Admin stylesheet
│   ├── assets/js/            Admin interactions and pagination
│   └── includes/             Shared admin navigation
├── api/                      License verification endpoints
├── assets/                   Repository banner and screenshot placeholders
├── audit/                    Forensic audit, inventory, diff, and validation evidence
├── cron/                     CLI maintenance scripts; web access denied on Apache
├── docs/                     Architecture, API, deployment, and maintainer guides
├── includes/                 Configuration, database, auth, security, validation, core logic
├── scripts/                  Validation and release packaging utilities
├── tests/                    Non-database smoke tests
├── database.sql              Sanitized public schema and local-development seed
├── install.php               One-time installation wizard
├── index.php                 Restricted-area landing page
└── migration*.sql            Historical additive migrations
```

Private runtime configuration belongs in `includes/config.local.php` and must not be committed.
