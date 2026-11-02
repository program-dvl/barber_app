# Live HTTP crawl baseline — 2026-11-02

Checked approximately 10:09–10:14 UTC (15:39–15:44 Asia/Kolkata), before any
repairs by this task. Source: owner-authorized unauthenticated HTTPS requests to
`https://clipperdesk.com`. This records our fetches, not Google-origin crawling,
indexing, impressions or rankings.

## Results

- `/sitemap.xml`: HTTP 200, valid UTF-8 XML in the standard sitemap namespace,
  9,952 decoded bytes and **127 unique URLs**. Ordinary, Googlebot user-agent and
  gzip responses contained identical XML. The compressed response transferred
  1,229 bytes and completed in 1.81 seconds in one sample.
- All **127/127 sitemap URLs returned HTTP 200**, one unique title, one unique
  meta description, a self-referencing canonical, index/follow in HTTP and HTML
  directives, and syntactically valid JSON-LD. None redirected or failed.
- **32 marketing URLs returned no rendered page body, H1 or anchor links** in
  their original HTML. They contain metadata, structured data and an Inertia
  payload, with `<div id="app"></div>`. This makes their visible content and
  internal discovery depend on JavaScript rendering. A Googlebot user-agent
  homepage fetch behaved the same. This is consistent with unavailable production
  SSR; it is not proof that Google cannot render or index them.
- **95 Help URLs have readable server fallback content** with a single H1, even
  while the marketing server-rendered body is unavailable.
- **`/robots.txt` returns HTTP 404**, despite the body containing an Allow-all
  policy and the correct sitemap URL. Repeated independently. The application
  controller returns a normal response, so nginx handling of this special URL
  should be checked; that cause is an inference pending server inspection.
- HTTP root and HTTPS/HTTP `www` variants end at the HTTPS apex homepage.
  `/sitemap` ends at `/sitemap.xml`. The crawl captured final destinations rather
  than each individual redirect status.
- Login/register and unapproved privacy/refund documents remain noindex.
  Retired roadmap/changelog return 410; a missing URL returns 404 and noindex.
  The incidental `/terms` probe returned 404; it is not a sitemap URL or asserted
  to be the configured Terms path.

## Limits and follow-up

The sitemap fetch error reported by Search Console was **not reproduced** by
ordinary, Googlebot user-agent or gzip HTTP fetches. No evidence currently
justifies blaming XML validity, truncation, compression, an AAAA record or a
specific sitemap URL. Public DNS showed one IPv4 A record and no AAAA record in
this check. Google-origin access must be assessed in Search Console and server
access/error logs if its error persists.

Repair production SSR and the robots HTTP status, then repeat the full original
HTML crawl. A browser-rendered pass is separate from this HTTP evidence. GSC
sitemap processing and individual index status need separate confirmation after
Google refreshes them; sitemap acceptance does not establish ranking.

## Files

- `live-crawl.csv`: page-by-page audit register.
- `live-crawl.json`: complete normalized response evidence, including public
  links/schema; cookies and full Inertia payloads are deliberately excluded.
- `live-googlebot-check.json`: homepage with a Googlebot user agent; this does
  not impersonate a genuine verified Google source IP.
- `live-sitemap-integrity.json`: compressed XML and sanitized header evidence.
