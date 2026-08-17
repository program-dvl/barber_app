# Public booking, self-service, and waitlist journey

Status: Implemented and verified through communications (2026-08-14)

Requirements: FR-06, FR-07, FR-09, FR-10, FR-19, and FR-20.

## Client journey

1. A published, active Business resolves by booking slug. The page returns only
   online Locations, Services/add-ons, Staff display profiles, and current
   public policy; private contact and schedule reasons remain server-side.
2. Starting creates an expiring `PublicBookingFlow`. The browser keeps the raw
   opaque secret in session storage for refresh/back navigation; the database
   stores only its digest.
3. The client chooses Location, explicit Service/add-on lines, new/returning
   eligibility, any or preferred Staff (subject to shop control), and a local
   date. Search delegates to `AvailabilityQuery` and remains advisory.
4. Selecting a time creates a ten-minute `CapacityHold` through the shared
   scheduling engine. The flow freezes the exact effective Service values and
   public policy version shown for confirmation.
5. Required details are name, mobile, and email. Date of birth, referral,
   communication preference, explicit WhatsApp opt-in, special request, and
   marketing opt-in are separate optional fields. Marketing consent never gates
   transactional booking.
6. Review shows explicit lines, effective price/from treatment, duration,
   deposit amount/status, cancellation policy, terms, and privacy links. The
   client must accept the displayed booking policy.
7. Confirmation rechecks flow expiry, policy version, Hold status, current
   configuration, and atomic capacity. Success returns a `GH-` reference,
   calendar download, and secure view link. Exact command replay returns the
   same Appointment.

## Secure self-service

The view link displays Appointment time in its governing Location time zone,
Location, lines, price/deposit state, policy snapshot, and status. It issues
short-lived independent links for:

- reschedule through a linked, revalidated replacement;
- cancellation subject to the frozen cutoff and waitlist matching;
- rebook through the current catalog and policy;
- contact update with versioned change evidence and no sensitive audit values;
- Appointment-bound waitlist join and versioned leave;
- payment-status display without accepting card data.

The raw token is never stored. Links are purpose-bound and UTC-expiring. Action
links are consumed once; material Appointment/contact changes revoke older
links, then redirect to a fresh view link. A booking reference cannot retrieve
an Appointment.

## Waitlist lifecycle

An active request records Location, Service, optional preferred Staff, client
contact, acceptable date/time range, notification method, notes, expiry, and
version. An active fingerprint converges exact duplicates. A cancellation or
other opening is checked with the authoritative availability engine. Matching
requests are offered in configured batches and retain `offered`, `claimed`,
`lost`, `revoked`, or expired evidence.

Claim locks all siblings in one batch before booking. The first valid claim
commits a normal `waitlist`-source Appointment through `BookingCommitCommand`;
every sibling becomes `lost`. An expired, revoked, already-claimed, or newly
unavailable offer fails without a partial Appointment.

## Safe failures and recovery

| Condition | Client result | Capacity/history result |
| --- | --- | --- |
| Flow or Hold expired | Start again and see current options | Expired Hold consumes no capacity |
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
