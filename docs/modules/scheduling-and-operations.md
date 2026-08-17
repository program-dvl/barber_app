# Module: Scheduling and operations

Status: Availability, capacity hold, booking commit, operational calendar,
appointment lifecycle, schedule blocks, walk-ins, public booking, secure
self-service, waitlist, and operational exceptions implemented and verified
(2026-08-12).

Requirements: FR-06 through FR-10. The implemented slice covers FR-06 through
FR-10, excluding provider-backed deposit collection which remains Prompt 10.

## Purpose

Provide one reliable availability model for online booking, reception, phone,
calendar changes, multi-service visits, walk-ins, and waitlist claims. Prompt 05
implements the shared rule engine and atomic write boundary. Prompt 06 adds the
operational commands and projections without permitting HTTP or Vue code to
write Appointment timestamps. Prompt 07 adds public adapters and waitlist
coordination through those same contracts.

## Implemented invariants

1. `BookingRuleEngine` is the only availability policy implementation used by
   advisory search, capacity holds, direct booking, and hold confirmation.
2. Search results are advisory. Every hold and commit resolves current
   configuration, schedules, notice/advance rules, segment occupancy, existing
   claims, and unexpired holds again inside the use case.
3. A transaction creates every Appointment, Service Line, Segment, resource
   claim, initial status-history row, and immutable commercial snapshot, or
   creates none.
4. Staff and pooled physical-resource rows are locked in deterministic ID order
   before conflict reads and writes. Half-open intervals `[start, end)` allow
   adjacent work but reject real overlap at any Location.
5. Processing segments occupy staff only when configured. Resource requirements
   may occupy a named segment or the complete Service Line.
6. Holds have a Business-scoped stable idempotency key, request digest, stable
   owner key, optional actor attribution, Location/source, exact
   segment/resource claims, UTC expiry, and explicit
   active/confirmed/expired state. Expired holds never consume capacity.
7. Booking command keys are claimed through a unique row before capacity work.
   Exact replay returns the original Hold or Appointment; key reuse with a
   different digest is rejected.
8. Preferred staff is attempted first. `allowAnyQualified=true` permits a safe
   fallback; false never silently changes provider. A segment may name a
   different qualified provider for a handoff.
9. Location, staff, and resource wall-clock windows are interpreted in the
   Location IANA time zone. UTC claims preserve offset-bearing local intent and
   support DST changes and schedules that cross local midnight.
10. Domain errors expose a stable rule code and recovery-safe message. Break,
    leave, personal-block, maintenance, Appointment, and internal schedule
    details are not returned.
11. Lifecycle changes use an expected Appointment version and a Business-scoped
    operation key. Replays return the existing result and stale edits fail
    without overwriting a newer action.
12. Reschedule, resize, reassign, and service changes create a revalidated,
    linked replacement Appointment. The prior Appointment becomes
    `rescheduled`; its historical commercial and schedule evidence is retained.
13. Manager override is limited to notice and advance-window policy warnings,
    requires explicit acknowledgement and a reason, and creates audit/change
    evidence. Staff, resource, block, closure, hours, qualification, and overlap
    integrity failures remain non-overridable.
14. Walk-in assignment is provisional. Starting service commits a normal
    Appointment through the shared engine; any collision with future work fails
    visibly and leaves the queue entry unconverted.
15. Public selection, Holds, confirmation, self-service replacements, and
    waitlist claims call the same availability/commit/lifecycle contracts. No
    public route owns a second capacity rule.
16. Public flows and Appointment action links store only SHA-256 token digests,
    expire in UTC, are purpose-bound, and return enumeration-resistant errors.
    Contact, cancellation, and replacement changes revoke older links.
17. Waitlist duplicates use an active-request fingerprint. Opening offers use a
    configured batch size; all matches in one batch are locked before claim so
    exactly one client can create the replacement Appointment.

## Command and query contracts

- `AvailabilityQuery::search(AvailabilitySearch): list<slot>` accepts a bounded
  local-date range, Location, source, client type, service lines, preferred/any
  staff choice, and optional per-segment provider choices. Results include UTC
  and offset-bearing local instants, line/segment allocation, price, currency,
  and resource claims.
- `CapacityHoldCommand::hold(BookingRequest, idempotencyKey, ttlSeconds):
  CapacityHold` accepts a 30-1,800 second TTL and returns the original Hold on
  exact replay.
- `BookingCommitCommand::commit(BookingRequest, idempotencyKey): Appointment`
  performs a direct all-or-none booking.
- `BookingCommitCommand::confirmHold(businessId, holdPublicId, idempotencyKey,
  asOfUtc): Appointment` locks the Hold, rejects expiry, re-resolves every rule,
  rejects changed plan/snapshot as `STALE_AVAILABILITY`, and confirms once.
- `CapacityHoldExpiryCommand::expire(asOfUtc, businessId?): int` marks eligible
  Holds expired idempotently. Capacity queries also compare `expires_at`, so
  delayed cleanup cannot retain capacity.
