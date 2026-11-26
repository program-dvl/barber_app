# Public website content and SEO

Read-only performance review: [14 November SEO evidence and next-change plan](../audits/2026-11-14-seo-performance/README.md)
covers all136 current Business sitemap URLs, seven blogs, authenticated Search
Console/GA4 observations and measurement limitations. Its backlog is proposed;
no tracking expansion, editorial publication or product behavior is approved by
the audit. That historical audit predates the owner-approved 15 November refinement below.

Updated: 2026-11-02. Scope: approved public acquisition under PRD sections 1–4,
[FR-01 through FR-20](../product-requirements.md), ADR-024/026/033/061/063/065/080.
This specification does not promote deferred product capabilities or waive launch gates.

## Customer content boundary

### Product Hunt launch badge (2026-11-25)

The public MarketingFooter and its Blade legal fallback link to the scheduled
ClipperDesk Product Hunt launch using a local copy of the platform's
“Find us on” artwork, omitting the snapshot vote count. The image is served locally, with reserved dimensions, lazy
loading, a 54px-high link and visible keyboard focus. New-tab behavior is announced;
no third-party script, analytics event, ranking claim or award is introduced.
This is acquisition presentation under FR-01/19 and PRD sections 4/12/15.
Production deployment is separate from local implementation and validation.

`config/frontsite.php` holds customer copy and internal requirement traceability.
Feature, solution and use-case controllers remove `requirements` before returning
page props. Requirement IDs and delivery evidence belong in documentation and
tests, never HTML, browser payloads, metadata or structured data. Public copy
uses appointment, client, staff, location and recorded payment terminology.

Use only implemented customer workflows. Checkout records externally received
payments, including partial/split receipts and manual refund adjustments; it
does not charge cards or execute provider refunds. Online deposit collection is
unavailable; deposit-required bookings need business contact and cannot bypass
verified payment. Email/SMS requires enabled delivery providers; SMS additionally
requires consent, supported destinations and available credits. Respect the
current resource editor, privacy-retention and regulated-industry boundaries.

The 13 approved solution pages describe fit for shared scheduling and business
workflows, not 13 specialist modules. Clinical records, insurance, prescribing,
healthcare compliance, dedicated pet records, memberships/classes, payroll and
campaigns remain outside the advertised capability set. Stock report pages are
not offered in the Reports workspace; public reporting copy does not advertise them.

## Metadata and indexation

`PublicPage` prepares page-owned SEO and the existing server schema graph.
`PublicHead` renders the same properties through the shared client/SSR app
composition, updating them on Inertia navigation. Blade supplies marked metadata
and a title when SSR is unavailable, without duplicating a successful SSR head.
Schema is safely encoded, is absent on errors/private pages, and is removed when
visiting authentication pages. Production canonical, sitemap and robots URLs use
the configured primary `brand.website_url`, independent of a request host alias.
Development previews retain their own route host.

Keep the existing indexation policy: curated marketing pages indexable; auth,
booking, private links and unapproved legal documents noindex; retired boilerplate
routes return 410. `/sitemap` redirects to XML. Only eligible articles and an
approved privacy notice extend the sitemap. The current `en-IN` profile is retained
pending regional approval under OPEN-15; no invented translated pages are published.

## Editorial and legal boundaries

### Article administration and media (2026-11-04)

The restored Content → Articles form supports draft saving without a featured
image. Its Article section has one **Status** choice, **Draft** or **Published**;
there is no separate Publication section or manual reviewer/date entry. Saving
Published records the named signed-in platform administrator as reviewer and
the current UTC review/first-publication time. Summary, topic, featured image
and search title/description remain required before publication, alongside the
existing content/privacy gates. Selecting Published is the administrator's
review/publish action; the platform never changes a draft to Published itself.

Ordinary saves retain the first actual publication date. Material changes to
public content, images or search fields update the review attribution/date and
materially-updated date; no-op and Active-only saves retain their prior review.
Changing back to Draft retains historical dates without public visibility;
republishing updates the review while retaining an actual prior publication
date. An old future schedule remains hidden until reached unless an administrator
explicitly saves Published, which publishes now and replaces that future date.
New scheduling is not exposed in this simplified editor. Active can hide a
published article; it never publishes a draft. Publication validation reports
incomplete/blocked content at the field and preserves the entered work.

