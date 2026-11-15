# SEO performance and next-change plan — 2026-11-14

Status: read-only production audit and proposed backlog. No product code, article,
Google setting, indexing request, hosting configuration or publication gate changed.
Scope: PRD sections 1–4/12/15, FR-01/19/21, MP-01/04 and MPG-05–10;
[public website](../../modules/public-website.md),
[Marketplace](../../modules/marketplace.md), ADR-024/065/079 and OPEN-12/15.

## Assessment

Technical crawlability is substantially healthier than in the 4 November audit.
All **136/136 Business sitemap URLs** pass the existing original-HTML checks;
robots returns 200, and five sampled marketing host aliases redirect 301 correctly.
The next priority is measurement quality and qualified discovery, rather than
another generic technical repair or a large batch of similar pages.

Search visibility is increasing from a very small base: **293 impressions and
one click**, with most broad commercial queries far below the first page. The
single click is to Help, not a product or blog landing page. No demonstrated
organic trial acquisition or revenue can be established from these dashboards.

GA4 has two deliberate coverage limits: it excludes blog details and strips
referrers/query parameters. Its **20 Direct sessions** therefore cannot establish
that visitors typed the URL or that organic acquisition is zero. Eight measured
users are too few to establish an engagement or conversion benchmark.

## Dates, launch cohorts and fair comparisons

There is no single verified launch date covering every current surface:

| Milestone | Evidence / interpretation |
| --- | --- |
| 2026-11-02 | Prior audit records GA4 enabled and received live events; Business sitemap submitted in Search Console. |
| 2026-11-04 | Robots/host redirect release; first four blog publication dates. |
| 2026-11-05 | Verified Business acquisition release 3474540 and editorial revisions to four blogs. |
| 2026-11-05–06 | Remaining three current blogs published, according to live Article metadata. |
| 2026-11-11 | Prior public audit verified Marketplace profile SEO, including a subsequently identified demo. |
| 2026-11-14 | Current read-only audit. Current Marketplace sitemap has no shards; known demo BeRich returns 404. |

Search Console's selected **3 months** view displays data from **11 October to
10 November**. That is the observed available range, not proof that launch was
11 October. The historical evidence includes older Google crawl dates. The
current search data cannot evaluate changes deployed on 11 or 14 November.
GA4 is selected to **17 October–13 November**, while collection began 2 November
according to the prior production record. There is no comparable pre-GA4 baseline.

| Complete seven-day period available in Search Console | Impressions | Clicks |
| --- | ---: | ---: |
| 28 October–3 November | 56 | 0 |
| 4–10 November | 227 | 1 |

