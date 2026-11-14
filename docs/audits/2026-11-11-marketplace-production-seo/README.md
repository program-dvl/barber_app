# Production Marketplace SEO audit — 2026-11-11

Requirements: MP-01 and MPG-05–07. Read-only public production checks, approximately
10:13–10:19 UTC (15:43–15:49 Asia/Kolkata). Individual response dates are retained in
[http-report.json](http-report.json). No production configuration, account, indexation,
Search Console, Google Business Profile, campaign or booking was changed.

## Conclusion

Marketplace SEO is deployed and its profile indexation is operating on the canonical
production host. The unchanged **136** URLs belong to the original Business/marketing/
Help sitemap, not the Marketplace profile inventory. The separate Marketplace sitemap
advertised in live robots.txt points to one shard with **three** profiles: BeRich,
Wells Studio and Rock Studio. BeRich passes the checked public fetch/indexability
conditions. This establishes technical eligibility, not Google's actual index status
or a reason for its absence from a particular search.

The production business data and LocalBusiness markup have unresolved issues below;
this audit does not certify the complete SEO release or rich-result eligibility.

## Observed route matrix

| URL on https://clipperdesk.com | Live result |
| --- | --- |
| `/robots.txt` | 200/text/plain, allows crawling, advertises both sitemap URLs |
| `/sitemap.xml` | 200/valid XML, 136 Business/marketing/Help URLs |
| `/marketplace/sitemap.xml` | 200/valid sitemap index, one profiles shard |
| `/marketplace/sitemaps/profiles/0.xml` | 200/valid XML, three clean canonical profiles |
| `/marketplace/salons/berich` | 200; index/follow in both header and HTML; self canonical; one title/H1/main |
| `/marketplace/salons/wells-studio` | 200; index/follow in both header and HTML; self canonical |
| `/marketplace/salons/rock-studio` | 200; index/follow in both header and HTML; self canonical |
| `/marketplace` | 200/noindex/follow; contains ordinary business links and real initial HTML |
| `/marketplace?q=BeRich` | 200/noindex/follow; canonical points to clean Marketplace root |
| BeRich with its public `?location=…` selection | 200/noindex/follow; canonical points to clean BeRich profile |
| `/marketplace/sign-in` | 200/noindex/nofollow; private/no-store |
| `/marketplace/salons/seo-check-nonexistent` | Real 404/noindex; private/no-store |

The clean BeRich page includes the name, public address, Cut & finish service,
duration, CA$60.00 price and booking navigation in original HTML, with valid JSON-LD
syntax. Rendering is substantive, not an empty JavaScript shell or outage fallback.
A mobile Googlebot user-agent request returned the same visible text, canonical and
index directives. This is a user-agent comparison; it does not prove requests from
Google's actual network succeed.

The sitemap shard's material-change timestamp for all three profiles is
`2026-11-11T03:44:06+00:00`. It is not evidence of first publication, Google's crawl
time or first indexing. No crawler logs or Search Console data were available.

## Findings requiring follow-through

1. **Confirmed demo inventory; production withdrawal required.** BeRich's public props
   specify country `CA` (Canada), currency `CAD`, and branch time zone
   `America/Atikokan`. Its visible address says `1 Mills Cir, Ontario, CA 91764, USA`
   (Ontario, California). The HTML and schema agree with the saved CAD prices;
   the conflict is between the business configuration and its address. The owner subsequently confirmed BeRich is a test/demo store. Do not correct
   invented business facts merely to promote this fixture in Google. For actual
   production businesses, verify country, currency, location time zone, real name,
   address and services through existing authorized setup workflows.
   Do not rewrite financial history, silently infer location from the word Ontario,
   or publish invented details. Test inventory should be paused using the existing
   listing visibility control instead of requesting Google indexing.