Featured and inline images accept JPEG, PNG or WebP up to 5 MiB. Final media uses
`articles.disk`: `ARTICLE_MEDIA_DISK` when set, otherwise S3 for an existing S3
deployment or `public` for a local deployment. Paths are generated under
`articles/`; local files are served through `storage/app/public` and the normal
public storage link. Existing S3 media must keep its configured public URL and
accessibility; no migration/copy/deletion of existing objects is automatic.
Saved article images are public media; private client records do not belong in
article content. Livewire temporary uploads use the existing private local disk,
with its signed preview, validation, throttle and cleanup rules retained.

The public renderer supports existing Markdown and the admin rich editor's HTML,
then sanitizes both through Symfony's installed HTML sanitizer. Headings, links,
images, lists and tables survive; executable markup, unsafe URL schemes and
arbitrary styles/classes do not. The page title owns H1. Unsupported alignment
buttons are omitted from the editor. Public blog cards, article detail/related
images and article social metadata use the saved featured image, with the
existing editorial visual only as a fallback. Saving no longer blocks on unused browser-generated
social images.

Before publication, match both the stored-query marker policy and decoded text;
report blocked links/attributes visibly rather than saving an article that the
public query will exclude. Reject known private/task/temporary/signed links.
The editor caps content at 100,000 characters and publication checks expanded
HTML against the sanitizer limit, avoiding silent truncation. Draft text remains
available for correction; no automatic publication or approval is inferred.

The repository's PHP-FPM `.user.ini` raises upload/request transport limits to
32/40 MiB while per-workflow validation stays authoritative (article images:
5 MiB; existing salon-media limits remain). This does not configure PHP CLI,
Nginx, S3 or load-balancer limits. The deployment runbook must verify the actual
2.3 MB failing image journey; source/configuration alone is not production proof.
See [article upload deployment and recovery](../deployment/article-media.md).

Article publication requires nonblank content/search fields, an existing named
author, owner, image, reached publication/review dates and published/active state.
`PublicContentPolicy` excludes internal markers and obvious placeholders from the
same query used by article detail, listing, resource counts and sitemap. It never
changes stored records. The rule is deliberately conservative; edit flagged
editorial wording before publication. Markdown rendering strips unsafe HTML/links
and demotes article H1 headings below the page title. Human feature-claim review
is still required; a marker scan cannot prove accuracy.

The owner authorized public Privacy Policy and Terms publication on 2 November
2026 (ADR-080). Terms version 1.0 shows version, last updated and effective date,
without review labels or draft-only body text. Privacy version 1.2 shows its
owner-authorized effective date without the review banner. That visible date
is separate from the existing privacy approval, provider/operations, release
check and indexation/sitemap gates; none are enabled by this presentation change.
The separate Refund Policy remains a non-effective review document, including
its shared-component warning and review metadata. Internal approval checklists
and release notes stay in the audit. Do not invent legal approval, jurisdiction,
liability caps, an effective date without owner authority, a support SLA or live
refund certification.

## Verification

Run Marketing, Roadmap-retirement and frontend PublicHead checks, the production
client/SSR build and frontsite budgets. `scripts/audit-public-content.mjs` checks
the sitemap plus public utilities at phone/tablet/desktop widths, screenshot
layout, metadata counts, media, headings, links and navigation without reload.
Use isolated fixtures to exercise unavailable/verified pricing and yearly-only
catalogs; never manufacture verified prices in a real billing database.

Latest evidence and page inventory: [2026-10-13 audit](../audits/2026-10-13-public-content/README.md).

## Search submission and deployment checks (2026-11-02)

PRD sections 1–4/12/15 and ADR-024/065: `PublicSitemap` is the shared curated
projection for the HTTP endpoint and `generate:sitemap`. The command prints XML;
it does not crawl the website or write `public/sitemap.xml`. A static file could
bypass current article/privacy publication gates under Nginx. Preserve any
existing production file for explicit operator inspection rather than silently
overwriting or deleting it.

Run `node scripts/check-public-seo.mjs https://clipperdesk.com` after deployment.
It checks the sitemap, robots HTTP status, and original HTML for all eligible
public URLs, including rendered content. Passing HTTP checks, a Google live test,
sitemap discovery, indexing and search impressions are separate evidence.
Do not present submission as confirmed indexing or a ranking guarantee. Preserve
private/noindex and legal approval gates. Operational checks and the pending
robots routing repair are documented in [search operations](../deployment/search-indexing.md).
The [2026-11-02 audit](../audits/2026-11-02-search-indexing/README.md) records live
repairs, Google evidence, local code validation and unfinished work separately.

