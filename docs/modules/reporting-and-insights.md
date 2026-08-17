# Module: Reporting and insights

Status: Implemented and locally verified on SQLite and MySQL 8 on 2026-08-15.

Requirement: FR-18, with metrics from PRD Section 15.

## Purpose

Turn operational and financial records into trusted, drillable daily decisions
and measurable customer value.

## Responsibilities

- today's appointment, queue, staffing, expected/collected revenue,
  outstanding balance, new-client, and low-stock indicators;
- appointment, sales, service, staff, method, location, discount, refund, tip,
  commission, client, cancellation/no-show, utilisation, visit, product, stock,
  and cash-close reports;
- date, location, staff, service, and status filters;
- previous-period comparison where definitions are stable;
- CSV export and printable summaries;
- centralized metric definitions and versions; and
- product funnel, reliability, retention, usage, and support instrumentation.

## Core invariants

1. Every dashboard count drills into the filtered source records.
2. Every surface shows data freshness and governing location time zone.
3. Financial metrics reconcile to immutable sales, payments, refunds, and
   adjustments.
4. Deposits are not counted as sale revenue merely because cash was collected.
5. Permissions restrict financial, client, staff, and cross-location views.
6. Metric definitions are centralized, versioned, and effective-dated.
7. Reports classify new/returning clients and utilisation consistently.
8. Exports preserve tenant scope and use asynchronous jobs for large datasets.

## Required metric definitions

- gross and net sales;
- collected revenue versus expected revenue;
- taxes, discounts, refunds, deposits, tips, and outstanding balances;
- staff/service revenue attribution;
- available service time and booked service time;
- new versus returning client;
- cancellation and no-show denominator;
- payment reconciliation exception; and
- active-shop and activation funnel events.

### Money foundation definitions (version 1, 2026-08-15)

| Metric | Definition / source |
| --- | --- |
| Gross sales | Sum of `Sale.subtotal_minor` before discount, tax treatment as captured on the Sale. |
| Net sales | Sale total less recorded refund and void/correction effects; deposits are not revenue by themselves. |
| Collected revenue | Successful Sale payment transactions plus deposit value applied to Sales, less refunds; traceable to provider/cash evidence. |
| Outstanding balance | Sum of open Sale `balance_minor`, after deposits, tenders, refunds, and compensating corrections. |
| Deposit liability | Original deposit less applied, refunded, forfeited, and credited allocations. |
| Reconciliation exception | Open `payment_reconciliation_tasks` after the configured provider-settlement window. |
| Expected cash | Cash-close opening cash plus net cash payment/refund transactions in the Location local business day. |

### Central metric catalog version 1.0.0 (effective 2026-08-15 UTC)

The executable catalog is `App\Domain\Reporting\Services\MetricCatalog`.
Every report response/export carries `metric_version`, definition effective
date, source label, UTC freshness, governing Location IANA time zone, normalized
filters, rows with source IDs/drill links, reconciled totals, and previous
equivalent-period totals where the definition is stable. Cross-time-zone
multi-Location requests fail explicitly and must be run per Location.

| Metric | Version 1 definition | Authoritative source / drill |
| --- | --- | --- |
| Gross revenue | Completed Sale Line quantity × frozen unit price before line discount | `sales` -> `sale_lines` |
| Net revenue | Gross revenue less frozen discounts and succeeded refund/void effects | Sale -> Line / Payment Transaction |
| Collected revenue | Successful Sale tenders plus Deposit allocations applied to a Sale, less refund/void effects | Sale -> Payment Transaction / Deposit Allocation |
| Expected revenue | Open/completed Sale total in the governing local period | Sale |
| Taxes | Frozen `Sale.tax_minor` reproduced from its calculation snapshot | Sale -> Sale Line |
| Discounts | Frozen `SaleLine.discount_minor` | Sale Line |
| Refunds/voids | Succeeded immutable refund/void Payment Transactions | Payment Transaction -> original tender/Sale |
| Deposits | `apply` Deposit Allocations only; collection without application is not revenue | Deposit Allocation |
| Tips | Earned Tip Entries plus refund/void offsets and manager adjustments | Tip Entry -> Sale/Payment |
| Commission | Earned Commission Entries plus refund/void offsets and manager adjustments | Commission Entry -> Sale Line/rule/Payment |
| Outstanding | Sale balance after deposits, tenders, refunds, and corrections | Sale -> allocations/transactions |
| Utilisation | Occupying Appointment Segment minutes divided by effective working-rule minutes | Staff -> Appointment Segment / availability rule |
| Client classification | New only for the Client's first completed Sale in the Business; later completed Sales are returning | Client -> Sale |
| No-show rate | No-show Appointments divided by eligible-to-occur Appointments, excluding cancelled/rescheduled | Appointment |
| Reconciliation exception | Open payment reconciliation task after the provider settlement window | Reconciliation Task |

### Phase 1 report and dashboard catalog

The today dashboard exposes appointment status, walk-ins waiting, active Staff,
expected/collected/outstanding values, new Clients, and low stock only when the
Membership has the underlying calendar, finance, own/all Staff, Inventory, and
Location scope. Each card links to the matching filter and states freshness and
Location time zone.

Implemented reports are: appointments, sales, Service revenue, Staff revenue,
payment method, Location, discount, refund/void, tip, commission, new/returning
Client, cancellation/no-show, utilisation, popular Service, visit frequency,
product sales, stock/valuation inputs, daily cash close, and the combined
payroll export. Relevant date, Location, Staff, Service, and status filters are
shared by screen, printable summary, and CSV. Stock is scoped through Location
levels rather than the Product aggregate. Large/core report exports always use
the tenant-aware `exports` queue; the job re-resolves the active Business and
requesting Membership before executing the saved normalized filter/scope.

### Phase 1 instrumentation catalog

Version 1 defines and accepts allow-listed, idempotent events for acquisition
(`trial.qualified_started`), activation/time-to-value (`booking.published`,
`booking.first_slot_available`, `subscription.paid`), booking
(`booking.time_selected`, `booking.completed`), reliability
(`booking.conflict_detected`, `notification.critical_outcome`), revenue
protection (`appointment.no_show`, `payment.reconciliation_opened`,
`checkout.refunded`), operations (`staff.available_minutes`,
`staff.booked_minutes`), retention (`subscription.month_end_active`), usage
(`shop.weekly_work_completed`, `checkout.completed`,
`report.export_completed`), and support (`support.setup_contact`). Dimensions
are restricted to acquisition channel, plan, business type, Staff band,
geography, cohort, Location public ID, outcome, and source. Subject identities
are HMAC hashes; email/mobile-like values are rejected and no Client name,
contact, notes, free text, raw link, or provider payload is accepted.

These definitions are source-of-truth inputs for Prompt 11 reporting. They use
integer minor units, explicit Sale currency, Location time zone for day
boundaries, and immutable transaction/allocation evidence.

## Major acceptance evidence

- an owner drills from today's collected revenue to a report, sale, and payment;
- post-refund views reconcile without changing prior historical evidence;
- staff and accountant roles see only permitted fields and scopes;
- location/time-zone boundaries produce correct daily totals;
- dashboard and export totals match for the same filters; and
- instrumentation definitions match PRD Section 15 and are privacy-safe.

Verification: the focused suite passes 8 tests / 93 assertions on SQLite and
MySQL 8. The dedicated MySQL volume case projects 2,000 completed Sales in
27.37 ms using 8 SQL statements, returning `collected_minor=1,800,000`. This is
a local indexed-query baseline, not sustained production topology/load proof.
