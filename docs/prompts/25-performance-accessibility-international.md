# Prompt 25 — Performance, Accessibility and International Readiness

Execute after Prompt 24. Perform a dedicated public-site hardening pass using measured evidence. Prepare extensible locale/country architecture without pretending Good Hours is approved or available globally.

## 1. Mission

Bring the complete marketing and public-content experience to a documented performance, WCAG 2.2 AA, responsive and browser-quality baseline, while removing architectural blockers to future localization, currencies, country variations and `hreflang`.

## 2. Why This Phase Exists

Page-by-page implementation can accumulate layout shift, heavy screenshots, duplicated JavaScript, inaccessible disclosures and English/India assumptions. A cross-site pass catches systemic issues and creates a safe international foundation before acquisition traffic grows.

## 3. Prerequisites

Prompts 14–24 are implemented and technical indexation is stable. Use representative production SSR builds and realistic content/data. External independent WCAG, target-topology load and production observability evidence remain Prompt 13 blockers unless actually supplied.

## 4. Read Before Changing Anything

Read mandatory docs, design/quality standards, Prompt 13 blockers, all Phase 1.5 reports, `package.json`/lock files, Vite SSR config, app/CSS/font setup, media assets, layouts/components, localization library/translations, locale/currency/timezone config, server/cache middleware and existing browser/accessibility/performance tests. Inspect route-level bundles and rendered pages.

## 5. Scope

- Measure/remediate Core Web Vitals risks: LCP, CLS and INP, plus server/render/cache/image/font/JS/CSS factors.
- Audit every public template at 360px, common mobile/tablet/desktop widths, zoom, keyboard and representative browsers.
- Remediate WCAG 2.2 AA issues in Phase 1.5 surfaces.
- Establish locale/country/currency URL/content/data architecture and future `hreflang` rules without publishing unapproved locales/country pages.
- Add budgets, automated regression checks and durable evidence.

## 6. Out of Scope

Claiming real-user CWV without field data, independent accessibility certification, production load/DR observability work, machine translation, launching countries/currencies/languages, tax/legal localization or mass country pages.

## 7. Product Truth

Performance/accessibility claims require measured dated evidence and context. Current implementation direction includes India/`en-IN`, while USA/Canada/UK/Europe are future possibilities—not active availability claims. Prices/currencies/legal text/provider support remain server/policy-owned and locale-sensitive.

## 8. Information Architecture

Preserve Prompt 23 canonicals/indexation. Document future locale URL strategy (for example path-prefixed or default-locale policy), fallback behavior, content ownership and canonical/`hreflang` relationships before adding routes. One language/country page must not masquerade as many regional offerings.

## 9. UX Requirements

Navigation, reading, CTA, forms and disclosures remain responsive during slow load, keyboard use, zoom and reduced motion. Loading/error/empty states should not shift content or trap focus. A future locale selector is not added until multiple approved locales exist; if added later, it must communicate language/region clearly and preserve valid destination state.

## 10. UI / Design Requirements

Preserve Good Hours visual intent while fixing contrast, type scale, spacing, target size, focus, overflow and motion. Responsive images use art direction only when content-equivalent. Avoid visual changes that make marketing diverge from product tokens. Document component-level fixes for reuse.

## 11. Content Requirements

Remove string concatenation and layout assumptions that make translation unsafe. Allow text expansion, pluralization and locale-aware dates/numbers/currencies. Separate translatable copy from authoritative plan/product identifiers. Do not translate legal/commercial content without owned review.

## 12. SEO Requirements

Performance changes must preserve SSR copy, canonicals, metadata, schema and crawlable links. Define `hreflang` only for equivalent approved localized pages, with self/reciprocal links and `x-default` policy if justified. Do not add `hreflang` for countries sharing untranslated duplicate pages.

## 13. GEO / AEO Requirements

Keep canonical facts and answer blocks visible under responsive/locale behavior. Translation must preserve evidence qualifiers and category terms. Do not generate localized “AI answer” pages or allow machine translation to change product/legal facts silently.

## 14. Structured Data Requirements

Ensure JSON-LD language, currency, URLs and availability align with visible locale content. Do not emit offers for unsupported currencies/regions. Preserve stable entity IDs across translations and validate graphs after caching/SSR changes.

## 15. Internal Linking

Performance optimizations cannot convert crawlable anchors into opaque client interactions. Future locale links must point to real equivalents, not home-page fallbacks. Re-run broken/orphan/redirect checks after route/cache changes.

## 16. Conversion Requirements

