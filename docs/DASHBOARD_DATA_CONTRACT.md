# Licora Dashboard Data Contract — Implemented Contract

## Purpose

এই document implementation-এর আগে metric meaning freeze করে, যাতে UI label এবং backend query পরে একে অপরের সঙ্গে conflict না করে।

This contract is implemented by the Phase 1 read model and corrected in **Licora v5.6.1**. Phase 2 will consume it for reload-free browser refresh.

## Endpoint

Implemented:

`GET /admin/ajax/dashboard-data.php`

### Security

- authenticated admin session required
- GET/read-only
- no mutation
- no credentials/private keys
- no caching
- same application-origin usage

## Success Envelope

```json
{
  "success": true,
  "generated_at": "2026-08-20T20:00:00Z",
  "data": {
    "licenses": {},
    "devices": {},
    "api_activity": {},
    "recent_activity": {
      "v1_tracked": [],
      "v2_tracked": []
    },
    "expiration": {},
    "health": {}
  }
}
```

## Error Envelope

```json
{
  "success": false,
  "code": "DASHBOARD_DATA_ERROR",
  "message": "Dashboard data could not be refreshed."
}
```

## License Contract

Recommended fields:

```json
{
  "total": 0,
  "active": 0,
  "expired": 0,
  "suspended": 0,
  "expiring_soon": 0
}
```

Definitions:

- `total`: all license rows
- `active`: `status='active'` and `expires_at > NOW()`
- `expired`: `expires_at <= NOW()`
- `suspended`: `status='suspended'`
- `expiring_soon`: active licenses expiring in a defined future window; proposed 30 days

## Device Contract

Recommended:

```json
{
  "total_records": 0,
  "active_flagged": 0,
  "recently_seen": 0,
  "recency_window_seconds": 300
}
```

`recently_seen` must use explicit recency, not merely a boolean label.

For v1/base devices, `devices.last_active` is authoritative presence evidence.

For v2 device credentials, `v2_device_credentials.last_seen_at` is fresher activity evidence. Implementation must avoid double-counting the same logical device.

If a safe unified count cannot be proven, return separate v1/base and v2 recency counts instead of inventing a combined number.

## API Activity Contract

Do not call one table “all requests”.

Recommended response:

```json
{
  "v1_tracked": {
    "source": "api_logs",
    "last_14_days": []
  },
  "v2_tracked": {
    "source": "v2_audit_logs",
    "last_14_days": []
  },
  "legacy_check_license_included": false
}
```

UI must use labels consistent with these sources.

A combined value is allowed only if each source represents one normalized request event and tests confirm no double counting.

## Expiration Contract

Recommended:

```json
{
  "expired_last_30_days": [],
  "expiring_next_30_days": []
}
```

Do not label future expirations as expired.

## Recent Activity Contract

Must explicitly identify source/type.

Example:

```json
[
  {
    "source": "api_v1",
    "event": "verify",
    "occurred_at": "...",
    "status": 200,
    "license_display": "ABC123..."
  }
]
```

The implemented top-level `recent_activity` object preserves source separation:

```json
{
  "v1_tracked": [],
  "v2_tracked": []
}
```

`api_activity.v1_tracked.recent_calls` and `api_activity.v2_tracked.recent_events` remain available for source-specific consumers; the top-level field is a contract-aligned convenience view over the same already-read data.

If v1 and v2 are combined, normalize only display-safe fields.

Never expose:

- full API keys
- private keys
- refresh tokens
- signing keys
- DB credentials

## Health Contract

Only measured facts.

Recommended structure:

```json
{
  "database": {
    "ok": true,
    "label": "Connected"
  },
  "environment": {
    "value": "production"
  },
  "php": {
    "ok": true,
    "version": "8.x"
  },
  "cron_scripts": {
    "available": true
  },
  "api_v2": {
    "schema_ready": true,
    "public_key_ready": true,
    "key_pair_ready": true
  }
}
```

Do not return `cron_running=true` without heartbeat evidence.

Do not return `api_running=true` merely because a PHP file exists.

`api_v2.key_pair_ready=true` is allowed only when the configured private and public signing keys both exist, are readable, parse successfully, and cryptographically match. The endpoint exposes only readiness booleans; it never returns private-key content or filesystem paths.

## Time Contract

- server-generated timestamp must be returned
- UI displays last successful refresh
- client clock must not be used as authoritative DB/event time

## Read-Only Guarantee

Dashboard endpoint must not execute:

- `INSERT`
- `UPDATE`
- `DELETE`
- schema migration
- cleanup
- license state transition
- device revocation
- updater action

Data maintenance remains in existing API/admin/cron paths.

## Compatibility

This contract must not modify existing external API v1/v2 JSON contracts.

It is an internal authenticated admin endpoint only.
