# Search discovery repair — 2026-11-04

Owner-authorized robots.txt, crawlable blog pagination and marketing alias
consolidation. PRD sections1–4/12/15; ADR-024/065.

Before:131 live sitemap pages passed original-HTML checks, but robots returned404
(before.json). Saved only the existing Nginx robots location fallback through
Forge, preserving the40m upload setting. Live robots now returns200/text/plain
and the expected main-domain sitemap. No static robots/sitemap file was added.

Local code: twelve-post archives with crawlable Previous/Next, distinct page
canonicals, stable ordering and invalid-page404s; successful explicit marketing
routes on known aliases redirect301, or Inertia409 external visits. Tenant booking
and private/auth/API/mutation paths retain behavior. Booking-host root redirects
only because it is the marketing home route. No real articles were modified.

Validation: Marketing276 tests/4246 assertions; frontend219 tests; client/SSR
build and all21 route budgets passed. Scoped Pint/whitespace passed. Isolated
25-article browser visited page2→3→2;390px viewport has no overflow and both
actions are44px. Actual Vue SSR tests verify crawlable link markup.

Robots screenshot: robots-live.jpg. Archive phone screenshot: pagination-phone.jpg.
Source **3122f24** deployed through Forge push-to-deploy **82111204**, confirmed
Deployed. Live route checks (live-routes.json) confirm book-host home/blog/
barbershop solution and www features redirect301 to matching primary paths.
Primary blog/sitemap/image return200; book/manage-appointment retain200 and
noindex without marketing redirects. Page2 correctly returns404 while only four
eligible posts exist; the25-article isolated preview verifies later-page behavior.
The live browser follows book-host/blog to clipperdesk.com/blog with all four posts.
Full131-page post-robots crawl passes (after-robots.json); final route probes were
performed after source deployment. No production booking or article was created.
No Google Search Console indexing request or ranking improvement is claimed.
Google indexing/ranking is outside these technical verification claims.
