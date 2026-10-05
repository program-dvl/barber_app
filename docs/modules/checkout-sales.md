# Checkout and sales

Scope: FR-14–FR-19; appointment/Client handoffs under FR-06/08/11. Approved
requirements are [FR-15–17](../product-requirements.md#fr-15-checkout-and-basic-point-of-sale).
ADR-004/020/039/047 and the [quality contract](../quality-and-testing.md) apply.

## Baseline and boundaries

The Phase 1 ledger owns Sale, SaleLine, PaymentTransaction, Deposit/Allocation,
SaleReceipt, CashClose, inventory movements and commission/tip entries. Checkout
starts from a completed Appointment; converted walk-ins use that same aggregate.
Booking snapshots retain the performed service's original price and primary
performer. Existing EffectiveServiceResolver owns added-service variant pricing.
MoneyCalculator alone calculates integer minor units, half-up tax per line and
tax-inclusive/exclusive totals from CommerceSetting. Product stock is location
specific; stock/commission/tips commit only when the sale's balance reaches zero. A saved
basket cannot be amended/cancelled in this workspace; stock drift rolls back the
recording command and needs an authorized stock correction or support review.
OPEN-18 records the missing compensating amendment/transfer contract.

Stripe appointment intents/webhooks are separate from SaaS billing. The current
checkout screen records externally received tenders; it has no certified card
collection or provider refund executor (OPEN-13/16). Never describe an internal
record as a provider charge/refund. Memberships, packages, gift cards, wallets,
loyalty, coupon campaigns and multi-performer allocation remain deferred; do not
invent benefit instruments or payment methods from Phase 2.

## Workspace implementation contract

Review a completed visit before saving its sale. Keep client, reference, local
time, location, source and primary performers visible. Service/product search is
bounded and branch/currency scoped. Added services use qualified active staff and
current effective prices; booked rows retain their source snapshot. Removal and
adjustments require a recorded reason. Price overrides require an owner/manager;
discounts require discounts.apply, with elevated approval beyond the configured
threshold. A boolean from the browser is never manager authority.

Preview totals on the server. Bind preparation to that quote's fingerprint and
serialize on the Appointment root. A prepared Sale is immutable in this workspace;
reopening returns its original snapshot instead of rebuilding it. Display/apply
only the same appointment's verified, unallocated deposits, up to the balance;
surface excess separately. Never silently apply unrelated client balances.

Stage one or multiple actually received tenders; cash tendered/change is optional
and only the sale allocation enters the ledger. Partial payment keeps the Sale
open. Pay later saves an outstanding sale and creates no successful tender.
Persist a pending command's exact payload/key per user/business/sale until a
definitive response; uncertain requests freeze edits and offer safe replay and
ledger refresh. Lock the Sale before checking idempotency. Reuse of a key for a
different sale, amount or method must fail. Split tender commits atomically with
inventory and commission; server errors report that no records were committed.

Financial history is server paginated and scoped by tenant/location. Revenue
permission gates business history; checkout permission grants the operational
visit's payment detail and printable receipt. No provider payloads/contact
snapshots are exposed. Sale detail distinguishes original totals, net collection,
refunds and recorded balance. Refund/void is an append-only original-method record
after explicit amount/reason/stock disposition review; provider payments requiring
execution remain blocked, and cumulative item quantity/value cannot be returned
twice. Audit records retain actor/reason/source changes without cluttering payment.

Receipts are issued after completion and remain immutable. A later refund is
shown in sale detail without rewriting the issued receipt. Print/Save as PDF is
available through the browser. Existing consent-gated payment.receipt events use
the approved communication pipeline; no new email/SMS send action is implied.
After completion offer receipt, rebooking, Calendar/Queue and next visit.

## Verification

Evidence, supported workflows and explicit limits are recorded in the
[2026-10-03 review](../audits/2026-10-03-checkout/README.md). Local unit/HTTP/browser
checks do not certify live providers, target-topology races/load, regional tax or
independent accessibility/browser compatibility. Existing release gates remain.

## Services integration (ADR-049, 2026-10-04)

[Services](services.md) owns current staff/branch/base prices and qualifications.
Added add-ons require a retained eligible parent service in the reviewed basket;
booked historical rows keep their original snapshots. No service tax-category
reference selects a different rate or exemption; CommerceSetting and recorded
booked tax remain authoritative. Inactivation/renaming does not rewrite sale
lines, receipts, client history, commission or reports. Service import cannot
silently overwrite configurations used by existing visits.
