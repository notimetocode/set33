---
paths:
  - 'routes/**/*.php'
---

# Routes

## Separate API route groups for ЛК and ПА
Expose distinct API route files/prefixes for app (ЛК) and admin (e.g. routes/api/app.php → /api/app/*, routes/api/admin.php → /api/admin/*). Public SEO pages use web routes, not the SPA APIs. Do not share controllers across ЛК and ПА unless the action is deliberately identical and authorized separately.
