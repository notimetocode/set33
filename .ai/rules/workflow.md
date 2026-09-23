---
paths:
  - '**/*'
  - '**/*.{php,vue,blade.php,js,ts,scss,sass,css}'
---

# Agent workflow: npm and tests

Do not run `npm run build`, `npm run dev`, `npm install`, or other frontend bundling/install commands unless the user explicitly asks. Assume the developer's `npm run dev` (Vite HMR) is already running; after frontend edits, do not build or suggest building by default.

Do not run the test suite (`php artisan test`, PHPUnit, Pest, etc.) unless the user explicitly asks. Writing or updating tests when the task calls for them is fine; executing them is not, without a request.

Pint/formatting after PHP edits remains allowed unless the user says otherwise.
