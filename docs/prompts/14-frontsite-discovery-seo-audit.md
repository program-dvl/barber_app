# Prompt 14 — Front-Site Discovery and SEO Audit

Use this document as the complete execution brief for Phase 1.5 Prompt 14. Work autonomously through safe, repository-local inspection and documentation. Do not build the marketing page set in this phase.

## 1. Mission

Audit every current public-facing surface and establish an evidence-backed implementation plan for a premium Good Hours acquisition website. Produce the approved route and content architecture, an indexation policy, a reuse/remove/replace inventory, and recorded decisions needed by Prompts 15–27.

## 2. Why This Phase Exists

The repository began as Larafast. Its homepage, header, footer, blog, schema and public assets can contain generic boilerplate or unsupported claims. Phase 1 product behavior exists, but Prompt 13 currently records a launch **NO-GO**. Later prompts need a verified baseline so they do not market aspirational behavior as shipped capability or confuse the marketing site with tenant booking pages.

## 3. Prerequisites

- Prompts 00–13 are complete; confirm their exit evidence in `docs/project-status.md`.
- Do not waive Prompt 13 blockers. Treat OPEN-10 and OPEN-11 and provider/legal/operational certification as unresolved until the source of truth says otherwise.
- If prerequisite evidence is missing, continue the read-only audit, record the gap, and do not assert launch readiness.

## 4. Read Before Changing Anything

Read `AGENTS.md`, `docs/README.md`, `docs/project-status.md`, `docs/product-requirements.md`, `docs/product-brief.md`, `docs/architecture.md`, `docs/design-system.md`, `docs/public-booking-journey.md`, `docs/quality-and-testing.md`, `docs/decisions.md`, and Prompts 00–13. Then inspect route definitions, controllers, Inertia pages/layouts/components, CSS tokens, auth/register flow, billing catalog and entitlements, metadata views/services, sitemap command/controller, `robots.txt`, blog/admin content model, legal Markdown, analytics/instrumentation, tests, built assets and evidence screenshots. Use manifests and lock files for versions.

## 5. Scope

- Inventory all anonymous routes and classify each as marketing, tenant booking, auth, legal, content, transactional, utility/test, webhook/API, redirect, or error behavior.
- Audit the current homepage, navigation, footer, auth entry points, signup/trial CTAs, pricing data flow, blog, product screenshots, metadata, canonical handling, sitemap, robots, schema, index/noindex, internal links, error pages, analytics, accessibility, responsive behavior, performance risks and claims.
- Search for competitor/comparison pages and Phase 2 leakage.
- Define the final public information architecture and phased page backlog.
- Create/update audit and decision documentation needed by later prompts, including evidence, owner and status.

## 6. Out of Scope

- Building the final shared shell, homepage, pricing page, feature/industry/use-case pages or mass content.
- Reworking authenticated SaaS modules or the tenant public booking flow.
- Choosing vendors, purchasing a domain, claiming legal approval, or enabling production services.

## 7. Product Truth

Good Hours is the daily operating system for salons and barbershops. Brand promise: “Make every hour count.” Primary positioning: “Run your salon or barbershop from booking to checkout.” Validate every capability against implemented code, tests and status evidence. Organize truth around get booked, protect the calendar, run the day, get paid, know clients and know the business. Mark unverified, blocked and Phase 2 capabilities explicitly. Never infer truth from old marketing copy.

## 8. Information Architecture

Propose one route matrix with route, purpose, audience/search intent, source capability, content owner, CTA, canonical, indexability, schema type, prerequisite and intended prompt. Include Home, Features/topic clusters, Industries, Use cases, Pricing, Resources/Blog/Guides, Company/Trust/Legal and conversion destinations only where justified. Separately map `/book`, `/book/{slug}` and secure transactional booking URLs. Identify legacy routes such as roadmap, changelog, coming-soon, sitemap/test utilities and recommend retain, redirect, noindex, restrict or remove—without destructive cleanup here.

## 9. UX Requirements

Trace anonymous journeys for first-time owner, evaluating owner, returning visitor, existing customer login, trial signup, content reader and booking client. Flag dead ends, ambiguous CTAs, marketing/booking confusion, weak mobile navigation, inaccessible controls and inconsistent terminology. Define intended paths and recovery/error states.

## 10. UI / Design Requirements

Compare existing marketing UI with the Good Hours semantic tokens and brand direction. Inventory reusable components versus Larafast components/assets that require replacement. Assess typography, color, density, imagery, product proof, breakpoint behavior, focus, motion and 44px targets. Do not treat generated `public/build` files as source.

## 11. Content Requirements

Create a claim ledger: exact claim, location, category, evidence link, status (`verified`, `qualified`, `remove`, `decision-needed`) and safe replacement. Detect placeholder copy, fabricated integrations/logos/social links, unsupported testimonials, awards, counts, ROI, uptime or certification, and vague SaaS language. Define page briefs, owners and evidence sources; do not write dozens of landing pages.

## 12. SEO Requirements

