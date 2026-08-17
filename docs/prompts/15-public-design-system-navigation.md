# Prompt 15 — Public Design System and Navigation

Execute this prompt only after Prompt 14 has defined the public IA and recorded its evidence. Build the reusable Good Hours marketing shell; do not build all destination page content.

## 1. Mission

Replace the generic Larafast public shell with an accessible, responsive, production-quality Good Hours marketing system that can support Prompts 16–27 while remaining visually related to the authenticated product and clearly distinct from tenant booking.

## 2. Why This Phase Exists

The current `HomeLayout`, header, footer and marketing components contain generic structure, claims and placeholder links. A shared, tested shell prevents every later page from inventing typography, navigation, spacing, CTAs, metadata hooks and responsive behavior independently.

## 3. Prerequisites

- Prompt 14 audit, IA, indexation matrix, claim ledger and reuse/remove/replace inventory are complete.
- Resolve or explicitly record navigation-label and CTA decisions that materially change scope.
- Prompt 13 NO-GO does not block local implementation, but it forbids launch-ready assertions.

## 4. Read Before Changing Anything

Read the mandatory project docs, Prompt 14 outputs, `docs/design-system.md`, ADR-011 in `docs/decisions.md`, current `HomeLayout.vue`, Header/Footer/Banner/Copyright and marketing components, `resources/css/app.css`, `ProductMark.vue`, public booking/auth layouts, route names, localization helpers and relevant tests. Inspect current behavior at mobile/tablet/desktop before editing.

## 5. Scope

- Create/refine the marketing layout, header, desktop and mobile navigation, footer and optional announcement region.
- Add coherent primitives for containers, sections, headings, text measure, buttons/links, cards, screenshot/proof frames, comparison tables, FAQ/disclosure, breadcrumbs and simple conversion bands.
- Make route-aware, auth-aware navigation and reusable SEO/page-header hooks.
- Replace or quarantine placeholder links/assets reached by the new shell.
- Add component and interaction tests appropriate to the stack.

## 6. Out of Scope

Homepage composition, detailed feature/industry/use-case content, final pricing, blog redesign, technical SEO overhaul and analytics funnel implementation belong to Prompts 16–26. Do not refactor authenticated application layouts.

## 7. Product Truth

Use Good Hours, “Make every hour count,” and verified Phase 1 capability language only. The shell may route toward product education, pricing, resources, signup and login, but must not imply live certification, domain ownership, support channels or a demo workflow that does not exist.

## 8. Information Architecture

Implement the Prompt 14-approved hierarchy. Prefer a compact primary navigation such as Product, Solutions, Pricing and Resources plus clear Login and trial/signup actions, but use the approved labels/routes. Add a mega menu only when the IA contains enough useful destinations and it remains keyboard/mobile usable. Footer groups must reflect real pages, not boilerplate service/social links. Handle missing destinations honestly during sequential rollout.

## 9. UX Requirements

Provide skip-to-content, stable current-page indication, clear menu open/close state, Escape/outside-click behavior, focus return, route-change closure and auth-aware actions. Avoid hover-only disclosure. Primary CTA is consistent; secondary action never competes. Logo returns to Home. Footer gives useful recovery and trust paths.

## 10. UI / Design Requirements

Reuse Good Hours semantic tokens: deep pine, poppy/expressive accents, apricot, oat and ink; Manrope for product/message and Newsreader only for selective editorial expression. Premium means calm, deliberate, fast and dependable—not gradient-heavy boilerplate. Standardize max widths, vertical rhythm, radii, shadows, icon treatment, focus rings and image aspect ratios. Prefer existing Heroicons and brand mark over new dependencies.

## 11. Content Requirements

Write concise navigation, accessibility labels and footer copy in the calm/capable/human voice. Remove “Build your SaaS faster,” generic agency/service links, empty newsletters, fake socials and unrelated partner/integration branding. Do not add an announcement without truthful, current content and dismissal behavior.

## 12. SEO Requirements

The layout must support unique page title/description/canonical/social metadata supplied by each page. Use crawlable anchor links for navigation. Do not make client-side-only menus or heading components hide semantic content. One page-level `h1` is owned by the page, not the shell.

