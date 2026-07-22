# Build and Validation

The application has no compile step and no Composer runtime dependency. A release is a validated source archive.

## Local validation

```bash
bash scripts/validate.sh
```

Checks include:

- PHP syntax for every `.php` file.
- JavaScript syntax when Node.js is installed.
- Pure-function security smoke tests.
- Required repository files.
- Absence of known private deployment markers.
- Confirmation that the public SQL file contains only the approved seed tables.

## Manual validation

Static checks cannot prove database behavior. Before a release:

1. Import `database.sql` into an empty disposable database.
2. Complete installer and manual installation paths.
3. Test all three roles.
4. Create, verify, extend, suspend, blacklist, and delete disposable licenses.
5. Exercise device limit and reconnection paths.
6. Create, test, deactivate, and delete API keys.
7. Test CSV and SQL exports.
8. Run cron scripts from CLI.
9. Verify protected files are denied by the production web server.
10. Restore a backup into a second database.
