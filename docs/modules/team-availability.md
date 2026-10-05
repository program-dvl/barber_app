# Team and availability

Requirements: [FR-03, FR-04, FR-05, FR-06, FR-08, FR-17–FR-19](../product-requirements.md).
Dependencies: ADR-008/014/015/032/038/044/045 and the implemented
[scheduling operations](scheduling-operations.md) contract. This specification
restores the workforce boundary previously tracked in OPEN-14.

## Workforce workspace

One Team & availability navigation entry contains Team, Weekly coverage, Time off and manager-only Access. A linked staff drawer is the operational profile: Overview, Schedule,
Services and permissioned Access. Employment/bookability is separate from
Membership/login status. User accounts without a bookable profile remain visible
to managers in Access. Professional titles never grant permissions.

Managers edit only staff whose assigned branches are within their scope; owners
may manage all. Reception with calendar-all permission reads assigned-branch
coverage; calendar-own staff read only their linked profile. Read-only consumers
receive neither private leave reasons nor login/permission configuration.

## Shared scheduling truth

Weekly coverage and daily gaps use CalendarWorkspaceQuery and the existing
AvailabilityConfiguration. Appointment filtering cannot create capacity. Closed
locations, leave, recurring/dated breaks, personal blocks, holds, cross-branch
work and occupying appointment segments are respected. Processing releases staff
only when the canonical service segment does. Overdue in-service appointments
have no known finish and must not produce Available now.

Scheduled hours, booking windows and free staff time are distinct. Utilization
is the active reserved union (appointments, holds and schedule blocks) within effective booking windows, excluding breaks/leave;
completed visits remain in the appointment count but no longer reserve capacity;
it is a scheduling measure, not attendance, payroll or performance evaluation.
Service/resource fit remains authoritative at Calendar/Queue commit. A booking
gap does not promise that a walk-in can be served.

## Editing and consequences

Regular weekly hours allow different days and split shifts, compact copying,
assigned-branch hours and recurring breaks. Dated exceptions replace that day's
regular windows; full/partial multi-day time off and one-time breaks use existing
rule kinds. No HR approval, payroll or external calendar sync is invented.
Existing overnight records are displayed; newly edited rules require end after
start until an explicit overnight-edit contract is approved.

Server validation checks complete dates/times, branch assignment, interval
overlaps and cross-zone cross-branch collisions. Every schedule edit uses an
exact-payload, expiring preview and a revision of the stored schedule. Writes
lock staff against atomic booking. Changed proposals, changed appointment versions or new future bookings
invalidate review. The review lists every future occupying appointment outside
the proposed windows, including existing exceptions. A normalized content hash
keeps exact review binding independent of MySQL JSON object-key order. Conflicting visits must be resolved in Calendar, or explicitly
retained with a reason; the editor never silently moves/cancels appointments.
Active capacity holds must be resolved or expire before reducing their windows.
Important before/after changes are audited within the transaction. Expired dated
exceptions remain intact and are excluded from current editing; new exceptions
need a current/future end date. The directory projects two weeks of reservations,
shows seven days of coverage and four upcoming shift days. Metadata required for
revision checks may include historical rules; appointment history remains bounded.

Active holds protect deactivation, branch removal and qualification removal.
Future-effective service variants are preserved and block current-only editing
until their dated configurations are resolved. Work branches and login-access
branches are independently managed; changing one never synchronizes the other.

Services are searchable by category and support the existing staff duration,
price and online visibility variants. Current assignments are versioned for
future resolution; historical appointment/sale snapshots and commission ledger
remain untouched. Compensation links use existing statement authorization and show balances
separately by recorded currency. Existing commission/tip entries remain append-only.
Login roles use Business-scoped starter/custom roles and exact permission sets;
changing a preset/custom toggle must not drop ungrouped existing capabilities.

## Verification

Implementation and browser evidence are recorded only after checks in the dated
Team audit and project-status. Local checks do not waive external release gates.
