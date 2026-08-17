# Prompt 17 — Product and Feature Architecture

Execute after the homepage is verified. Build a focused product/feature architecture from actual customer jobs and search intent; do not automatically publish one page per database module.

## 1. Mission

Create `/features` and the smallest set of high-value feature detail pages that accurately explain Good Hours as a connected operating system, using topic clusters that are useful for evaluation, search discovery and internal linking.

## 2. Why This Phase Exists

Owners need more depth than a homepage, while search engines need coherent topic authority rather than thin feature permutations. This phase creates durable product education without exposing implementation jargon or Phase 2 promises.

## 3. Prerequisites

Prompts 14–16 are complete, their routes/claims/components are verified, and approved feature clusters exist in the IA. Any page lacking enough unique intent, product evidence or copy must remain deferred or be consolidated.

## 4. Read Before Changing Anything

Read mandatory project docs, all Phase 1 module docs and linked requirement IDs, Prompt 14 claim/IA artifacts, Prompt 15 primitives, Prompt 16 homepage, route/controller/model/service/test evidence and relevant UI screenshots. Confirm current production dependency status for payments and communications before describing them.

## 5. Scope

- Build a strong `/features` overview.
- Evaluate and implement only justified clusters among online booking, calendar/availability, staff scheduling/permissions/commissions, client management/forms/consent, deposits/no-show protection, walk-ins/waitlist, checkout/payments, resource scheduling, reminders/communications, inventory and reporting.
- Each detail page needs unique problem framing, workflow, verified capability proof, limitations, related links, CTA and metadata.
- Create reusable feature-detail content structures without a rigid page factory.

## 6. Out of Scope

Industry and problem pages, pricing comparison, competitor-versus pages, product module changes, undocumented integrations and Phase 2 features. Do not create pages merely because a keyword exists.

## 7. Product Truth

Map every capability statement to a PRD requirement, implemented status/test or visible UI. Describe connected workflows and guardrails: tenancy/location ownership, permissions, timezone correctness, idempotency, auditability and append-only financial history where relevant. Explain sandbox/provider limitations without overwhelming normal copy; never imply live certification.

## 8. Information Architecture

Document a cluster decision table with candidate, primary intent, audience stage, unique value, evidence depth, overlap/cannibalization, route, parent and disposition. Prefer outcome-oriented slugs and human labels. `/features` is the hub; detail pages form coherent clusters and do not duplicate industry/use-case pages owned by Prompts 18–19.

## 9. UX Requirements

Allow visitors to scan capabilities by customer job, then explore a workflow without losing context. Each page should answer what it solves, how it works, who benefits, what connects next and what action is available. Use screenshots/diagrams with contextual captions, accessible breadcrumbs and a useful related-content ending.

## 10. UI / Design Requirements

Use the public design system and consistent page-detail composition while allowing different proof layouts. Avoid icon-grid monotony and oversized decorative hero blocks. Comparison/process visuals must remain readable on mobile. Reuse real product screenshot frames and crop only without misrepresenting UI state.

## 11. Content Requirements

Write concrete operational narratives. Say what staff/owners/clients do and what the system records; avoid code/table names. Include limits or prerequisites where they affect evaluation. Candidate topics are not mandatory pages. No filler FAQs, repeated intros, keyword swaps, fabricated integrations, automation outcomes or product screenshots.

## 12. SEO Requirements

Assign one primary intent and distinct title/description/`h1` per page. Prevent keyword cannibalization through consolidation, canonicals or clear differentiation. Ensure SSR content, semantic headings, descriptive links, useful image metadata and indexability follow Prompt 14. Do not publish thin pages to sitemap.

## 13. GEO / AEO Requirements

For each page include concise, visible definitions and answers: what the capability is in salon operations, how Good Hours handles it, which adjacent capabilities connect, and meaningful limitations. Keep terminology consistent across pages and the product UI.

## 14. Structured Data Requirements

Use only approved WebPage/BreadcrumbList/Product or SoftwareApplication relationships supported by visible content. Do not create separate Product entities per feature. Do not add FAQ schema for invented or hidden questions, ratings, aggregate offers or feature availability not proven in code.

## 15. Internal Linking

Link hub → cluster pages, detail → hub/adjacent workflow, homepage → strongest details, and details → relevant industry/use-case/pricing/resource pages as they become available. Use specific anchor language. Maintain a link matrix and prevent orphan or circular boilerplate link blocks.

## 16. Conversion Requirements

Use CTA hierarchy appropriate to product evaluation: primary verified trial/signup and secondary related exploration/pricing. Page/source placement must be available to the approved event contract. Keep claims beside their proof; do not gate basic feature information behind a form.

## 17. Responsive Requirements

At 360px, feature navigation, proof sections, tabs/disclosures and comparison content must not overflow or become unreadable. Avoid mobile-only information loss. Validate tablet content rhythm and desktop line lengths; support touch and orientation.

## 18. Accessibility

Meet WCAG 2.2 AA with logical headings, labeled breadcrumbs, descriptive links, alternative text/captions, accessible tables, keyboard-complete tabs/disclosures and visible focus. Never encode capability/state by color alone.

## 19. Performance Requirements

Budget screenshots and page-specific JavaScript. Use responsive images with dimensions/formats, lazy-load below fold, avoid shipping all feature media/components on every page and ensure server-rendered core content. Check route-level bundle deltas.

## 20. Analytics

Record privacy-safe page/cluster and CTA placement using existing conventions only. Do not create a unique event name for every link; use stable event plus structured allowed properties when consistent with the repository. No PII or booking/tenant identifiers.

## 21. Security / Privacy Considerations

Screenshots and examples must be synthetic and scrubbed. Do not expose internal permissions, webhook endpoints, provider IDs or security configuration. Sanitize any data-driven copy and keep route parameters constrained.

## 22. Implementation Instructions

1. Score candidates and get an evidence-backed final page set; record excluded/merged topics.
2. Define route/controller/page data contracts and centralized content ownership appropriate to the current Laravel/Inertia architecture.
3. Implement hub first, then only approved details with real copy and proof.
4. Add unique metadata, breadcrumbs and links without premature schema duplication.
5. Add route/page/content-contract tests and update IA, claim ledger and status.
6. Verify each page visually and crawl it as anonymous SSR output.

## 23. Do Not

- Do not generate all candidate pages automatically or swap feature keywords into one template.
- Do not advertise Phase 2 memberships, loyalty, gift cards, marketplace, AI, advanced marketing/payroll or other guarded scope.
- Do not promise no-shows are eliminated, payments are universally available or reminders are guaranteed.
- Do not edit authenticated workflows to satisfy marketing copy.

## 24. Acceptance Criteria

- `/features` explains the connected system and routes to a justified, non-overlapping detail set.
- Every published detail has unique intent, substantive verified content, proof, metadata, breadcrumbs, related links and conversion action.
- Candidate disposition and claim evidence are documented.
- No thin/orphan/duplicate/unsupported page enters sitemap eligibility.
- Responsive, accessibility, SSR, tests and production build pass.

## 25. Validation / Testing

Run route and HTTP tests, content/claim fixtures or contract tests, unique title/description/`h1` checks, internal-link crawl, canonical/indexability checks, structured-data parse if changed, production SSR build, page bundle/image audit, accessibility and visual checks at 360/tablet/desktop, console review and `git diff --check`.

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

Include the candidate disposition table, requirement/evidence links and whether Prompt 18 is unblocked.