## Optional public measurement (2026-11-02)

PRD sections 4/12/15 and ADR-079 approve a narrow GA4 adapter. The server gates
configuration by production environment, primary host, guest status and explicit
curated marketing route/path. The browser waits for prior opt-in; rejection,
withdrawal, GPC/DNT, missing storage/configuration and excluded navigation fail
closed. Public legal pages can expose preferences without being measured.
Use sanitized manual pageviews and bounded CTA/pricing choices only. Never send
queries, referrers, form input, arbitrary event properties or private business
records. Keep all automatic enhanced measurement and advertising options off in
Google. The deployment/audit record distinguishes local tests, actual Google
configuration, Forge environment changes and production receipt evidence.

[Setup and recovery](../deployment/google-analytics.md),
[evidence](../audits/2026-11-02-google-analytics/README.md).

Publication evidence: [2026-11-02 legal publication audit](../audits/2026-11-02-legal-publication/README.md).

## Search discovery repair — 2026-11-04

The blog archive exposes crawlable Previous/Next links after twelve eligible
articles, with a current-page label and stable publication-date/id ordering.
Each later page has its own canonical `?page=N`; page one links use `/blog`.
Invalid or out-of-range pages return noindex 404 without exposing drafts.

In production, successful curated marketing GET/HEAD responses on the configured
booking host or www alias redirect permanently to the primary HTTPS origin.
The route registry and successful response gate preserve tenant booking, private
links, authentication, APIs, assets, mutations, signed queries and missing pages.
Inertia visits use its external-location response to avoid cross-origin XHR.
The booking-host root redirects because it resolves to marketing.home; actual
`/{salon-slug}` and `/book` booking entry points retain their origin.

## Live editorial maintenance — 2026-11-05

The owner-authorised four-article review uses the normal admin publication save.
Retain stable slugs and first publication dates when changing article intent,
headings, summaries and search metadata. Material revisions receive the actual
updated date through the existing publication service. Use descriptive links to
relevant product pages and supporting articles; shared topics enable the existing
related-article projection. Preserve official sourcing, clear illustrative-data
labels and current feature limits. Buying guides must not imply independent
product testing or customer results without evidence. Current editorial content,
rollback copies and live desktop/phone checks are recorded in the
[dated review](../audits/2026-11-05-blog-editorial/README.md); successful page checks
are separate from Google indexing, ranking and traffic outcomes.

## Acquisition proof and working resources — 2026-11-05

The homepage keeps broad appointment-led positioning with salons and barbershops
as the leading examples. All thirteen industry pages remain available. Priority
industry pages and all four shared feature pages add scenario-specific copy,
FAQs and real demo screenshots. `ProductEvidence` resolves only named reviewed
public media through `HelpCatalog`; invalid/missing media fail closed while the
text and normal actions remain available. Captions identify sample data and
preserve payment/deposit and specialist limits.

`/features/product-walkthrough` shows five real product screens with trial checks,
feature links and published Help destinations. It is a curated WebPage in the
sitemap and alias policy, not a customer story or a claimed live interactive demo.
Its guest page views use the existing prior-consent analytics contract; no new
tracking type or private route is enabled. Existing CTA allowlists remain.

The existing Resources hub gains a daily operations guide with five workflow
sections and ten checks. All maintained guides have Print or save as PDF; print
styles retain the title/body and remove navigation, illustrations and actions.
Limitations use the shared AppNotice. Pricing clarifies shared industry coverage,
optional extra text credits, separate client payment fees and recorded checkout
without changing rates or subscription behaviour. See the
[dated implementation review](../audits/2026-11-05-acquisition-pages/README.md).

## Email-led setup and migration assistance — 2026-11-06

Owner-approved FR-02 acquisition refinement: promote personal account setup and
reviewed migration help on Home, Pricing, industry solution pages, feature detail
pages and the product walkthrough. Shared SetupSupport uses existing marketing
tokens, a wrapping action row and the configured support email. Requirements are
gathered manually by email; no intake form, upload, migration engine or in-app
workflow is added. Describe clients and upcoming appointments/bookings explicitly;
other records depend on review. Agree scope, timing and any costs before work;
never promise universal compatibility, free service or a fixed turnaround. Ask
for no client files in the initial email. Owner review precedes going live.


## Focused audience resources — 2026-11-07

