# Good Hours technical SEO and discovery contract

Status: Accepted Prompt 23 implementation record (2026-08-16)

The server classifies approved marketing/editorial route names as
`index,follow`. Tenant booking, authentication and unfinished legal routes are
`noindex,follow`; secure-token, application, admin, webhook, utility and error
families are `noindex,nofollow,noarchive`. The policy is emitted in both HTML
and `X-Robots-Tag`; authentication remains the actual access control.

`/sitemap.xml` is built only from approved route/config registries and the
publishable article query. `/sitemap` redirects once with 301. Dynamic crawling
is removed, `robots.txt` is generated from configured `APP_URL`, and no tenant,
auth, token, roadmap, changelog, provider or test URL can enter discovery.

Metadata uses query-free absolute canonicals, page-owned descriptions, proper
Open Graph properties, `en_IN` locale and no keywords tag. The JSON-LD graph
contains only Organization, WebSite, WebPage, SoftwareApplication, current
visible pricing Offers and eligible Article entities. It has no founder,
address, contact, award, social, rating, review, certification or LocalBusiness
placeholder. Host/scheme authority remains configured `APP_URL` pending
OPEN-11; proxy/CDN redirects require deployment ownership.

## Prompt 27 crawl result

The final deterministic crawl contains 23 base URLs plus eligible articles.
Every base URL returned 200, `index, follow, max-image-preview:large`, and its
exact query-free self-canonical. Authentication, tenant booking, secure token,
legal draft, roadmap, changelog, provider, admin, utility, and error URLs remain
outside the sitemap and fail closed. `/sitemap` redirects once with 301 and
`robots.txt` identifies only `/sitemap.xml`. Stored-XSS, unknown-slug, real-404,
schema-field, and query-leak regressions pass.
