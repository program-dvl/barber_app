# Codex implementation prompt: Marketplace local discovery and organic growth

Copy this prompt into a new Codex chat opened in
`/Applications/AMPPS/www/barber_app`.

---

Implement the local discovery and organic acquisition system specified in
`docs/strategy/marketplace-local-growth-plan.md`.

This is an implementation request. Complete stages A-D locally, including meaningful
tests, documentation and a reviewable release package. Do not stop after a plan,
mock UI, metadata-only change or a handful of placeholder location pages. Progress
through the dependency gates below; do not claim production ranking, Google indexing,
customer acquisition, deployment or revenue from local evidence.

## Product objective and scope

Clipperdesk should help a newly onboarded appointment business gain relevant local
customer discovery, confirmed appointments and completed visits. Report attributed
outcomes honestly. Customer payments remain manual through existing business sales/
collection recording; no booking gateway, commission system or paid placement.

Cover ALL supported audiences in `config/frontsite.php` `/solutions`: barbershops,
salons, independent stylists, spa/sauna, nail salons, medspas, massage, fitness/
recovery, physical therapy, health practices, tattooing/piercing, pet grooming and
tanning studios. Do not implement a hair-only directory or assume these marketing
audiences equal saved operational business types or service categories.

Read the strategy in full. The owner manages shared booking links, Google Business
Profile and WhatsApp marketing independently. Do not build those integrations,
post to third-party accounts, send outreach or spend advertising money. No new
clinical workflows, classes, memberships or unsupported professional claims.

## First inspect the actual repository

Read AGENTS.md and its required documents, then:

- `docs/strategy/marketplace-local-growth-plan.md`;
- `docs/modules/marketplace.md`, `public-website.md`, `location-intelligence.md`,
  `services.md`, `business-setup.md`, `client-reviews.md`, `reports.md`;
- `docs/public-booking-journey.md`, `docs/deployment/search-indexing.md`;
- `docs/design-system.md`, `docs/quality-and-testing.md`;
- current `composer.lock` and `package-lock.json` for package versions.

Inspect existing source before deciding on new tables/components:

- `app/Support/Seo/{PublicIndexation,PublicSitemap,PublicAnalytics,PublicUrl,StructuredDataGraph}.php`;
- `app/Http/Controllers/SitemapController.php`, `routes/marketplace.php`,
  `app/Http/Middleware/{ApplyHttpSecurity,HandleInertiaRequests}.php`;
- `app/Domain/Marketplace/Services/{MarketplaceListings,DiscoveryCatalogue,DiscoveryFilters}.php`;
- Marketplace controllers; public booking service/controller/flow; review services;
- existing report/checkout/financial ledger projections and authorization;
- `config/{frontsite,reference-data,business-onboarding}.php`;
- `resources/views/{app,seo/metatags}.blade.php` and Marketplace/booking Vue pages.

Discover equivalent files if paths changed. Preserve all existing uncommitted user
work. Do not reset, discard, overwrite unrelated edits or deploy a preview database.

## A. Audit and record the implementation contract

Create `docs/audits/<date>-marketplace-local-growth/README.md` with current behavior,
gaps, source paths and evidence. Do not describe the strategy as already implemented.
Add approved implementation requirement IDs (e.g. MPG-01 onward) and accepted
choices to the PRD/decisions/module documentation before changing behavior. The
execution of this prompt authorizes the narrow strategy implementation locally;
other deferred modules and production release gates are not waived.

Record:

1. All-audience taxonomy/mapping and geography provenance.
2. One canonical URL for every public entity and the clean directory route matrix.
3. Separate listing eligibility, SEO readiness, publication and withdrawal states.
4. Route/header/meta/sitemap/schema/cache and private-page rules.
5. Attribution definitions, privacy boundaries, retention and performance budgets.

Use existing records to resolve routine choices; do not ask questions already
answered by the strategy/repository. Record nonblocking assumptions. If a truly
required production credential, legal approval or market choice is missing, finish
all independent local work and provide a concrete operator action; do not fabricate
live validation or silently enable indexation. Keep all publication flags safe.

A exit: the contracts are recorded, broad scope is mapped to existing requirements,
and an explicit dependency/test plan exists. No public gate flipped yet.

## B. Build supply projection and multi-industry discovery

- Introduce explicit many-to-many service concepts/families, audience mappings,
  reviewed aliases and stable geography IDs. Preserve tenant catalogue names and
  original operational business types. Reconcile `nail_studio` versus legacy
  `nail_salon` assumptions and other existing values without data loss.
- Build a tenant-safe public listing/service/location read projection with source
  provenance, idempotent updates after commit, retries and reconciliation. Reuse
  existing event/outbox patterns when present. Never expose CRM/consultation records.
- Wire existing onboarding/publication/edit/pause/closure/review-consent changes to
  the projection. Registration alone must not publish unfinished/private data.
- Replace three-category assumptions with all-audience matching/suggestions using
  actual eligible public services, staff/branch qualification and owner visibility.
  Ambiguous mappings stay exact-name searchable rather than acquiring false labels.
- Implement bounded geo/service retrieval, deterministic ranking and clear nearby
  alternatives. Relevant new businesses get bounded exploration without needing
  fake reviews. Preserve honest rating inclusion and version the ranking policy.
- Keep owner corrections inside existing setup/Services/Locations workflows and
  permissions. Add a compact useful publication/readiness panel, not duplicate setup
  forms or a fake SEO score. Reuse existing booking links.
