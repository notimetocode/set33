---
paths:
  - 'app/Actions/**/*.php'
---

# Actions

## Business logic in Actions
Reusable business operations belong in App\Actions (invoked via handle()). Controllers stay thin and call Actions instead of embedding domain logic.
