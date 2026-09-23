---
paths:
  - 'app/Http/**/*.php'
---

# Http

## Token-based authentication
Authenticate SPA APIs with tokens (Laravel Sanctum personal access tokens / API tokens). Issue and revoke tokens explicitly; do not rely on session cookies for ЛК or ПА API auth. Protect /api/app/* and /api/admin/* with auth:sanctum (or equivalent token guard) plus role/ability checks.