Existing salon/barbershop pages add server-selected editorial links using the
normal Article publishable scope. Hidden, future and draft articles remain
excluded. Reviewed client-profile and walk-in-queue media supplement existing
screens. Five existing articles offer blank printable worksheets and links to
relevant walkthrough anchors and industry pages. Templates collect no input.
Reuse shared typography, cards, focus styles and wrapping 44px actions. Print
shows the worksheet alone. Preserve stable article URLs, metadata and dates;
no database publication, fabricated review or ranking guarantee is introduced.

## Marketplace navigation — 2026-11-08

The existing acquisition site remains Business at `/`, with a Business brand
qualifier and explicit Marketplace navigation/footer link. The approved separate
customer identity lives at `/marketplace`; it is not a replacement for acquisition
URLs or signup/subscription flows. Marketplace and customer/private pages remain
noindex pending launch review. See [Marketplace](marketplace.md) for the connected
implementation and deployment limitations.

The 2026-11-08 entry refinement retains the established Business root/acquisition
URLs and adds a prominent customer discovery band on Home. `/business` permanently
redirects to the existing root. Both headers/footer distinguish the two audiences.
A later marketplace-first root migration requires separate indexable directory and
canonical/redirect review; no ranking benefit or public launch is claimed here.

The later owner screenshot refinement supersedes the Home discovery band described
above: remove that oversized repeated prompt. A compact mark/name/Business lockup
and sage Find a salon navigation entry now distinguish the audiences without
competing with the Business hero. Root URLs, trial economics and Marketplace
noindex/launch gates remain. The existing navigation drawer is used below1280px.

## Inventory-backed Marketplace SEO (MPG-05–07)

Business root and Solutions remain acquisition pages. The additional reviewed
registry serves clean country/city/audience/service hubs and eligible substantive
profiles at the existing Marketplace booking URL. `MarketplacePublicPage` supplies
metadata/schema and shared projection facts; app.blade renders those facts when
SSR is unavailable. No page matrix, credential claims or fabricated supply is seeded.
The existing Business sitemap is retained; Marketplace adds its own sitemap index
and stable ID-bucket shards, advertised after global indexing and canonical-origin
gates pass. Private pages remain noindex/no-store even during rollback.
See [release package](../release/2026-11-08-marketplace-local-growth/README.md).


2026-11-11 policy refinement under MPG-05/07. Individual Marketplace profiles are
prepared automatically from eligible published booking data. Canonical-origin checks
reuse BRAND_WEBSITE_URL. The separate growth origin and business pilot allowlist are
retired. One platform indexing switch defaults off. Public website acquisition URLs
and private-page noindex/no-store contracts retain their existing behavior.


## SEO follow-up — 2026-11-15

FR-01/19/21; PRD sections 4/12/15 and the accepted measurement refinement:
`PublicAnalytics` admits only a route-bound article passing `publishable()` on the
primary production host. It adds only that clean article path to the approved
path set. Drafts, future/review-missing/blocked content, errors and signed-in visits
remain unmeasured. Articles gain an explicit product/pricing/trial next step and
publisher disclosure. RelatedReading reuses the publication scope on existing
feature/use-case pages; withdrawing an article withdraws these links too.

Consent version 2 renews prior acceptance, retaining unexpired rejection. After
acceptance, browser-only classification adds finite `entry_source` labels to
manual pageviews/CTA events; raw referrers remain empty and query/title/customer
input remains excluded. Source labels stay in that document, including its
Inertia public navigations, not across full reloads or registration. They do not
repair standard GA channel reports or identify organic signups. Actual feature
hero/evidence and article trial contexts use the bounded click transport.

Client-management copy names the records/handoff job; its client-history use-case
links to salons and distinct consultation/migration guidance. Maintained guides
`service-timing-and-buffers` and `switch-from-paper-bookings` use the existing
Resources, print, sitemap and shared AppNotice presentation. No new specialist
feature, dynamic local page or author biography is implied.

Article schema includes the actual SEO image. `Article::publicUpdatedAt()` excludes
pre-publication and future updates from the visible/schema projection, retaining
stored values and first publication. An author URL remains absent until an actual
substantive bio exists. Privacy 1.3 explains the approved scope; no provider or
privacy operational setting is changed. [Evidence and release checks](../audits/2026-11-15-seo-followup/README.md).

## Free no-show cost calculator — 2026-11-16

