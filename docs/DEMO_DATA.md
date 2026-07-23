# Demo Data

## Purpose

The v5.1.0 installer can create optional demonstration data for evaluation. Production installations should leave the option unchecked.

## Existing-schema mapping

No product or customer table is added.

| Demo concept | Existing storage |
|---|---|
| Demo product | `api_keys.app_name = DEMO PRODUCT` |
| Demo API credential | Existing `api_keys` row named `[DEMO] Installer API Credential` |
| Demo license | Existing `licenses` row |
| Demo customer | License notes beginning `[DEMO CUSTOMER]` |
| Demo status | Existing `settings` keys |

## Security

The installer generates a random API credential and stores its hash and authenticated encrypted copy using the existing v2 encryption format. The raw token is never displayed or logged by the installer.

The demo license uses the existing four-segment license format and existing encrypted storage format. License validation logic is not modified.

## Removal

Run from the repository root:

```bash
php scripts/remove-demo-data.php
```

The utility removes only records referenced by installer demo settings and only when their DEMO markers match. It does not remove unrelated licenses, API keys, customers, products, or settings.

## Production conversion

Do not rename demo records into production records. Remove demo data and create production API keys and licenses through the existing admin interface.
