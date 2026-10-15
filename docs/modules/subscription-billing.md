# Subscription & Billing

Updated: 2026-10-15. Requirements: [FR-01](../product-requirements.md#fr-01-saas-registration-and-subscription-management),
[FR-13](../product-requirements.md#fr-13-notifications-and-communication),
FR-19, FR-20 and [PRD §9](../product-requirements.md#9-subscription-packaging-and-commercial-rules).
See ADR-029/038/041/054/056/057/061/063/068 and `../stripe-billing.md`.

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
from Checkout shows one focused pending notice. Payment-method/invoice recovery remains available. Cancellation is at term end;
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
   Separately certify destination costs and paid credit reversals before SMS sales.

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
