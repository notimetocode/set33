---
paths:
  - '**/*'
  - '**/*.{php,vue,blade.php,js,ts}'
---

# General

## Three application surfaces
The app has three isolated surfaces: (1) public SEO site — server-rendered Blade/SSR-friendly pages, not a SPA; (2) user area /app (ЛК) — Vue SPA; (3) admin panel (ПА) — Vue SPA. Never mix their routes, layouts, entrypoints, or auth contexts.

## UI and copy language (i18n)
Public site: English is the default locale (no URL prefix); other locales use `/{locale}/…`. Use Blade + `lang/{locale}/*.php` with `__()`. User area (ЛК): load JSON from `resources/js/app/i18n/locales/{locale}.json` based on `users.locale`. Admin panel (ПА): not translated — keep Russian hardcoded. Config: `config/localization.php`; `APP_LOCALE=en`, fallback English. Proper nouns, brand names, emails, and technical tokens may stay untranslated.
