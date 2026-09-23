---
paths:
  - '**/*'
  - '**/*.{php,vue,blade.php,js,ts}'
---

# General

## Three application surfaces
The app has three isolated surfaces: (1) public SEO site — server-rendered Blade/SSR-friendly pages, not a SPA; (2) user area /app (ЛК) — Vue SPA; (3) admin panel (ПА) — Vue SPA. Never mix their routes, layouts, entrypoints, or auth contexts.

## UI and copy language is Russian
Until i18n/translations are introduced, all user-facing text (Blade, Vue, validation messages shown in UI, emails, flash messages, button labels, placeholders) must be written in Russian. Do not add English UI strings except proper nouns, brand names, email addresses, and technical tokens. App locale is Russian (config/app.php / APP_LOCALE=ru); fallback locale stays English for missing framework keys.
