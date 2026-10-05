# Subscription & Billing

Updated: 2026-10-05. Requirements: [FR-01](../product-requirements.md#fr-01-saas-registration-and-subscription-management),
[FR-13](../product-requirements.md#fr-13-notifications-and-communication),
FR-19, FR-20 and [PRD §9](../product-requirements.md#9-subscription-packaging-and-commercial-rules).
See ADR-029/038/041/054 and `../stripe-billing.md`.

## Approved pricing contract

One core software plan. The recurring amount is base (one location and one
bookable staff profile) plus extra locations times the location rate plus extra
bookable staff times the staff rate. An active StaffProfile counts once across
branches. Login-only reception/admin users, inactive profiles and physical chairs
do not count. Existing active-location and staff entitlement guards remain the
server authority. Archive/deactivate preserves history; purchasing capacity does
not create staff or branches.

Country-specific, explicitly published local-currency rate cards own amounts,
monthly/yearly cadence, included SMS credits, destination multipliers and optional
packs. This implementation supports two-decimal USD/CAD/GBP/EUR/INR/AUD only.
A business uses its saved country; changing currency/country needs a separately
reviewed migration. No automatic foreign-exchange conversions. The USD draft is
illustrative, disabled and unapproved. No other countries' prices are invented.

Existing Starter/Pro subscribers retain their terms until an owner explicitly
reviews and confirms a migration. Purchased rate cards are immutable; publishing
new commercial terms requires new base Stripe prices and a new fingerprint.
Core features remain separate from user permissions. Extra staff do not enable
enterprise support, branded sender, conversational messaging or marketing.

## Owner workspace

The overview uses bounded local projections, dated payment information, actual
recurring totals (all capacity components), usage, scoped invoices and grouped
unpaid currency balances. It makes no provider calls on ordinary page visits.
Sensitive provider metadata and raw payment credentials are not exposed.
Invoice details are tenant/permission checked. Provider links require HTTPS and
approved Stripe hosts. History paginates; failed/paid/retry states retain evidence.

The calculator shows whole location/staff counts, exact local breakdown, annual
savings and included monthly texts. Server reviews bind actor, business,
subscription version, immutable rate, fixed proration date, expiry and target
quantities. Owners confirm the exact review. Payment responses and URL flags do
not grant access. Provider state must be verified by signed webhooks or an
owner-authorized provider read; stale events cannot overwrite newer state.

New purchases use three licensed recurring Stripe items, omitting zero addons.
Same-interval increases use pending-if-incomplete with immediate proration.
Decreases, mixed increases/decreases, interval changes and cheaper migrations are
scheduled at renewal. Owners must first fit active resources into the requested
limits; records are retained. Later growth may require remediation at renewal.
Currency changes, discounted subscriptions, outstanding debt and conflicting
schedules require a separate billing review. Tax settings are retained across
capacity phases; incomplete invoice previews never masquerade as today's charge.
Finite schedule duration and stable idempotency keys prevent ambiguous repeats.

A submitted request remains visible during uncertain provider outcomes. Initial
checkout preparation is recoverable using the same attempt and Stripe key.
Failed/expired pending updates release the request only after authoritative state.
Payment-method/invoice recovery remains available. Cancellation is at term end;
reactivation and cancellation are version checked and repeated requests are safe.
Unknown remote outcomes requiring support must be reconciled, never discarded to
start a replacement charge. Production MySQL concurrency remains a release gate.

## SMS and routine email

Included SMS credits are pooled across the business and reset on the subscription
monthly anniversary, including on annual subscriptions; end-of-month anchors
clamp safely. They do not roll over. Initial migration closes overlapping legacy
annual usage windows while retaining their history and aligned consumption.
Prepaid credits carry forward while the billing account is retained; automatic
purchases are off. Credit purchases are available only to active subscribers after the separate
`CAPACITY_SMS_PURCHASES_ENABLED` switch is approved. It defaults off until
refund/dispute handling is certified.

Credits reflect actual rendered GSM/Unicode segments (including extension
characters and surrogate pairs) multiplied by the approved destination-prefix
rate. Unsupported destinations do not send. Included credits are consumed first;
any remainder uses an append-only prepaid ledger under the subscription lock.
The entire cost is reserved before delivery or nothing is consumed. Repeat
reservation/release is idempotent; failed pre-send attempts release the same
recorded allowance window. The existing SMS-only transactional consent and
provider gates remain. A provider failure never purchases extra credits.

Top-ups use one-time Stripe Checkout, known customer and immutable pack terms.
Only an owned, paid, complete exact session grants credits, once, including
asynchronous success. Subtotal must equal the pack; total must equal subtotal
plus nonnegative provider tax, with no unapproved discount. Routine operational
SES emails are included, with existing delivery/abuse controls; no unlimited
marketing campaigns or new client email channel are promised.

Before launch, support must define and certify credit refund/chargeback reversal
handling and negative-balance recovery. Automatic refund/dispute ledger reversal
is not implemented; do not label the SMS wallet production-ready before this
operational gate is met. No refunds or real provider sends were executed locally.

## Setup and verification

1. Apply the reviewed source package without overwriting intervening edits.
2. Run additive migrations; the capacity migration intentionally refuses a blind
   rollback of financial history. Take the normal verified backup first.
3. Agree country rate/allowance/pack/destination costs, including carrier charges,
   sender registration, Twilio number costs and SES/support costs. Populate
   `config/capacity-billing.php` and explicit Stripe Price IDs.
4. Approve each country and run `billing:verify-capacity-rates XX --apply`. This
   reads Stripe prices and installs local immutable mappings; it creates no
   remote objects and changes no existing subscription.
5. Configure/certify tax policy and `CAPACITY_AUTOMATIC_TAX`, credentials, webhooks,
   customer portal and provider sandbox paths. Enable `CAPACITY_BILLING_ENABLED`
   only after mappings and rollout gates pass. Drafts are never public purchases.
6. Certify paid checkout, 3DS/decline/pending expiry, immediate proration,
   renewal schedule/interval changes, cancellations, duplicate/out-of-order
   events, taxed/async top-ups, refund/dispute operations and destination rates.

Focused evidence: `CapacityBillingTest`, `BillingWorkspaceTest`, provider/lifecycle
regressions, all frontend helper tests and client/SSR production build. Local
SQLite tests do not certify real payments, SES/Twilio delivery, concurrency or
independent accessibility. See project status for the actual verified results.
