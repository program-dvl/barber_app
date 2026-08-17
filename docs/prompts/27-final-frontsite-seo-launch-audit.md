# Prompt 27 — Final Front-Site and SEO Launch Audit

Execute only after Prompts 14–26 have completion evidence. This is a remediation and evidence prompt: inspect the entire Phase 1.5 result, fix every safe in-scope defect, and report genuine blockers. It does **not** override Prompt 13's product-wide launch NO-GO.

## 1. Mission

Deliver the strongest safe, verified Good Hours front-facing website state by auditing and fixing routes, UI, responsive behavior, content, SEO, GEO/AEO, conversion, accessibility, performance and engineering quality across all Phase 1.5 work.

## 2. Why This Phase Exists

Sequential implementation creates integration gaps that individual prompt acceptance tests may miss: dead links, inconsistent claims, metadata collisions, mobile overflow, schema drift, duplicated events or launch-language leakage. The final gate needs cross-site evidence and remediation, not a checklist-only report.

## 3. Prerequisites

- Prompts 14–26 show complete, verified exit evidence; otherwise list the unmet dependency and do not manufacture it.
- Working tree and current baseline are captured so unrelated user work is preserved.
- External credentials/services must not be invoked destructively. Prompt 13 live provider/legal/security/operations blockers stay open until independently evidenced.

## 4. Read Before Changing Anything

Read `AGENTS.md`, every source-of-truth doc, decisions/open decisions, Prompt 13 report, all Prompt 14–26 files/completion artifacts, route/IA/indexation/claim/entity/CTA/event matrices and current manifests/locks. Inspect all changed public code, tests, content, generated assets, SSR output and representative browser pages. Do not trust prior completion reports without reproducing key evidence.

## 5. Scope

- Audit and safely fix all Phase 1.5 defects.
- Routes: broken/missing/dead routes, redirect chains/loops, status codes and 404/410 behavior.
- UI: brand, layout, spacing, typography, navigation/footer, components and CTAs.
- Responsive/accessibility/performance: every page template and interaction.
- Content/SEO/GEO: claims, quality, metadata, canonicals, sitemap/robots/indexation/schema, headings, links, entity/answer consistency.
- Conversion/analytics: destinations, trial/pricing/signup, forms, attribution, consent, events.
- Engineering: configured type/TypeScript checks, lint, tests, SSR production build, runtime/console/hydration issues and phase-introduced dead code. If this JavaScript/Vue repository has no type-check or lint script, record that fact instead of inventing a command or silently claiming it passed.

## 6. Out of Scope

Authenticated-product redesign, new Phase 2 features/pages, domain purchase, production deployment, live payments/messages, legal approval, independent pen test/WCAG certification, production load/DR/observability work and unrelated legacy cleanup. Record these; do not fake closure.

## 7. Product Truth

Revalidate every visible and machine-readable claim against current code/tests/status/catalog—not Prompt 14's historical snapshot. Remove or qualify unsupported claims immediately where safe. Good Hours is the salon/barbershop operating system from booking to checkout; marketing site and tenant public booking remain distinct. Prompt 13 NO-GO and OPEN-10/OPEN-11 must be visible in launch conclusions.

## 8. Information Architecture

Compare executable route list, global navigation, footer, content hubs, sitemap and approved IA. Every published route needs purpose, owner, unique intent, canonical/indexability, inbound path and valid status. Remove from navigation/sitemap or noindex/quarantine incomplete pages; use tested redirects for replaced public URLs. Never expose private routes to complete the graph.

## 9. UX Requirements

Walk core journeys: new owner Home→features/industry/use case→Pricing→register/trial; content reader→related product→action; existing user→login/dashboard; legal/support discovery; invalid route recovery; and booking client entering `/book/{slug}` without marketing confusion. Fix dead ends, inconsistent states, ambiguous labels and broken back behavior.

## 10. UI / Design Requirements

Review every template side by side for Good Hours tokens, typography, section spacing, content width, cards, screenshots, tables, FAQ, breadcrumbs, focus and CTA hierarchy. Fix drift and leftover Larafast/placeholder visuals. Do not erase purposeful page differentiation or undertake a taste-only redesign with no acceptance benefit.

## 11. Content Requirements

Search for placeholder/generic copy, grammar/spelling, duplicated sections, thin pages, ambiguous category language, fake socials/integrations/testimonials/logos/metrics/awards/reviews/certifications, stale price/trial details and Phase 2 terms. Reconcile the claim ledger. Fix safe copy against evidence; defer business/legal facts needing owner approval and prevent unsupported publication.

## 12. SEO Requirements

For every indexable URL verify unique title/description/`h1`, canonical absolute URL, 200 status, SSR content, social metadata, heading hierarchy, crawlable links, sitemap membership and permitted robots behavior. Verify noindex/private routes are absent from sitemap. Check duplicates/parameters/pagination, redirects, 404/410/soft 404, image metadata, semantic HTML, orphan pages and legacy route handling. Fix failures and rerun the crawl.

## 13. GEO / AEO Requirements

Reconcile canonical Good Hours facts, category, audience, capabilities, prices/qualifiers and company identity across every authoritative page and schema node. Ensure important questions receive useful direct answers and comparisons are fair. Remove hidden/bot-specific/fake-citation/keyword-FAQ content and Phase 2 leakage.

## 14. Structured Data Requirements

Parse and validate every emitted JSON-LD graph by route. Visible content, canonical, entity `@id`, authors/dates, breadcrumbs, offers/currency/availability and FAQ answers must match. Remove duplicate/conflicting/fabricated Organization/LocalBusiness/Product/Review/Award/Offer fields. Schema warnings are triaged by eligibility and truth, not blindly silenced.

