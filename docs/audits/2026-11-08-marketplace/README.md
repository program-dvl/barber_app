# Marketplace implementation — 2026-11-08

Scope: PRD MP-01–04 and the owner's approval to finish the connected Marketplace.
Canonical contract: [Marketplace module](../../modules/marketplace.md).

## Delivered locally

- Distinct Marketplace identity and customer navigation; Business acquisition
  qualifier and two-way navigation without replacing existing acquisition URLs.
- Enrolled active salon discovery, category/name/service/address, rating/sort,
  currency-specific price/date/today filters, session-only approximate nearby and
  map/list views; canonical salon profiles with team/gallery/reviews/location.
- Dedicated passwordless verified customer identity, signup/login recovery,
  versioned terms acceptance, session separation, upcoming/past bookings,
  favourites, booking-linked inbox and private-link claiming with matching email.
- Marketplace booking entry uses canonical holds/commit, service/location/date
  handoff, transactional automatic account linkage and replacement linkage.
- Owner listing preview/publish/pause with permission/revision/audit, booking
  session counts, scoped salon inbox, encrypted paged messaging, read status,
  exact-send replay, customer pause/report and audited operator concern review.
- Persistent safe feedback, saved drafts, rate cooldown and support references.
- Manual booking payments unchanged; no booking gateway/connected accounts,
  charges, payouts or automatic refunds. Existing required-deposit gates remain.

## Verification

The final results are recorded in project status. Retained local command logs:
`/tmp/marketplace-release-regression.log`, `/tmp/marketplace-front-verified.log`,
`/tmp/clipper-marketplace-complete-build.log`, `/tmp/marketplace-budgets.log`.

The broad backend run covers Marketplace, public booking/waitlist/reviews,
workspace access, marketing and canonical booking engine. Includes fractional
clock precision, single-use/expired email links, email-send outage, wrong-account
and wrong-tenant access, duplicate messages, pause, contact-change access removal,
old-message pagination, listing revision/pause, inherited reschedules, approximate
nearby, effective starting prices and date availability, deposit blocking,
platform-failure stop, simultaneous customer/business sessions, terms and support.
Existing booking engine coverage includes DST and stale/concurrent availability.
MySQL concurrent booking tests were not executed in this task.

Two existing verification fixtures needed maintenance: the booking presentation
mount harness now supplies the new usePage dependency; the product-tour test now
expects the two already-shipped reviewed salon/barber screenshots, matching the
2026-11-07 audience refinement. No screenshot content or public feature claims
were removed to make that assertion pass.

## Browser observations (isolated fictional data)

Preview at `http://127.0.0.1:8039`, isolated SQLite under ignored
`tmp/marketplace-qa/`, logged mail and fake client transports. No real provider
email, SMS, customer booking or payment was performed.

Observed populated discovery → salon → live times → selected-service booking →
future date/time → details review → confirmation. Booking appeared automatically
in the customer's account. Favourite save was visible. Customer email-link sign-in
into an already-created fictional account succeeded without affecting Business
sign-in. A customer message appeared in the salon inbox; the salon reply appeared
in the customer conversation; read states changed appropriately. Owner inbox
showed the marketplace-started/confirmed counters and linked visit. Customer
conversation at 390px had document width 390px and no sub-44px action buttons.
Desktop account at 1440px was visually observed. A small-screen wordmark wrap was
found and corrected in source. No fresh screenshots of this continuation were
saved before browser access was disabled; `desktop.png` is earlier foundation
only and must not be presented as final connected-journey evidence.

Browser access was subsequently disabled in Work settings. Final filter/map/inbox
navigation refinements, final wordmark correction, persistent-error/cooldown
screens, owner pause/preview at desktop/phone, expired-link recovery and full mobile
booking must be visually rechecked before claiming complete UI certification.
Maps at the new origin were not certified; existing map error/list fallback code
builds, but provider authorization and rendered map still need exact-origin checks.

## Local migration recovery

