# Public booking, self-service, and waitlist journey

Status: Booking flow refined and locally verified (2026-10-10); paid public launch gates remain open.

Requirements: FR-06, FR-07, FR-09, FR-10, FR-19, and FR-20.

## Public links and client journey

Requirements: FR-06/07/09/10/19/20; ADR-055. Primary links use
`BookingUrl` and `config/booking.php`: `https://book.clipperdesk.com/{slug}`.
The public `/book/{slug}` fallback renders the same component and controller,
without redirecting a current slug. Both carry the primary canonical URL and the
existing tenant-booking noindex policy. Historical aliases redirect to the
current primary link. Unpublished, paused, deleted and unknown businesses return
a generic unavailable page. [Deployment contract](deployment/public-booking.md).

1. **Services.** Show public business/location information and the online catalog
   with price/duration, useful categories/search and explicit add-ons. Hide a
   single-location selector. Restriction badges explain returning/new-client
   services without an upfront previous-visit question. Continue works on mobile.
2. **Staff & time.** Offer any available staff, optional preference or required
   preference according to current business rules. Show location-local today,
   bounded dates, a nearby-date rail and morning/afternoon/evening time groups.
   Availability uses an accessible skeleton; requests are debounced/aborted and
   stale responses ignored. A selected
   time reserves capacity for ten minutes; replacing it retires the previous
   hold in the same transaction, with rollback restoring the old hold on failure.
3. **Details & inline review.** Required fields are name, mobile and email.
   The phone control shows a compact flag/dialing code with a wider searchable
   full-country menu; international pasted numbers infer their country.
   Date of birth, referral, request and marketing preferences are disclosed only
   on demand. Review privately resolves an unambiguous active same-business
   normalized name/contact match, rejects contradictory contacts and checks
   service/new-client restrictions and actual client-specific deposit rules.
   No saved profile or history is returned, and no customer contacts are stored
   in browser session storage or flow state. Editing essentials requires review
   again. Terms, privacy, frozen cancellation policy and explicit optional SMS
   consent remain distinct; marketing never gates booking.
4. **Confirmation.** The customer accepts the displayed policy and submits once.
   Confirmation locks the business/flow, binds the reviewed essentials, checks
   current eligibility/deposit, flow/hold expiry, policy/configuration and atomic
   capacity. Concurrent requests converge on one appointment. An ambiguous
   response can retry the same frozen request/key. Success shows reference,
   appointment time, location/address, service and staff with calendar/private
   management links in a centered card; it does not claim that a provider message
   was delivered. Flow restoration and GET page navigation use appointment
   skeletons with status text and reduced-motion support.

The browser stores only flow credentials, expiry and public selections in
session storage. Resume validates the digest, business and expiry; it restores a
hold or an existing confirmation without resubmitting a booking. Customers
re-enter contacts after refresh. Availability responses contain public times,
price and currency only, excluding engine plans, database IDs, resources and
private configuration. Search remains advisory; confirmation revalidates.

Required deposits still stop confirmation until verified paid evidence exists.
OPEN-16 retains the missing payment element/return/status journey; the visible
contact path is accurate, but it is not a completed paid online-booking flow.

## Secure self-service

The view link displays Appointment time in its governing Location time zone,
Location, lines, price/deposit state, policy snapshot, and status. It issues
short-lived independent links for:

- reschedule by choosing actual available location-local times, excluding the
  original appointment from advisory capacity, grouped by time of day and loaded
  with a skeleton, then committing a linked, revalidated replacement;
- cancellation subject to the frozen cutoff and waitlist matching;
- rebook through the current catalog and policy;
- contact update with versioned change evidence and no sensitive audit values;
- Appointment-bound waitlist join and versioned leave;
- payment-status display without accepting card data.

The raw token is never stored. Links are purpose-bound and UTC-expiring. Action
links are consumed once; material Appointment/contact changes revoke older
links, then redirect to a fresh view link. A booking reference cannot retrieve
an Appointment.

View/action headings are centered; form labels and editable information retain
their reading alignment. Reschedule/cancel enforce the accepted policy snapshot's
cutoff rather than retroactively applying a changed business default; legacy
appointments without a snapshot use the configured fallback.

## Waitlist lifecycle

An active request records Location, Service, optional preferred Staff, client
contact, acceptable date/time range, notification method, notes, expiry, and
version. An active fingerprint converges exact duplicates. A cancellation or
other opening is checked at its exact instant with the authoritative availability
engine, without truncating the day to its earliest available times. Matching
requests are offered in configured batches and retain `offered`, `claimed`,
`lost`, `revoked`, or expired evidence.

Claim locks all siblings in one batch before booking. The first valid claim
commits a normal `waitlist`-source Appointment through `BookingCommitCommand`;
every sibling becomes `lost`. An expired, revoked, already-claimed, or newly
unavailable offer fails without a partial Appointment.

## Safe failures and recovery

| Condition | Client result | Capacity/history result |
| --- | --- | --- |
| Flow or Hold expired | Return to current times while retaining in-memory details | Expired Hold consumes no capacity |
| Public policy changed | Review the current policy again | No Appointment is created |
| Slot/configuration changed | Choose another current slot | Commit transaction rolls back |
| Required deposit, provider not connected | Contact the Business; no payment fields are shown | Hold is not confirmed; Prompt 10 owns provider coordination |
| Invalid/unknown/wrong-purpose token | Generic not-found response | No tenant or Appointment detail leaks |
| Expired/used/revoked action link | Request a fresh link from the Business | Existing Appointment is unchanged |
| Duplicate waitlist request | Existing active request is returned | No duplicate notification demand |
| Waitlist race | Loser sees the opening is no longer available | Exactly one normal Appointment exists |

## Conversion and privacy evidence

`PublicBookingEvent` records started, availability/time-selection, Hold, and
completed milestones using a session digest and low-cardinality counts/source
metadata. Names, email, mobile, raw tokens, free text, and exact private
schedule reasons are excluded. Public creation and mutation endpoints are
rate-limited. Provider-backed communication now extends the same privacy and
idempotency rules through one
Business/event/channel/recipient message key, encrypted destinations, signed
short-lived action links, and content-minimized support history. Provider-backed
payment events in Prompt 10 must reuse the deposit/receipt intents.

## Receipt notification actions (2026-10-04, FR-13/14)

Signed receipt links display the existing issued sale receipt, with its immutable
recorded financial values, until link expiry. Legacy appointment-target links
resolve the appointment's issued receipt. Viewing creates no payment/receipt and
uses private no-store/no-referrer/noindex headers. Future notification action-link
expiry is based on intended delivery time; existing revocation/tenant checks and
single-use mutation actions remain. See [Communications](modules/communications.md).