2. **LocalBusiness schema improvement required.** The business entity currently
   supplies addresses only through `location: Place.address`, placing the complete
   address string in `streetAddress`. It lacks `address` directly on the
   `LocalBusiness` entity, which Google's LocalBusiness documentation lists as
   required. Add truthful business/branch address modeling and structured locality,
   region, postal code and country only when the existing source data supports them.
   The current graph is valid JSON but has not passed Google's Rich Results Test.
   This affects structured-data completeness; it is not proof of an ordinary
   indexing/ranking block. See
   [Google LocalBusiness requirements](https://developers.google.com/search/docs/appearance/structured-data/local-business).
3. **Actual indexing remains unknown.** No authorized URL Inspection result was
   obtained. The browser tool returned “Local browser use is disabled in Work
   settings”. Public web searches did not establish Google's index status. A very
   precise query still cannot force an unindexed page to appear. Google states
   that a sitemap does not guarantee crawling or indexing; use the indexed report
   and live test to distinguish not discovered, not crawled, crawled not indexed,
   another selected canonical or already indexed. See
   [Google's sitemap guidance](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview)
   and [URL Inspection guidance](https://developers.google.com/search/help/debug).

## Concrete operator actions

- Keep the existing Business sitemap submission. Once real business data is
  confirmed, submit `https://clipperdesk.com/marketplace/sitemap.xml` once in the
  existing Search Console property if it is not already submitted. There is no
  need to replace the 136-URL sitemap or manually add every store. robots.txt
  already advertises the Marketplace index.
- Inspect `https://clipperdesk.com/marketplace/salons/berich` **without query
  parameters**. Record the indexed report's verdict/reason, last crawl,
  user-declared and Google-selected canonicals. Run Test live URL, inspect rendered
  content and request indexing if the real, corrected page is eligible. Requesting
  indexing is not a guarantee. Do not submit accounts, filters or secure links.
- Implement/test the schema correction locally, then use the normal reviewed
  deployment and Google's Rich Results Test. No runtime flag change is required
  for the observed eligible profiles; they already permit indexing.
- Monitor actual impressions/clicks and completed-visit outcomes. A local booking
  page does not itself create a Google Maps/Business Profile listing. Owner-managed
  Google Business Profile work remains separate.

## Source paths and evidence limits

`app/Support/Seo/PublicSitemap.php` generates the original sitemap independently.
`app/Domain/Marketplace/LocalGrowth/Services/GrowthSitemap.php` generates the
Marketplace index/shards dynamically from eligible registry entries.
`app/Http/Controllers/SitemapController.php` advertises the additional index after
indexation/eligible-supply gates. `SeoPageRegistry.php` and `PublicIndexation.php`
control profile readiness/exact origin and clean-query indexation.
`MarketplacePublicPage.php` generates the graph with the address finding above.

The root and profile URLs are already updated dynamically; no sitemap build or
manual XML edit is needed for routine publication. The 136 + 3 distinct URL entries
observed across the two systems are **139 advertised URL candidates**, not 139 indexed
pages or Search Console's discovered count.

Evidence consists of 13 bounded guest GETs and source comparison. The sanitized
JSON excludes cookies, auth props and full Inertia payloads. Public robots/sitemap
snapshots are saved beside it. No full 136-page marketing crawl, production SSR
outage simulation, booking mutation, browser visual certification, field performance,
Google-origin access, Rich Results Test or Search Console certification is claimed.
No application behavior was changed, so no product build or regression suite was
needed for this read-only audit. Documentation whitespace checks passed.

## Owner clarification after the HTTP audit

The owner confirmed on 2026-11-11 that BeRich is a **test/demo store**. Its absence
from a Google query is not a suitable validation of acquisition for a real salon.
The observed inclusion of this fixture in an indexable production sitemap requires
operator cleanup: Business setup → Marketplace listing → clear **Show my business
on Marketplace** → **Save visibility**. This should remove it from directory and
sitemap delivery; verify the live shard no longer includes its clean URL and that
the Marketplace profile returns404. Preserve account, booking and financial history.
Do not submit BeRich for indexing or disable indexing globally for real businesses.

No production visibility action was performed by this chat. BeRich's removal is
pending live verification. Whether the other two profiles are real or demo remains
unconfirmed; their HTTP eligibility must not be treated as proof of genuine supply.
The existing pitch-demo exclusion relies on saved provenance; this owner-confirmed
fixture is not safely classifiable from a name/address heuristic. No slug-specific
code exception or new environment setting is introduced.
