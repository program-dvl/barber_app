# Stripe subscription migration audit

Date: 2026-08-29  
Scope: FR-01 business subscription billing, trials, entitlements, and recovery.
Appointment deposits and salon-client payments remain the separate FR-14/FR-15
Stripe boundary.

## Decision and source-of-truth outcome

The 2026-08-29 product direction replaces ADR-021's Paddle provider selection
with Stripe. The accepted parts of ADR-007 remain the target architecture:

- `Business`, not `User`, owns the SaaS subscription;
- provider APIs remain behind `SubscriptionProvider`;
- normalized subscription, invoice, payment, change, event, and entitlement
  records remain the application contract;
- Stripe webhooks, not browser redirects, converge paid access; and
- salon-client commerce remains a separate provider contract even when both
  boundaries use Stripe.

Larafast's current Stripe guidance recommends Stripe Checkout, Customer Portal,
Cashier/Stripe webhook signature verification, and server-side subscription
middleware. This repository deliberately does not adopt Cashier's User-owned
subscription schema because it contradicts the Business ownership required by
FR-01. It does reuse the installed Cashier/Stripe SDK and Larafast's hosted
Checkout/Portal posture at the provider edge.

## Reusable implementation

The following application-owned foundation is retained:

- `SubscriptionProvider` and `StripeSubscriptionProvider`;
- Business-owned plans, effective prices, subscriptions, immutable changes,
  invoices, payments, coupons, checkout attempts, provider events, notices,
  entitlement definitions, plan grants, and Business overrides;
- `SubscriptionLifecycleManager`, `EntitlementEvaluator`, HTTP/job/import
  entitlement guards, and platform support visibility;
- verified-owner, exactly-once Business and trial creation;
- signed, deduplicated, ordering-aware Stripe event ingestion;
- retry/grace/restricted/termination states, safe downgrade snapshots, and
  export recovery; and
- scheduled reconciliation and billing-notice commands.

Cashier's legacy User `Billable` path is already disabled and Cashier routes are
ignored. The legacy Stripe controller/listener, generic product catalog, and
Filament billing resources are not approved tenant billing behavior.

## Provider replacement inventory

Paddle currently appears in all of these active subscription seams:

- `@paddle/paddle-js` and its lockfile entries;
- `config/billing.php`, `.env.example`, and provider selection binding;
- Paddle provider, signature verifier, webhook processor, checkout reconciler,
  webhook controller, route, CSRF assumptions, and replay adapters;
- Paddle catalog synchronization command and the historical Paddle catalog
  migration;
- inline-checkout and legacy Paddle Vue components;
- billing overview, public pricing copy, onboarding selection validation, and
  tests; and
- architecture, decision, status, isolation, support, and launch documents.

Historical Paddle database rows must not be deleted. They remain immutable
financial/provider evidence. New checkout and pricing selection will accept
only active Stripe price mappings. Existing Paddle subscriptions require an
explicit customer migration or support transition; they are never relabeled as
Stripe subscriptions.

## Verified gaps in the current Stripe path

1. `BusinessBillingController` requires Paddle tokens, creates Paddle attempts,
   returns Paddle transaction data, and exposes a Paddle-only review page.
2. The active catalog contains hard-coded Paddle price IDs. No Stripe plan-price
   environment mappings are configured.
3. The Stripe webhook secret is not configured in the inspected local
   environment. Secret values were not read or printed.
4. Trial expiry is not advanced to a restricted state, and trial-ending or
   trial-expired notices are not scheduled.
5. Most tenant write routes do not share a subscription-state guard. Existing
   feature checks are sound where used, but coverage is incomplete.
6. Stripe `customer.subscription.updated` can project `active` over a locally
   scheduled cancellation because `cancel_at_period_end` is not normalized.
7. Saved-card evidence expects an expanded payment method that Stripe webhook
   payloads do not normally include.
8. Checkout attempts are not used to deduplicate Stripe sessions or correlate
   successful Checkout sessions.
9. Event retries record failure state, but an exception can roll back the
   attempt increment.
