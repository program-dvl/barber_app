# Live HTTP and content follow-up — 2026-11-04

Read-only audit under PRD sections 1–4/12/15, the public website specification
and ADR-024/026/033/065. No code, hosting, indexation policy or publication gate
was changed. The owner's hosting/deployment hold remains in effect.

## Current technical result

The check began at **07:45:02 UTC / 13:15:02 Asia/Kolkata** on 2026-11-04.
`live-readiness.json` contains the full page register.

- **127 sitemap URLs; all 127 page checks pass.** Each returns HTTP 200,
  readable original HTML with one H1/main region, links, one self canonical,
  index/follow directives, single unique title/description and valid JSON-LD.
  The November 2 production SSR repair remains effective in this observation.
- **The overall check still fails because `/robots.txt` returns HTTP 404.**
  The robots body and sitemap declaration are correct; checking body text alone
  would conceal the unresolved status. `/sitemap.xml` returns HTTP 200.
  The known nginx exact-location repair remains documented in
  `docs/deployment/search-indexing.md`; it was not applied here.
- `https://www.clipperdesk.com/` returns 301 to the HTTPS apex.
  `http://clipperdesk.com/` returns 301 to the HTTPS apex.
  `http://www.clipperdesk.com/` returns 301 to HTTPS www and then to the apex
  (two hops). This audit does not infer that the extra hop caused low traffic.
- This is our HTTP access, not proof Google fetched or indexed all 127 URLs.
  Search Console performance and individual indexing evidence must be assessed
  separately. Site visits, search impressions and search clicks are different
  measurements.

## Concrete content gaps to prioritize against Search Console queries

The observations below come from the live original HTML captured in
`live-content.json` and the corresponding repository templates/configuration.
These are editorial hypotheses grounded in current content, not measured search
volumes, a competitor study or promises of ranking improvement. Word counts
measure visible main-region text and are not ranking targets.

| Page | What is present now | Specific useful improvement to prepare |
| --- | --- | --- |
| `/solutions/barbershops` | 255 words explaining queue/calendar fit and changes, plus one editorial barbershop image. No concrete worked day, public product screenshot or targeted FAQ. | Show one real supported example: a walk-in requested between two booked visits, how staff availability is checked, where the queue/client record appears, and how the visit reaches recorded checkout. Use an isolated demonstration screenshot; link the exact queue/calendar Help instructions. Answer who can use it, how bookings/walk-ins fit together, whether clients need an app, and what checkout does. |
| `/solutions/salons` | 264 words; multi-service visits, protected client context and preserved policies are described in general terms. | Show an actual supported multi-service appointment and the client record/calendar handoff. Add a concise decision section for solo vs small teams and a link to the live pricing estimator. Explain the currently unavailable deposit collection rather than advertising salon payments, memberships or campaigns that are outside the shipped scope. |
| `/solutions/independent-stylists` | 264 words, with headings such as “Setup needs to show what is truly missing” and “constant reconciliation.” | Make headings match the solo owner's task: publish a booking link, work from one calendar, preserve visit notes and record payment. Show one-chair setup and clearly explain the browser-based client booking experience. These tasks are already represented in the product and current spec. |
| `/` | Broad title/description span beauty, wellness, fitness, health and pets; H1 is the approved brand promise. The first paragraph says appointments, clients and recorded payments without naming the primary salon/barber buyer. | Preserve ADR-033's umbrella identity, but consider one plain explanatory sentence near the hero naming appointment software for barbershops and salons, with the broader segments retained below. Validate this against actual non-brand query impressions before a copy change. |
| `/guides/salon-opening-checklist` | 200 words, titled “Prepare your business for online booking”; four sections cover configuring hours, services, staff and preview/publication. The URL suggests a broader salon-opening checklist than the article delivers. | Make the resource listing/description clearly promise an online-booking setup checklist. Add a usable example checklist and specific Help links for each step. Do not infer this covers leases, licensing, business opening or jurisdictional compliance. Preserve a stable canonical unless a reviewed migration is justified. |
| `/features/calendar-and-walk-ins` | 237 words, general workflow/limits, related feature links. | Add one illustrated queue-to-calendar example and link to precise operational Help articles. This gives owners something concrete to assess when looking for appointment-and-walk-in software; do not inflate it with repeated generic keywords. |
| `/blog` and `/resources` | Blog main content is only 36 words and redirects the reader to practical guides; there are **zero published article-detail URLs** in the sitemap. Resources has two guides and 119 words of main text. | The existing site does not yet have a substantial editorial acquisition library. Expand the two useful guides first and create a small reviewed sequence around actual barber/salon jobs and verified GSC query opportunities. Preserve author/review/publication gates; do not mass-publish generic industry pages or invent customer proof. An empty blog should not be presented as evidence a content programme is already running. |

The 13 solution pages are already discoverable and have distinct metadata; a new
batch of near-identical solution pages is not supported by this audit. Focus on
clearer primary-customer evidence and useful task depth in the existing pages.
A valid sitemap and metadata are baseline mechanics; neither proves demand,
relevance, customer trust or a competitive search position.

## Next decisions from the console evidence

1. Separate **no impressions** from **impressions at low position** and
   **reasonable-position impressions without clicks**, using page/query data.
   Apply copy/content work to the observed condition rather than changing all
   titles at once.
2. Keep the robots status repair as the known operational item for completion
   after the deployment hold is lifted. The response is currently 404, not 5xx;
   this audit does not label it a complete Google crawl block or claim it is the
   explanation for low visits.
3. Recheck the URLs that were unknown or duplicates on November 2 using fresh
   URL Inspection evidence. Canonical HTTP behavior is correct now, but that
   alone does not establish Google's latest selected canonical.

## Additional host finding from Search Console — 07:48 UTC

Search Console's current 24-hour Pages report surfaced marketing URLs under
`book.clipperdesk.com` (medspas and physical therapy). The read-only follow-up
`alias-host-checks.json` confirms the live host behavior:

| Requested URL on booking host | HTTP | Redirect | Robots | Canonical |
| --- | --- | --- | --- | --- |
| `/solutions/medspas` | 200 | None | index, follow | Matching `https://clipperdesk.com` path |
| `/solutions/physical-therapy` | 200 | None | index, follow | Matching `https://clipperdesk.com` path |
| `/features` | 200 | None | index, follow | Matching `https://clipperdesk.com` path |
| `/` | 200 | None | index, follow | `https://clipperdesk.com/` |

All four contain rendered marketing headings and links; matching apex URLs also
return HTTP 200 with the same title/H1. Production currently serves duplicate
marketing pages on the booking host even though canonical metadata is correct.
The route explanation is concrete: `routes/web.php:75` scopes only the
one-segment nonreserved salon slug to the booking host; the marketing routes
beginning at line 147 are domain-less and can serve other hostnames. Reserved
slugs such as `features` intentionally bypass the salon matcher, then fall
through to marketing.

This is a higher-priority technical follow-up than rewriting sitemap XML. A
reviewed narrow redirect for approved acquisition GET/HEAD routes on the booking
host can consolidate these pages on the public apex. Preserve actual salon
`/{slug}` booking, media/assets, auth/API and private task behavior; a blanket
booking-host redirect would break legitimate client journeys. Do not block all
booking-host access or change the sitemap to list the duplicate host. This task
made no implementation or hosting change.

The appearance of book URLs in today's Search Console performance is observed
by the parent console audit; the HTTP checks do not independently establish why
Google associated those URLs with impressions or its currently selected
canonical. URL Inspection must distinguish historical selection from current
live metadata before declaring canonical consolidation fixed.
