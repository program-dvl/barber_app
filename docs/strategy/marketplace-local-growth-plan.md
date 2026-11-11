# Clipperdesk Marketplace: local discovery and business growth

Date: 2026-11-08 (Asia/Kolkata). Status: proposed strategy and implementation brief,
not evidence of shipped SEO, rankings, demand or revenue. Requested by the owner.
Companion: [separate-thread implementation prompt](../prompts/marketplace-local-growth.md).

## 1. Outcome and commercial principle

Help a newly onboarded appointment business become discoverable for the services it
actually offers in the places it actually serves, convert relevant demand into a
booking, and show the owner the resulting completed visits and recorded sales.

The primary outcome is **additional completed visits from Marketplace discovery**.
Profile views and Google impressions diagnose progress; neither is income. A saved
appointment value is not collected revenue. Customer payments remain manual; report
recorded collections from the existing financial ledger separately.

Build an acquisition product, not a collection of keyword pages:

1. A business publishes accurate, eligible services and locations.
2. Clipperdesk prepares its public profile and appropriate local discovery pages.
3. A customer arrives from search/editorial links or searches inside Marketplace.
4. Useful local results lead directly into the existing booking journey.
5. Confirmation, completion and recorded sale outcomes are attributed honestly.
6. Real public reviews and updated business information improve the next discovery.

Owners may share their booking links in Google Business Profile, WhatsApp, their
website or elsewhere. Those are owner-managed channels; no Google profile management,
WhatsApp campaign engine or external posting is part of this project.

## 2. All supported industries, with one discovery architecture

The supplied live `/solutions` URL could not be fetched by the research tool.
The current repository source, `config/frontsite.php`, defines these 13 audiences:

| Supported audience | Example customer intent, only if actually offered |
| --- | --- |
| Barbershops | Skin fade near a chosen neighbourhood |
| Salons | Hair colour appointment in a city |
| Independent stylists | A stylist offering a specific cut locally |
| Spa & sauna | A bookable sauna or spa appointment |
| Nail salons | Gel manicure nearby |
| Medspas | A named, legitimately offered aesthetics consultation |
| Massage | A massage appointment in the selected area |
| Fitness & recovery | An individual training or recovery appointment |
| Physical therapy | A provider offering an appointment type locally |
| Health practices | A named practice's published appointment service |
| Tattooing & piercing | A local piercing appointment or tattoo consultation |
| Pet grooming | A dog grooming appointment in the selected city |
| Tanning studios | A published tanning session locally |

These are examples of search intent, not statements that inventory or specialist
capabilities exist. Support the existing appointment model; do not add classes,
memberships, diagnosis, insurance, prescribing or clinical workflows by implication.

Business audience, operational business type and bookable service are different
concepts. Reconcile `frontsite.solutions`, `reference-data.business_types`,
`business-onboarding.business_types` and actual saved values through explicit
mappings. In particular, current discovery assumes `nail_salon`, while the reference
catalogue uses `nail_studio`. Do not silently rename or lose existing businesses.

Use a many-to-many discovery taxonomy: audience -> service family -> service concept.
A multi-service business can offer several families. Map its actual published service
to a concept without replacing its own service name, price, duration or eligibility.
Keep ambiguous services unmapped until reviewed, but searchable by their exact name.
Use controlled synonyms, locale and category context; distinguish dog grooming from
human grooming, sports massage from physiotherapy, and consultations from treatments.
An independent professional may offer hair, nails, massage or another supported
service; do not equate that operating model with one service family.

For health-related industries, publish factual provider/service information only.
Do not invent credentials, treatment benefits, efficacy claims or emergency support.
Include public qualifications only when supplied and appropriately reviewed. Use
an accurate schema type; never manufacture a medical endorsement.

## 3. Baseline: what exists and what must change

Repository observations in this planning turn:

- Marketplace has published-business eligibility, canonical booking, customer
  accounts, public consented reviews, local map pins and session-only location search.
- Discovery's broad editorial categories are currently only Hair/Barber/Nails.
  `DiscoveryCatalogue` aggregates public services but uses keyword category matching.