Record current titles, descriptions, canonical output, headings, status codes, redirects, crawl paths, sitemap membership, robots behavior, duplicate/parameter risks and social cards. Identify the current dynamic sitemap crawl risk and `/sitemap` versus `/sitemap.xml` behavior. Establish an explicit indexation matrix and measurable baseline. SEO must serve real intent; reject keyword stuffing, doorway/location permutations and thin programmatic pages.

## 13. GEO / AEO Requirements

Audit whether Good Hours, its category, audience and verified capabilities are stated consistently and answerably. Identify missing concise definitions, factual FAQs, comparison context and citation-worthy pages. Treat GEO/AEO as clear human-readable information architecture, not crawler tricks.

## 14. Structured Data Requirements

Inventory every JSON-LD producer and rendered type. Verify each property against repository/business evidence. Pay special attention to `SchemaOrg`, translated organization fields, awards, founders, addresses, `sameAs`, reviews, offers and article authors/images/dates. Mark fabricated or placeholder markup for removal in Prompt 23. Define eligible types per route; schema must match visible content and must not imply a local salon operated by Good Hours.

## 15. Internal Linking

Produce a crawl graph or route-to-route matrix showing global navigation, footer, contextual links, breadcrumbs, related content, CTA targets, inbound links and orphan candidates. Keep marketing acquisition links distinct from a tenant booking customer's task flow.

## 16. Conversion Requirements

Verify the actual registration and trial mechanics before naming a CTA. Document primary and secondary CTA hierarchy, destinations, authentication-aware behavior, failure states and current friction. A demo/contact funnel is optional only if an owned receiving workflow exists or is explicitly approved; never create a dead form.

## 17. Responsive Requirements

Audit at 360px, representative mobile, tablet and desktop widths. Record navigation, text wrapping, tables, imagery, dialogs, footer, horizontal overflow, tap targets, zoom and orientation failures with reproducible evidence.

## 18. Accessibility

Assess semantic landmarks/headings, keyboard order, focus visibility, skip navigation, names/labels, errors, contrast, reduced motion, image alternatives, screen-reader announcements and target sizes against WCAG 2.2 AA. Separate verified automated findings from manual checks.

## 19. Performance Requirements

Capture a repeatable development or production-build baseline for LCP/CLS/INP risks, server response/rendering, font loading, image dimensions/formats, lazy loading, JS/CSS weight, third parties and cache headers. Do not publish lab results as customer-facing performance claims.

## 20. Analytics

Inventory application-owned instrumentation and any third-party tags, consent behavior, payloads and PII risk. Map current/future funnel events without installing a vendor. Record an explicit decision gap if marketing attribution/consent ownership is unresolved.

## 21. Security / Privacy Considerations

Do not expose secrets, tenant data, raw booking tokens, private files, customer PII or internal diagnostics. Audit `v-html`, forms, upload links, OG-image/test routes, webhook visibility, cookies and tracking. Treat legal copy as counsel-owned and OPEN-10/OPEN-11 as blockers, not copywriting tasks.

## 22. Implementation Instructions

1. Capture route and component inventories from executable code, not memory.
2. Render representative routes and collect screenshots/evidence where the environment supports it.
3. Create an audit artifact under `docs/` containing findings, severity, evidence, recommendation, owner and target prompt.
4. Add the approved/proposed IA and indexation matrices to the appropriate durable docs; record unresolved choices in `docs/decisions.md` open decisions.
5. Update `docs/project-status.md` only with work actually inspected and verified.
6. Make only small foundation fixes required to make the audit reliable; defer implementation to the numbered owner prompt.
7. Preserve unrelated dirty-worktree changes.

## 23. Do Not

- Do not create a page per keyword, industry, city or country.
- Do not redesign the authenticated product or public booking journey.
- Do not publish fake testimonials, logos, customers, metrics, prices, integrations, certifications or schema.
- Do not promote memberships, packages, gift cards, loyalty, referrals, advanced marketing/payroll/procurement, native apps, marketplace/e-commerce, franchise/SSO, AI receptionist/chatbot, dynamic pricing or forecasting.
- Do not silently delete legacy routes/data or claim Prompt 13 is launch-ready.

## 24. Acceptance Criteria

- Every public route and anonymous surface has an evidence-backed classification and disposition.
- The claim, component/asset, SEO/schema, indexation, analytics and accessibility/performance inventories are complete.
- The proposed IA has search intent, product evidence, CTA, indexability and prompt ownership.
- Marketing and tenant booking URL policies are unmistakably separate.
- All ambiguity/blockers are recorded; no unsupported claim remains unflagged.
- No broad landing-page implementation occurred.

## 25. Validation / Testing

Run route listing, focused existing tests, production build, link/crawl checks, metadata/schema parsing, representative status-code checks, mobile/keyboard smoke tests and `git diff --check`. Use the repository's actual scripts (`php artisan test` and `npm run build` where appropriate). Report failures honestly; do not alter application behavior merely to make audit output green.

## 26. Completion Report

Return a concise report with these exact fields:

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

Also link the audit, IA, indexation matrix and decision records, and state whether Prompt 15 is unblocked.
