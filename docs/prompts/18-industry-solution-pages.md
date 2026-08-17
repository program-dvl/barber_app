# Prompt 18 — Industry and Business-Type Solution Pages

Execute only after Prompt 17. Create genuinely differentiated solution pages for business types that Good Hours can truthfully serve; page count is earned by distinct operational needs and product evidence.

## 1. Mission

Build a focused industry hub and selected salon/barbershop business-type landing pages that connect each audience's daily workflow to verified Good Hours capabilities and relevant product proof.

## 2. Why This Phase Exists

“Salon software” audiences share needs but do not operate identically. High-quality industry pages can clarify fit and search intent; keyword-swapped templates create misleading, thin content and reputational/SEO risk.

## 3. Prerequisites

Prompts 14–17 must be complete. The IA, feature clusters, claim ledger and relevant screenshot evidence must exist. Do not publish an industry page until its differentiators and product support are evidenced.

## 4. Read Before Changing Anything

Read mandatory project docs, Prompt 14–17 outputs, requirements/module docs for business configuration, services/resources, availability, calendar/walk-ins, booking, clients/forms, payments, inventory/reporting and staff. Inspect actual UI/tests and any approved customer research in-repo; do not use assumptions as facts.

## 5. Scope

- Evaluate salon, barbershop, hair salon, beauty salon, nail salon, spa and independent stylist intents.
- Implement an industry hub and only the pages with distinct, supportable narratives.
- Potential differentiation: barbershop walk-ins/queue/fast checkout/staff calendar/deposits; nail services/add-ons/resources/client history/forms; spa rooms/resources/consultation/consent/multi-service constraints; independent operators simplified ownership/calendar/client/payment flow.
- Record consolidate/defer decisions for overlapping or unsupported candidates.

## 6. Out of Scope

City/country pages, local directory listings, tenant booking pages, fake customer stories, regulatory advice, franchise features and vertical capabilities not implemented. Do not change domain behavior to make a vertical claim true.

## 7. Product Truth

Use implemented Phase 1 capability evidence. “Supports” must mean the workflow actually exists and is usable for that business type. Be precise about resources, appointment composition, deposits, payments, communications and forms. Do not imply medical/spa compliance, HIPAA, automated marketing or multi-country payment/legal readiness.

## 8. Information Architecture

Create a decision matrix: candidate, search intent, operational differences, supported workflow, unique proof, overlap, route and disposition. Use an approved hub such as `/solutions` or `/industries`, not both unless each has a distinct purpose. Pages sit under one stable hierarchy and link to product clusters without duplicating Prompt 19 problem pages.

## 9. UX Requirements

Visitors must quickly identify whether the page understands their operation. Each page should move from audience-specific challenge to daily workflow, proof, relevant capabilities, honest fit/limits, related resources and CTA. Avoid forcing readers through generic feature grids.

## 10. UI / Design Requirements

Use the Prompt 15 system and real product UI. Differentiate pages through meaningful content order and proof—not arbitrary color themes or stereotypical stock imagery. Keep industry navigation scannable and accessible; screenshots must remain legible and correspond to the described workflow.

## 11. Content Requirements

Write each page from a distinct brief with audience vocabulary supported by research/product evidence. Never substitute one industry name into shared copy. Do not stereotype businesses or promise revenue/no-show/time outcomes. Explain where shared capability works differently. No testimonials, logos, counts, awards or invented quotes.

## 12. SEO Requirements

Assign distinct primary intent/title/description/`h1`; assess cannibalization between salon, hair salon and beauty salon before publishing. Consolidate or canonicalize only for genuine duplication—do not create pages then hide thinness. Use descriptive URLs, SSR content, useful headings and sitemap eligibility only after quality review.

## 13. GEO / AEO Requirements

Give concise visible answers: what software for this business type needs to manage, how Good Hours fits, and which verified workflows matter. Keep entity/category language consistent and cite product facts via internal links. Human usefulness takes priority over answer-engine formatting.

## 14. Structured Data Requirements

Use WebPage/BreadcrumbList and approved Good Hours entity references only. These are software solution pages, not `LocalBusiness` records for salons. Never add local addresses, service areas, reviews, ratings or industry-specific certifications without verified visible evidence.

## 15. Internal Linking

Link hub ↔ each published industry page; industry → relevant feature/use-case/resource/pricing pages; homepage → strongest industry entry points. Use audience-specific anchors naturally. Ensure every page has meaningful inbound links and no cross-link block listing irrelevant verticals.

## 16. Conversion Requirements

Use the verified trial/signup CTA with industry/page placement context. Secondary links should support evaluation through relevant features/pricing/resources. Do not offer industry demos, onboarding, migration or consultation unless a real owned workflow exists.

## 17. Responsive Requirements

Verify 360px, tablet and desktop content, industry navigation, screenshots, cards, tables and CTAs. Ensure long vertical names/localized copy can wrap, touch targets are at least 44px and no desktop-only comparison loses meaning on mobile.

## 18. Accessibility

Meet WCAG 2.2 AA with semantic headings, breadcrumbs, descriptive links, accessible media/tables, focus, contrast and reduced motion. Avoid alt text that repeats surrounding keyword-heavy headings.

## 19. Performance Requirements

Do not load every industry's images on each route. Use correctly sized responsive assets, reserved dimensions and below-fold lazy loading. Keep shared bundles shared and page-specific content light; verify SSR and route bundle impact.

## 20. Analytics

Use approved privacy-safe page category, industry slug and CTA placement properties. Keep the event taxonomy bounded. Do not collect inferred sensitive business information beyond the visited route or pass query PII.

## 21. Security / Privacy Considerations

Use synthetic screenshot data. Avoid health/sensitive client examples, real consultation forms or client images. Do not state legal/security compliance based on industry assumptions. Validate any content source and escape output.

## 22. Implementation Instructions

1. Score candidates for distinct intent, evidence and substantive content before routing.
2. Document briefs and claim sources; merge or defer weak candidates.
3. Implement the hub and approved pages using existing layout/primitives/content data conventions.
4. Add metadata, breadcrumbs, links, conversion context and tests.
5. Update IA/claim/indexation records and status with only verified pages.
6. Review every page side by side for keyword substitution or duplicated sections.

## 23. Do Not

- Do not create all seven candidates by default, use doorway/city pages or spin near-identical copy.
- Do not imply Good Hours operates a salon, has customers in an industry or meets industry regulations without proof.
- Do not advertise Phase 2 or unsupported multi-service/resource behavior.
- Do not use unlicensed or stereotypical stock imagery.

## 24. Acceptance Criteria

- Each published page has unique audience insight, workflow, verified proof and search intent.
- Overlapping/unsupported candidates have documented dispositions.
- Pages have unique metadata, valid routes, breadcrumbs, internal links, CTA and correct schema/indexability posture.
- No unsupported industry, compliance or performance claims appear.
- Responsive, accessibility, SSR, test and production build evidence passes.

## 25. Validation / Testing

Run route/page tests, claim review, duplicate-copy similarity review, title/description/heading uniqueness checks, link/orphan crawl, canonical/sitemap checks, structured-data validation if changed, `npm run build`, visual and accessibility checks at representative widths, console/hydration review and `git diff --check`.

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

Include candidate scoring/dispositions, claim evidence and whether Prompt 19 is unblocked.