Scope: FR-01/19/21, [approved calculator](../decisions.md#free-no-show-calculator-with-optional-refinements--2026-11-16).
`marketing.tools.no-show` serves `/tools/no-show-calculator` in the Business marketing
shell. Resources and the footer link to it; Resources remains the current header
section. Central PublicPage/indexation/sitemap/canonical/alias and breadcrumb graph
contracts apply. There is a human-readable formula/example fallback when SSR is
unavailable and an explicit JavaScript requirement for interaction.

The client-only model uses integer currency minor units. Average value ×
(missed visits − refilled slots) gives the empty-slot service value. Subtract retained
payments for those slots and their per-visit costs avoided, then floor at zero.
The remaining number is estimated earnings at risk before fixed costs, not net
profit. Optional refinements show the full deduction breakdown. A user-selected
scenario models proportionally fewer empty slots with the same averages; annual
values repeat the monthly rate. Six existing currencies are display choices, never
exchange-rate conversion. Blank/invalid values clear results rather than silently
becoming zero; zero visits and fully covered amounts are valid zero outcomes.

Bounds: average/per-visit value up to 1,000,000, monthly visits up to 100,000, refilled
visits no greater than missed visits, retained amounts up to the model's maximum
gross service value. Currency inputs permit up to two decimals. Clipboard actions
are explicit, use shared ToastRegion for brief confirmation, and provide AppNotice
plus selectable text if unavailable. Inputs are neither stored nor measured, and
no new analytics route/event eligibility is added. The CTA preserves authentication-
aware trial/dashboard navigation and actual reminder/SMS capability limits.

Evidence and browser limitation: [local calculator checks](../audits/2026-11-16-no-show-calculator/README.md).

Calculator UI follow-up, 2026-11-16: repaired shared currency/unit flex layout in the
main and optional fields, aligned leading prefixes, and stabilized adjustment/result
labels. The exact port-8000 route responds and exposes main/adjustment controls.
An isolated built-renderer check covers all five inputs, labels and helper references.
Actual desktop/mobile rendering and interactions remain blocked by disabled browser
access; these code/markup checks do not establish pixel-perfect visual conformance.

Calculator shell follow-up, 2026-11-16: the page retains the shared HomeLayout →
MarketingLayout header/footer, and now uses PublicContainer, cd-public-section,
cd-public-title and cd-eyebrow for consistent width, spacing and headline scale.
Local HTTP metadata and the sitemap's single calculator entry were verified.

## Software evaluation scorecard · 2026-11-17

FR-01/05/06/21. Resources and the calendar feature link to the maintained
salon software demo scorecard. It uses the shared guide, print action, inline
practice-workspace notice and article metadata. The buyer records results on
paper. Official setup links have an editorial check date. When SSR is unavailable,
the initial HTML still exposes the scenarios, worksheet, citations and product link.
[Audience, editorial policy and measurement](../strategy/salon-software-evaluation.md)

### Printable operating worksheets · 2026-11-17

Four companion pages use the same canonical guide routes, printable worksheet
layout, official reference links and original-HTML fallback. The migration
rehearsal and slot exercise cross-link with the general changeover and timing
guides respectively. The calculator links to the attendance planner. The
walk-in sheet provides a local observation method with fictional examples.
No worksheet collects data or adds a form, tracking event or database table.

### Combined interactive Business homepage — 2026-11-19

FR-01/06/08/09/11/15/18: owner-approved architectural salon hero and sample walk-in
demo replace the previous opening. Day buttons adjust illustrative scene labels,
copy and image treatment; the demo selects one of two sample staff slots and can
be replayed. No live booking or provider operation occurs. Preserve existing
industry/feature destinations, FAQs, setup help, metadata and schema. Main content
and conversion links have a Blade fallback when SSR is unavailable. Images use
responsive WebP and reduced-motion behavior is provided. Full browser acceptance
is pending: [implementation and evidence](../audits/2026-11-19-homepage/README.md).

### Homepage search-content refinement — 2026-11-19

FR-01/06/08/09/11/15/18: homepage targets appointment scheduling and service business management with
natural descriptions and contextual links for conflicts, walk-ins and changeover.
`config/homepage.php` owns lead, SEO copy, industry links, use cases and FAQs for
both Vue/SSR and fallback HTML. Keep these in parity; do not add keyword lists or
unsupported specialist claims. Existing canonical/schema/indexation rules remain.
[SEO audit and intent map](../audits/2026-11-19-homepage/seo-review.md).

Owner correction: the homepage must visibly represent all supported business
categories, including in title, description, hero audience and FAQs. Do not reduce
its category to salon/barbershop software. All 13 approved industry destinations
are directly linked from shared homepage content; fit/limitations remain on their
detail pages. The salon hero/demo is illustrative only.

### Homepage editorial section — 2026-11-20

FR-01/06/08/09: the owner-requested “Stay in the moment” section follows the sample demo. Shared `homepage.moment_benefits` feeds Vue/SSR and fallback HTML. A lazy-loaded 74,748-byte WebP portrait is illustrative, not a customer endorsement. Hero scene labels now follow actions in document flow to avoid the reported overlap. Browser visual verification remains pending.

### Solutions editorial refresh — 2026-11-25

FR-01/06/08/09/11: the hub and four owner-selected pages (barbershops, nail-salons,
spas, massage) extend the homepage visual direction. `solution-experiences.php`
owns illustrative headlines, treatment lengths and turnaround for these pages;
other industries retain their existing presentation. The hub retains all 13
crawlable destinations. Timing choices calculate occupied time against a fictional
11:00 booking, with a useful shorter-service recovery; they do not reserve time,
check live resources or call operational APIs. Existing screenshots, industry
limitations, FAQs, pricing and signup remain. Titles, canonicals and schema retain
the existing server contract. Fallback HTML includes the new headlines and sample
service facts. Browser visual verification is pending; see the dated audit.

All-industry follow-up (2026-11-25): `solution-experiences.php` now covers all 13
canonical industry slugs. Each has its own acquisition headline, editorial story,
service choices and timing context. General scheduling examples for regulated
industries explicitly retain evaluation-only boundaries. Product screenshots are
optional details disclosures; the actual product walkthrough stays linked.
The tanning image is replaced to match staff-led spray-tan appointments
rather than the unsupported bed-control workflow. Full visual evidence is recorded
in the dated audit separately from automated tests.

## Owner-approved sales contact — 25 November 2026

PRD: public sales enquiry addition under FR-01. `/contact` is canonical/indexable
with ContactPage schema and sitemap inclusion. Workflow/setup CTAs use this route;
industry links carry the optional industry selection. Private Gmail BCC is server
configuration only. Session-specific responses are private/no-store.

Name/email/business/message are encrypted in `sales_enquiries`. CSRF, UUID bound to
the session, honeypot and five-new-enquiries-per-ten-minute IP limit protect submission.
Validation preserves entered fields and a timed retry button honours the cooldown.
One atomic claim precedes mail delivery; browser retries do not send twice.
A saved-but-delayed outcome must not announce delivery. “Send another enquiry”
explicitly renews the token after completion. No client records should be submitted.

Deployment requires the new sales-enquiries migration and working standard Laravel
mail transport. The mailer's acceptance is not proof of inbox delivery. Monitor
`Sales enquiry email requires operator attention` logs and run `php artisan
sales:enquiries` for unresolved references (no message contents printed). Correct the
transport, check provider records for non-delivery, wait at least ten minutes, then
run `php artisan sales:enquiries REFERENCE --retry --delivery-checked`. Never retry an
ambiguous accepted delivery; check both destination mailboxes/provider first. Sent
records cannot be resent by this command. Preserve APP_KEY to decrypt stored enquiries.
Retention duration and any future operator UI remain an explicit open decision.


### Search acquisition iteration — 2026-11-26

FR-01/06/09/11/15: salon and barber solution FAQs now explain concrete purchase-evaluation scenarios rather than claim guaranteed results. Salon related reading includes the existing software-demo scorecard alongside publishable editorial articles. Shared configuration retains online deposit and membership limits and feeds existing public rendering. The homepage remains multi-industry. No ranking, deployment or Google indexing outcome is established by this local change.


### Query-led follow-through — 2026-11-26

FR-06/08/11: US Search Console query/page inspection informed three existing-page improvements: salon client history, role-aware record keeping and future-opening waitlist evaluation. The calendar page links the actual customer waitlist help journey. No new keyword-variant URLs or specialist feature claims were added. Historical US query samples are sparse and not evidence of stable rankings. The first content batch was observed live with mobile width 390 and no horizontal overflow; salon recrawl request was accepted. See the execution evidence for later release state.

### Client-record resource and workflow evidence — 26 November 2026

Existing client-management, calendar/walk-in and salon pages expose optional configured walkthrough steps. The generic ProductEvidence and salon editorial layout reuse established tokens. The new salon-client-record-template guide reuses printable worksheet rendering, source references and resource/sitemap integration. It contains fictional planning details, no client-data submission, no import schema and no scope expansion. See the competitor/distribution audit for rationale and primary sources.
