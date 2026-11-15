# SEO follow-up implementation — 2026-11-15

Status: implemented and verified locally; not deployed. Authority: owner's approval
of the [14 November plan](../2026-11-14-seo-performance/README.md) and subsequent
continue instruction. Scope: FR-01/19/21, PRD sections 4/12/15, MP-01/04 and
[accepted measurement/content refinement](../../decisions.md#public-seo-measurement-and-content-refinement--2026-11-15).
No external message, publication through the live article admin, Google setting,
production data/configuration, indexing request, git commit or deployment changed.
Existing audit documentation in the workspace was preserved.

## What changed

- Published blog details qualify for consent-based measurement only when the
  route-bound Article passes the same publication policy as public discovery.
  Only its clean server-owned path is added. Unknown, draft, future, unreviewed,
  inactive, blocked, signed-in, alternate-host and error pages stay excluded.
- Source families are classified in browser memory after consent and sent as
  `entry_source` on manual pageviews and trial clicks. Raw referrers remain empty;
  no URL query/fragment, form fields, account identity or campaign text is sent.
  Labels persist for this document's public navigation, not across reloads or
  signup. They do not prove organic attribution or replace standard GA channels.
- Consent payload 2 requests a new choice after previous acceptance for the new
  scope; unexpired previous rejection is retained. New rejections remain version1 for safe rollback. No new preference key, cookie,
  tracking provider or private-page measurement. Privacy Policy 1.3 and the cookie
  notice describe the change. Existing operational/legal publication flags were
  not edited or certified.
- Actual feature hero/evidence and solution evidence CTA contexts now qualify,
  with an article next-step context. Preserve the 200 ms maximum wait, duplicate
  suppression and fresh private navigation. CTA counts still do not mean signups.
- Added read-only `analytics:acquisition-report`, using existing owner intent and
  trial records. Local dates map to UTC inclusive-start/exclusive-end boundaries.
  Output contains aggregate counts and limitations only; explicit pitch demos
  excluded. No new tables, identity bridge, mutation or outbound analytics send.
- Corrected barbershop/nail Marketplace exclusions while retaining availability
  limits and no new-booking guarantee. Clarified client-record/handoff intent and
  directed the client-history use-case to salons. All 13 solution URLs retained.
- Added relevant publishable article and guide links to feature/use-case journeys;
  draft/withdrawal automatically removes article links. Article pages now offer
  trial, product walkthrough and pricing next steps with publisher disclosure.
- Added maintained printable guides at `/guides/service-timing-and-buffers` and
  `/guides/switch-from-paper-bookings`. They reuse Resources, sitemap, canonical,
  guide layout, existing images, print styling and AppNotice. Content explains
  active/processing/cleanup timing and distinguishes client CSV import from
  reviewed assisted appointment transfer. No new product import/timing feature.
- Article JSON-LD includes its actual featured image. Public “updated” projection
  omits edits before first publication or in the future, without modifying saved
  editorial history or stable blog URLs. No author URL/credentials invented.

## Verification completed

| Evidence | Result / limit |
| --- | --- |
| [Backend](backend.txt) | Marketing + Registration:302 passed,4,662 assertions; one existing conditional registration-disabled test skipped. |
| [Focused backend](focused-backend.txt) |38 passed,190 assertions for analytics gates, article policy/image/date, guide withdrawal and aggregate report. |
| [Frontend](frontend.txt) |253 passed, including 31 analytics tests. Covers source exact-host classification, spoofed/untrusted values, no referrer access before consent, version renewal/rejection, approved article paths, actual CTA labels and existing outage/privacy/navigation cases. |
| [Build](build.txt) |Client and SSR production builds pass on installed Node 24.19.0. Existing DaisyUI `@property` and Manrope font-resolution warnings remain. |
| [Budgets](budgets.txt) |25 public route bundle entries pass existing asset budgets. |
| [Formatting](formatting.txt) |Scoped Pint and whitespace checks pass. |
| [Original HTML crawl](local-readiness.json) |132/132 sitemap URLs pass the existing crawler with the actual built SSR renderer and isolated temporary SQLite data. Includes both new guides and one fictional article. Production's seven articles were not copied into this database; 132 is a local count, not today's production inventory. |

The backend report test includes one registration before the local midnight
boundary, a trial exactly at the boundary, an unverified registration, a record at
the exclusive next-day boundary, replayed onboarding and explicit demo exclusion.
JSON output is checked for no account email or business name. Existing HTTP
registration tests also exercise verified onboarding and repeated verification.
This is not an actual production signup or organic conversion audit.

The first combined backend/build run hit a temporary missing Vite manifest while
the build rewrote assets. After the completed build, the full backend run passed.
An initial run with the shell's Node 20 could not run the installed frontend
packages; rerunning on installed supported Node 24 passed. No package or lockfile
change was needed. Final focused rerun and frontend suite follow the CTA refinement.

### Browser observations before access was lost

An isolated local server on 127.0.0.1:8054 used a temporary SQLite database and one
clearly fictional article, no normal or production database. Actual client-management
page loaded with the new heading, definition and both reading links. At 390×844,
document width was 390px with no horizontal overflow. Following the actual migration
reading link reached the new guide; it also had 390px document width. Its long
Before you use this guide notice remained readable and the print/header controls
measured 44px high. The phone screenshot was visually inspected in the tool output.

Browser access then returned “Local browser access is unavailable for this chat”.
No alternate browser/native automation was used. The screenshot was not persisted
as a file before access was lost; no new screenshot artifact or full visual pass is
claimed. The temporary viewport could not be reset after access became unavailable.

Remaining browser verification: article→trial navigation and back/abandonment,
actual renewed-consent/storage-failure/Google-outage notices, long support links,
phone and desktop reading cards/guide print layout, and remaining affected pages.
Automated unit/HTTP evidence above does not replace these browser observations.

## Release and externally dependent work remaining

1. Finish the browser checks above when access is restored. Measure representative
   mobile/desktop lab performance on home, pricing, solution and blog. Do not use
   local HTTP response time or budget passes as CWV/lab-speed certification.
2. Deploy the reviewed source/config with matching client/SSR artifacts, refresh
   normal configuration and verify no stale client/SSR mix. No migration/new secret
   is required. Recheck live original HTML and retained private/booking behavior.
3. Verify actual Google event receipt and create/read back the event-scoped
   `entry_source` custom dimension only if absent. The custom dimension was not
   configured in this chat. Do not equate the Google family with Organic Search.
   [Exact runbook](../../deployment/google-analytics.md#article-source-measurement-release--2026-11-15).
4. Fresh URL Inspection remains required for the salon-opening blog, Security and
   account-profile Help. Recheck historical booking/www aliases after recrawl and
   use Rich Results Test for updated Article markup. No indexing request, Google
   live test or selected-canonical result was obtained during implementation.
5. Run the new aggregate command via authorized production operator access and
   compare complete windows separately from GSC/GA. No production aggregate was
   read; the report's local fixtures are not real business performance.
6. A genuine consenting customer story and substantive author bio remain dependent
   on real details. A concise question was sent to the owner; no answer is present
   at this checkpoint. No fictitious credentials/testimonial/page was created.
7. Marketplace local expansion remains inventory-dependent. Keep existing
   noindex/private/profile/supply gates. Do not create empty city pages or index
   demo inventory. Retain all existing seven blog URLs rather than cloning topics.

This implementation does not claim deployed traffic, customer recovery, Google
indexing, rankings, rich-result eligibility, revenue lift or regional privacy
certification. Historical 14 November performance and launch cohorts remain in
the original audit; the 15 November work has no post-release results yet.