Local MySQL's initial migration stopped at a composite foreign key whose columns
were in the wrong order for the existing appointment index. Corrected to
`appointment_id,business_id` → `appointments(id,business_id)`. The initial migration
can resume its own created tables/fields. A read-only index check found the
interrupted create had also skipped later uniqueness statements; migration 000003
restored the unique appointment link and customer/business index. All three
marketplace migrations were applied locally. No appointment/financial history
was rewritten or removed. Deployment has not been performed.

## Deployment and release checks

1. Deploy all three new migrations, code and matching client/SSR build together;
   use normal cache refresh/worker lifecycle. Do not expose customer routes to an
   unmigrated database. Preserve data for recovery; do not run destructive rollback.
2. Verify the chosen first-party HTTPS `APP_URL`, normal mail delivery and signed
   link round-trip on that exact host. Configure the existing map origin normally.
   No new payment or email service is required.
3. Review Marketplace terms/privacy with the platform owner before external signup.
   Confirm retention and review-removal/appeals procedures separately. Current
   concern management does not selectively hide ratings or erase messages.
4. Complete the remaining desktop/mobile browser checks above, including mail/map
   outage, expired link, entered draft preservation and visible retry countdown.
5. Verify production calendar sync/manual payment recording, cancellation and
   reschedule/receipt journeys with a controlled permitted test salon. Do not infer
   production success from local tests or redirect flags.
6. Enroll salons through owner-reviewed preview and consent. Existing real salons
   remain unlisted unless they opt in. Confirm no-required-deposit setup for instant
   manual-payment bookings; do not bypass a salon's saved deposit requirement.
7. Measure broad price/date discovery at intended enrollment before expansion;
   it currently streams canonical per-salon queries rather than a search index.
8. Keep scheduler enabled for `marketplace:prune-login-links`. Monitor login mail,
   failure references and concerns. Messaging is in-app with explicit refresh;
   no push or email/SMS chat notification is claimed.

No production host change, rollout, real customer enrollment, legal certification,
load certification or live provider-send result is claimed.

## Customer refinement verification

This later owner-approved refinement supersedes the default-hidden listing,
price/currency UI and manual claim-by-link delivery descriptions above. Apply
**four** marketplace migrations, including 000004 (Google subject/mobile), before
traffic. Published active online-booking salons now appear by default; saved owner
pause wins. No stored salon configuration was mass-published or changed. Review
this default visibility rule during rollout instead of the earlier opt-in step6.

Chrome access was restored on 2026-11-08. Read-only logged-in review of Fresha
homepage/profile/account menu and Salonist homepage/profile/account menu confirmed
unified service/location/date search, dedicated account navigation and explicit
logout. The owner's supplied screenshots additionally show contextual service,
recent-location and date choices. Clipperdesk implements category/current-result
suggestions, optional date and approximate location; it does not invent recent
search data or copy competitor assets/account details.

Local isolated fictional browser evidence at 8039:

- Email link into the existing fictional customer succeeded using logged mail.
- Saving a new name stayed on Profile with “Your profile has been updated.”
- Revisiting `/marketplace/sign-in/confirm` while authenticated opened bookings,
  not the expired-link screen. A real preview booking remained visible.
- Logout removed customer access. The signed-out confirmation recovery offered
  “Get a new sign-in link”, which opened the working email/Google entry page.
- Default discovery showed the existing preview salon; unmatched location showed
  the no-match notice; “Explore all salons” restored the list.
- Service/location/date panels, Tomorrow selection and Escape dismissal worked.
- Profile and search were visually inspected on desktop and 390×844. Phone document
  width was390; header brand touch height was corrected and rechecked at44px.
  Temporary viewport override was reset and the preview tab closed.

Screenshots contain fictional data only:
[profile desktop](profile-desktop.png), [profile phone](profile-mobile.png),
[saved profile phone](profile-mobile-saved.png), [search desktop](search-desktop.png),
[date search phone](search-mobile.png), [expired link phone](expired-link-mobile.png).

