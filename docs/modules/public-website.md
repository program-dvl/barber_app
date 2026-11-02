# Public website content and SEO

Updated: 2026-10-13. Scope: approved public acquisition under PRD sections 1–4,
[FR-01 through FR-20](../product-requirements.md), ADR-024/026/033/061/063/065.
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

Article publication requires nonblank content/search fields, an existing named
author, owner, image, reached publication/review dates and published/active state.
`PublicContentPolicy` excludes internal markers and obvious placeholders from the
same query used by article detail, listing, resource counts and sitemap. It never
changes stored records. The rule is deliberately conservative; edit flagged
editorial wording before publication. Markdown rendering strips unsafe HTML/links
and demotes article H1 headings below the page title. Human feature-claim review
is still required; a marker scan cannot prove accuracy.

Terms/refund remain non-effective review documents. Privacy becomes effective and
indexable only through its existing approved-operation gates; the unapproved
version has an explicit customer notice. Internal approval checklists and release
notes are maintained in the audit, not public policies. No content task may invent
legal terms, an effective date, a support SLA or live refund certification.

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
