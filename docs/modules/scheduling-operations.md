# Calendar and scheduling operations

Requirements: [FR-03–FR-08, FR-11, FR-15, FR-19](../product-requirements.md),
with ADR-015/016/036/037/043/044/045 in [decisions](../decisions.md).

## Existing authoritative boundaries

`CalendarQueryService` provides a bounded location-time appointment/block/queue
projection. `AvailabilityConfiguration` resolves location hours, special hours,
closures, staff working windows, temporary changes, recurring breaks and leave.
`BookingRuleEngine` validates qualification, hours, notice, staff/resource
capacity, active holds and cross-location staff occupancy. `AtomicBookingService`
commits under deterministic locks. Calendar edits use `AppointmentLifecycleService`:
expected versions, scoped idempotency keys and linked replacement appointments
preserve before/after history. Capacity conflicts cannot be overridden.

The controller selects only assigned locations, fails closed for calendar-own
members without a staff profile, redacts contact/notes, and projects capabilities
from membership permissions rather than professional titles. Checkout is a
separate authorized canonical Sale workflow; this calendar never records money.

## Calendar workspace redesign contract

The primary view is staff columns for a single local day. Day overview and Week
remain available; Agenda provides a deliberate small-screen alternative. URL
parameters own date/location/filters/view; device preferences are scoped to the
signed-in user and business, store only calendar selections, and never grant
access. No Month view is introduced without an approved use case.

The visual target follows ClipperDesk's compact operational shell: one toolbar,
light operational strip, sticky staff/time references, proportional appointment
cards, quiet hatched unavailable periods and a contextual native-dialog drawer.
On phones the agenda takes priority over a compressed desktop grid.

Availability is a read-only projection of the same configuration windows and
unfiltered occupying segments, schedule blocks and active holds, including
cross-location staff commitments. Filtering appointments must never manufacture
free time. Empty staff time is a scheduling gap, not a guarantee that a service
and its resources can be booked. Creation and reviewed drag changes always go
through authoritative commit revalidation. Processing segments do not imply
staff occupancy. Overnight windows and clipped cross-day visits remain visible.

Staff schedules expose kind/label/time, never private leave reasons. Payment
readiness is available only with checkout permission and comes from canonical
sale status/balance. Per-appointment management follows all/own permissions;
terminal visits offer rebooking/notes/authorized checkout rather than invalid
lifecycle or schedule changes. No-show and cancellation require explicit review.

Scope excludes recurring series generation, inferred client risk, predicted
lateness, automated conflict resolution and new payment collection. Local
screenshots and regression evidence do not certify production load, independent
WCAG compliance or the existing external release gates.

## Verified implementation details (2026-10-03)

`CalendarWorkspaceQuery` loads configuration relationships once per selected team
and groups occupying records by staff. Appointment reads stay within the selected
one/seven-day range and retain the 1,000-appointment completeness warning. Client
lookup is debounced, abortable and limited to 15 results; search within the calendar
uses the loaded day/week, without loading an unbounded history.

The view remembers non-sensitive view/filter selections per user/business and
keeps explicit URL selections authoritative. The visible idle page refreshes every
minute, pausing for inputs, disclosures, dialogs and mutations. Arrow/Home/End
navigation moves between available desktop slots, with limited tab stops. Phones
use an agenda; coarse-pointer devices use the booking drawer rather than tiny grid
slot targets. Native dialogs keep keyboard focus and restore it after closing.

Clock-change dates pause ambiguous wall-grid slot/drop interactions. Booking guards
check dated closures and staff exclusions as absolute intervals, including the
previous day's overnight carry. Inactive/former staff with selected-range visits
remain visible while their unavailable schedule cannot offer booking targets.
Linked replacements preserve canonical client identity without duplicating records.

Verification and precise limitations: [dated audit](../audits/2026-10-03-calendar/README.md).


## Walk-in operations board (2026-10-03)

Requirements: FR-03–FR-08, FR-11, FR-15 and FR-19; ADR-045. The queue is a
single authorized location's front-desk workspace. Waiting (including Notified
and Assigned), In service and a collapsed bounded recently finished list retain
the persisted state model. Position and NEXT always refer to actual queue order;
search/status/staff filters never reorder records. Preferred/assigned staff and
recorded manual changes are visible. Reorder requires walk-in management plus
schedule-override permission, an explicit reason and an unchanged complete queue.
There is no inferred priority, VIP rule, automatic assignment or temporary hold.

