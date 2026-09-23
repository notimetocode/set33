---
paths:
  - 'resources/**/*.{vue,js,ts,scss,sass,css}'
  - 'resources/**/*.{vue,scss,sass}'
---

# Resources

## Vue + Bootstrap frontend stack
Frontend is Vue. Use Bootstrap as the base style system (not Tailwind). Build two Vue SPA entrypoints for ЛК and ПА; keep the public site outside those SPAs and SEO-friendly.

## One SCSS file per UI element
Every component, layout, and reusable UI element has its own SCSS file colocated or mirrored under resources/scss. Import through a clear hierarchy (abstracts → vendors/bootstrap → base → components → layouts → pages per surface). Prefer shared components over duplicated markup/styles.

## Visual design tokens (light green product-SaaS)
Brand/primary is green `#0B7A4B` (`$primary`, `$app-accent`, CSS `--brand`). Admin marker uses darker green `$admin-accent` / `--brand-admin` (`#065F3C`), not a separate navy theme. Surfaces are light (`$body-bg` `#FAFBFA`, `--surface` white). Typography: Instrument Sans only (including section labels and code). Do not introduce Tailwind. Keep chrome light (white headers with hairline borders), green accent for CTAs and brand marks.
