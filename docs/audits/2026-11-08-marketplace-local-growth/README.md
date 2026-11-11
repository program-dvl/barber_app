# Marketplace local growth implementation audit

Date: 2026-11-08, Asia/Kolkata. Scope: MPG-01–10, strategy stages A–D locally.
Stage E is a release package, not deployment approval. Existing uncommitted work
was inventoried and preserved. Baseline is the working tree, not HEAD.

## A: inspected baseline and gaps

| Boundary | Existing source/evidence | Gap before this change |
| --- | --- | --- |
| Public supply | MarketplaceListings, BusinessMarketplaceController | Published businesses default listed; explicit pause wins; no durable eligible service/branch projection or SEO review state. |
| Matching | DiscoveryCatalogue, DiscoveryFilters, DiscoveryController | Hair/barber/nails keyword assumptions (wrong legacy nail type), per-business catalog/media/review calls, unbounded availability work for date filters. |
| Booking | PublicBookingService, PublicBookingFlowController, Booking/Welcome.vue | Canonical guest hold/commit/resume already works; discovery_source is entry-route counter, not acquisition proof. |
| Reviews | ClientReviewService | Consent-only all-rating summary and withdrawal already authoritative; do not create another review source. |
| SEO | PublicIndexation, PublicSitemap, PublicUrl, StructuredDataGraph, SitemapController | Marketplace noindex and absent from sitemap; app.blade fallback has metadata but no useful Marketplace body. |
| Privacy/cache | ApplyHttpSecurity, HandleInertiaRequests, PublicAnalytics | Marketplace private no-store; Business/customer props prevent safe whole-page public caching. Existing GA allowlist excludes private journeys. |
| Owner/reporting | BusinessMarketplaceController, ReportQuery, payment_transactions | Starts/confirmations only; canonical append-only ledger exists, attribution does not. |
| Location | LocationData, Locations editor, reference-data | Owner entrance and reviewed address exist; no authoritative geography hierarchy or public-address choice for SEO. |

Read required strategy/module/PRD/decision/design/quality/deployment docs and both
lockfiles. Installed: Laravel 13.25.0, Inertia Laravel 2.0.25, Vue 3.5.34,
Inertia Vue 3.6.1, Vite 8.2.1, Pest 4.7.8, PHPUnit 12.5.33. System Node20 is
below the manifest minimum; use installed Node24 for build. No dependency upgrade.

## Taxonomy and geography contract

All Solutions keys are covered; audiences are not operational types/categories.

| Audience | Operational interpretation | Real eligible service families |
| --- | --- | --- |
| barbershops | barber_shop | barbering, hair |
| salons | hair_salon, beauty_studio, makeup_bridal | hair, beauty, nails |
| independent-stylists | independent_professional is an operating model | actual mapped families only |
| spas | spa | spa, massage, beauty |
| nail-salons | nail_studio plus legacy nail_salon | nails |
| medspas | skin_aesthetics is broad, not a medical qualification | aesthetics appointments only |
| massage | massage | massage |
| fitness-recovery | wellness is broad | fitness/recovery appointments |
| physical-therapy | no saved specialist type assumed | physical-therapy appointments |
| health-practices | no saved specialist type assumed | practice appointments |
| tattoo-piercing | no saved specialist type assumed | tattoo/piercing appointments |
| pet-grooming | no human grooming inference | pet grooming |
| tanning-studios | no saved specialist type assumed | tanning |

Final keys are verified against config/frontsite.php in tests. Versioned reviewed
aliases match exact normalized names and approved context; no substring clinical
classification. Tenant many-to-many corrections preserve original names/types.
Geography uses stable IDs and country/region/city slugs with source/reviewer/time;
branch bindings carry address fingerprint and explicit owner-publication evidence.
No guessed city, seeded live region, Google content import or automatic enrollment.

## URL and delivery matrix

| Entity | Canonical | Policy |
| --- | --- | --- |
| Business marketing | existing `/`, `/solutions/*` | existing rules unchanged |
| Provider/book entry | `/marketplace/salons/{slug}` | substantive eligible profile; SEO approval independent of hub density |
| Owner booking origins | existing book host and `/book/{slug}` | preserve journey/noindex; same entity canonical where eligible |
| Geography | `/marketplace/{country}`, `/{country}/{region}/{city}` | registry only, real supply and reviewed context |
| Audience | city path + `/{audience}` | actual matching offers, registry, distinct purpose |
| Service | city path + `/services/{concept}` | actual mapped eligible offers, registry |
| Pagination | clean route + `?page=N` | distinct validated page canonical, ordinary prev/next links |
| Search/filter/date/map | `/marketplace?...` | noindex, no sitemap, bounded retrieval |
| Account/token/hold/preview/API/errors | existing paths | noindex; private no-store preserved |
| Alias | real reserved old slug only | 301 to current equivalent; unknown/withdrawn 404 |

