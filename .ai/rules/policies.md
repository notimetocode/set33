---
paths:
  - 'app/Policies/**/*.php'
---

# Policies

## Separate policies for ЛК and ПА
Authorize app (ЛК) and admin through separate Policy classes or dedicated ability namespaces (e.g. App\* vs Admin\*). Never reuse an admin policy for app endpoints or vice versa. Gate each API group with its own middleware/guards and policy checks.