## 15. Internal Linking

Run a full anonymous SSR crawl from Home and hubs, comparing discovered URLs, sitemap and IA. Fix broken links, redirect hops, misleading/empty anchors and unintended orphans. Validate hub/spoke/contextual/resource-product paths. Confirm signed, transactional, auth, admin, API and webhook URLs remain deliberately undiscoverable.

## 16. Conversion Requirements

Test every CTA/form state and destination as anonymous, authenticated-unsubscribed and subscribed where applicable. Verify trial and pricing selection from authoritative data, signup attribution and server-authoritative completion events. Remove dead demo/contact/newsletter flows. Check consent, duplicate events and ensure no click is reported as a completed conversion.

## 17. Responsive Requirements

Inspect all templates at 360px, second mobile, tablet portrait/landscape, desktop/wide, 200% zoom and long-string stress. Fix overflow, clipped menus, unreadable screenshots/tables, wrong order, obscured sticky UI, tap targets and orientation/virtual-keyboard issues. Recheck screenshots after fixes.

## 18. Accessibility

Perform automated and manual WCAG 2.2 AA review: keyboard-only journey, skip/landmark/headings, focus visibility/order/not-obscured, dialogs/disclosures/menus, names/labels/errors/status, contrast, reflow/zoom, target size, alt/captions, reduced motion and screen-reader smoke tests. Fix in-scope issues and preserve honest external-audit blocker.

## 19. Performance Requirements

Compare Phase 25 budgets and current production SSR measurements. Audit LCP/CLS/INP causes, route chunks/dead JS/CSS, fonts, images, lazy/eager priority, third parties, cache/compression, queries and SSR time. Fix material regressions without hiding content or weakening access/security. Distinguish lab evidence from field data.

## 20. Analytics

Validate event dictionary, trigger authority, deduplication, consent, retention/expiry, UTM allowlisting and payload privacy via synthetic journeys. Inspect network/log payloads for PII, tokens, provider IDs and private URLs. Fix duplicates/misattribution; do not add an unapproved vendor to produce a dashboard.

## 21. Security / Privacy Considerations

Recheck auth/private/signed routes, robots/noindex/cache, stored HTML, forms/rate limits/CSRF, external links, uploads/media, error leakage, OG/test utilities and analytics. Never publish tenant/client data or cache private views. Do not weaken tenant isolation, authorization, auditability, timezone correctness, idempotency or append-only financial history.

## 22. Implementation Instructions

1. Capture baseline: working tree, routes, build/tests, crawl, screenshots, bundle/performance and known blockers.
2. Build a severity-ranked defect register with route/evidence/owner; link each issue to its originating prompt/requirement.
3. Fix all safe P0/P1 and in-scope P2 defects, preferring root/shared-component fixes. Re-run focused validation after each class.
4. Do not stop at findings. For issues needing business/legal/external authority, prevent unsafe publication where possible and record precise unblock evidence/owner.
5. Run the complete validation matrix from clean production artifacts, then re-crawl and compare.
6. Remove only dead code introduced by Phase 1.5 when references/tests prove safety; preserve unrelated legacy/user changes.
7. Update status, decisions, claim/indexation/entity/event docs with final verified facts and explicit GO/NO-GO conclusions for **front-site technical readiness** and **overall product launch** separately.

## 23. Do Not

- Do not produce only an audit report when safe fixes are possible.
- Do not add new feature/page scope, fabricate evidence, relax tests/indexation/security, or mark external blockers complete.
- Do not delete unknown legacy data/routes, rewrite unrelated code or manually edit build output.
- Do not declare overall launch GO while Prompt 13 critical/high evidence is unresolved.
- Do not create city/country/AI pages, fake proof, unapproved trackers or Phase 2 claims during remediation.

## 24. Acceptance Criteria

- Defect register has evidence, severity, disposition and no unresolved in-scope P0/P1 issue.
- All approved public routes/journeys work; incomplete/dead/private surfaces are correctly redirected, unavailable or excluded.
- UI/content/claims/CTA/brand are consistent and no boilerplate/placeholder/Phase 2 leakage remains.
- Crawl, metadata, sitemap, robots, indexation, canonical, schema, headings and internal linking invariants pass.
- Required responsive/accessibility/performance/analytics/privacy checks pass or have explicit external blockers.
- Focused/full tests and production SSR build pass; no runtime, console, hydration or phase-introduced dead-code issue remains.
- Final report distinguishes front-site readiness from overall product launch readiness.

## 25. Validation / Testing

Run the repository's configured type/TypeScript check and lint commands when present, or explicitly report their absence; run the full relevant PHP suite and targeted MySQL suite where configured; production `npm run build`; route list and data-driven HTTP tests; full SSR crawl/link/orphan/redirect/status/canonical/noindex/sitemap/robots/schema audit; claim/Phase 2/placeholder searches; pricing/signup/attribution/event E2E with synthetic data; stored-XSS/form/security regression; automated accessibility plus manual keyboard/screen-reader/zoom/reduced-motion; cross-browser/responsive visual evidence; performance/bundle/query/cache comparison; console/network/hydration review; `git diff --check`; and final changed-file review. Never conceal skipped/unavailable external checks.

## 26. Completion Report

Return a concise evidence-backed report with these exact fields:

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

Also include defects found/fixed/open by severity, crawl/page counts, accessibility/performance evidence, Phase 1.5 front-site GO/NO-GO, overall product launch GO/NO-GO, and every external unblock owner/evidence required.
