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