That is 171 additional impressions, or 305.4%, from a tiny denominator. It is an
encouraging discovery signal, not a controlled test: several releases, new
articles, crawl timing and query mix changed together. Do not attribute the
increase to one edit. The previous audit's37 impressions through 1 November and
today's293 through 10 November are expanding cumulative windows, not equal periods.
Average position 55.8 in that old snapshot versus 67.9 now does not establish a
same-keyword ranking decline. The published page-level total is 325 impressions;
page aggregation differs from the 293 property total and must not be added to it.
See [Google's metric definitions](https://support.google.com/webmasters/answer/7576553).

## Search Console findings

Property: `sc-domain:clipperdesk.com`; Web/text search. Current chart:1 click,
293 impressions, displayed CTR0.3%, average position 67.9. All54 reported page
rows and all 89 visible query rows were reviewed. All54 page rows are preserved in
[gsc-pages.csv](gsc-pages.csv); the query file contains a selected opportunity
sample, not a complete export. The only click was 9 November to
`/help/articles/notifications-consent`.

| Current canonical page | Clicks | Impressions | Position | Implication |
| --- | ---: | ---: | ---: | --- |
| `/solutions/spas` | 0 | 49 | 89.9 | Discovery without competitive ranking. |
| `/solutions/pet-grooming` | 0 | 38 | 91.3 | Do not prioritize from impressions alone. |
| `/solutions/physical-therapy` | 0 | 25 | 89.1 | Preserve non-clinical scope; weak acquisition evidence. |
| `/solutions/massage` | 0 | 23 | 85.2 | Supported industry; no conversion evidence. |
| `/solutions` | 0 | 21 | 53.8 | Improve routes to relevant industry/product proof. |
| `/solutions/salons` | 0 | 8 | 90.6 | Core intent remains weak despite existing content improvements. |
| `/solutions/independent-stylists` | 0 | 4 | 25.5 | Small early opportunity, not reliable ranking evidence. |
| `/features/client-management` | 0 | 2 | 81.0 | Relevant client-record queries justify a focused intent review. |

The canonical barbershop page has no row in this performance table, but **is in
Google's indexed examples**, last crawled 6 November. Its booking-host alias has
three historical impressions at 97.7. Absence from the performance table does not
mean a page is unindexed. Similarly, homepage position 1.3 from six impressions
does not mean it ranks first for salon software.

Query opportunities are small: salon client management 10 impressions/77.7;
hair salon waitlist management software 4/25; salon record keeping software 2/13;
salon waitlist management software 2/30. Verify each query's actual landing page,
country and device before changing copy. No keyword-volume research or competitor
rank-share dataset was collected. Payroll, memberships, medical-record and
unrelated queries must not expand product claims.

The US supplies 199/293 impressions and the only click. Australia22, UK21,
Canada16 and India10 follow. Desktop has 209 impressions/no clicks, mobile 83/one
click, tablet 1/no clicks. These samples do not establish a country launch choice
or a desktop/mobile conversion difference.

### Indexing and health

The Pages report is dated **9 November**, not 14 November:136 indexed examples,
160 excluded across six reasons. Sitemaps reports Success, last read 12 November,
136 discovered URLs. Its only submitted sitemap is the Business sitemap.

| Reported exclusion | Count | Treatment |
| --- | ---: | --- |
| Page with redirect | 111 | Usually expected host consolidation; assess destination, not elimination of this count. |
| Excluded by noindex | 34 | Preserve private/account/filter/legal gates; do not bulk remove. |
| Alternate with correct canonical | 10 | Usually intentional duplicates. |
| Not found | 1 | Example is `book.clipperdesk.com/help`, crawled 4 November; not a current sitemap URL. |
| Discovered, not indexed | 3 | Salon-opening blog, `/security`, `/help/articles/account-profile`; check fresh URL Inspection before diagnosing current failure. |
| Google chose another canonical | 1 | Historical www client-history use-case, crawled 13 September; apex version is indexed. |

All136 indexed examples were expanded. Six of seven current blogs and the
salon/barbershop pages are listed. The report also retains three booking-host
marketing URLs: security, resources and checkout/reporting. Therefore136 indexed
does **not** mean every one of today's136 sitemap URLs is indexed. The complete
Help membership was not separately reconciled URL by URL;94 Help examples were
present. No fresh per-URL inspection census was run.

Five live samples—three booking-host solutions and www home/client-management—now
redirect 301 to their matching apex URL. Historical impressions on aliases do not
prove the redirects are broken today. Retain redirects and monitor Google's
selected canonicals after recrawl; extend spot checks to the three retained aliases.
[Google's canonical guidance](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)
supports consistent redirects, canonical tags, internal links and sitemap URLs.

Manual actions and Security issues both say **No issues detected**. Links reports
zero external links and 1,158 internal links, without a freshness date. This is a
reported dataset, not proof that no backlinks exist anywhere. Core Web Vitals has
insufficient mobile and desktop field data. No current lab speed score was obtained.
Fetch times in the crawl are not LCP, INP or a Googlebot performance benchmark.

## GA4 findings and measurement work

Property561865152, ClipperDesk — Website. Reports say 100% of available data;
that does not mean 100% of visitors are measured. Consent, guest/route restrictions,
privacy signals, ad blockers and unmeasured visits limit coverage.

| Metric,17 October–13 November | Observed |
| --- | ---: |
| Active / new users | 8 / 8 |
| Sessions / engaged sessions | 20 / 9 |
| Engagement rate | 45% |
| Average engagement per active user / session | 26s / 10s |
| Pageviews / all events | 47 / 82 |
| Trial-button events / users triggering them | 6 / 3 |
| Reported key events | 3 |

Home has 23 views from 7 active users; pricing 9 from 4. The other ten reported paths
are preserved in [ga4-pages.csv](ga4-pages.csv). Landing sessions: home 14,
health-practices 2, physical-therapy 2, blog archive 1, pricing 1. All20 sessions are
classified Direct. Seven active users are in India and one in the US; all eight
are recorded on Macintosh/desktop. Owner/testing activity is a plausible source
of contamination, not an established identity finding. No internal filter was
changed or verified. Do not remove a geography or operating system as a proxy
for internal users.

The code explicitly sends empty `page_referrer`, sets `ignore_referrer`, removes
queries and uses a fixed title. `PublicAnalytics` admits curated pages and
`blog.index`, **not published blog detail routes or Help**. Live HTML confirms no
GA measurement configuration on any of the seven blog details. Missing article
GA rows are consequently a coverage limitation, not zero readership.

`marketing_cta_clicked` is a button click, not completed registration. Six event
occurrences and three key events are different observed counts; the cause was
not re-audited. Do not infer three signups or apply 6/8 as a signup rate. The$0
revenue display also cannot establish zero business revenue: this guest tracker
does not measure subscription receipts or booking collections.

Proposed first implementation slice, requiring an explicit OPEN-12 refinement:

1. Permit only policy-approved published article IDs/slugs, with server-owned
   sanitized fields; preserve consent, private/preview exclusions and withdrawal.
2. Define bounded consent-based source labels such as organic-search/referral/
   direct-or-unknown without sending raw referrer URLs, arbitrary UTM values or
   private queries. Validate actual GA behavior; a custom label will not
   automatically repair GA4's standard channel grouping or historical data.
3. Report verified Business account/trial activation from authoritative internal
   aggregates separately from CTA clicks. Define any consented attribution handoff
   before implementation; do not load Google on registration/account pages or
   add cross-service identifiers to bridge the current exclusion.
4. Establish a documented consented staff/test opt-out and controlled test
   protocol. Do not retroactively delete data or enable broad IP collection.
5. Confirm actual event/key-event configuration, report lag, no duplicate manual
   pageviews and the real browser journey through a completed trial activation.

No tracking expansion, property link, event setting or privacy approval occurred.

## All seven blogs: keep, improve or add?

All seven live articles have unique metadata, substantive original HTML,
self-canonicals and syntactically valid JSON-LD. Published4–6 November; only 4–6
days of possible search exposure appear before the 10 November data cutoff.
Their worksheets, worked examples and product links already exist. The right
default is to retain them and measure, not republish duplicate versions.

| Existing article | Search signal | Specific next action |
| --- | --- | --- |
| Software buying guide | 12 impressions,0 clicks,25.8; indexed example,6 November crawl | Highest blog opportunity. Preserve its honest buying-checklist positioning and stable 2027 URL; verify useful comparison tasks, visible publisher disclosure and links into pricing/product walkthrough. Add genuine first-hand evidence when available. |
| Reduce no-shows | 3 impressions,0 clicks,31; indexed example,6 November | Keep scripts, denominator and worksheet. Strengthen a useful policy/product next step; preserve the current no-online-deposits limitation. |
| Run a salon/barbershop | No reported performance row; indexed example,4 November | Keep as the operating overview; make links to walk-ins, rebooking and opening tasks easy to choose. Do not create another daily-operations article. |
| Start a hair salon | No reported performance row; discovered/not indexed in 9 November report | First obtain current inspection. Improve contextual links from startup/setup resources; distinguish business-opening costs from the separate online-booking setup checklist. No jurisdiction-specific licensing promises. |
| Increase rebooking | No reported row; indexed example,9 November | Preserve script/scorecard focus and distinguish bookings made from actual completed returns. Monitor before rewriting. |
| Manage barbershop walk-ins | No reported row; indexed example,9 November | Keep worked waits and shift worksheet; strengthen the reciprocal barbershop/calendar journey. No duplicate walk-in blog. |
| Salon consultation notes | No reported row; indexed example,9 November | Keep its existing template and example. Make the client-management/record-keeping journey clear; no separate near-identical template page. |

There is a shared structured-data improvement: Article nodes lack an explicit
`image` and author URL, although WebPage nodes already reference real featured
images and Article nodes name the author. Add truthful Article image and an
author URL only to a substantive existing/new bio that actually identifies that
person. This is recommended completeness, not an indexing prerequisite or a
guaranteed rich result. Validate with Google's Rich Results Test.
Consultation notes has `dateModified` 11 seconds before `datePublished`; review
first-publication/material-update semantics and render accurate dates without
faking freshness or rewriting historical publication records.
See [Google's Article guidance](https://developers.google.com/search/docs/appearance/structured-data/article).

## Recommended next changes, in order

Effort below is rough engineering/editorial effort, not a delivery commitment.
Owners are suggested roles, not assigned people. Implementation must follow
the repository's normal tests, design-system review and deployment authority.

| Order / timing | Concrete work | Suggested owner / effort | Acceptance and confidence |
| --- | --- | --- | --- |
| P0, first week | Deliver the approved measurement slice above; annotate release dates and define qualified trial activation. | Engineering + Product/Privacy;2–4 days after contract approval | Real guest consent/navigation evidence, no private tracking, one valid activation in internal reporting. High confidence measurement is currently incomplete; no promised traffic lift. |
| P1, first week | Correct obsolete Marketplace claims on barbershops and nail-salons. Review broad Business-to-Marketplace copy against current availability. | Product/content + Engineering;0.5–1 day | Replace “no marketplace” with accurate separate-service/current-availability wording, while retaining no guaranteed new bookings. Review real desktop/mobile pages. High confidence factual issue. |
| P1, first week | Inspect the three awaiting URLs; verify retained alias redirects; complete Article image/author/date review. | Engineering/editor;1–2 days | Correct public originals, truthful markup, valid rich-result test and recorded Google verdicts. Submit only eligible real URLs, without promises or repeated requests. |
| P1, weeks 1–2 | Focus existing `/features/client-management`, client-history use-case, `/solutions/salons`, `/solutions/barbershops`, and their linked guides. | Product/editor + Engineering;2–3 days | Assign one primary intent per URL; improve explicit salon/client-record/queue language where accurate, direct demo proof and a coherent next action. Preserve existing screenshots and worksheets. Low-volume query opportunity; medium confidence. |
| P1, weeks 1–2 | Obtain a representative mobile/desktop lab baseline for home, pricing, a solution and a blog; test actual consent, screenshots/print and trial navigation. | Engineering/design;1–2 days | Record tool conditions and median lab results; fix observed bottlenecks. Field targets: p75 LCP≤2.5s, INP≤200 ms, CLS≤0.1 when sufficient field data exists. No false “passed CWV” claim. |
| P2, weeks 2–4 | Publish one or two distinct practical guides from the shortlist below, only after intent mapping and editorial review. | Editor + real operator reviewer;1–2 days each | Real worked example, usable checklist, author/reviewer accountability, differentiated existing-page links, live QA and measurement. Test demand; no guaranteed search volume. |
| P2, weeks 2–6 | Develop one genuine consented salon/barber story and relevant partner/customer references. | Founder/customer success;effort depends on evidence | Verifiable workflow, permission and measured denominator/date if claiming results. Real relevant mentions; no bought ranking links or fabricated reviews. |
| P3, supply-dependent | Reassess Marketplace profiles and one inventory-backed local cluster. | Marketplace/Product/Operations | Real eligible supply, approved geography/content, public data accuracy, booking completion and privacy/indexation gates pass first. |

The client-history and feature pages serve different intents: outcome/workflow
versus product capability. Link them and improve distinction before considering
consolidation. The waiting-list terms are hypotheses for the existing calendar/
walk-in and cancellation-recovery journeys; do not conflate walk-in queues with
cancelled-slot waitlists or create a page promising unsupported automation.

The current 13 solution pages remain available. Higher spa/pet impressions do not
justify abandoning the approved salon/barber focus or building 13 more blog silos.
No bulk noindex, deletion, URL renaming or domain-root migration is recommended.
The observed `en-IN` schema language remains an existing regional decision; do
not infer a switch to US or Canadian localization solely from search impressions.

### New-page shortlist, after the existing-page work

| Proposed content | Distinct job and destination | Gate before creating |
| --- | --- | --- |
| Blog: set salon appointment lengths, buffers and staff eligibility | Help an owner prepare a bookable service menu; link to online booking and scheduling-conflict workflow. | Check current query-to-page intent and avoid repeating the walk-in guide; verify actual timing controls. |
| Blog: moving salon bookings from paper/spreadsheets | Practical cleanup, client CSV review, appointment handover, preview and launch checklist; link to existing migration help and client management. | Match implemented import fields and clearly distinguish assisted appointment migration from self-service import. Never imply transfer of every record. |
| Conditional feature page: team scheduling | A focused buyer view of shifts, breaks, time off, service eligibility and conflicts, if this needs more depth than the existing feature architecture. | Audit existing pages for overlap and demonstrated customer/search need; verify module scope. Otherwise improve the existing calendar page. |
| Genuine customer story / substantive author bio | Provide accountable first-hand evidence and real author identity. | Actual permission, reviewed facts and useful substance; not an invented testimonial or thin SEO author route. |

Do not add more generic salon-software, rebooking, no-show, walk-in or consultation
articles: those intents already have pages. Do not add empty city/service hubs,
country duplicates, competitor comparison pages without current evidence, or
payroll/clinical/membership/integration promises. Keep all existing stable article
slugs even when their titles become evergreen.

This follows Google's emphasis on original usefulness and real experience, not
word-count targets or mass keyword variants:
[people-first content guidance](https://developers.google.com/search/docs/fundamentals/creating-helpful-content).

## Marketplace is a separate acquisition cohort

Current public GETs: `/marketplace`200/noindex, an empty Marketplace sitemap index,
and known demo `/marketplace/salons/berich`404/noindex. This supersedes the 11 November
snapshot's three advertised profiles. An empty sitemap proves no currently
advertised eligible profile shards, not the reason for each business's removal
or the total private/business inventory. No production visibility was changed.

Keep the launch/editorial/private/filter indexation policy intact. Improve
LocalBusiness address/schema when real profiles return; the prior audit's schema
finding cannot be recertified with no eligible profile in the current sitemap.
Do not submit the empty sitemap simply to inflate submission coverage. Once
genuine supply and gates permit, use a substantive profile/cluster and monitor
search impressions → consented discovery → confirmed/completed visits. Recorded
collections are separate, currency-specific ledger outcomes, not incremental
revenue claims. This plan neither changes the root to Marketplace nor creates a
Google Business Profile on behalf of a SaaS or salon.

## Review schedule and success measures

- Weekly: latest complete seven-day GSC period versus previous seven days, by
  fixed commercial/blog cohorts, branded/non-branded queries, page, country and
  device. Keep query privacy omissions and low sample size visible.
- At28 and 56 days after the next verified release: equal complete 28-day windows
  where available. Annotate launch/edit dates; do not include preliminary 24-hour
  data in finalized totals. Avoid frequent title changes before a useful window.
- Primary Business outcome: verified trial activations and subsequent product
  activation, with organic attribution only where defensibly measured. CTA
  clicks, impressions and indexed counts are diagnostic measures.
- Primary Marketplace outcome: attributable completed real visits, once genuine
  inventory exists. Newsletter subscriptions and profile views are different
  outcomes, not bookings.
- Operational checks: no new unintended noindex/canonical failures, no private
  URLs in sitemaps/events, accurate copy, reliable consent and trial navigation.

Use site-specific baselines. There is no defensible universal “industry standard”
traffic, CTR or engagement target for a new site with one organic click. Google
provides measurable performance thresholds for CWV, not a universal signup rate:
[Web Vitals](https://web.dev/articles/vitals). Rank/CTR improvements are evaluated
for relevant comparable queries rather than a changing global average.

### Open decisions before implementation

1. OPEN-12: approve the precise article/source/outcome measurement contract and
   privacy review; preserve current exclusions until approved.
2. Product should confirm a single named release/cohort and primary commercial
   outcome for the next experiment. Dates above are evidence, not an invented
   unified launch date.
3. OPEN-15 remains authoritative for regional expansion. Observed US demand does
   not approve country-specific tax, clinical, legal or communications claims.

## Evidence, coverage and limits

| Artifact | What it establishes |
| --- | --- |
| [google-observations.json](google-observations.json) | Authenticated UI observations, dates, metrics, report reasons and known limits. |
| [gsc-pages.csv](gsc-pages.csv) / [gsc-days.csv](gsc-days.csv) | All54 reported page rows and 31 daily rows, manually transcribed from visible Google tables. |
| [gsc-query-opportunities.csv](gsc-query-opportunities.csv) | Selected query opportunities and scope exclusions; not all 89 queries. |
| [ga4-pages.csv](ga4-pages.csv) | All12 reported page paths and observed metrics. Users across paths are not additive. |
| [all-pages.csv](all-pages.csv) | Every136 sitemap URL, technical result, available performance, measurement coverage and proposed treatment. Blank metrics mean not reported/collected, not a fabricated zero. |
| [live-readiness.json](live-readiness.json) | Existing read-only crawler:136 original-HTML checks plus robots/sitemap, collected 14 November. |
| [live-content.json](live-content.json) |50 guest GETs: all 41 non-Help sitemap pages plus Marketplace/alias/robots samples; visible main text, links, headings, schema and sanitized checks. |

This is a full technical Business-sitemap audit and a prioritized content/intent
review, not a full legal/editorial/accessibility certification of 95 Help pages.
The tool's CSV export timed out; Google data above is explicitly transcribed UI
evidence, not a claimed downloadable API export. No passwords, cookies, Google
session identifiers or private customer payloads are included.

No current Lighthouse/PageSpeed laboratory run, fresh individual URL Inspection
census, live Google-origin fetch/log analysis, competitor benchmark, keyword
search-volume dataset, actual trial database/revenue audit or new desktop/mobile
visual certification was performed. Earlier screenshots certify earlier checks.
No product tests/build were run because no application behavior changed; the
production crawl and arithmetic/inventory consistency checks are the evidence
for this documentation-only audit. The plan is ready for implementation review,
not evidence that traffic or a deployed customer journey has been improved.


## Implementation follow-through — 2026-11-15

The owner approved the plan and work proceeded locally. See the
[implementation evidence and release checklist](../2026-11-15-seo-followup/README.md)
for the bounded measurement refinement, two maintained guides, existing-page
updates, actual local verification and outstanding browser/deployment/Google
checks. This does not change the historical performance results above.
