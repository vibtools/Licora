# Coding Standards

## PHP

- Target PHP 8.0 or later unless a version change is approved.
- Use four spaces and braces consistent with surrounding code.
- Prefer strict comparisons and explicit casts at trust boundaries.
- Use PDO prepared statements for values.
- Sanitize for validation and escape at output; do not treat trimming as HTML sanitization.
- Use `Security::escape()` for HTML output.
- Use `Security::requireCSRFToken()` for state changes.
- Use `AdminHelpers::requireManage()` or `requireDelete()` for protected actions.
- Avoid secrets in logs, exceptions, and debug responses.
- Prefer `__DIR__` for filesystem paths.

## SQL

- Use additive migrations for compatibility.
- Name indexes and constraints explicitly.
- Include rollback notes.
- Never commit operational rows or database backups.

## JavaScript and CSS

- Keep UI behavior progressively enhanced.
- Avoid inline secrets or server data not already visible in the page.
- Preserve keyboard accessibility and native form behavior.
- Test Bootstrap/Tailwind interaction when changing layout classes.

## Documentation

Document endpoint, schema, security, and operational changes in the same pull request. Use examples with reserved domains and fake values.
