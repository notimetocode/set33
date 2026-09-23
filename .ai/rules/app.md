---
paths:
  - 'app/**/*.php'
---

# App

## Clean architecture boundaries
Keep layers thin and single-purpose: Controllers orchestrate HTTP only; Form Requests validate; Policies authorize; Actions/Services hold business logic; Models stay domain-focused. Prefer reuse of shared Actions over copying logic between ЛК and ПА. No fat controllers or cross-surface leakage.