## 13. GEO / AEO Requirements

Use consistent Good Hours product and category terminology in visible global copy. Navigation should expose the primary entity/topic clusters to people and crawlers without stuffing repeated keywords.

## 14. Structured Data Requirements

Do not emit global fake Organization/Product schema from visual components. Provide a safe layout-level integration point for later verified JSON-LD. Breadcrumb UI must be able to map to `BreadcrumbList` without divergence, but Prompt 23 owns final markup.

## 15. Internal Linking

Use named routes and the Prompt 14 matrix. Global links should include only durable, useful pages; contextual primitives accept explicit destinations. Prevent placeholder `#` links, inaccessible button-as-link patterns and orphaned pages. Keep `/book/{slug}` out of marketing navigation.

## 16. Conversion Requirements

Implement one primary CTA component with explicit source/context support and valid registration/trial destination. Provide an authenticated variant such as Dashboard. Do not invent “Book a demo.” Preserve query parameters only through an approved attribution contract; Prompt 26 owns complete analytics.

## 17. Responsive Requirements

Support 360px without horizontal scrolling, readable lines or clipped navigation. Mobile menu must fit small/landscape viewports, scroll internally when required and lock background appropriately. Comparison/table primitives need a labeled responsive strategy. Verify tablet transitions and wide-screen text measure.

## 18. Accessibility

Meet WCAG 2.2 AA: semantic header/nav/main/footer, visible focus, keyboard-complete disclosures, accurate `aria-expanded`/`aria-controls`, dialog semantics only if the menu is truly modal, reduced motion, contrast, target size and screen-reader labels. Do not duplicate navigation announcements unnecessarily.

## 19. Performance Requirements

Avoid a UI framework or animation dependency. Keep shell JavaScript small; prefer CSS for presentational response. Load fonts/assets through the existing optimized path, reserve image dimensions and avoid layout shift. Lazy-load below-fold media, never the likely LCP asset.

## 20. Analytics

Expose stable, privacy-safe component context for later CTA/navigation events without firing duplicate events on hydration. Do not install a tracker or send labels, URLs with tokens, emails or tenant identifiers.

## 21. Security / Privacy Considerations

Escape content, sanitize any CMS-fed rich content, use safe external-link behavior, preserve CSRF for forms and avoid user data in markup. Announcement/newsletter/contact features must not exist without backend validation, rate limiting, privacy copy and ownership.

## 22. Implementation Instructions

1. Confirm Prompt 14 approved routes and component dispositions.
2. Create marketing-specific primitives in a coherent namespace; reuse low-level brand tokens/icons rather than coupling to authenticated domain widgets.
3. Migrate the current public layout and header/footer to the new shell incrementally.
4. Remove visible boilerplate from the shell while avoiding unrelated destructive cleanup.
5. Add tests for anonymous/authenticated navigation state, active links, mobile menu keyboard behavior and real route destinations.
6. Document component contracts and update status only after browser/build verification.

## 23. Do Not

- Do not copy the authenticated sidebar into marketing or merge marketing with `PublicBookingLayout`.
- Do not create every later page, add dead links, fake socials/newsletters, a decorative mega menu, new fonts, a second token system or unapproved packages.
- Do not hard-code environment URLs or expose Prompt 13 blockers as solved.
- Do not manually edit compiled assets.

## 24. Acceptance Criteria

- All public marketing pages can use one semantic layout and consistent primitives.
- Desktop/mobile navigation is complete, keyboard accessible, auth-aware and route-correct.
- Footer contains no placeholder or unsupported content.
- Brand tokens and typography match the documented product system.
- 360px, tablet and desktop views have no shell overflow or interaction failure.
- Automated tests and production build pass, with manual keyboard/screen-reader checks recorded.

## 25. Validation / Testing

Run focused PHP/frontend tests, route resolution checks, production SSR build, rendered page smoke tests, keyboard/focus tests, automated accessibility scan if available, contrast checks and 360/tablet/desktop screenshots. Check hydration console, broken links, `git diff --check` and that no existing authenticated/public-booking regression is introduced.

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

Include screenshots/evidence links and whether Prompt 16 is unblocked.
