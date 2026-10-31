# Subscription & Billing

Updated: 2026-10-29. Requirements: [FR-01](../product-requirements.md#fr-01-saas-registration-and-subscription-management),
[FR-13](../product-requirements.md#fr-13-notifications-and-communication),
FR-19, FR-20 and [PRD §9](../product-requirements.md#9-subscription-packaging-and-commercial-rules).
See ADR-029/038/041/054/056/057/061/063/068/069/071/072 and `../stripe-billing.md`.

ADR-075 clarifies authenticated estimate visibility without changing purchase
authority; see below.

## Approved pricing contract

One core software plan. The recurring amount is base (one location and one
bookable staff profile) plus extra locations times the location rate plus extra
bookable staff priced progressively by their position in the business-wide team. An active StaffProfile counts once across
branches. Login-only reception/admin users, inactive profiles and physical chairs
do not count. Existing active-location and staff entitlement guards remain the
server authority. Archive/deactivate preserves history; purchasing capacity does
not create staff or branches.

The owner approved USD-only software billing on 2026-10-11 (ADR-056): monthly
USD 29 base / 19 extra location. ADR-063 (2026-10-12) replaces flat staff pricing:
first person included, positions 2–5 USD 9 each, 6–10 USD 6 each, 11+ USD 4 each.
Annual component amounts are exactly ten times monthly; the full annual amount
is charged upfront, with a rounded monthly equivalent displayed separately.
Only approved, remotely verified immutable rates are public. The default USD
pricing mode selects the US rate card for new businesses regardless of operating
country. Business/sales currencies remain unchanged. Existing paid currency or
retained market changes still require a separately reviewed migration; there is
no automatic currency conversion. Other countries are hidden until approved.
Paid text packs are absent from this approved catalog; the purchase gate stays off.

Starter/Pro are not offered in the owner billing workspace (ADR-057). Retained
test subscription/invoice records stay readable, and owners may explicitly
review a switch to capacity pricing. Old tier checkout links redirect to the
Plan tab; old tier purchase/change requests return 410 without provider writes. Purchased rate cards are immutable; publishing
new commercial terms requires new base Stripe prices and a new fingerprint.
Core features remain separate from user permissions. Extra staff do not enable
enterprise support, branded sender, conversational messaging or marketing.

## Owner workspace

The workspace separates Overview, Plan and Invoices with keyboard-accessible
tabs and persisted section URLs. Overview omits empty invoice cards and technical
rollout details. A trial shows its end date and subscription action. Compact usage
metrics show zero allowances as “Not included” and warn on actual overuse, not
merely using the allowed location count. Plan contains only the location and
bookable-staff calculator, with monthly/yearly cadence.
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

Signed-in owners may view approved current software estimates while current
Stripe mappings are unavailable. The estimate remains non-binding and purchase
actions explain their unavailable state. This does not publish unverified rates
through the public catalogue, enable review/checkout without verified current
cards, or substitute retained older terms for current terms. Standard billing
visits still make no provider calls. The local recovery created and verified two
current monthly/annual cards against six test Prices while preserving two older
cards; it did not migrate paid customers, charge anyone or certify live billing.
[Setup recovery evidence](../audits/2026-10-29-setup-recovery/README.md) records the
environment and remaining deployment limits.

New purchases use three licensed recurring Stripe items, omitting zero addons.
The staff Price is graduated with additional-staff bounds 4/9/infinity and unit
amounts 900/600/400 cents monthly, 9000/6000/4000 annually. Quantity is total
bookable staff minus one. Verification expands and checks every tier; webhook
projection accepts verified tiered/graduated IDs without requiring expanded tiers.
Per-unit staff Prices remain supported only for retained immutable cards.
An owner may explicitly select current pricing and review/confirm a migration;
all rate-card switches take effect at renewal. Quotes alone never change access.
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
An open Checkout can be resumed from its recorded URL; authenticated expiry
releases the associated capacity request so the owner can review again. Returning
from Checkout automatically verifies the saved payment and shows its confirmed outcome (ADR-072). Payment-method/invoice recovery remains available. Cancellation is at term end;
reactivation and cancellation are version checked and repeated requests are safe.
Unknown remote outcomes requiring support must be reconciled, never discarded to
start a replacement charge. Production MySQL concurrency remains a release gate.

## Automatic payment return (ADR-072)

The Plan tab initially shows **Checking your payment** for a submitted checkout,
without flashing an unfinished-checkout notice or offering restart while checking.
The server supplies a local return result only for the current business's exact
saved attempt/session and associated checkout request. A previously confirmed,
applied checkout immediately shows **Your subscription is active**, including when
signed webhooks arrived before the browser. A success URL alone grants nothing.
Ordinary billing GETs still make no Stripe calls.

Automatic status requests use `verify_only`: read the original owned Checkout and
project its subscription/invoice through the existing lifecycle. Never create a
customer, Checkout or subscription, replay preparation, expire a payable session,
or submit a paid change from this mode. Existing guarded retirement of unconverted
test setup when moving to live remains available; paid/unknown live history stays
protected. A missing preparation remains available for explicit safe recovery.

One entry check follows a processing payment at 2/3/5/8/12-second intervals (six
reads over 30 seconds, excluding request latency). Stop immediately on error,
navigation, a terminal result, an open Checkout or a verified invoice requiring
payment. Preserve Retry-After and the current selection. A real processing delay
shows **Your payment is pending**; it is not described as abandoned and cannot
start another subscription. A verified open checkout offers Continue and Start
again. Applied payment automatically refreshes subscription, purchased quantities,
allowances and account access without a manual status action. If a payment remains
pending beyond the bounded checks, signed webhooks continue to update the account;
the retained status action can refresh the page's result later.

### Confirmed subscription with a stale checkout request (2026-10-28)

A stored submitted request must not contradict an already-confirmed purchase.
`CapacityCheckoutRecovery::settleConfirmed` repairs only the local workflow record
under Business → subscription → request locks. Require its exact Stripe attempt to
be confirmed with a confirmation date, the same business/provider subscription and
plan-price binding, an active/cancel-scheduled subscription and exact agreement
between request, attempt and purchased snapshot for rate fingerprint/card,
currency, cadence, locations, staff and total. A paid invoice or active status
alone cannot satisfy this proof. Unknown or mismatched requests remain protected.

Billing presentation, status/restart/confirm and subsequent review reuse this
repair. Mark the request applied at the stored confirmation time, clear its stale
checkout URL and append one audit record; do not change subscription versions,
terms, entitlements, invoices, payment amounts or provider timestamps. Billing GETs
still make no provider calls. A repaired visit supplies the confirmed outcome even
without return query parameters, and subsequent visits no longer show a blocker.

The Plan panel prefers an explicit confirmed/applied result over the same stale
request prop, including partial refreshes, without dismissing a different request.
Show **Your subscription is active** and **Change your subscription** for confirmed
customers. Only a freshly verified open Checkout offers **Complete your
subscription** and payment continuation. Unknown confirmation does not masquerade
as abandoned payment and a confirmed attempt never offers Start again.

### Database-independent rate identity (2026-10-28 root-cause follow-up)

A verified `BillingRateCard.fingerprint` is the immutable pricing identity. Quotes
must reuse that saved value whenever a rate card exists. Never regenerate it from
`terms` decoded from database JSON: MySQL normalizes object-key order, while the
original verification hashed configuration order. Those objects have the same
commercial meaning but different encoded bytes. Draft/unverified quotes still
hash their configuration; existing verified cards and Stripe IDs are unchanged.

Provider projections, renewal schedules and owner change classification match the
verified rate-card ID. This keeps initial activation, no-op selection, immediate
expansion and scheduled renewal independent of JSON encoding order. It does not
permit switching to a different rate card or changing retained customer terms.

For an already-confirmed checkout, the local completion repair accepts either the
card's saved fingerprint or the exact legacy hash of that same card's decoded
terms in the request/attempt/snapshot. All existing confirmed-attempt, tenant,
provider subscription, price, card, currency, cadence, quantity and amount checks
remain mandatory. Arbitrary/mismatched fingerprints remain blocked. The repair
changes only workflow state; existing subscription and invoice history stays intact.

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
characters and surrogate pairs), at **one credit per segment worldwide**. The
client's phone country, the business's country/currency and historical route maps
do not restrict sending or multiply quota use (ADR-076). Included credits are consumed first;
any remainder uses an append-only prepaid ledger under the subscription lock.
The entire cost is reserved before delivery or nothing is consumed. Repeat
reservation/release is idempotent; failed pre-send attempts release the same
recorded allowance window. The existing SMS-only transactional consent and
provider gates remain. A provider failure never purchases extra credits.

The rule applies at delivery to existing and new subscriptions without changing
their paid snapshots, verified pricing identity or prior usage. `sms_routes`
remains deprecated pricing metadata solely for retained rate-card compatibility;
it is not a destination allow-list. No Stripe price or subscription migration is
needed. Previously suppressed messages remain historical and are not replayed.

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
3. Configure the intended test/live Stripe account and run
   `billing:verify-capacity-rates US --provision` (live mutations require `--force`).
   This creates/reuses and verifies six components (four per-unit and two graduated staff Prices), then installs local mappings
   transactionally. Explicit configured IDs can instead use `--apply` (read-only
   provider verification). Command-managed IDs reload from matching rate cards;
   changing amounts/features never silently reuses a mismatched fingerprint.
4. Run `billing:configure-stripe-webhook --local` for CLI forwarding instructions,
   or `billing:configure-stripe-webhook https://your-host/billing/webhooks/stripe
   --apply` for a managed remote endpoint (also `--force` in live mode). New endpoint
   secrets are saved privately, not printed; managed repeats keep the secret.
5. Missing approval or server-side `STRIPE_SECRET` is reported explicitly, even
   with `--force`. After changing deployment environment values, clear cached
   configuration before retrying; `--force` only authorizes live provisioning.
   Follow [the deployment guide](../deployment/subscription-billing-stripe.md)
   for environment values, signing secrets, tax/portal setup, workers/scheduler and
   rollout checks. Enable billing only after mappings and environment gates pass.
6. Certify paid conversion, 3DS/decline, immediate proration, scheduled renewal,
   cancellation, duplicate/out-of-order delivery and production MySQL concurrency.
   Separately review platform carrier costs and certify paid credit reversals before SMS sales;
   these controls do not create client-country restrictions within the plan.

Focused evidence: `CapacityBillingTest`, `BillingWorkspaceTest`, provider/lifecycle
regressions, all frontend helper tests and client/SSR production build. Local
SQLite tests do not certify real payments, SES/Twilio delivery, concurrency or
independent accessibility. See project status for the actual verified results.

The [public pricing page](public-pricing.md) shares the capacity rates and estimate
helpers. Initial trial selection can reuse validated signup preferences, while
paid/pending capacity and the server billing review remain authoritative.


## Progressive pricing upgrade

Deploy the new revision and provision its Stripe mappings before publishing new
purchasable rates. No additional schema migration is required for progressive
terms: existing JSON rate cards/snapshots retain their original terms. See the
[rollout and migration steps](../deployment/subscription-billing-stripe.md#progressive-pricing-upgrade-2026-10-12).
The old rate card is not rewritten, archived or mass-swapped. Included monthly
SMS remains 100 credits per purchased bookable staff member; a team of 20 keeps
2,000. Existing overrides and retained-plan allowances stay authoritative.

## Abandoned checkout and portal recovery (ADR-068)

FR-01/19/20: initial capacity Checkout now has tenant-authorized status,
continuation and Start again recovery through `CapacityCheckoutRecovery` and the
existing Stripe reconciler/projector. The operation is committed before dispatch;
a lost response preserves its customer binding and original idempotency key.
Open sessions are explicitly expired before a new purchase can be reviewed.
Completed sessions reconcile the existing subscription. Authoritative Stripe
expiration replaces a shorter local estimate of the session lifetime.

Inputs remain editable for estimation while a submitted checkout blocks a second
review. The banner states the original checkout quantities/price. Start again
retains edited quantities, clears only the verified closed request and requires
another explicit review/confirmation. Failure leaves recovery actions available;
provider errors never become a raw Server Error or an unverified fresh purchase.

New attempts identify the exact capacity change. Old attempts use matching
quantity/fingerprint/time bounds; a stale expiry cannot close a newer request.
No customer/subscription, invoice, paid capacity or historical quote is deleted.
Wrong-mode/account objects require restored original configuration; unresolved
creation without a customer may replay only the original keys within 23 hours,
otherwise support must verify it. Private credential fingerprints stay server-side.
Portal errors remain inside billing, including direct-URL navigation failures.

See [recovery operations](../deployment/subscription-billing-stripe.md#abandoned-checkout-and-credential-recovery-2026-10-15)
and [verified evidence](../audits/2026-10-15-billing-recovery/README.md).

## Customer-first recovery refinement (ADR-069)

FR-01/19/20: a stale test Checkout on an otherwise unconverted trial no longer
blocks first live purchase. Under live credentials, status/restart locally
supersedes the cs_test_ attempt, retains its test-customer evidence and removes
only the current test customer binding. No real subscription, invoice, card,
capacity, balance, schedule, other pending/confirmed attempt or confirmed checkout
may exist. The next reviewed/confirmed purchase creates the live customer/session.
Genuine live/unknown attempts still need authoritative original-account evidence.

Capacity actions have independent named per-user limits. The frontend performs
one entry check and explicit later checks; background polling cannot exhaust
restart. Errors supply a typed code, safe reference, support link where needed
and Retry-After. The UI preserves estimates and shows a visible wait; persistent
operator-required cases replace ineffective restart with Contact support.
Checkout departure closes the review dialog. A transient per-business return
marker forces one authoritative document reload after Stripe; stale Inertia
history props cannot expose a fresh review over an existing payable checkout.

Inline billing feedback, shared workspace notices/Feedback alerts and ToastRegion
follow [the product-wide notice contract](../design-system.md#customer-first-recovery-and-notification-composition-2026-10-15).
The neutral notice includes a semantic icon, concise heading/body and separately
spaced action row, with alert/status semantics and 44px mobile controls.
`billing:inspect-checkout` provides redacted read-only operator diagnostics; see
[the recovery runbook](../deployment/subscription-billing-stripe.md#customer-recovery-refinement-2026-10-15).

## Checkout and payment lifecycle recovery (ADR-071)

FR-01/19/20: explicit restart also retires a `pending_capacity_` preparation whose
saved mode is test, including current test credentials. All ADR-069 unconverted
trial exclusions still apply; a real/unknown pending operation is never reset on
local absence alone. Retain the saved test customer privately and the original
request/attempt; preserve edited quantities and billing cadence through restart.
A persistent test preparation verification error exposes immediate Start again,
without a support-only state or pointless cooldown on this locally safe action.

Initial invoice decline keeps the original attempt retryable. A legacy failed
attempt with `initial_payment_failed` may recover only from an owned authenticated
session tied to its exact submitted request. Asynchronous failure retains the
pending purchase. A completed session no longer exposes its unusable Checkout URL;
where payment remains due, expose only the same verified open invoice, with matching
customer/subscription, currency, quoted subtotal and approved HTTPS payment host.
A first subscription reaching `incomplete_expired` may release its active provider
binding only before paid conversion; retain paid/invoice/checkout history and audit.

Activation and purchased capacity commit together under Business/subscription
locks. API observation times match stored whole-second precision; fresh snapshots
can reconcile same-second results, while equal/older signed events cannot revert
purchased capacity. Regression coverage includes fractional timestamps.

Status checks first reconcile paid changes. An unresolved original expansion or
renewal update may resume its original idempotency keys within 23 hours. The current
items must still match, proration/billing dates remain fixed and renewal schedules
must match the original create result. Never generate a replacement payment key.
Old, ambiguous or persistent provider failures remain saved for operator review.
An outstanding payment gets an Open invoices action instead of a duplicate checkout.

Operator inspection accepts a Business public ID or exact Support reference;
`--provider` adds read-only customer ownership and six monthly/annual price checks.
It prints no credentials, fingerprints, provider identifiers or raw diagnostics.
See [current runbook](../deployment/subscription-billing-stripe.md#payment-recovery-follow-up-2026-10-25)
and [local verification and deployment limitations](../audits/2026-10-25-billing-flow/README.md).

Customer copy follows the [plain-language contract](../design-system.md#every-word-must-serve-the-customer-2026-10-25).
Payment success describes the subscription outcome, not internal capacity
verification. The first purchase, later change, scheduled change and pending
payment have distinct headings. A useful heading needs no repetitive body copy.
The public pricing and recovery help use the same non-technical vocabulary.

### Checkout creation diagnostics (2026-10-25 follow-up)

An owner-reported restart succeeded but fresh Checkout creation still returned
503. Subsequent deployed inspection confirmed the current test customer is readable
and owned, while all six monthly/annual subscription Price IDs return 404
resource_missing. This establishes unavailable catalog mappings in that account;
matching key modes alone do not validate price ownership. Provision and verify
current test prices, then restart the old test preparation and review fresh terms.
The owner subsequently supplied successful deployed provisioning output and
confirmed that Start again → fresh review → Continue opens Stripe. This closes
the reported redirect failure; it does not verify a completed payment.

New Stripe API checkout failures retain only HTTP status, a validated error code
and an allowlisted top-level parameter in the pending attempt's private
`last_error`. The inspector reports this classification as `checkout_failure`;
older attempts show null. Raw provider messages/bodies, secrets and contact data
are not stored there or returned to the customer. Preserve the original exception
chain and log its provider request reference privately for operator investigation.
A successful retry clears the stale classification without making a second
attempt or resetting the saved purchase. Existing recovery/financial guards stay
in force; diagnostics alone do not establish that checkout works in production.

### Price catalog recovery after switching Stripe accounts

The existing `billing:verify-capacity-rates US --provision` creates/reuses catalog
objects in the current account and installs new verified immutable mappings.
It never creates a subscription or payment. Missing database-fallback mappings
can be replaced this way without editing old cards or paid snapshots. A pending
checkout still retains its old quote; restart/review is necessary to use the new
mappings. Simply checking the old request keeps its original unavailable prices.

Explicit CAPACITY subscription Price ID overrides remain authoritative. Provisioning
now reads all configured overrides before any catalog writes; if any are missing,
it identifies their variable names and asks the operator to correct/clear them and
rebuild configuration. It never silently overrides environment configuration.
The inspector labels mapping sources and gives catalog-specific next steps for
confirmed missing/mismatched prices, distinguishing provider read failures from
proof that setup is required. No customer-facing credential/configuration burden.
Monthly/annual account-switch tests repair all six missing prices, preserve old
paid subscription/cards, restart the pending trial and open one fresh checkout.