Separate listing eligibility, readiness, owner request and operator approval;
approval is revision-bound and default absent. Paused/withdrawn profiles stop delivery.
Empty hubs stay truthful noindex. Previously approved nonempty hubs have a
seven-day low-supply editorial review window, then noindex until requalified.
No fabricated supply or false redirect is used.
Global enabled + indexation flag + exact production origin + selected pilot IDs
are independent gates. Neither migration nor backfill enables indexing.

Public cache stores only allowlisted projection facts, invalidated by source revision;
never cache an authenticated HTML/Inertia response in a shared HTTP cache. No crawler
calls every provider's live availability. Actual time selection reuses canonical
services. Schema uses the same public facts, generic LocalBusiness where appropriate,
ItemList/BreadcrumbList and actual offers; no aggregateRating/review stars promise.

## Dependency and verification plan

A exits when MPG IDs, decisions, mappings, route/privacy policies and this plan exist.
B requires all-audience fixtures, current qualification/tenant gates, rollback/retry/
out-of-order reconciliation tests and bounded-query fixtures before C uses supply.
C requires raw HTML with SSR disabled/unavailable, header/meta/canonical/schema/
sitemap, alias/empty/withdrawal/filter pagination and identity-leak matrices.
D uses immutable flow attribution and live appointment/ledger aggregates; verify
failed/duplicate/resumed/replaced/cancelled/completed visits, refund/currency/tenant
boundaries, absent consent and unknown source. Stage E remains external.

Budgets: retrieval <=200 candidate offers, 12 businesses/page, <=100 suggestions;
no date search beyond 12 candidate businesses; crawler 0 availability calls; query
count independent of total inventory for retrieval. Synthetic 1,000-offer benchmark
records actual query/time/memory evidence, not production throughput. Existing
frontsite bundle budgets and client/SSR builds remain mandatory. Target field CWV
LCP2.5s/INP200ms/CLS0.1 are targets, not locally proven field results.

Attribution: operational entry != discovery origin != owner shared-link label.
Only consented 30-day observations may identify acquisition; missing is unknown.
No raw query/referrer/private URL/contact/IP/device fingerprint in analytics.
60-day optional evidence retention/pruning; no destruction of financial/visit history.
Owner reports use branch-local half-open dates, one currency, no customer details;
low-volume external aggregates suppressed below3. Search Console unavailable until
reviewed aggregate import/read authorization; no causality or incremental ROI claims.

## Evidence and remaining release work

Implementation/test/crawl results will be added after they run. No production
indexing, deployment, acquisition, revenue or visual-success claim follows from A.

### B dependency evidence before C

SupplyTest passed 5 tests / 69 assertions, plus the retry-repair case 1 / 7.
All 13 audiences, null/legacy operational types, exact unmapped search, source rollback,
duplicate stale jobs, explicit pause, qualification/expiry and composite foreign-tenant
constraints verified locally on SQLite. 100 businesses / 1,000 qualified offers:
retrieval+suggestions 3 queries, 8.76ms measured, 114,294,784-byte peak test-process
memory; zero availability calls. This is local query-budget evidence, not MySQL
concurrency or production throughput. C may consume this read projection; remaining
UI/owner command and broad regressions are part of final validation.

### C dependency evidence before D

SeoTest passed 7 / 104 (SSR-disabled/failure fallback, profile-before-hub, exact
origin/pilot/default-off policy, schemas, private paths, real aliases/withdrawal,
13-provider pagination and material-change review). The separately enabled real
built Node24 SSR gateway test passed for both directory and canonical booking HTML.
The sandbox blocked port binding/contact; scoped local-server/test escalation
succeeded. No synthetic HTML renderer stood in for this SSR test. Browser and
production-origin review remain final/release evidence, respectively.

## Final local evidence — 2026-11-09

The A contracts preceded behavior changes; B projection/retry evidence allowed C,
and C initial-HTML/default-off/real-SSR evidence allowed D. This records the local
implementation and limits; production launch gates remain outstanding.

- Exact PHP class sweep: **1,310 passed, 31 skipped, 14,166 assertions**, using 109
  separate serial class processes. One old privacy-copy expectation failed in
  the sweep and passed on recheck with the owner-requested cookie labels. The
  initial combined process exceeded the suite's256MB limit; isolated class runs
  avoid that issue and overlapping fake filesystem stores. The runner loads all
  definitions because older tests share fixture functions across files.
- Growth classes after formatting: attribution6/106, private customer photos4/49,
  owner workflows3/50, SEO8/126 (one opt-in live SSR skip), supply7/81 on its final
  recheck. Supply covers13 audiences, nail legacy/null, unknown/exact names,
  non-English alias safety, hard qualification/tenant gates, rollback, duplicate
  jobs, missed-work repair, temporal expiry and bounded retrieval.
