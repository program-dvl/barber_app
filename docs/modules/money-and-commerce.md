# Module: Money and commerce

Status: FR-14 through FR-17 implemented in code and locally verified on SQLite and MySQL 8 on 2026-08-15; live-provider certification remains a launch gate.

Requirements: FR-14 through FR-17.

## Purpose

Protect appointment revenue, close services and retail sales in one system,
maintain stock, and produce explainable commission and tip records.

## Responsibilities

- deposit and cancellation policies, fees, waivers, transfers, forfeitures,
  credits, and refunds;
- online payment intents, verified webhooks, recovery, and reconciliation;
- checkout basket, versioned sale items, taxes, discounts, overrides, tips,
  deposits applied, and balance;
- cash, card, local method, bank transfer, payment link, custom method, split
  tender, partial payment, and pay-later records;
- receipts, voids, corrections, full/partial refunds, and cash close;
- retail catalogue, stock receipt, sale deduction, refund disposition, manual
  adjustment, low stock, and movement history; and
- versioned commission rules, entries, reversals, tips, statements, adjustments,
  and export.

## Core invariants

1. Money uses integer minor units and explicit currency.
2. Deposit policy and amount are displayed before payment and bound to the
   intended tenant, client, and appointment.
3. A successful payment, refund, or provider event affects state exactly once.
4. Payment success plus booking failure creates automatic recovery or an
   explicit reconciliation task.
5. Deposit applied, transferred, refunded, forfeited, credited, and remaining
   values reconcile to the original payment.
6. A sale is never silently rewritten after completion or cash close.
7. Discounts and price overrides require role thresholds and audit evidence.
8. Stock moves once when the relevant sale state commits and is corrected with
   a traceable movement.
9. Commission rules are effective-dated; refunds create reversals or offsets.
10. Financial, inventory, and commission totals reproduce from underlying
    entries.

## Required interfaces

- centralized monetary calculation and rounding
- appointment-payment provider contract and normalized event ingestion
- idempotent deposit allocation and reconciliation
- sale builder from appointment snapshots
- payment/refund/void/correction commands
- cash-close calculator
- inventory movement ledger
- commission calculator with effective rule lookup

## Implemented FR-14 / FR-15 boundary

`App\Domain\MoneyCommerce` now owns `CommerceSetting`, `PaymentIntent`, a
signature-verified provider-event inbox, immutable `PaymentTransaction`,
`Deposit`/`DepositAllocation`, `Sale`/immutable `SaleLine`, receipt snapshots,
cash closes, and reconciliation tasks. Appointment and Sale remain linked,
never merged: the Sale copies appointment service-line source values at
checkout and can add an authorized add-on/product line.

The sole calculation path is `App\Support\Money\MoneyCalculator`. Values are
integer minor units and one ISO currency per Sale. It applies a line discount
before tax, calculates inclusive tax as the rounded tax portion of the net
line and exclusive tax as rounded net-line tax, rounds half-up per line, then
adds tips. The persisted calculation snapshot records inputs, ordering, and
totals so a receipt and a report can be reproduced.

Deposit policy snapshots support none, fixed, percentage, full prepayment,
per-service rules, new-client-only, spend threshold, and prior-no-show rules.
They expose cancellation cutoff, refundability, cancellation fee, and no-show
fee before payment. Binding, applying, transferring, refunding, forfeiting,
and crediting a Deposit are idempotent allocations bounded by its original
successful payment. Cancellation waiver is explicit and does not erase policy
or payment history.

Public deposit payment extends the existing Capacity Hold rather than creating
an Appointment early. A provider Payment Intent retains encrypted booking
details and a policy/source snapshot. A verified successful webhook creates a
single payment transaction, then confirms the Hold. Failure to finalize does
not lose the payment: it opens a tenant-scoped reconciliation task with provider
evidence. Duplicate events return the original effect and late non-success
events cannot rewind success.

Checkout supports cash, card, UPI, bank transfer, payment link, custom,
pay-later, partial, and split tenders. Refunds and wrong-allocation corrections
are compensating payment transactions; sale lines and original payments are
not deleted. A receipt is a branded immutable snapshot, suitable for the
digital receipt route and browser printing. Cash close persists opening cash,
method summary, expected/actual cash, reasoned variance, outstanding balance,
and append-only post-close adjustments.

### Provider boundary and launch controls

Stripe is the currently implemented appointment-card adapter, separate from
the existing SaaS-subscription lifecycle. Its webhook must use
`STRIPE_APPOINTMENT_WEBHOOK_SECRET`; browser return pages are never evidence of
payment. Paddle was assessed but is not an appointment-payment adapter: its
published merchant-of-record product is for SaaS and digital products. It must
not be used to charge a salon client for an in-person service or retail sale
without a written Paddle approval and a replacement decision. Do not put
provider API keys in source control, receipts, logs, or test fixtures.

