# Validation Report

**Date:** 2026-07-22  
**Command:** `bash scripts/validate.sh`  
**Exit code:** `0`

```text
[1/6] Checking required files
[2/6] PHP syntax
[3/6] Security smoke test
Security smoke test passed.
[4/6] JavaScript syntax
[5/6] Public-release marker scan
[6/6] SQL seed scope
Approved seed tables: admin_users, settings
Validation passed.
```

## Additional evidence

- Original archive SHA-256: `d29442cfd68a337d2d6b5afae5d27364014de4fd2a923f7492ca7c69acd56c27`
- Original executable PHP files unchanged byte-for-byte: `26/27`
- Existing PHP files intentionally modified: `includes/config.php`
- Original application PHP files deleted: `0`
- Original SQL operational rows removed from public seed: `2,746`
- Documentation internal links missing: `0`
- GitHub YAML files parsed: `5`; error: `none`
- Privacy regression scan: `pass`
- End-to-end database/browser integration: `not executed in this environment`
