# Daily workspace dashboard

Requirements: [FR-18](../product-requirements.md#fr-18-dashboard-and-operational-reporting), FR-03, FR-05–FR-08, FR-11–FR-12, FR-15–FR-16; PRD sections 10 and 12.
Decision: ADR-043. Presentation: [design system](../design-system.md).

## Responsibilities and scope

The dashboard projects existing appointment, queue, opening-hours, staff availability
and commerce truth. It is not a second scheduler, attendance tracker, task manager,
forecasting system, client membership product or source of generated advice.
Professional titles do not grant access. Membership permissions and assigned
locations determine what appears, including exact custom permission sets.

- Calendar-all users see their selected accessible location; calendar-own users
  see appointments containing their linked StaffProfile and only their own hours.
  A missing profile fails closed, including all status counts.
- Finance access independently permits location-day sale aggregates; finance-only
  users receive no appointment schedule, peer hours, notes or contact details.
- Contacts require clients.contact.view; appointment notes require
  clients.notes.manage. Sensitive CRM notes are never loaded by this projection.
- Walk-in records require walk_ins.manage. Checkout links require checkout.manage;
  financial aggregates require revenue.view. Inventory stays out of the visible
  operations release under ADR-036; its internal projection checks workspace
  permission, location access and entitlement. Readiness requires settings.manage.
- Selecting an inaccessible or foreign location returns 404; dates use strict
  YYYY-MM-DD validation and the selected location's IANA time zone.

## Daily operation

Default Now & next distinguishes recorded in-service/arrived/checked-in visits,
future appointments within two hours, later work, unresolved past arrivals and
finished history. Terminal states cannot appear as current work or create alerts.
On another date, appointments are labelled scheduled rather than now/overdue.

Attention prioritizes past scheduled finishes, arrivals not recorded, missing
staff, pending confirmation and incomplete forms. These cues never automatically
mark a no-show or assert a client is physically late. A single appointment has
one attention entry containing all its reasons. Mobile initially shows two
entries after opening the compact attention summary; desktop shows four.
Tablet and phone summaries keep current work close to the top of the screen.

Confirm, arrival, check-in, service start and completion invoke the existing
AppointmentLifecycleCommand through its authorized endpoint with record version
and deterministic operation key. Backend transition rules, history, audit and
communication outbox remain authoritative. Failures retain context and expose
readable feedback. Cancellation, rescheduling and exceptions continue in the
calendar; checkout/payment retains its separate review flow.

A details drawer shows permitted appointment context and opens the exact calendar
record with date/location retained. Call/email links open the user's own apps.
No communication is sent by visiting the dashboard. Completed unpaid visits,
including older dates, link to the exact authorized checkout. Settled sales do
not receive a payment action. Checkout reads and writes enforce location access. The linked calendar also
redacts contact/notes without their permissions, preserves unreadable fields when
rescheduling, and gates note edits. Printed schedules enforce assigned locations.

## Availability and money

Hours use LocalHoursResolver, including exceptional hours/closures. Team windows
reuse StaffAvailabilityResolver, subtract breaks/leave and intersect location
hours. Staff scheduled means at least one working window on this date; it does
not assert physical attendance, immediate free capacity or a guaranteed slot.

Sale value retains the FR-18 expected-sale aggregate: open/completed sale totals
for the location-day, excluding appointments without a sale. Collected retains
paid plus applied deposits minus refunds. New paying clients uses first completed
sale evidence. Money links preserve the governed report/date/location. These are
reporting projections, not cash collected during the clock day or forecasts.
The internal low-stock projection uses per-location inventory levels and the
inventory entitlement; the dashboard does not expose a stock link into a report
that ADR-036 keeps outside the visible release.

## Loading and scale

CalendarQuery remains shared. Dashboard appointment details are capped at 100
with an explicit warning and active visits prioritized before finished history;
status totals are independently exact. This slice
shows all statuses, while the calendar retains its normal defaults. Queue details
are bounded to eight (three shown initially); checkout details to eight (three
shown). Team availability is eager loaded, with no query per person.

Data is live when requested. Visible, idle dashboards refresh only their data
props every 60 seconds; hidden tabs, open drawers and mutations pause refresh.
A refresh control and local updated time make freshness explicit. Local midnight
moves an active Today workspace to the new location-day. Failed background
loads retain the last information with an explicit refresh message; an uncertain
mutation asks the user to refresh before retrying. Saved device/user
presentation keys remain compatible; changing the date/location resets filters.
No charts, provider calls, persistent cache or financial mutations occur on load.

Verification and limitations are recorded in the [dated audit](../audits/2026-10-02-daily-workspace/README.md).
