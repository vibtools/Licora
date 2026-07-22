# Dependency Review

**Date:** 2026-07-22

## Server runtime

- PHP 8.0 or later. Required extensions used by the code: PDO with `pdo_mysql`, OpenSSL, and JSON.
- MySQL or MariaDB with InnoDB and `utf8mb4` support.
- Apache `.htaccess` support or equivalent Nginx/IIS rules.
- No `composer.json`, `composer.lock`, `vendor/`, npm manifest, or bundled third-party package tree exists in the uploaded source.

## Frontend network dependencies

| Resource | Pinning | Referencing files |
|---|---|---:|
| `https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css` | Version-pinned | 13 |
| `https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css` | Version-pinned | 13 |
| `https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js` | Version-pinned | 12 |
| `https://cdn.jsdelivr.net/npm/chart.js` | Unpinned | 1 |
| `https://cdn.tailwindcss.com` | Unpinned | 11 |

## Risk assessment

- Bootstrap 5.1.3 CSS/JavaScript and Bootstrap Icons 1.8.1 are version-pinned in URLs, but no Subresource Integrity attributes were detected.
- Tailwind CDN and Chart.js references are unpinned and can change independently of this repository.
- A restrictive Content Security Policy was not detected.
- CI cannot audit CDN-delivered assets because the project has no package manifest or lockfile.
- For hardened deployment, self-host reviewed assets, pin exact versions, add integrity metadata where applicable, and define a tested CSP.

## License compatibility boundary

The repository itself is released under MIT. No third-party source is vendored into the repository. Operators remain responsible for confirming upstream terms and notices for CDN assets at deployment time.