### Reconciliation proof

For each Sale: `balance = total - deposit applied - successful tenders +
refunds/corrections`. For each Deposit: `original = applied + refunded +
forfeited + credited + remaining`. A provider event records ID, payload hash,
signature verification, provider occurrence time, processing status, and the
normalized payment evidence. The provider transaction/reference and internal
idempotency key are unique. Cash expected equals opening cash plus net cash
payment transactions; every variance requires a reason.

### Financial state table

| Record | States | Permitted progression |
| --- | --- | --- |
| Payment intent | `pending`, `succeeded`, `failed` | Verified success is terminal; a late failure cannot rewind it. |
| Deposit | `bound`, `settled` | Allocation only: apply, transfer, refund, forfeit, or credit until no remainder. |
| Sale | `open`, `completed` | Payment/deposit application can complete it; refund/void/correction append new transactions and may reopen balance. |
| Payment transaction | `succeeded` plus kind `payment`, `refund`, `void`, `correction` | Immutable; correction links to original transaction. |
| Reconciliation task | `open`, `resolved` | Only visible, evidence-backed recovery closes it. |
| Cash close | one close per business/location/local date | A close is immutable; elevated adjustments append separately. |

## Implemented FR-16 inventory boundary

`App\Domain\Inventory` owns tenant products/categories, location stock levels,
and append-only movements. A Product stores SKU, optional barcode, sale/cost
minor units, tax rate, currency, status, aggregate stock, and default low-stock
threshold. `inventory_levels` is the location-authorized balance; the Product
aggregate is its cross-location total. Receipts, reviewed CSV opening stock,
and manual adjustments lock the Product and Location level, require a stable
idempotency key, and record before/after, delta, actor, reason, source, and UTC
time. CSV version 1 has an exact header and row-level rollback/error evidence.

The final successful Sale tender invokes the inventory ledger in the same
database transaction. Each inventory-backed product Sale Line has exactly one
`sale:{sale}:line:{line}` deduction. Replay returns the original tender and
cannot create a second movement. Product refunds and voids cannot proceed
without an allocation and `restock`, `write_off`, or `customer_keeps`
disposition. Every disposition is retained in `sale_line_refunds`; even a
zero-delta write-off/customer-keeps outcome has a traceable movement.

## Implemented FR-17 commission and tip boundary

`App\Domain\Commissions` owns immutable, effective-dated
`service_percentage`, `product_percentage`, and `fixed_service` rules. Lookup
uses the Sale completion instant, then most-specific Staff/Service and latest
effective version; a fixed Service rule takes precedence over a Service
percentage at equal specificity. Percentage commission uses Sale Line value
after line discount. Fixed Service commission multiplies by quantity. Earned
entries retain rule, staff, Sale Line, base, rate, currency, and time, so later
rule edits cannot rewrite payroll history.

Tips remain separate. Sale completion projects frozen tip allocations into
append-only Tip Entries. A reasoned line refund reverses commission in
proportion to the affected discounted line value; the reversal is capped by
the original earned entry. Tip reversal is a documented proportional offset of
the refund/void amount against the completed Sale total and is capped by the
earned Tip. Manager Commission/Tip adjustments are signed values with approver,
reason, and idempotency. Staff/date statements expose Sale Line and payment
sources; the queued payroll export combines both ledgers without claiming to
run payroll.

## Major acceptance evidence

- delayed, duplicate, and out-of-order webhooks converge on one correct result;
- payment success plus failed finalisation safely recovers or refunds;
- deposit plus cash/card split tender reconciles and prints correctly;
- final total below deposit uses an explicit approved workflow;
- partial refund adjusts sale totals, stock disposition, commission, and reports
  without erasure;
- product stock changes exactly once; and
- commission and tip statements are reproducible from sale lines.

Verified Prompt 11 evidence adds: exact-once two-unit deduction and replay;
restock and customer-keeps/void dispositions; CSV opening stock, receipt, and
reasoned damage adjustment; discounted product/fixed-service commission;
effective rule replacement; refund and tip offsets; manager adjustment; staff
statement; and payroll/report export isolation. See
`tests/Feature/InventoryCommissionReporting/InventoryCommissionReportingTest.php`.

## Open decisions

ADR-020 resolves the implementation profile: India/INR/en-IN, business-owned
tax-inclusive or tax-exclusive configuration with a zero default until the
shop supplies an approved rate, Stripe for appointment card collection, and
cash/card/UPI/bank-transfer/payment-link/custom/pay-later tenders. Live tax,
receipt wording, provider account, and local-method certification remain launch
controls rather than assumptions in application code.
