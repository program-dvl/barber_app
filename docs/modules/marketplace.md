# Clipperdesk Marketplace

Requirements: [MP-01–04](../product-requirements.md#owner-approved-marketplace-expansion--2026-11-08).
Approval: [marketplace expansion](../decisions.md#marketplace-expansion-approval--2026-11-08).
Updated 2026-11-11. Implemented locally; not deployed or certified for public launch.

## Two connected experiences

Business remains at `/` and its existing workspace routes, now explicitly labelled
Business with Marketplace navigation. `/marketplace` has the approved ivory,
forest and sage customer identity. `/marketplace/salons/{slug}` renders the existing
`Booking/Welcome` page directly, with Marketplace navigation and account linkage.
It reuses public salon photos, biographies, consented verified-visit reviews,
services, price variants, locations, usual hours, map and directions. The duplicate
Marketplace service/time preview was removed. `/salons/{slug}/book` redirects to
that same salon URL while preserving service/location/date choices. Direct salon
booking links continue to use the same component and canonical commands. Owner preview uses authorized private
media URLs and cannot book, save favourites or load further public reviews.
Business-wide gallery/branding remains the existing canonical editor; no duplicate
marketplace-only media store or invented listing/review data is created.

Discovery supports service/business text, service category, address/location,
compact Top rated and Available today choices, alphabetical/highest-rating sorting,
optional date, list/map views and nearby search. A unified service/location/date
bar loads actual currently eligible service names/categories from the canonical
public booking catalogue. Suggestions exclude add-ons, hidden/ineffective services,
unqualified/hidden staff, missing branch eligibility and unlisted salons. The
read-only `/marketplace/services` endpoint is throttled; legacy catalogue aggregation is
uncached. The enabled growth path uses the bounded projection and query evidence below.

The public directory uses all 13 Solutions audiences and qualified public service
offers automatically. A compatibility directory path is retained internally for
older callers and regression coverage.
Specific service suggestions clear the broad audience selection.
No currency/price filter is exposed in the UI; legacy query parameters remain valid.

Google Places search reuses the configured loader and supported attributed widget.
It fetches only selected location geometry, not a stored POI/address catalogue.
Text-only city search and device location remain working alternatives. Current
location requests high accuracy only on explicit action; actual accuracy depends
on the browser/device. A geographic search centre keeps full reported precision
in the server session, expires for use after 30 minutes and is removed on expiry
check/clear; coordinates never enter URLs or customer/booking records. This replaces
the earlier rounded approximate-centre rule under the owner's latest instruction.
Within-25-km matching uses saved owner entrance pins and straight-line estimates;
salons without pins still appear in ordinary text/list searches.

Map view has an adjacent scrollable results list, numbered selectable pins, active
salon highlighting, independent Explore & book actions and Show all pins. It labels
its page scope and preserves cards without pins. It stacks on phones. Provider
failure retains all salon cards and offers Show salon list; no repeated reload is
presented as a repair. Existing Google attribution and key/origin restrictions apply.

Price/date filters reuse the public catalogue, staff/location price overrides and
canonical availability query. The disabled-growth legacy path streams candidates in batches of 50; the enabled
growth path bounds live date checks to 12 candidate businesses and labels its scope.
Neither caches stale available slots as a booking promise. This is a
correctness-first launch implementation, not a benchmarked large-directory search
index. Broad date searches require load evidence before enrollment at scale.
Cross-currency price comparisons/conversions are not performed. Next time labels
include the salon time zone; selection and reservation happen in canonical booking.

## Listing consent and owner workflow

Business → Marketplace & messages offers preview, explicit listing/pause controls,
links to canonical details/photos and marketplace-started/confirmed session counts.
Only business-wide settings authority can change publication. Updates use business
locks, revision checks and audit events. Unpublished/paused online booking,
inactive businesses and businesses without active locations never appear.
Following the owner's refinement approval, published active online-booking salons
appear by default. An explicit saved owner pause (`marketplace_consented_at` set,
`marketplace_listed` false) always wins. Draft, suspended, disabled or location-less
salons stay hidden. No database mass-enrollment or configuration publication occurs.
The old slug environment variable is retired. Pausing Marketplace preserves direct booking links.

## Customer identity and bookings

A dedicated `marketplace` session guard and customer table are independent of the
Business `web` guard. Login/signup/recovery use a private 15-minute, single-use
email link or stateful Google OAuth with a verified email. Both use persistent
remembered customer login, independently of Business. Google reuses the existing
configured callback, dispatches by saved audience and never creates a Business user.
New Google accounts review terms before creation. No provider tokens are stored.
GET previews do not consume links; an explicit CSRF-protected POST
completes sign-in, regenerates the session and records the accepted terms version.
An existing customer with the current version need not accept it on every login.
New accounts cannot access Business. Customer sign-out preserves a simultaneous
Business sign-in. Customer email/name props are restricted to Marketplace pages.
Email sends use configured first-party `APP_URL` and the existing mail transport;
no new sender/service is provisioned. Mail failure invalidates that link, preserves
entered email, explains recovery and retains a one-minute email cooldown.
Expired login tokens are pruned daily after a one-day buffer.

Marketplace booking uses the existing hold/commit services through scoped routes;
services/location/date carry into booking. Signed-in flow provenance captures the
customer; successful matching-email booking links transactionally to the account.
Guest booking remains possible. On account/inbox access, existing unlinked bookings
with the same normalized verified email are linked automatically, with locking and
audit. Links owned by another account are never reassigned. Matching email grants
only the booking projection, never merges tenant CRM records. Different emails
cannot claim records. Current-contact checks remove access after contact changes.
The legacy private-link endpoint remains compatible but has no customer claim UI.

The customer dashboard has upcoming/past pagination, favourites and Manage booking.
A dedicated profile supports name/mobile changes and read-only verified email;
profile saves explicitly return to the profile, never a stale confirmation Referer.
Already-authenticated sign-in/confirmation URLs return to bookings. The avatar menu
and profile navigation expose logout, bookings and messages. Management opens fresh canonical secure
links for cancellation, rescheduling, waitlist, review and receipt/payment details
as supported by the existing appointment experience. Replacement appointments
inherit account linkage inside the canonical lifecycle transaction. The original
visit remains in history; private CRM notes/files/preferences are never returned.

## Manual payments

Marketplace does not charge customers, save cards, initiate booking refunds or
create connected payment accounts. The business collects payment and records its
amount/method through existing Checkout & sales with append-only history.
SaaS subscription billing is independent and unchanged. Existing deposit-required
booking gates remain: no payment bypass is introduced; customers must contact the
salon if its policy requires a deposit. Review that policy operationally before
listing such a salon. No automatic provider collection is advertised.

## Booking conversations and support

Customer `/marketplace/messages` and Business → Marketplace & messages list
booking-linked conversations. Customer ownership and current matching contact
are rechecked for every read/write. Staff need client-contact permission plus
canonical appointment-view/update authority, including branch/own-staff scope.
Business message access never grants another tenant access.

Messages are plain escaped text, encrypted at rest, append-only, paged 50 at a
time and unique per booking/sender/request UUID. Exact retries return the same
message; changed retries fail. Read status reflects opening the displayed page.
Drafts and request keys are remembered in the browser history until successful
send. The current inbox uses explicit refresh; there is no background polling,
push, email/SMS chat notification or implied real-time delivery. Sending stores the
message for the other party's inbox, rather than claiming they have read it.

Customers can pause/reopen both directions without cancelling their appointment,
and save an encrypted concern with a safe reference and support-email action.
Authorized platform operators see Marketplace concerns, audit reads and append a
reason when changing open/resolved status. This does not introduce selective
negative-review hiding; public review consent/withdrawal and all-rating aggregation
remain authoritative. Content removal/appeals and data-retention policy remain
separate governed work; this change performs no destructive history deletion.

## Recovery, deployment and evidence

Persistent feedback uses shared AppNotice. Mutations retain drafts, stop repeated
submission during processing, respect Retry-After and disable further mutation on
persistent platform/session errors with a reachable support reference. No customer
is asked to repair infrastructure. Links and support remain available.

Apply all four `2026_11_08_*` migrations before routing traffic. The third verifies
indexes after a partially completed MySQL DDL run; composite tenant FK order matches
`appointments(id,business_id)`. The initial interrupted local migration was resumed
and repaired without changing existing visits. Do not roll back customer/message
history as a recovery action. Build client/SSR assets, refresh application caches,
and retain the scheduler for login-link pruning. Migration 000004 adds the optional
customer mobile number and unique Google subject identifier. Use existing HTTPS/mail/map
configuration and verify the exact public host/origin before launch.

[Implementation evidence, verification limits and launch checklist](../audits/2026-11-08-marketplace/README.md).

## Account presentation refinement — 2026-11-08

MP-02/04 pages now share MarketplaceAccountLayout with desktop navigation/identity
rail, mobile four-item navigation, consistent page header and aligned card/form
rhythm. Bookings use zoned date/detail/action cards; saved favourites remain on the
booking page and have a direct navigation anchor. Inbox uses a compact conversation
list and explicit loading-disabled refresh. Profile keeps the stable save redirect,
read-only verified email, aligned optional phone input and customer logout.
Conversation separates message history/composer from booking context and pause/report
controls, preserving idempotency/drafts and read access while paused. Customer
management and Business-side conversation routes/permissions remain unchanged.
No message preview, online presence, new unread total or fabricated activity is added.

[Evidence and visual-verification limits](../audits/2026-11-08-marketplace-account-design/README.md).

Profile simplification (owner screenshot follow-up): removed the right-hand email/
sign-in information cards and duplicate logout button. The profile form uses the
account content width; logout remains in shared account/header navigation.

Marketplace header/footer wordmark is “Clipperdesk”. The header badge uses the
Business CD monogram geometry recoloured forest/lime, retaining the sparkle accent.

Marketplace account email and client appointment/service email share the same CD/
sparkle PNG and capital-C Clipperdesk wordmark via one email brand partial. Client
email uses forest/sage colours and Marketplace sender display name while preserving
salon identity and existing purpose-specific details. See
[communications](communications.md#marketplace-identity-for-client-emails--2026-11-08)
for versioned content and delivery boundaries. Business operator emails remain on
the Business stream. The PNG must deploy with the Blade/config changes.

## Local growth execution contract (MPG-01–10)

[Audit and dependency gates](../audits/2026-11-08-marketplace-local-growth/README.md)
define the additive taxonomy, geography, public projection, SEO registry and
attribution slice approved 2026-11-08. Earlier three-category behavior is baseline,
not the target. Global SEO publication remains off until stage E. Existing account,
booking, review consent, operational types and manual payment rules remain canonical.

### Optional customer photo (MP-05, 2026-11-09)

Customers may select, preview, explicitly save or remove their own profile photo.
JPEG/PNG/WebP only, at most 5MiB/12MP; normalize to a 256px JPEG avatar and remove
metadata. Self-authenticated media remains private/no-store/noindex. Navigation
and profile reuse the saved image with initials fallback. Failed uploads retain
the draft and current saved photo. No Business gallery or public identity is added.

Avatar refinement: picture selection is on the profile avatar itself, with a
camera cue and accessible Add/Change profile picture button. Save/Discard/Remove
stay explicit; the native file picker row is hidden. The currently open selected
photo is preserved while source changes are prepared.

### Compact signed-in customer pages — 2026-11-09

The avatar opens picture selection. Save/Cancel appear in its identity header only
for a selected draft. Saved photos offer a small corner × on hover/focus (always
available on touch), with a tooltip and the shared removal confirmation dialog. There is
no standalone photo settings section or permanent Remove photo text action. Picture constraints remain accessible and
server-validated; name/phone drafts survive a photo save/failure. Shared account
navigation omits decorative overlines and the repeated discovery promotion. My
bookings keeps functional saved businesses; Inbox drops duplicate advice cards.
Conversation retains visit details, pause/report and send/recovery controls. All
customer copy uses business/appointment terms across the 13 audiences. Browser
verification of this final compact revision remains pending because local browser
use became disabled in Work settings; mounted component tests are not visual proof.

Desktop account buttons use 36px height and less padding; profile fields use 40px
height. Mobile buttons/fields keep 44px targets and legible input text. The shared
phone country selector and conversation controls follow these account-scoped sizes.

### Shared Marketplace booking shell — 2026-11-09

MP-01/MPG-06: `/marketplace/salons/{slug}` continues to render the canonical
Booking/Welcome component. PublicBookingLayout now wraps its Marketplace branch
in the actual MarketplaceLayout, retaining the same forest/lime CD wordmark,
customer navigation/account menu, ivory canvas and footer throughout all four
booking steps. Business logo/gallery, location/service/staff eligibility, prices,
policies and booking requests are unchanged. Saved business accent variables are
scoped to the inner booking content and cannot recolour the shared header/footer.
The old booking ProductMark header and duplicate account links/status notices
are omitted from this surface. Direct owner links and private preview retain
their previous shell. No route, indexation, login requirement or payment change.

### Focused Business Messages and transactional email — 2026-11-09

MP-04/FR-13: the Business navigation label is **Messages**. The existing
`business.marketplace.index` route renders BusinessInbox using BusinessMessagesLayout
and the ordinary AppLayout header, tokens and white/slate surfaces. Search matches
customer name or booking reference literally; All/Unread and explicit Refresh are
available. The list includes a short escaped preview, unread count, booking reference
and appointment time zone. There is no presence, socket or immediate-delivery claim.
On desktop a selected conversation retains its inbox; phones show one pane with an
All conversations return. Booking details are collapsed above the history. The
customer-side account layout and pause/report controls remain separate.

Inbox paging is 25 rows. Latest messages and unread totals use eager/aggregate
queries rather than a query per row. Calendar scope, branch assignments and client
contact access apply before any name/preview is projected. The authenticated thread
still checks appointment view/update permissions; encrypted history, 50-message
cursors and stable send keys/drafts are unchanged. Business navigation now obtains
Ziggy's per-app route helper through injection, including during SSR.

Listing, publication readiness and booking-entry activity moved to
`business.marketplace.settings` (`/businesses/{business}/app/marketplace/settings`),
linked from Business setup → Marketplace listing. BusinessSetupAccess still requires
settings permission and full business scope. No listing or SEO gate is enabled by
opening Messages. Existing preview, mutations and outcome reports are preserved.

New saved messages receive one alert UUID and queued email-delivery ledger row in
the same transaction; dispatch happens after commit. Replays/rollbacks cannot create
another alert. Customer messages notify the lowest-ID active verified owner account;
business replies notify the verified customer whose email still matches the booking.
Delivery rechecks tenant, active business, recipient, pause and read state. Already
read/paused/unavailable conversations are skipped, not retrospectively emailed.
Email includes a generic notice and authenticated conversation link, no body, client
identity, CRM fields or bearer appointment token. Sign-in resumes only a validated
same-site conversation path for 30 minutes; ownership is checked after authentication.
Operational notification permission is separate from optional acquisition analytics.

`MARKETPLACE_MESSAGE_EMAIL_ENABLED` defaults true for this approved feature; false
stops new alert creation and queued delivery while preserving messages/history.
A worker consuming the configured `emails` queue and the scheduler support delivery;
local `QUEUE_CONNECTION=sync` performs after-commit work in the request. The recovery
command `marketplace:message-email-sweep` dispatches only queued new alerts. Accepted,
failed, skipped or unknown rows are not automatically replayed. Interrupted sends
become unknown after 15 minutes. Transport acceptance is not proof of inbox delivery.
No historical backfill, SMS, marketing mail or reply-by-email is introduced.

[Evidence, local migration and operating checks](../audits/2026-11-09-marketplace-messages-email/README.md).

### Customer email and private-page identity follow-up — 2026-11-09

MP-02/MPG-07: Sign-in and client appointment/service email embed the packaged PNG as
a related CID image, independent of development-host reachability. Marketplace mail
uses a consistent ivory surround with white content, forest/sage actions and a text
wordmark fallback. Business operator mail retains its theme. Secure Manage booking
and its existing action views, waitlist offers and client forms/completion now share
MarketplaceLayout through PublicBookingLayout self-service mode. Invalid/expired
private customer links retain real status codes and the same recovery shell.
The signed customer receipt uses Marketplace colours/embedded raster branding while
retaining the issued snapshot; Business/PDF rendering is unchanged. No account is
required to use an existing secure link. All private pages remain no-store/noindex.

### Automatic store discovery and MySQL recovery — 2026-11-11

MP-01/04 and revised MPG-03/05/07. Public discovery is enabled in normal operation.
Completing and publishing the existing booking setup refreshes the store projection
after commit, before returning when available. The existing durable queue/scheduled
reconciliation recovers a failed refresh. Existing stores are picked up by the same
scheduled sweep. Owners have one optional visibility control. The profile request
form and manual per-store publication approval are removed from the normal workflow.

Individual profile records update automatically from published qualified services,
business description and the existing public booking address. Source is recorded as
`automatic-profile-policy-v1`, with the evaluated content hash. Real public name, address and qualified services control Google eligibility.
The description remains optional. Photos/reviews have no minimum quota. Pauses, withdrawal,
unpublished/private/demo supply and tenant/qualification guards remain authoritative.
Curated area hubs continue to use reviewed geography and editorial content.

Only `MARKETPLACE_GROWTH_INDEXATION` remains as the normal Google rollout setting,
default false. Canonical-host checks reuse BRAND_WEBSITE_URL. Retired slug, enabled,
origin and pilot-ID environment variables have no effect. No per-store environment
edit, allowlist, geography approval or SEO approval is required for discovery or an
individual profile. This supersedes the earlier manual profile/pilot policy above.

The growth migration uses a short explicit attribution tenant FK name and resumes
existing tables by restoring missing indexes/constraints while preserving rows.
`php artisan migrate` can finish the reported partial MySQL run. The repeatable
local MySQL regression is `php scripts/verify-marketplace-mysql-migration.php`.
See [2026-11-11 evidence](../audits/2026-11-11-marketplace-automatic-publication/README.md).

### Discovery rating and cookie footer — 2026-11-11

MP-01/MPG-06/08: Cards show the real rating and review count using a namespaced
class that avoids the shared star-widget mask. Remove the redundant verified-visits
suffix. Cookie settings is a compact, right-aligned control in the existing footer
on the same canvas; direct booking retains its own footer control. Both reopen the
existing consent panel, with acceptance/rejection and privacy signals preserved.
[Local checks and browser limitation](../audits/2026-11-11-marketplace-card-footer/README.md).

## Launch-stage editorial experience — 2026-11-14

Scope: MP-01/04/05, MPG-05/07; [accepted launch decision](../decisions.md#honest-marketplace-launch-homepage--2026-11-14).
`DiscoveryController` checks global `PublicOfferQuery` eligibility independently of
search filters. `LaunchHome` renders only when it is empty. Otherwise the existing
list/map/search/book flow remains, with an area-updates form and useful empty-search
recovery. Demo, owner pause, qualification and projection validity gates are retained.

`marketplace-editorial.php` contains six editorial groups covering every Solutions
audience, category preparation tips and three guides. `/marketplace/explore/{slug}`
and `/marketplace/guides[/{slug}]` are real destinations; category pages offer a
filtered search link only for audiences with actual eligible supply. Imagery is
labelled editorial inspiration. The non-SSR directory fallback includes the launch
explanation and editorial links. Editorial routes remain noindex, outside hub/profile
sitemaps, pending any separately reviewed SEO expansion.

`LaunchUpdates` is a compact newsletter-style form with city, email and an
unselected consent checkbox. Explicit owner refinement removes the email-confirmation
gate: `subscribed_at`/`subscription_method=direct-opt-in` activates the area request,
while `confirmed_at` remains null for unverified email. Existing verified requests
are migrated; unconfirmed legacy records stay pending until explicit signup or a
still-valid old confirmation link. Existing private token pages remain noindex,
no-store and no-referrer, with POST-only confirm/cancel and idempotent outcomes.

Signup success immediately shows “You’re on the list”; repeated successful requests
produce the same outcome without a resend warning. Address encryption, unique normalized
email/area keys, enrollment cache lock, IP/recipient throttles and consent version/time
are retained. A separate informational welcome email has a daily recipient limit; its
failure leaves signup successful, with a safe operator reference and no automatic resend.
Storage failure preserves entries, shows AppNotice with a timed retry and support action,
and never claims a subscription exists. No account or booking consent is created.
Welcome and area messages use standard CD Marketplace mail branding with area-specific
footer copy, rather than incorrect sign-in/security instructions.

The launch sender selects active subscribed requests, including direct opt-ins,
and still requires a real eligible business plus operator location review. Retention
uses subscription state: pending legacy requests age out after seven days, and active
requests remain for up to a year (cancelled/delivered requests: 30 days).

Operational sending and retention: [launch updates runbook](../support/marketplace-launch-updates.md).
