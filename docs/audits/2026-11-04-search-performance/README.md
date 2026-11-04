# Google Search performance follow-up — 2026-11-04

Read-only review of the authorized `sc-domain:clipperdesk.com` property and
public HTTP pages on 4 November 2026. Requirements: PRD sections 1–4/12/15,
public website module, ADR-024/033/065. Compared with the 2 November audit.
No source code, Google property settings, indexation requests, hosting or
publication gates changed. The owner's previous hosting hold remains in force.

## Assessment

Discovery and rendered public content are working, but it would be inaccurate
to say all SEO is finished. Two technical items remain: robots returns 404 and
marketing pages are accessible/indexed on the booking host. Search visibility
is still small and most relevant observed commercial queries rank well below
the first page. The evidence supports prioritizing consolidation and useful
buyer content rather than treating sitemap acceptance as traffic acquisition.

## Actual performance

| Metric | Prior check, 2 November | Current finalized view, 4 November |
| --- | --- | --- |
| Selected range | Three months | Three months |
| Displayed dates with data | 11–31 October | 11 October–1 November |
| Google search clicks | 0 | 0 |
| Search impressions | 28 | 37 |
| CTR | 0% | 0% |
| Average position | 61.8 | 55.8 |

This is **9 additional impressions**, approximately 32%, but on a tiny sample
and an additional reported day. It is not a controlled period comparison or
proof that the 2 November repairs improved ranking: the finalized view stops
before those repairs. Lower position numbers are better, but changing query
mix can change this average without a given keyword improving.

