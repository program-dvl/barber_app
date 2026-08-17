# Prompt 23 — Technical SEO, Indexation and Structured Data

Execute after Prompt 22. This is the authoritative technical SEO implementation pass for all existing public surfaces. Be conservative: correct crawl/index controls and truthful machine-readable data are more important than adding markup.

## 1. Mission

Implement and test a centralized metadata, canonical, indexation, sitemap, robots, redirect/error and JSON-LD framework that makes approved Good Hours marketing content crawlable while keeping authenticated, private, transactional, parameterized and unsafe dynamic URLs out of search results.

## 2. Why This Phase Exists

Current behavior includes a dynamic sitemap crawl, permissive `robots.txt`, little/no explicit noindex handling, possible `/sitemap` versus `/sitemap.xml` mismatch and boilerplate schema with placeholder founders/address/awards/reviews. Tenant booking and secure appointment routes require deliberate treatment distinct from marketing pages.

## 3. Prerequisites

Prompts 14–22 must be complete with final route, canonical/indexation and entity matrices. Product/organization facts must be approved. Back up or fixture current route behavior before changing shared metadata. If policy for tenant booking indexation is unresolved, fail closed to the documented safe posture and record the decision.

## 4. Read Before Changing Anything

Read mandatory docs, all Phase 1.5 artifacts, route list/middleware, `app.blade.php`, `Seo.vue`, metadata/schema Blade views, `SchemaOrg`, sitemap controller/command/package config, `robots.txt`, OG image service/routes, SSR setup, blog schema, public booking routes/controllers, signed/secure routes, auth/admin/webhooks/APIs, error handling and tests. Inspect rendered HTML and HTTP headers, not only source files.

## 5. Scope

- Central metadata contract: titles/templates, descriptions, canonical, robots directives, Open Graph/Twitter, image/locale and safe defaults.
- Deterministic XML sitemap index/url sets from approved indexable route/content sources.
- Robots directives, global `X-Robots-Tag`/meta policies where appropriate and route-family classification.
- Canonicalization, parameter handling, redirects, trailing slash/host/scheme policy, 404/410 and internal crawler access.
- Truthful JSON-LD and breadcrumbs.
- Semantic/image technical checks and automated SEO tests/crawler.

## 6. Out of Scope

Search ranking guarantees, backlink campaigns, Search Console ownership, domain purchase, mass redirects/location pages, cloaking, hidden crawler content and changes to business behavior. External deployment/CDN configuration should be documented if not repository-owned.

## 7. Product Truth

Machine-readable claims follow the same evidence rules as visible copy. Good Hours is a SaaS product/operator entity, not the local salon/barbershop entities represented by tenant booking pages. Pricing/offers, company details, authors, dates and FAQs must match visible current authoritative data. Remove unsupported schema rather than filling it with guesses.

## 8. Information Architecture

Maintain a route-family policy with these explicit defaults, adjusted only by recorded evidence:

- Index/follow: approved substantive marketing, pricing, trust and published editorial pages.
- Conditional: selected public tenant booking landing pages only if owners consent, content is sufficiently unique, canonical ownership is clear and privacy/quality/moderation controls exist; otherwise noindex.
- Noindex/follow or noindex/nofollow as justified: auth/register/reset/verification, transactional booking steps, manage-appointment, secure form/file/waitlist links, previews, internal search/filter parameters and utility/test pages.
- Non-crawlable and no sitemap: authenticated business/platform/admin routes, APIs/webhooks, provider callbacks, signed/token URLs, health/internal diagnostics and error endpoints.

Robots disallow is not a privacy/security control and does not replace authentication or noindex.

## 9. UX Requirements

Canonical/redirect changes must preserve valid user journeys, attribution parameters and secure flows. Custom 404 should help anonymous users recover without leaking route existence; 410 is used only for intentionally permanently removed public resources. Social previews display accurate title/image/description. Breadcrumb UI and markup match.

## 10. UI / Design Requirements

Add branded accessible 404/410/server-error views appropriate to public versus authenticated context without exposing exceptions. OG images must use verified brand assets, readable safe text and stable dimensions. Do not render SEO-only visible blocks or visually hide keyword content.

## 11. Content Requirements

Define title templates and length/uniqueness guidance, not brittle character truncation. Descriptions are page-specific and human-readable. Canonical host/domain must come from reviewed configuration; because `getgoodhours.com` ownership remains OPEN-11, avoid falsely hard-coding it. Remove default keywords metadata if it has no supported purpose.

## 12. SEO Requirements

- Return self-canonicals for indexable pages and one canonical after redirect normalization.
- Normalize host/scheme/trailing slash consistently; document query parameters retained for function versus ignored for canonical.
- Sitemap uses canonical absolute URLs, correct content modification dates, valid XML and only 200/indexable pages; split/index if scale warrants.
- `robots.txt` points to the canonical sitemap and does not expose secret paths as a security list.
- Noindex pages must not appear in sitemap; blocked-by-robots pages cannot rely on unseen meta noindex.
- Redirects are server-side, minimal-hop and tested; missing/retired content returns accurate 404/410, not soft 200.
- Every indexable route is reachable through crawlable internal links and SSR output.
- Audit duplicates from locale, pagination, filters, route-model slugs, legacy paths and `/sitemap`.

