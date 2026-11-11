# Reports and analytical workspace

Scope: FR-18, FR-14–17 and FR-19. Existing immutable sale/payment/commission
ledgers and Calendar capacity configuration remain the source of truth.

The workspace provides a historical overview and grouped sales, bookings, clients,
services, team and finance reports. Memberships/packages/gift cards and recurring
billing are Phase 2; inventory stays hidden under ADR-036. There are no forecasts,
external benchmarks, health scores, speculative causes or new payment commands.

Report scope is tenant/assigned-location first, then own-calendar/own-compensation
where independently required. Financial visibility requires revenue.view; tips
and commission require their existing compensation permissions. Contacts are not
selected. A report crossing different location time zones must choose one branch.
All dates are strict local calendar dates; UTC half-open bounds and calendar-day
comparisons handle daylight saving. Monetary reports select one recorded currency.

SQL projections aggregate complete filtered data, independently of 30-row detail
pagination. Search and sort use explicit columns. Exports reuse scope/projections
and stream chunks; print is a bounded summary with full filtered totals. Current
configuration cannot change historical sale-line prices/descriptions. Capacity
uses the shared Calendar working windows, breaks, leave and branch closures;
appointment/block inputs are read in batches, occupied segments are unioned/clipped,
and historical configuration limitations
are disclosed. It does not infer past attendance or rebuild deleted schedules. Prior-period
capacity comparison is unavailable without historical schedule snapshots.

Sale-date reports retain the existing completion-cohort receipts formula:
paid + applied deposits − all recorded refunds. The transaction-date report uses
successful payment/refund/void occurrence dates and excludes deposit collection.
These two bases must never be conflated. Gross/discounted line prices retain their
original inclusive/exclusive tax basis. Returns can include tax/tips; the existing
sales-less-returns figure is not labelled tax-exclusive revenue. Stored tax/tips
and allocated/unallocated returns remain explicit; no new allocation is invented.

New/returning uses first completed sale before the selected period, within the
permitted branches across recorded currencies, and counts distinct paying clients.
Selected-currency visits and money remain scoped separately. It does not claim visit
retention, cohort conversion or completed appointments without a sale.

Verification and remaining limits belong in the dated review, and only verified
facts are recorded in project-status.md.

## Discovery & booking outcomes (MPG-08–10)

An owner with revenue permission can report a selected authorized branch, strict
local date range (up to 367 days) and recorded currency. Cohort is first confirmed
public booking flow in that interval; each logical appointment is counted once
after replacement. Latest visit status/value and all linked recorded collections,
refunds/voids and allocation movements are separate. These are booking-cohort
collections, not transaction-date revenue. Unknown acquisition stays unknown.
New-to-business clients are distinct clients completing their first recorded
appointment in that business, with timestamp and ID tie-breaks. No contact details
or cross-business identities are selected. Required-deposit protections and manual
collection commands remain unchanged.

Acquisition observation needs separate explicit optional consent, browser/header
privacy permission and a retained session; 30-day maximum window, 60-day optional
evidence retention. Necessary booking entry is independent. Search Console import
is authorized page/day aggregate JSON only; no query/customer fields or live
connection is implied. Counts below three are withheld. Profile aggregates cover
the whole business profile, not an individual branch.