The fresh **24-hour view, 3–4 November**, reports **12 impressions, 0 clicks,
0% CTR and average position 43.1**. Treat it separately: its hourly data can be
preliminary and it must not be added to the finalized three-month total.
Google explains these differences in its [Performance report guidance](https://support.google.com/webmasters/answer/7576553/performance-report-search).

An impression means a search appearance, not a website visit. Search Console
counts Google search activity, not all channels or all visits. The approved
GA4 integration also requires consent and is scoped to guest marketing pages;
its visitor totals should not be equated with all website traffic. GA4 totals,
completed signups and customer acquisition were not re-audited in this task.

### What the searches show

| Observed query | Finalized impressions | Average position |
| --- | --- | --- |
| pre-visit planning software | 10 | 32.1 |
| schedule spa software | 3 | 88.3 |
| spa staff scheduling software | 2 | 91.0 |
| piercing shop management software | 2 | 91.0 |

Most named commercial queries appear far from the first page. A few individual
low-volume pages/queries have better positions; they do not establish consistent
rankings across the primary barber/salon market. Zero clicks from 37 impressions
is too small a sample to diagnose poor snippet CTR as the main problem.

Finalized page examples: health-practices 11 impressions / position 38.0,
spas 9 / 88.6, solutions 8 / 46.4 and independent-stylists 3 / 32.7. These
page totals use page aggregation and can exceed the property chart total.
The United States accounts for **24/37 finalized impressions** and **10/12
in the fresh 24-hour view**; this is an observed early audience, not keyword
demand research or approval to change country/locale product scope.

The latest query sample includes medical-spa phrases at positions 13/15 and
spa payroll at 87. Preserve truthful scope: the public site must not advertise
payroll, clinical records, insurance or regulated compliance to pursue those
queries. Search impressions do not authorize a new product capability.

## Indexing and health

- Sitemap still **Success**, last read 2 November, **127 discovered pages**.
- All **127/127 live sitemap pages pass** status, canonical, unique metadata,
  rendered H1/main/links and valid JSON-LD checks. The SSR repair remains effective.
- Manual actions and Security issues: **No issues detected**.
- Ten bounded key-page/Help inspections: **7 indexed / 3 discovered, not indexed**.
  This is a sample, not a fresh full 127-page census.
- Online-booking changed from unknown to **indexed**, with a 4 November saved
  crawl. `/use-cases` changed from a www-canonical duplicate to **indexed with
  the apex canonical**, with a 3 November saved crawl. Home/pricing are indexed.
- Barbershops, salons and account-profile still show **discovered, no crawl**.
  Three previously uninspected Help samples are indexed.
- The aggregate Pages report is **still dated 23 October** and reports
  8 indexed / 12 excluded. Do not present that stale report as the current total.
  The previous audit directly confirmed 72 indexed URLs; this sample cannot
  establish that all of those remain indexed or calculate a new global count.

### Unresolved robots status

`https://clipperdesk.com/robots.txt` still returns **HTTP 404** while its body
contains correct crawl rules and sitemap declaration. The existing prepared
Nginx `try_files` fix remains applicable; no save/reload was made here.
This is an incomplete deployment detail, **not evidence that all Google
crawling is blocked** or a proven explanation for the low traffic.

### Newly confirmed marketing host duplicates

The latest 24-hour Pages table includes:

- `https://book.clipperdesk.com/solutions/medspas`: 3 impressions / position 16.
- `https://book.clipperdesk.com/solutions/physical-therapy`: 1 impression / position 94.

Live checks confirm these two paths, `/features` and `/` return **200 with
index/follow and marketing content** on the booking host, without a redirect.
They correctly declare the matching apex canonical. Nevertheless, Google's
saved inspections independently index both booking-host solution URLs and
select the **booking-host URL** as canonical. Apex medspas is also indexed;
apex physical-therapy is discovered but uncrawled. Canonical tags alone have
not consolidated these observed URLs.

The marketing routes are domain-less, so reserved/multi-segment paths can fall
through the dedicated booking-slug matcher to marketing. Prepare a narrow
permanent redirect for approved public marketing GET/HEAD routes from booking
host to apex. Preserve actual salon `/{slug}` booking, private tasks, media,
assets and required auth/API behavior; a blanket host redirect would break
client journeys. No tenant booking or secure/private appointment URL was
inspected, and this finding is not evidence those private pages are indexed.

See `alias-host-checks.json` and `google-indexing-health.json` for exact evidence.

## Prioritized improvement plan

| Priority | Concrete work | Evidence and acceptance |
| --- | --- | --- |
| 1 — complete technical consolidation | After the owner's deployment hold is lifted, apply the prepared robots fallback and narrowly redirect public marketing aliases on the booking host. | Robots 200, full 127-page readiness check green, alias redirects to matching apex, real salon/private booking still works; recheck Google's selected canonical after recrawl. |
| 2 — get core acquisition pages crawled | Strengthen contextual links to the existing barber/salon pages from home, relevant feature/guide pages and the solutions hub; review their content, then use URL Inspection when eligible. | Both currently show discovered/no crawl. Keep sitemap active, request only eligible key pages, respect challenges/quotas, record Google's eventual result without promising a date. |
| 3 — show useful product evidence | Expand barbershop and salon pages with one accurate worked appointment/walk-in workflow, real isolated-demo screenshots, pricing context and concise buyer questions. | Current pages describe generalized workflows in 255/264 words and use editorial hero imagery. Depth, clarity and proof are the objective; there is no word-count ranking target. Preserve payment/deposit and specialist-module limits. |
| 4 — build a small useful guide library | Deepen the two existing guides first, then publish reviewed guides such as managing walk-ins alongside appointments, preparing staff/service hours for online booking and keeping repeat-client history. Link each to the matching product page and detailed Help. | Sitemap contains **zero published blog article URLs**, only two short guides and 95 Help pages. Help serves existing customers; the acquisition library needs its own practical buyer value. Preserve author/review/publication gates and avoid many near-identical industry pages. |
| 5 — earn recognition from real users | Obtain consented customer examples and genuine mentions/links from customers, relevant partners or industry communities where the product is useful. | Google's Links report currently reports **0 external links**, with no update date; that is a lagging/reported dataset, not proof no backlinks exist or a causal ranking diagnosis. No outreach or paid link purchase is authorized or performed here. |
| 6 — measure qualified reach | Review weekly per-page/per-query impressions, clicks and position for the core commercial pages; assess opted-in marketing trial clicks separately from confirmed account creation. | Fix the technical items, then compare sufficiently complete equal periods. Prefer relevant barber/salon discovery and actual signups over a global indexed-page count. Do not optimize unsupported payroll/clinical claims to match irrelevant queries. |

These are recommendations, not implemented changes or traffic forecasts.
Google recommends useful, reliable content, customer search language and
crawlable descriptive links in its [Search Essentials](https://developers.google.com/search/docs/essentials).
Its [traffic guidance](https://developers.google.com/search/docs/monitor-debug/debugging-search-traffic-drops)
explains that effects can take days to months; review over several weeks rather
than expecting measurable acquisition from a sitemap submitted two days ago.
No fixed ranking, traffic or signup outcome is promised.

## Evidence and limits

| File | Contents |
| --- | --- |
| `google-performance.json` | Exact visible metrics; finalized and 24-hour data separated |
| `performance-*.csv` | Query/page/country/device and recent-day views |
| `performance-24h-*.csv` | Preliminary query/page/country views |
| `google-performance-summary.png` / `google-performance-24h.png` | Console evidence screenshots |
| `google-indexing-health.json` | Sitemap, stale aggregate report, security, 10 key inspections plus 4 host comparisons |
| `google-links.json` | Google's reported external/internal link samples and limitations |
| `live-readiness.json` | Full 127-page HTTP check; only overall failure is robots 404 |
| `live-summary.md` / `live-content.json` | Current content observations and specific editorial opportunities |
| `alias-host-checks.json` | Current booking-host marketing duplicates and matching apex responses |

No paid tools, keyword-volume dataset, competitor benchmark, GA4 conversion
review, full new index census or field performance measurement was performed.
This audit made no Google submission, code/hosting change, outbound contact or
recurring automation. No tests were necessary because product behavior was
unchanged; the live checks are evidence of current responses, not local tests.