- `AppointmentImpactSource` is now backed by the Appointment store and returns
  future active Appointment public IDs for Location, Staff, Service, and
  Physical Resource changes, with proposed date-range filtering.
- `CalendarQuery::calendar(CalendarFilter): array` returns bounded day, week,
  today, or staff projections with Location/Staff/Service/status filters,
  accessible textual status cues, blocks, and active walk-ins.
- `AppointmentLifecycleCommand` owns controlled transitions, internal-note
  changes, and linked replacement operations. Every mutation checks version,
  claims an operation key, appends history, and emits an internal notification
  event where downstream communication is required.
- `ScheduleBlockService` creates staff breaks and personal blocks after the
  same capacity conflict checks. There is no block overlap override.
- `WalkInQueueService` owns evidence-based estimates, reorder history,
  assignment, notification intent, conversion/start, abandonment, and actual
  wait evidence.
- `OperationalExceptionService` records late arrival, service overrun, staff
  unavailability, and unexpected closure impacts with explicit recovery lists.
- `PublicBookingService` owns passwordless flow state, catalog filtering,
  availability search, Hold selection, policy snapshots, confirmation, and
  privacy-safe conversion events.
- `SecureAppointmentLinkService` issues view/reschedule/cancel/rebook/contact/
  waitlist/payment-status tokens; `AppointmentSelfService` applies supported
  mutations and rotates the client back to a fresh view link.
- `WaitlistService` owns deduplication, preference matching, controlled-batch
  offers, offer history, versioned leave, and atomic first-valid claim.

## Public booking and waitlist

The mobile-first `/book/{booking_slug}` journey covers Location, explicit
Service/add-on lines, new/returning eligibility, any/preferred Staff, local
date/time, contact and optional fields, price/duration/deposit/cancellation/
terms/privacy review, separate marketing consent, confirmation reference, and
calendar download. A refresh-safe opaque flow secret is stored only in the
browser session and its digest is persisted. Holds last ten minutes and every
confirmation revalidates capacity and the frozen policy version.

Required deposits fail closed with `PAYMENT_NOT_CONNECTED` until Prompt 10
provides a verified provider flow; no card data is collected by this module.
Secure Appointment links provide view, reschedule, cancellation, rebook,
contact update, waitlist join/leave, and payment-status surfaces without an
account or reference-number lookup. See
[`../public-booking-journey.md`](../public-booking-journey.md) for the state and
failure contract.

Waitlist requests carry exact Location, Service, optional preferred Staff,
date/time range, contact method, expiry, status, and version. Identical active
requests converge on one row. A cancellation is matched through authoritative
availability, offers are retained as history, and simultaneous claimants lock
the entire batch in deterministic order. The winner commits through
`BookingCommitCommand`; all sibling offers become `lost`.

## Operational calendar and lifecycle

The authenticated calendar supports today, day, week, and staff-column views,
plus Location, Staff, Service, and status filtering. Appointment, blocked-time,
and walk-in states use labels/icons/border cues in addition to color. Supported
commands are create, multi-service add/remove, move, resize, reassign,
duplicate, rebook, internal note, controlled status transition, cancel/no-show,
block time, exception recording, closure recovery, and printable daily schedule.
Drag/drop opens the same reasoned confirmation workflow as the explicit Move
action; it never writes a timestamp directly.

The lifecycle is `confirmed -> late/arrived/cancelled/no_show`,
`late -> arrived/cancelled/no_show`, `arrived -> checked_in/cancelled`,
`checked_in -> in_service/cancelled`, and `in_service -> completed`.
`completed`, cancelled, no-show, and `rescheduled` records are terminal.
Late, cancellation, no-show, replacement, exception, closure, queue reorder,
and abandonment actions require a reason where operational meaning would
otherwise be lost. Actor, source, time, reason, before/after summary, and
notification intent are append-only evidence.

All `datetime-local` values are interpreted in the selected Location IANA time
zone and converted to UTC before reaching a domain command. Calendar output is
converted back to that Location time zone. This is tested independently of the
application/server UTC setting.

## Walk-in operations

Reception/manager/owner users can add a client, requested Service, preferred
Staff, arrival time, mobile, and private note. The estimate records queue demand,
service duration, future Appointment and Staff-capacity checks, estimated wait,
and expected service instant. A manager may reorder with confirmation and a
reason. Staff assignment, turn notification intent, service start, and “left”
are version-checked and recorded in `walk_in_histories`; conversion links the
queue entry to its normal Appointment and actual wait is retained.

`BookingRequest` uses internal IDs at the domain boundary. HTTP/public/calendar
adapters must tenant-resolve public identifiers first and call a command. The
architecture test rejects Appointment model/table imports from controllers,
Filament, and Vue source.

## Locking and idempotency strategy

MySQL 8/InnoDB is authoritative. Each write runs in one Laravel transaction with
up to five deadlock retries, following the current Laravel 13 transaction and
`lockForUpdate` APIs. Lock order is:

1. unique Business/scope/idempotency command-key row;
2. Location row with a shared configuration lock;
3. requested Service rows by ID with shared configuration locks;
4. every explicit or eligible candidate Staff row by ID; and
5. every required Physical Resource row by ID.

Candidate-assignment and resource-requirement discovery are also shared locking
reads, so InnoDB does not establish a stale repeatable-read snapshot before a
worker waits on an exclusive root. Staff and Resource rows use exclusive `FOR
UPDATE` locks. After locks are held, the engine uses indexed overlap reads for active
Appointments and active, unexpired Holds. Resource occupancy is the sum of
overlapping quantities. Hold confirmation excludes its own claims while
revalidating, then writes the Appointment and marks the Hold confirmed in the
same transaction. No manager capacity override exists in this core slice;
integrity conflicts are non-overridable.

Required lookup indexes are:

- `appointment_staff_conflict_lookup` and
  `capacity_hold_staff_conflict_lookup` for staff/time overlap;
- `appointment_resource_conflict_lookup` and
  `capacity_hold_resource_conflict_lookup` for resource/time/quantity overlap;
- `appointment_calendar_lookup` and `appointment_future_lookup` for Location
  calendar and impact queries;
- `capacity_hold_expiry_lookup` and `capacity_hold_window_lookup` for release
  and active-window queries; and
- `booking_command_key_unique`, `appointment_idempotency_unique`, and
  `capacity_hold_idempotency_unique` for replay safety.

All composite foreign keys and indexes have explicit MySQL-safe names. The
MySQL verification also corrected two previously hidden long-name failures in
the platform-access and business-configuration fresh-migration path.

## Verification evidence

SQLite domain/integration evidence:

- `tests/Feature/SchedulingOperations/BookingEngineTest.php`: 12 passed, 69
  assertions. It covers shared search/commit rules, direct and Hold replay,
  expiry, stale Hold snapshot, preferred/any staff, notice and advance windows,
  pooled resource quantity, staff-free/resource-busy behavior, processing
  release/retention, segment handoff, all-or-none failure, closure, break,
  maintenance, stale search, privacy-safe errors, travel overlap, explicit
  add-on eligibility/lines, staff-specific segmented duration, spring and
  repeated-hour fall DST changes, and local midnight.
- `tests/Architecture/SchedulingWriteBoundaryTest.php`: 1 passed, 1,368
  assertions over current controllers, Filament, and Vue files.

Production-engine evidence used MySQL 8.0.42/InnoDB in the dedicated
`barber_app_booking_test` database. `pcntl_fork` workers use independent PDO
connections and wait behind a pipe barrier before committing:

- 11 MySQL tests and 45 assertions passed across online-online,
  online-reception, staff-free/resource-busy, quantity two with three
  contenders, shared multi-segment handoff, expiry/commit, exact duplicate,
  stale-search, MySQL JSON snapshot normalization, concurrent stale lifecycle
  editing, two simultaneous waitlist claims, calendar projection, and
  performance cases.
- A representative one-day any-qualified search returning 20 slots measured
  231.09 ms and 786 SQL statements. A direct staff/resource commit measured
  17.75 ms and 57 SQL statements on the local MySQL instance. A 32-event day
  calendar measured 9.00 ms using 9 statements. These pass the
  provisional 500 ms search and 2 second commit targets but are not production
  load evidence.

Operational SQLite evidence is in
`tests/Feature/SchedulingOperations/CalendarWalkInOperationsTest.php`: 7 tests
and 93 assertions cover lifecycle/replay/staleness, replacement and override,
blocks and calendar cues, walk-in collision/reorder/notification/start,
exceptions/closure, HTTP permissions/cross-tenant IDs/local-time conversion,
and the idempotent production-like front-desk day simulation.

Browser verification used the seeded 8-appointment/2-walk-in/1-block day at
1440x950, 768x900, and 360x800. Calendar and queue had no page-level horizontal
overflow, no visible target under 44px, readable non-color status cues, correct
dialog initial/return focus and Escape close, representative contrast above
4.5:1, and no console errors or warnings.

## Known capacity limitations and remaining scope

- Search is intentionally bounded to 31 local days and 200 returned slots. It
  currently performs repeated authoritative rule reads (746 statements for the
  measured 20-slot case); caching/batched read projections are required before
  high-volume public launch, without weakening commit revalidation.
- Deterministic locking includes every candidate Staff member for an
  any-qualified service. Popular broad services can therefore serialize more
  commits than the final chosen allocation alone would require.
- Service Lines are composed contiguously in requested order. Segment-level
  provider handoff and staff release during processing are supported, but the
  search does not yet optimize overlapping independent Service Lines during a
  processing gap.
- MySQL tests prove process-level contention on one local database, not target
  production topology, network latency, replicas, sustained peak load, or
  failover.
- Prompt 07 and Prompt 09 communications are complete. Prompt 10 still owns
  provider-backed deposit collection and payment reconciliation and must emit
  the existing deposit/receipt intents.