- `PublicIndexation` does not allow Marketplace routes; existing profiles/search are
  currently noindex. `PublicSitemap` contains Business/help/editorial pages, not a
  Marketplace directory. This is deliberate current launch posture, not an indexing bug.
- The initial HTML has metadata fallbacks, but indexable public content must be
  verified with SSR healthy AND unavailable; metadata alone is insufficient.
- Marketplace flow counters identify starts/confirmations, not a complete acquisition
  or incremental-revenue attribution system.
- Existing public analytics is consent-based and restricts permitted routes; it
  must not simply be enabled on private booking/account URLs.
- Earlier local counts include demonstration fixtures. No production supply count,
  Search Console performance baseline or competitor ranking advantage was verified.

Preserve the Business root and `/solutions` acquisition pages. Consumer search pages
belong under `/marketplace`; a root-domain migration is not needed for this plan.

## 4. The three discovery surfaces

### A. Google and other external organic search

Create useful, crawlable public pages that answer an actual service/location query.
Google decides whether and where to show them. Structured data and a sitemap do not
guarantee appearance, position, review stars or bookings. Avoid thousands of empty
city/service combinations; Google identifies doorway and scaled low-value content
as spam risks. [Google spam guidance](https://developers.google.com/search/docs/essentials/spam-policies).

### B. Search inside Clipperdesk Marketplace

Once someone reaches Clipperdesk, we control service matching, location relevance,
result quality, fair exposure and booking usability. This is where a new eligible
business can receive relevant discovery immediately after publication, even before
Google indexes its profile. No fake availability, fabricated popularity or paid
placement mixed into organic order.

### C. Google Maps / local pack

Our Google-powered map displays participating Clipperdesk businesses; it does not
make them Google-verified or place them in Google's own local pack. Google's local
ranking depends on relevance, distance and prominence. We can maintain accurate
owner-supplied data and support a useful booking destination, but cannot promise
local-pack placement. Owner-managed Google profile activity remains outside scope.
[Google local ranking](https://support.google.com/business/answer/7091?hl=en).

## 5. Inventory-led SEO page architecture

Use country/region/city identifiers, not ambiguous city names alone. Store normalized
geography and approved neighbourhood relationships from owner-reviewed records;
keep provider data provenance explicit. Postal codes and coordinates are not a
licence to generate every possible locality page.

Proposed routes (illustrative city, provider and service names are not real inventory):

| Page | Example | Indexing rule |
| --- | --- | --- |
| Marketplace entry | `/marketplace` | Once global launch gate passes and useful public content exists |
| Country hub | `/marketplace/ca` | Only for supported, reviewed supply; no empty country matrix |
| City hub | `/marketplace/ca/on/hamilton` | Useful real supply and approved local content |
| Business-type city page | `/marketplace/ca/on/hamilton/pet-grooming` | Inventory-backed audience with distinct intent |
| Service city page | `/marketplace/ca/on/hamilton/services/gel-manicure` | Real eligible services and useful local choice |
| Provider profile | Existing `/marketplace/salons/{slug}` | Eligible, substantive public profile; canonical booking entry |
| Branch page, selectively | Optional stable branch identity | Only when genuinely distinct public location/content justify it |
| Arbitrary filters, dates, internal query results | Search query strings, times, map state | Noindex; exclude from SEO sitemaps |
| Customer/account/token/hold pages | Private routes | Never index or expose through directory links/schema |

Keep the existing provider route as the initial canonical even though its path says
`salons`; public labels must say the appropriate business type. A later neutral URL
migration requires a separate alias/301 and canonical plan, not two indexable copies.
Use one branch model across multi-location pages; do not automatically index one
copy per branch, staff member or service. Service-specific booking choices can use
existing query parameters without becoming independent indexable documents.

Every eligible business can have its own indexable profile without waiting for a
city's inventory threshold. Broader city/service hubs need enough usefulness to
stand alone. Suggested starting editorial gate: three distinct relevant businesses
for a service/city hub, otherwise review manually or keep it noindex. This is our
proposed quality threshold, NOT a Google requirement or a ranking formula. A single
provider profile can be valuable; a near-identical city doorway usually is not.

Each approved hub contains actual matches, accurate starting-price context within
one currency, visible provider information, reviewed local context, useful links
and direct booking actions. Optional FAQ content answers real questions, not repeated
keyword variants. Never scrape third-party reviews/photos or fabricate local claims.
Neighbourhood pages are selective and geography-accurate, not a second city matrix.

### Indexation state and content lifecycle

Separate `marketplace_listed`, `seo_eligible` and `seo_published`. Proposed state model:
`draft -> ready for review -> approved/public -> paused/withdrawn`, with reasons,
authorization, audit and revision checks. Signup alone does not imply Google-ready.
Maintain the existing owner's Marketplace pause and online-booking publication rules.

A profile needs accurate public name/location/service information, at least one
currently eligible service/branch/professional path, a safe contact/booking route,
and approved public visibility. Photos improve conversion but are optional; new
businesses need no reviews, arbitrary photo quota or keyword-stuffed description.
Do not expose a home address without the applicable owner's publication choice.

Publication/update/pause after commit should idempotently rebuild affected public
projections and invalidate only relevant caches/sitemap shards. Use an outbox or the
repository's existing reliable event mechanism; retry/reconciliation must repair a
missed update. Pauses remove public delivery/linking promptly, not just at next crawl.

Distinguish temporary lack of slots from closure: do not deindex a business whenever
it is fully booked. For withdrawn listings remove public data and apply noindex or
appropriate 404/410 behavior; only 301 when a real equivalent replacement exists.
A category losing one provider should not flap indexation daily; define a review
window and show honest current results. Unknown/invented routes return real 404s.

## 6. Automated onboarding journey

1. Owner completes normal setup; existing publication/readiness checks still apply.
2. Reuse the public name, reviewed locations, actual services, team assignments and
   public-consented reviews. Exclude all private CRM/consultation data.
3. Suggest taxonomy mappings from starter provenance and approved aliases; let the
   owner correct them. Uncertain mappings never silently become clinical claims.
4. Show one useful discoverability panel: what is published, what needs correction,
   profile preview and booking link. Examples: “Add your public location” or
   “Choose the services customers can book”, not an invented SEO score.
5. Queue only the affected profile and already-approved relevant hubs for refresh.
6. Show accurate states: “Available on Marketplace”, “Ready for search publication”,
   or “Submitted in sitemap”. Say “Indexed by Google” only with recent evidence.
7. The owner can copy their existing link. We do not publish to their Google or
   WhatsApp accounts, take over campaigns, or claim external performance as ours.

New businesses must get a fair chance without fake reviews: eligible new profiles
can appear in relevant Marketplace results and in clearly labelled new-business
sections. Exposure remains conditioned on service and location relevance.

## 7. Retrieval, matching and fair ranking

Start with structured SQL/read projections in the modular monolith. Benchmark before
adding a search vendor. Proposed components: discovery taxonomy, geography resolver,
public listing projection, eligibility policy, ranker, SEO page registry, sitemap
projection and attribution service. Reuse existing domain modules instead of parallel
catalogues, customers, schedules or financial stores.

Hard gates before ranking: active/public/listed business, eligible branch, actual
matching public service, current staff qualification and any existing client policy.
Use exact concepts/approved synonyms first; bounded partial-name matching second.
Location uses reviewed branch coordinates/area records, not IP assumptions. Show
nearby alternatives only as alternatives and preserve the requested area visibly.
Never silently call a distant provider “nearby”. Browser location remains optional.

Initial ranking priorities, not a claimed optimal mathematical formula:

1. Service/intent match and eligibility.
2. Requested locality or geographic distance.
3. Relevant bookability signal, with honest freshness.
4. Public review confidence with a neutral new-business prior; do not bury zero-review
   businesses or compare unrelated specialties as if their ratings were identical.
5. Bounded exposure rotation among similarly relevant eligible businesses.

Implement deterministic tie-breaking, ranker versioning and testable explanations.
Allocate a proposed 10% exploration slot among comparable candidates to new/under-
exposed businesses; test and tune rather than promise every business equal bookings.
Do not boost an unsuitable provider to meet an exposure target. Do not suppress low
ratings selectively. Keep sponsored placement out of this initial organic engine.

Broad SEO pages use cached public facts, not live availability across every provider.
Filter/date requests can evaluate a bounded candidate set through canonical services.
Final slot search/hold/commit always revalidates. Never advertise a stale cached slot
as reserved. Add query-count and response-time budgets with realistic generated
load fixtures; no demonstration fixture becomes real public inventory.

## 8. Technical SEO acceptance contract

- Centralize route-specific robots, canonical, metadata, sitemap and schema policy.
  Update response headers AND HTML/Vue metadata; no blanket Marketplace allowlist.
- Render useful provider names/services/locations/prices/public content and ordinary
  links in initial guest HTML, without requiring Google Maps or JS execution.
  Test SSR failure; use a shared-projection server fallback rather than two divergent
  public content implementations. Preserve the existing canonical booking component.
  [Google JavaScript SEO guidance](https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics).
- Keep signed-in personalization, email, profile, precise search centre and secure
  booking links out of cacheable guest HTML and structured data. Cache only public
  projections. Do not change existing private/no-store responses wholesale.
- Implement consistent canonical host, stable entity IDs, real status codes, alias
  301s, unique titles/descriptions, absolute image URLs and crawlable pagination.
  Self-canonicalize distinct paginated pages; do not canonicalize every result page
  to page 1 and thereby hide later inventory.
- Only selected clean landing routes are indexable. Noindex arbitrary filter/date/
  sort/location-query variants; do not let a crawl block prevent Google seeing a
  needed noindex directive. Control discovery of infinite filter combinations.
  [Google faceted-navigation guidance](https://developers.google.com/crawling/docs/faceted-navigation).
- Sitemap index/shards contain only eligible canonical200/indexable URLs. Accurate
  material-change `lastmod`, not daily timestamps. Existing sitemap remains compatible.
- Use accurate `LocalBusiness` subtypes where appropriate, stable entity identifiers,
  visible address/hours and actual public reviews; `BreadcrumbList`/`ItemList` for
  directory structure. Service/Offer markup must reflect real services/prices, not
  invented products. Validate applicable review markup rules before adding ratings.
  [Google LocalBusiness reference](https://developers.google.com/search/docs/appearance/structured-data/local-business).
- No promises of rich results, “Book with Google” integration or Google verification
  from schema alone. Google's Indexing API is not for these ordinary listing pages.
  [Supported Indexing API content](https://developers.google.com/search/apis/indexing-api/v3/using-api).
- Mobile-first loading: useful list before optional map, bounded media, clear prices
  and one existing booking journey. Aim for field p75 LCP<=2.5s, INP<=200ms, CLS<=0.1
  as proposed engineering targets; laboratory results do not establish field results.
  [Core Web Vitals guidance](https://developers.google.com/search/docs/appearance/core-web-vitals).
- Textual facts and honest crawlable pages also serve AI-assisted search. Do not sell
  a special AI schema, ranking promise or `llms.txt` as a substitute for this work.
  [Google AI search guidance](https://developers.google.com/search/docs/appearance/ai-features).

## 9. Measurement the owner can trust

Store the original acquisition observation separately from the booking entry route.
A visit to a provider's direct link is not automatically a newly acquired Marketplace
client. A later logged-in return must not overwrite the original source.

Proposed funnel: eligible result impression -> profile visit -> booking start ->
confirmed -> completed -> manually recorded collection. Record only events supported
by actual server/view evidence. Do not call server-rendered-but-unseen cards viewed.
No unconsented tracking across private journeys; distinguish necessary operational
records from optional acquisition analytics with explicit privacy review. When
consent/source evidence is missing, use unknown instead of fingerprinting.

Use a versioned attribution policy: preserve first known source, last non-direct
source and booking entry separately, with a proposed 30-day window. Treat campaign
parameters as untrusted labels, not proof that Clipperdesk generated demand. Do not
accept public parameters as commission, billing or new-client eligibility evidence.

Show owner-visible, tenant/branch-scoped reports for:

- Marketplace-attributed confirmations, completions and cancellations/no-shows.
- New-to-this-business clients, defined from authorized historical client/visit
  records, not global Marketplace signup and not cross-tenant identity disclosure.
- Appointment value versus actually recorded collections, refunds and net recorded
  collections from existing append-only facts, in separate currencies.
- External direct/owner-link sources separately from Marketplace discovery.
- Attribution confidence, date range, time zone and known gaps.

Do not claim proven incremental lift from last-click reporting. Use a controlled
rollout or comparable cohorts to estimate incremental value; without that evidence
label figures “attributed”, not “additional revenue we generated”.

Search Console provides aggregate query/page/impression/click data, not a customer's
search phrase. Import manually or via a separately authorized read-only connection;
keep unavailable/suppressed data unavailable. Separate branded-provider demand from
non-branded service/location demand.
[Search Console performance definitions](https://support.google.com/webmasters/answer/7576553?hl=en).
Avoid a cosmetic ROI percentage with missing
costs, unverifiable causality or unknown collections.

## 10. Marketing operation: build density, then expand

The architecture supports all 13 audiences. Do not launch 13 thin local categories in
every city. Select one or two geographic clusters based on real active providers,
public service coverage, useful appointment supply, local demand evidence and owner
support capacity. Choose the initial vertical mix from that supply; it need not be
hair/beauty. No target city is approved just because a demo account uses its name.

Weekly operating rhythm:

- Monday: inspect real supply, listing readiness, failed ingestion and booking errors.
- Tuesday: review approved local pages against queries, accuracy and useful choice.
- Wednesday: improve weak provider content and publish a small, inventory-backed
  service guide answering a customer decision, not a keyword-swapped article.
- Thursday: run owner-authorized local partnerships and editorial outreach with
  relevant associations, community publishers and local events. No bulk link buying,
  hidden links, fabricated reviews, scraped lead lists or automated unsolicited sends.
- Friday: examine non-branded discovery, conversion, completed visits, cancellations,
  exposure distribution and recorded collections; ship one evidence-based improvement.

Clipperdesk can create useful city/service guides, public profile previews and an
owner onboarding kit. Owner-managed Google/WhatsApp sharing is independent. Paid
ads are a later measured experiment requiring an explicit budget and authorization;
no spend or campaign account provisioning is included in the implementation prompt.

## 11. Competitive position: an execution standard, not a boast

Fresha already documents discovery/online-presence metrics and booking-funnel
reporting. Salonist already describes public service/location search and individual
profiles. Those features are a baseline, not our unique invention.
[Fresha online-presence dashboard](https://www.fresha.com/help-center/knowledge-base/online-presence/155005-knowledge-base-article-5),
[Salonist marketplace description](https://salonist.io/blog/salonist-marketplace-find-book-beauty-professionals-worldwide).

Proposed advantages to earn and measure:

| Standard | Evidence that would demonstrate value |
| --- | --- |
| All supported industries with accurate service context | Taxonomy coverage and wrong-result rate by audience |
| New businesses can compete without invented reviews | Relevant exposure distribution and time to first completed visit |
| Search facts match operational reality | Public service/price mismatches and failed booking rate |
| Honest owner economics with manual payment recording | Reproducible attributed completions and recorded net collections |
| Fewer steps from query to booking | Mobile discovery-to-confirmation conversion and error recovery |
| Useful local coverage rather than page volume | Qualified non-branded clicks and completed visits per indexed page |

No research here establishes that Clipperdesk currently outranks or outperforms
these competitors. Win one relevant local intent/cluster at a time, then expand.

## 12. Delivery order and gates

| Stage | Deliverable | Exit evidence |
| --- | --- | --- |
| A. Baseline and contracts | Real supply audit, all-audience mapping, URL/indexation/attribution decisions | Current-code audit, proposed schema/migration impact, preserved private routes |
| B. Supply and matching | Taxonomy/geography, owner corrections, public read model, fair retrieval | Multi-industry/branch tests, hidden/paused exclusion, bounded queries |
| C. SEO publication | Public profiles/hubs, initial HTML, metadata/schema/sitemap, lifecycle invalidation | Guest raw-HTML crawl, SSR outage, indexation matrix, zero private leaks |
| D. Conversion and economics | Preserved booking handoff, consent-safe attribution, owner reporting | Idempotent confirm/completion/sale/refund tests, sources and currencies separated |
| E. Controlled launch | Small real approved market/audience cohort, deployment runbook and rollback | Exact-origin browser/Google checks, provider gates, operator sign-off |
| F. Growth iteration | Query/content experiments, partnerships, evidence-driven expansion | Rolling cohort report; scale only with useful supply and measurable outcomes |

Stages A-D are the separate-thread implementation scope. E needs production approval
and actual deployment evidence; F is ongoing operations, not a claim a single code
change can finish. Keep a feature flag/indexation default-off until launch gates pass.

Suggested pilot targets, to adopt after baseline (not forecasts): zero private-page
index leaks and incorrect public claims; 95% eligible listing updates projected within
5 minutes with no stale eligibility served; reduce time to first Marketplace-confirmed
and completed visit; improve non-branded qualified clicks and conversion by cohort.
Track zero-impression businesses and exposure fairness, not only overall winners.
Do not invent a booking target before measuring real demand and supply.

### First 90 days of rollout

This is a proposed operating sequence, not a ranking or revenue forecast. Launch
week begins only after stage E's actual readiness and production approval.

| Window | Owner | Concrete output / decision |
| --- | --- | --- |
| Before launch | Engineering + founder | Stages A-D, crawl/privacy/load evidence, pilot inventory list and release decision |
| Weeks 1-2 | Founder/operator + engineering | Launch one reviewed cluster; verify every published profile, booking handoff, source capture and actual indexing evidence |
| Weeks 3-4 | Growth/editorial + operator | Fix poor matching and thin profiles; review first query/page data; publish only a few useful eligible hubs and one locally relevant guide |
| Weeks 5-8 | Growth/editorial + engineering | Weekly landing/conversion improvements, individually authorized local partnerships, review fairness and new-business exposure; compare cohorts |
| Weeks 9-12 | Founder + growth + engineering | Review completed visits, collected-value attribution, cancellations and cost to operate; expand one adjacent cluster only if supply and outcomes justify it |

For a small team one person may hold several roles. Budget engineering/support and
editorial time explicitly before pilot selection; no media spend is assumed. Owners
supply/review accurate public facts through their existing setup, rather than being
asked to learn technical SEO. Keep a weekly backlog of the five highest-impact
supply/conversion issues with an accountable person and measurable completion.

If pages are indexed but receive few relevant impressions, investigate local demand
and query coverage. If impressions grow but clicks do not, inspect relevance and
search snippets. If clicks grow but bookings do not, inspect supply, prices, mobile
booking failures and service/location mismatch. If confirmations do not become
completed visits, investigate fit, cancellations and operational quality. If visits
complete but collections are absent, report the recording gap instead of declaring
zero economic value. Each diagnosis leads to a different action.

## 13. Decisions for the implementation thread

Resolve from existing data first; record remaining ambiguity visibly:

- Actual initial markets and vertical mix, production origin and approved release cohort.
- Geography source/provenance and explicit mappings for all existing business types.
- Initial page-quality thresholds, neutral URL migration deferral and locality handling.
- Attribution/consent/retention policy and Search Console access if available.
- Public credentials/address policy for sensitive, home-based and regulated businesses.
- Capacity/read-model benchmarks and support ownership for publication failures.

Planning has changed no product behavior, robots directive, publication state,
tracking permission, Google profile, owner campaign or financial policy.