10. Public and authenticated billing UX still names Paddle and assumes an
    embedded checkout rather than Stripe-hosted Checkout.

## Target lifecycle

| Local state | Product access | Billing/export | Source of transition |
| --- | --- | --- | --- |
| `trialing` | Trial entitlements until `trial_ends_at` | Available | Verified owner bootstrap |
| `active` | Paid entitlements | Available | Signed Stripe subscription/invoice evidence |
| `past_due` | Allowed with warning | Available | First renewal failure |
| `grace` | Allowed with dated warning | Available | Later failed retry |
| `restricted` | Read-only; increasing operations denied | Available | Trial/grace expiry |
| `cancel_scheduled` | Paid entitlements through period end | Available | Stripe period-end cancellation |
| `canceled` | Read-only | Available through recovery window | Support cancellation/end evidence |
| `terminated` | Closed | Export/recovery through dated window | Signed terminal provider evidence |

Authentication/RBAC and subscription entitlements remain independent. A route
must first resolve an active Business membership and permission, then pass the
subscription/entitlement decision.

## Target provider boundary

- Central catalog configuration defines stable plan codes, display metadata,
  amounts, optional Stripe environment price IDs, and entitlement values.
- A dry-run-by-default Stripe catalog command can verify pre-created immutable
  recurring prices with `--apply` or provision the managed Products and Prices
  with `--provision`. Provisioning uses stable lookup keys and idempotency keys,
  commits the effective local mapping before archiving replaced managed Prices,
  and requires `--force` for live-mode mutations.
- Checkout accepts a local `billing_plan_prices.id`, revalidates its active
  Stripe mapping server-side, reuses an unexpired pending attempt, and creates
  Stripe Checkout with Business/attempt/price metadata.
- Stripe Customer Portal owns payment methods, billing details, invoice access,
  and payment recovery. Application controls preserve consequence-first plan
  changes, downgrade safety, cancellation, and audit history.
- Required Stripe webhook events are limited to Checkout completion,
  subscription created/updated/deleted, and invoice paid/payment failed. Other
  invoice lifecycle events may update normalized invoice evidence but do not
  grant access.
- Redirect success is presentation only. Webhook processing is authoritative,
  idempotent, replay-safe, and ordering-aware.

## Baseline verification

Before implementation, the focused billing suite passed 32 tests with 233
assertions. This proves the pre-migration Paddle behavior and normalized domain
foundation; it is not evidence for a live Stripe Checkout, Portal, webhook, tax,
retry, or settlement path.

## External launch gates

- configure Stripe monthly/annual price mappings for every public plan;
- configure a dedicated SaaS subscription webhook secret and required events;
- configure Customer Portal products, cancellation/update policy, branding,
  invoices, payment retries, and customer emails;
- certify test-mode Checkout, webhook delivery, retry/dunning, plan schedule,
  Portal recovery, and a Stripe test clock;
- approve tax, invoice, cancellation/refund, retention, and customer-support
  responsibilities; and
- migrate or close any real Paddle subscriptions through an operator-reviewed
  customer transition plan.

## 2026-08-30 access and synchronization follow-up

The first real test purchase exposed three connected integration defects, not
three isolated page bugs:

1. verified owner onboarding created the Business, Owner Membership and trial
   but no Location or Location assignment;
2. the Stripe Checkout completed remotely while the subscription webhook secret
   was absent, leaving the local attempt pending and the trial projection stale;
3. the sidebar did not consume the same Membership/entitlement/Location
   decisions as the backend, and expected 403 responses fell through to
   Inertia's raw error modal.

The stabilization establishes an idempotent default-Location invariant,
backfills existing Businesses, introduces one workspace feature decision source
for both navigation and route entry, and renders distinct upgrade, setup and
permission states. Report selection now filters by Membership permission and
defaults to Sales only when that report is authorized.

Stripe-signed webhooks remain authoritative. A stored pending Checkout may also
be reconciled through authenticated Stripe API evidence from the tenant-scoped
status endpoint or scheduled command. New Checkout fails closed without a
server key and webhook signing secret, so the application cannot knowingly take
another payment while real-time lifecycle verification is unavailable.