## 13. GEO / AEO Requirements

Technical output must expose consistent entity names, definitions and factual page relationships in SSR HTML. Do not create `llms.txt`, AI crawler allowlists/blocks or special answer pages unless a documented, reviewed requirement and current standard justifies them. Human-visible content is canonical.

## 14. Structured Data Requirements

Create a centralized, testable graph with stable `@id` values only after facts are verified. Candidates: Organization, WebSite, SoftwareApplication/Product, Offer from active catalog, WebPage, Article/BlogPosting, BreadcrumbList and eligible visible FAQPage. Validate type eligibility and required/recommended fields. Never output placeholder founder/address/contact/awards/`sameAs`, reviews/ratings, certifications, local-business identity, stale offers or schema absent from visible content. Encode JSON safely and ensure one coherent graph without conflicting duplicate emitters.

## 15. Internal Linking

Crawl the site from Home and compare discovered URLs with the approved IA/sitemap. Fix broken links, redirect hops, canonical mismatches and orphan indexable pages. Breadcrumbs and pagination use anchors. Secure/transactional links may remain undiscoverable by design and must never be added merely to satisfy crawl coverage.

## 16. Conversion Requirements

Preserve verified CTA/login/signup and UTM behavior while canonicalizing tracking parameters away from the indexable identity. Provider return and signup-completion pages are not indexable. Social/SEO changes must not break conversion state.

## 17. Responsive Requirements

Metadata is device-independent, but public error pages, breadcrumbs, social images and all audit-remediated components must work at 360px/tablet/desktop and zoom. Verify mobile crawler SSR parity without user-agent cloaking.

## 18. Accessibility

Semantic HTML, heading/landmark correctness, alt text, link names and breadcrumb/error accessibility are SEO and WCAG requirements. Do not use ARIA to repair invalid structure. Social/structured metadata does not replace accessible visible content.

## 19. Performance Requirements

Metadata/schema generation must avoid N+1 queries, recursive crawling at request time and provider API calls. Generate/cache sitemaps safely from database route sources, invalidate on publish/change and avoid unbounded memory. Keep JSON-LD compact and OG generation secured/cached. Measure SSR overhead.

## 20. Analytics

Exclude crawler/bot traffic from conversion semantics where the existing system supports it; do not create invasive fingerprinting. Canonical stripping must not destroy server-side attribution. Analytics URLs/events must never leak signed tokens, client PII or private route parameters.

## 21. Security / Privacy Considerations

Authentication/authorization remains mandatory regardless of robots/noindex. Add noindex and cache protections to signed/private/transactional views without exposing secrets in canonicals, OG tags, logs, referers or analytics. Restrict/remove test OG endpoints if unsafe. Prevent XML/JSON injection and sitemap tenant-data leakage.

## 22. Implementation Instructions

1. Export current route/HTML/header/sitemap/schema baseline and reconcile with Prompt 14 matrix.
2. Define typed/server-owned SEO data contract with safe defaults and route-family policy; avoid page-specific Blade fragments.
3. Implement deterministic canonical normalization and redirect rules with environment/host awareness.
4. Replace runtime crawling with explicit sitemap sources and correct `/sitemap.xml` response/robots directive; handle legacy `/sitemap` deliberately.
5. Apply noindex/header policy to every private/auth/transactional/parameter/test family, especially `/book` and `/book/{slug}` descendants according to the recorded conditional decision.
6. Replace conflicting/fabricated schema with one verified graph and tests.
7. Add public error behavior and run an automated local crawl asserting status, canonical, indexability, sitemap and schema invariants.
8. Update architecture/IA/decisions/status with exact verified policy.

## 23. Do Not

- Do not treat `robots.txt` as access control, put secret URLs in it, or add private URLs to sitemap.
- Do not index auth, admin, webhooks, callbacks, secure appointment/form/file links or transactional booking steps.
- Do not blanket-index every tenant page or blanket-disallow valid marketing CSS/JS.
- Do not fabricate schema or use keyword/meta/schema tricks, cloaking, soft 404s or client-only redirects.
- Do not hard-code an unowned production domain.

## 24. Acceptance Criteria

- Every route family has tested index/crawl/canonical/sitemap behavior and marketing versus private/transactional separation is explicit.
- `/sitemap.xml` is deterministic, valid and contains only canonical 200/indexable URLs; robots references it correctly.
- Metadata/social tags are unique/valid and SSR-visible; canonical/redirect/error/parameter rules are consistent.
- JSON-LD contains only verified visible facts and passes syntax/eligibility validation.
- No secure token, tenant/client PII, provider callback or test utility is exposed through discovery metadata.
- Automated crawl, focused tests, accessibility/performance checks and production build pass.

## 25. Validation / Testing

Run route-family data-driven tests; anonymous/authenticated/signed URL checks; header/meta/canonical/OG/Twitter assertions; XML schema and sitemap-vs-indexability reconciliation; robots parsing; redirect-loop/hop and 404/410/soft-404 tests; JSON parse and validator checks; internal crawl/orphan/broken-link scan; SSR HTML inspection; query/performance tests; `npm run build`; mobile/error accessibility; console/hydration review; security regression and `git diff --check`.

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

Attach the final route-family indexation table, sitemap inventory, redirect map, schema graph/types, crawl results and whether Prompt 24 is unblocked.