Automated regression: **332 tests / 5,073 assertions** across Marketplace, Google,
account notification and Marketing suites; **219 frontend tests**; client/SSR build
and all22 frontsite budgets pass. Logs: `/tmp/marketplace-refinement-regression.log`,
`/tmp/marketplace-refinement-frontend.log`, `/tmp/marketplace-refinement-build.log`,
`/tmp/marketplace-refinement-budgets.log`. Focused final rerun after the effective
listing audit correction: `/tmp/marketplace-refinement-final-tests.log`.

Same-email history/isolation, remembered customer+Business sessions, Google verified/
unverified identities, new-account terms, shared callback and standard email shell
are covered automatically. The live Google callback, production mail/map origins,
complete failure/cooldown mobile matrix, owner listing at both sizes, broad-search
load, legal review and deployed calendar/manual-payment journeys remain release
checks. No production issue, payment/provider integration or SEO result is claimed
resolved from this local evidence. Business remains the root acquisition page;
Marketplace is prominent in Home/navigation at `/marketplace`, still noindex until
its launch/content review. No new gateway is present.

## Unified booking and Google map continuation

Owner's latest screenshots showed a duplicate service/time screen followed by the
established booking page, inaccurate broad service suggestions and category cards
with no visible result change. The Marketplace salon now renders `Booking/Welcome`
directly; obsolete `/book` entry links redirect with their choices. The duplicate
`Marketplace/Salon.vue` is removed. Favourite/status/account affordances are kept;
private preview still cannot book and does not load additional public reviews.

Service suggestions aggregate actual canonical public catalogue services with an
eligible location and qualified visible staff. Category cards include matching
public service/category terms as well as business type; click sets the category
and reveals results after response. Specific suggestions clear broad category.
This is an uncached catalogue read, not directory-scale performance certification.

Google location selection reuses the configured supported Places widget and fetches
only geometry. Current location requests browser high accuracy on action. Full
reported precision goes only to a server browsing-session search centre, valid for
30minutes and removed on expiry check/clear; no customer location history/URL is
created. Map data remains owner-reviewed salon entrance pins. The privacy text and
location specifications distinguish this from browser-only individual-salon branch
sorting. No key, Google Cloud restriction, API provisioning or credential changed.
Map view now has numbered pins, selectable adjacent cards, Show all pins, separate
Explore & book, retained no-pin results and a working list fallback on denial.

Verification logs:

- `/tmp/marketplace-maps-regression.log`: **116 passed / 1,259 assertions** across
  Marketplace, Google, public booking, preview and location intelligence.
- `/tmp/marketplace-maps-policy-tests.log`: **32 passed / 258 assertions** covering
  privacy and HTTP security.
- `/tmp/marketplace-maps-frontend.log`: **224 passed**, including mounted category
  click/result reveal, named service selection, pin/card selection without forced
  navigation, map denial/list recovery, minimal Places fields and ignored stale
  selection callbacks. Shared booking presentation and Maps loader tests also pass.
- `/tmp/marketplace-maps-build.log`: client/SSR build passed; existing CSS/font
  warnings remain. `/tmp/marketplace-maps-budgets.log`: frontsite budgets.

Live browser check was attempted at `http://127.0.0.1:8000/marketplace`, the previously
configured origin. CUA returned “Local browser use is disabled in Work settings”.
No live map, new screenshot, Places result selection, browser geolocation permission
or desktop/mobile visual outcome was observed. Source layout and mounted mocks are
not substitutes for those checks. Existing earlier screenshots must not be used
as evidence of this new iteration. The isolated preview server was stopped; no
real customer/salon/provider data was changed and no booking/payment was made.

Remaining check: restore browser control, verify actual Places selection/current
location and denial, rendered pin/card map and fallback, category result visibility
and the single booking flow at desktop and phone on the exact configured origin.
Production origin, legal/retention review, scale and deployed scheduling/manual
payment certification remain the existing launch gates. No deployment performed.