- The separate **real built SSR + fallback SEO run passed9/141**. It fetches actual
  rendered directory and canonical booking content, then verifies outage fallback,
  exact-origin/pilot policy, HTML/schema facts, pagination, unknowns, withdrawal,
  cookies and unchanged material lastmod. No synthetic SSR gateway was substituted
  for the opt-in live-renderer case. The final raw HTTP crawl covers12 paths and
  keeps all local indexation directives off.
- **237 frontend tests passed**, including actual compiled Profile and shared
  AppDialog: avatar picker, preview/save, draft preservation, corner-removal
  confirmation, cancel with no request, safe focus, duplicate-submit disable and
  failure preservation. My bookings/Inbox/Conversation interactions remain covered.
  Final account backend recheck:36 passed/535 assertions.
- Client+SSR build on installed Node24 passed; frontsite budgets passed25 route
  entries including Marketplace and canonical booking. Existing CSS/font warnings
  remain. No package upgrade was needed. Scoped Pint and whitespace checks passed.
- Final synthetic100-business/1,000-offer fixture uses4 retrieval+suggestion SQL
  queries with a no-live-availability expectation. Exact elapsed time and process
  memory are in [load-local.json](load-local.json). This supersedes the earlier
  three-query measurement after adding the bounded new-business candidate pool.
  It is SQLite local evidence, not production-engine throughput/concurrency or
  field Core Web Vitals.14 MySQL concurrency tests remain configuration-skipped.

[Validation records](validation.json), [raw crawl](crawl-local.json), and
[actual screenshots with precise limitations](visual-evidence.md). The final
corner ×/confirmation and compact account control revision could not be visually
checked: CUA returned “Local browser use is disabled in Work settings”. Owner
publication/report views and full visual booking journey remain pending too.
The earlier user-selected photo draft tab was preserved. No browser save/upload
success is inferred from private raster/backend tests.

### Implemented paths and operating boundaries

- `config/marketplace-{growth,taxonomy}.php` and migrations000005/000006/photo:
  safe flags, many-to-many mappings, stable area/address bindings, projected
  service offers/terms/work, SEO registry and versioned attribution lineage.
- `app/Domain/Marketplace/LocalGrowth/`: current-source projection/jobs, live hard
  gates, bounded search/ranking, editorial readiness/approval, shared public DTO,
  sitemap, consent/retention, outcome report and aggregate import.
- Marketplace controllers/routes: clean registry routes, existing canonical
  provider booking, separate acquisition endpoint, sitemap shards, owner report
  and existing editor/readiness handoffs. Schema contains actual public facts,
  generic LocalBusiness IDs and no review-star/medical endorsement claims.
- `resources/views/marketplace/public-content.blade.php` + app fallback, Local and
  Discover Vue pages: meaningful initial HTML with ordinary links, useful empty
  states, real services/prices and public consented reviews. Maps remain optional.
- Private CustomerPhoto service/controller/route and Profile/account shell: raster
  validation/crop/metadata stripping, own-session photo delivery, avatar picker,
  corner × tooltip, shared confirmation and compact desktop/mobile controls.
- Existing checkout delegates receipt creation/event production to ReceiptService;
  the discovered duplicate event producer was removed. Repeated manual payment
  confirmation now retains one receipt event and existing amount/currency metadata.
  Financial ledger history is not rewritten.

Evidence records source hashes rather than an all-working-tree patch because
unrelated prior user edits must remain intact. All schema changes are unreleased
local candidates. No preview/demo database was deployed; synthetic QA used only
a new guarded `/tmp/clipperdesk-marketplace-growth-qa-*.sqlite` file.

### Release actions and unresolved external evidence

The [stage-E package](../../release/2026-11-08-marketplace-local-growth/README.md)
contains safe flags, exact-origin/pilot gate, migration/backfill/repair/rollback,
empty real reporting/import/cohort templates and a weekly operating checklist.
No real market/cohort, geography authority, Google credentials or revised legal
approval was fabricated. Optional Google read data remains unavailable until an
authorized aggregate export exists. The revised privacy disclosure requires its
reviewed version/effective-date/operations approval before production; existing
policy approval is not renewed by local tests.

Final browser/accessibility checks, production-engine migration/concurrency/load
rehearsal and the existing production launch/indexation authorization remain
required. No production robots/indexation, DNS, deployment, Search Console,
Google Business Profile, outreach, advertising or spending was changed. No local
result establishes Google indexing/ranking, acquired customers, incremental
revenue or competitor superiority.

The final live crawl also exposed missing normal route configuration when the
generic error renderer bypassed shared middleware. Exception rendering now carries
the same Ziggy definitions; the real SSR test verifies an invalid private token
returns useful Page not found HTML,404, noindex and private no-store. No private
record, token validity or customer facts were added to public analytics.
