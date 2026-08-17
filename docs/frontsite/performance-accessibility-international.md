# Frontsite performance, accessibility and international contract

Status: Prompt 25 measured implementation record (2026-08-16)

## Budgets and baseline

The repeatable pre-hardening build had a 256 KiB shared CSS asset, a 14.48 KiB
Home route JS chunk and six self-hosted font files between 96–112 KiB each.
Public route budgets now fail CI above 450 KiB core JS, 300 KiB shared CSS, 160
KiB per public route JS, 130 KiB per font file or 550 KiB per route-owned image.
The check reads the production Vite manifest and never counts a quarantined
private route chunk as public initial payload. Final post-build measurements are
recorded in Prompt 27's audit.

The public shell supplies a keyboard skip link, stable `main` target, visible
focus, 44px targets, reduced-motion behavior, sticky-header scroll padding,
content overflow wrapping and printable legal/editorial content. Tables are
named keyboard-scroll regions; radios, disclosures, breadcrumbs, lists and
status callouts retain native semantics. Hero media reserves intrinsic space;
below-fold evidence is lazy and the LCP proof is eager. No analytics, chatbot,
provider SDK or third-party font blocks public rendering.

Manual browser review covers 320/360/390 mobile, 768 tablet and 1440 desktop,
including keyboard menu Escape/focus return, 200% reflow, reduced motion,
pricing radio changes, FAQ disclosure, long breadcrumbs and error/legal pages.
Automated checks and local review are not an independent WCAG 2.2 AA
certification; assistive-technology and Chrome/Safari/Edge/Firefox external
matrix remain Prompt 13 launch gates.

## International decision

Phase 1 public content is one reviewed English-for-India experience declared as
`en-IN`. Application translation lookup remains `en` so existing message keys
continue to resolve. Dates/numbers/currency use `Intl` with `en-IN`, while
authoritative currency, prices, tax and legal text remain server/policy-owned.
No selector, locale path, country page, availability claim or `hreflang` is
published.

When a second fully reviewed equivalent exists, use path prefixes (`/en-in/`,
`/{language-region}/`) for every equivalent indexable page, reciprocal self
`hreflang`, and an explicit `x-default` only for a real neutral selector. Locale
inputs will be allow-listed and included in public cache keys. A missing
translation must not redirect to an unrelated homepage or silently machine-
translate legal/commercial qualifiers. Stable Good Hours entity IDs remain the
same across locales; visible language, offer currency and availability must
match each localized graph.