`WalkInWorkspaceQuery` forecasts waiting entries in order using qualified active
staff, effective service/staff variants, working windows, breaks/leave, closures,
bookings, blocks, active holds and cross-location reservations projected by
`CalendarWorkspaceQuery`. Each hypothetical placement subtracts its full service
span before placing later clients. Assigned staff take precedence over preferred
staff; a preference can delay one client while another uses a different opening.
This conservative staff forecast does not reserve capacity or simulate shared
resources or released processing time. Display ranges as advisory estimates;
unknown availability never produces an invented time. Arrival estimates use the
same ordered forecast and retain their evidence for history. Past-estimate cues
compare waiting clients against their saved arrival estimate without inferring
that reception promised that time. Overdue live services have an unknown finish
and block another start on that staff member until the visit is resolved.

Selecting staff performs the full authoritative booking check on demand, including
qualifications, resources and upcoming conflicts; starting revalidates at commit.
The configured booking interval still owns scheduled start times, shown in the
drawer. Expired checks require retry. Assignment itself reserves no time, so
reception can deliberately save a qualified staff preference for later.

Check-in searches at most eight active clients, debounced and abortable. Contact
search/display requires contact permission; visit-note creation/display requires
note permission. New clients need only name/mobile, with optional email. Saved
international mobiles are normalized for the visit without changing the profile;
invalid contacts produce an actionable field error. Selected canonical identity
survives conversion. Check-in and start have scoped replay keys; start combines
conversion, lifecycle and queue history in one transaction. Calendar start,
completion and cancellation/no-show synchronize the linked queue state in the
same lifecycle transaction. Reviewed Calendar replacements keep the queue linked
to the new visit and reflect its assigned state. Once a Calendar visit exists,
schedule/reassignment/cancellation use Calendar to preserve reserved capacity.
Authorized completion and canonical checkout links stay available in the board;
this screen never records payment or changes financial history.

A shared 15-second display clock updates elapsed waits without requests. The
visible idle page refreshes every 30 seconds, preserving content and scroll;
inputs, filter/recent disclosures, dialogs and mutations pause refresh. The
expanded staff panel does not pause refresh. After two minutes without a fresh
projection, estimates request refresh. One native drawer carries staff selection,
fit checks, optional reassignment notes and recent history. Phone cards retain
position, wait, service, staff and 44px Serve actions; team availability collapses
above the queue. Desktop uses sticky column labels and staff availability below
the shared topbar. No new per-entry timers, provider or migration is required.

Evidence and practical limits: [Walk-in review](../audits/2026-10-03-walk-in-queue/README.md).

## Workforce editing (ADR-048, 2026-10-03)

The [Team and availability specification](team-availability.md) owns the compact
workforce directory and staff editor. It reuses `CalendarWorkspaceQuery` rather
than computing another schedule. The projection can omit reservation queries for
window-only impact checks; location hours/exceptions are explicitly eager-loaded.
Schedule commands use exact normalized-content previews, revision/appointment
checks, active hold protection and booking-compatible staff locks. Existing visits
are never moved or cancelled by a schedule edit. Appointment assignment and Queue
commit still validate qualifications, effective variants, resources and full capacity.

## Services integration (ADR-049, 2026-10-04)

The [Services specification](services.md) owns catalogue editing and effective
variant history. Calendar projects effective bounds and qualified staff in the
selected branch zone, uses chosen-staff active/processing/cleanup duration and
shows an estimated service price/range. Add-on creation choices require a selected
eligible parent. Canonical commit still decides actual capacity and price; an
estimate or configured qualification never promises a free slot. Aggregate staff
variants can introduce missing processing/cleanup kinds while existing segment
identities/resource links remain intact. Queue keeps using the same resolver and
booking engine. Existing appointment snapshots are never rewritten by catalogue
edits.

## Client communication context (FR-13, ADR-052)

Appointment detail shows a batched, permission-scoped latest confirmation, reminder
and change/cancellation summary with a link to appointment-filtered notification
history. It does not expose message bodies or offer an uncontrolled resend.
[Communications](communications.md) owns delivery state, consent and retry rules.
