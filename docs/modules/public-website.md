# Public website content and SEO

Updated: 2026-11-02. Scope: approved public acquisition under PRD sections 1–4,
[FR-01 through FR-20](../product-requirements.md), ADR-024/026/033/061/063/065/080.
This specification does not promote deferred product capabilities or waive launch gates.

## Customer content boundary

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