CTA/forms must stay usable on slow networks, zoom, keyboard and mobile. Prevent double submission and layout shift. Locale/currency selection cannot imply commercial availability. Confirm analytics and attribution survive SSR/caching without delaying interaction.

## 17. Responsive Requirements

Test at minimum 360px mobile, a second mobile width, tablet portrait/landscape and desktop/wide desktop; include 200% and, where feasible, 400% reflow. Check nav, footer, heroes, screenshots, tables, pricing, articles, breadcrumbs, FAQs, forms, error pages, orientation, virtual keyboard, touch and long translated strings. No horizontal page scroll at 320/360 except intentionally scrollable labeled data regions.

## 18. Accessibility

Audit WCAG 2.2 AA across perceivable, operable, understandable and robust criteria: landmarks/headings, keyboard/focus order/visibility/not-obscured, skip links, names/roles/states, labels/instructions/errors/status, contrast/non-text contrast, reflow/zoom, target size, drag alternatives, reduced motion, timeouts, media alternatives and screen-reader output. Automated tools are support, not proof; record manual keyboard and assistive-tech checks plus unresolved independent audit.

## 19. Performance Requirements

- Establish page/template budgets for HTML, CSS, initial JS, images, fonts, requests and route chunks.
- Measure production SSR under repeatable conditions; record Lighthouse/WebPageTest-like lab context if tooling exists.
- Optimize LCP discovery/priority, responsive AVIF/WebP/fallbacks, dimensions, lazy loading and decoding.
- Self-host/subset/preload fonts only when measured; use fallback metrics to reduce shift.
- Remove dead/duplicate marketing JS/CSS, split route-specific code and avoid hydration work for static content.
- Audit caching/compression/CDN headers, server queries and SSR time without caching private/tenant data publicly.
- Track CLS sources and INP-long tasks; do not game metrics by delaying functionality.

## 20. Analytics

Analytics and consent scripts must not block rendering or interaction. Measure their bundle/request/CPU impact and load according to approved consent policy. Performance telemetry must avoid PII, signed URLs and tenant/customer identifiers; distinguish lab from real-user monitoring.

## 21. Security / Privacy Considerations

Do not solve speed by publicly caching private/signed/tenant-specific responses. Cache keys include approved locale/content variation; set appropriate private/no-store behavior. Third-party performance/accessibility services receive only approved public URLs/data. Validate locale inputs and prevent open redirects/header injection.

## 22. Implementation Instructions

1. Capture repeatable before baseline by page template, device and build; identify highest-impact root causes.
2. Fix shared component/layout issues before page-specific symptoms.
3. Optimize assets/build/SSR/cache conservatively with tests for private/public separation.
4. Complete automated plus manual accessibility remediation and document external verification still required.
5. Record internationalization ADR/contract, extract only needed architecture and add pseudo-locale/long-string tests where feasible.
6. Add enforceable budgets/regression checks appropriate to CI reliability.
7. Capture after evidence and update status without declaring external certification.

## 23. Do Not

- Do not chase a perfect lab score, hide content/functionality, disable accessibility, or lazy-load the LCP asset blindly.
- Do not add a new SPA/UI/image/i18n framework without demonstrated need.
- Do not publish country/language/currency pages, `hreflang` or availability claims before approval.
- Do not cache authenticated/transactional/tenant data as public or report WCAG/CWV certification from automated tests alone.

## 24. Acceptance Criteria

- Before/after evidence and budgets exist for every major public template; material regressions are fixed or explicitly owned.
- Site works at required widths/zoom/input modes with no systemic overflow, focus, contrast or semantic failures.
- Manual and automated WCAG 2.2 AA review is documented, with independent verification gap honest.
- Locale/country/currency/`hreflang` architecture is documented and tested without false launches.
- SSR/indexation/conversion/security behavior is preserved; tests and production build pass.

## 25. Validation / Testing

Run production SSR build, route-bundle and asset-size comparison, Lighthouse/performance tooling under recorded conditions, query/cache/header tests, image/font audit, automated accessibility scan, manual keyboard/screen-reader/reduced-motion/zoom/high-contrast checks, cross-browser smoke tests, visual screenshots at required widths, pseudo-locale/long-string and locale format tests, crawl/schema/canonical regression, console/hydration review and `git diff --check`.

## 26. Completion Report

Return these exact fields:

- Files changed
- Routes created
- Routes modified
- Components created
- Components reused
- SEO changes
- Schema changes
- Analytics changes
- Tests run
- Build result
- Warnings
- Assumptions
- Deferred items

Include before/after budgets/measurements, accessibility matrix, browser/device coverage, internationalization decision and whether Prompt 26 is unblocked.