- Use database/read-model primitives first; no new search SaaS or microservices
  unless actual benchmark evidence establishes a need and separate approval exists.

B exit: new and existing businesses across all audiences map correctly; inactive/
paused/unqualified/foreign branches cannot leak; updates/removal work under retry,
rollback and out-of-order events; query counts are bounded on realistic load fixtures.

## C. Build inventory-backed organic landing pages

- Preserve Business root and current `/solutions` acquisition URLs. Keep the existing
  canonical provider/booking page, with backwards-compatible alias behavior. No
  duplicate new service-selection page before canonical booking.
- Build clean geography/business-type/service hubs from real supply and a reviewed
  SEO page registry. Do not enumerate a city x service Cartesian product. Readiness
  thresholds are configurable editorial choices, not claimed Google requirements.
- An eligible substantive individual profile can qualify before its city has enough
  providers for a hub. Photos/reviews are not arbitrary mandatory quotas.
- Render useful public content in initial guest HTML, including an SSR-unavailable
  fallback using shared projections. Test actual HTML content and ordinary links,
  not just Inertia JSON props, JS-rendered screenshots or title tags.
- Centralize conditional robots headers/meta, canonical URLs, breadcrumbs, accurate
  entity/schema data, stable IDs, sitemap index/shards and real status codes. Keep
  arbitrary search/filter/date URLs, accounts/tokens/holds/private pages noindex and
  out of sitemaps. Preserve crawler access required to read noindex directives.
- Make guest public caching independent of customer/Business authentication. Test
  cookies and shared caches for identity, secure-link or precise-location leakage.
  Do not remove existing no-store policies from private routes.
- Add cache invalidation, accurate material-change lastmod and alias handling.
  Slot scarcity is not a closure. Withdrawn/paused listings cease public delivery;
  invalid paths return 404 and only real equivalents receive 301 redirects.
- Display real public information/prices, properly contextualized industry content,
  useful geography links and actual consented reviews. No fabricated providers,
  copy-swapped SEO prose, imported Google content or clinical claims.
- Use Maps optionally after a useful list loads. Do not run every provider's live
  availability engine during crawler page rendering. Revalidate slots through the
  existing booking hold/commit services.
- No Google Indexing API misuse, automatic rich-result claim or implied Google Book
  integration. Research current official guidance before implementing schema/indexing.

C exit: raw-HTML/crawler matrix passes for allowed and private routes, SSR outage,
empty supply, filtered pagination, locale/currency, pause/withdrawal and aliases.
No schema/HTML business facts disagree. Indexation is still default-off globally
until the existing production launch gate has been explicitly satisfied.

## D. Connect discovery to honest booking outcomes

- Preserve existing reservation, timezone/DST, tenant, customer ownership and manual
  collection rules. No new customer-login requirement or booking-link middle step.
- Separate discovery origin, owner shared-link source and actual booking entry.
  Preserve original provenance across login/redirect/resume/retry and rescheduling.
- Distinguish operational records from consent-requiring acquisition analytics.
  Honor existing privacy/analytics controls; unknown stays unknown. No fingerprints,
  customer search phrases, private URLs or CRM data sent to public analytics.
- Store versioned attribution evidence and confidence. Untrusted UTM/referrer labels
  must never authorize commissions, billing, account access or claims of causality.
- Show authorized owners attributed confirmations, completed visits, cancellations/
  no-shows, new-to-their-business clients, and ledger-recorded collections/refunds.
  Keep booked value separate from collected value and currencies separate. Avoid
  cross-tenant client identity or appointment details.
- Search Console data is aggregate and optional until an authorized read connection
  exists. Provide a documented import/interface; do not invent data or pause other
  work solely because no Google credentials are present.
- Use privacy-safe counts with sensible time zones, ranges and low-volume handling.
  Do not claim incremental revenue or guaranteed rankings without supporting evidence.

D exit: tests cover duplicate/out-of-order events, failed confirmation, abandonment,
resume, cancellation/replacement, refunds, multiple currencies, multiple tenants,
owner-vs-Marketplace sources, missing consent and unknown attribution. Owner reports
reproduce underlying ledger/appointment facts without double counting.

## Validation, documentation and handoff

Run targeted meaningful tests, required build/frontsite budgets and whitespace
checks. Add load/query-budget evidence for the new projections and retrieval. Use
isolated synthetic fixtures for all supported industries; never index demo inventory.
Visually check actual desktop/mobile search, local pages, publication notices and
booking/reporting journeys using the available browser. If browser access is blocked,
state the exact gap; no substitute screenshots or unsupported visual success claim.

Update `docs/project-status.md` only with verified facts, affected module/design
contracts, decisions and the audit. Include:

- implemented components/routes/migrations and safe backfill/recovery commands;
- quality/indexation policy matrix and screenshots/crawl reports when obtained;
- performance/test results, attribution definitions and source/provenance limits;
- release feature flags, exact-origin checks, owner-selected pilot cohort and rollback
  that preserves history; private-page noindex must survive both enable and rollback;
- a weekly operating checklist and empty-but-real reporting templates for the pilot.

Do not change production robots/indexation, Search Console, DNS, external campaigns,
Google Business Profiles or spend money. Prepare stage E as a concrete release
package and request any actually required production authorization only after the
local implementation is reviewable. Do not ask for repetitive implementation approval.

Finish with a concise implemented/verified/pending summary and clickable file links.
"Better than competitors" is a target to prove with relevant completed visits and
owner outcomes, not a marketing assertion to add to the website.

---
