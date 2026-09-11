# Stripe subscription billing

This document describes the ClipperDesk SaaS subscription boundary implemented
under ADR-029. It covers Business-owned subscriptions only. Stripe appointment
payments are a separate bounded context with a different webhook secret and
must not be projected into subscription state.

## Ownership and trust boundaries

- A `Business`, not a User, owns one normalized `BusinessSubscription`.
- All active Business members inherit plan capabilities, but their Membership
  role and permissions are evaluated separately. An entitlement never grants a
  user permission and a role never grants a paid feature.
- The application-owned billing tables are the local source for access checks.
  Stripe-signed webhooks are authoritative for external payment and
  subscription state. A success redirect is progress feedback only.
- Stripe secrets remain server-side. Checkout accepts a local price identifier,
  then validates the plan code, interval, amount and Stripe Price ID against the
  server catalog before making a provider request.

## Required environment configuration

Configure these independently in each environment. Never commit their values.

```dotenv
# Publishable browser key: pk_test_... or pk_live_...
STRIPE_KEY=
# Server-side secret: sk_test_.../sk_live_..., or a properly permissioned rk_... key
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_BILLING_CURRENCY=USD

STRIPE_STARTER_MONTHLY_PRICE_ID=
STRIPE_STARTER_ANNUAL_PRICE_ID=
STRIPE_PRO_MONTHLY_PRICE_ID=
STRIPE_PRO_ANNUAL_PRICE_ID=

BILLING_TRIAL_DAYS=14
BILLING_GRACE_DAYS=7
BILLING_EXPORT_DAYS_AFTER_TERMINATION=30
```

`STRIPE_KEY` and `STRIPE_SECRET` are not interchangeable. Catalog provisioning
uses `STRIPE_SECRET` on the server. A restricted `rk_...` key must have read and
write access to Stripe Products and Prices. Never expose an `sk_...` or `rk_...`
key to browser code or commit it to the repository.

The four Price ID variables are optional when the managed provisioner is used.
Set them only when adopting or strictly verifying Prices that already exist in
the matching Stripe mode. Generated IDs are stored in the effective-dated local
catalog and marked as command-managed; they do not need to be copied into
`.env`.

`STRIPE_APPOINTMENT_WEBHOOK_SECRET` is intentionally separate and is not a
substitute for `STRIPE_WEBHOOK_SECRET`.

After configuration and migrations, clear the application configuration cache
and preview the intended catalog:

```bash
php artisan migrate --force
php artisan config:clear
php artisan billing:sync-stripe-catalog
php artisan billing:sync-stripe-catalog --provision
```

The first billing command is a no-write preview. `--provision` converges the
managed Stripe catalog on `config/billing.php`, then synchronizes effective-
dated local mappings. LaraFast's documented
`stripe:create-products-and-prices` and `stripe:sync-products-and-prices`
commands are retained as aliases to this Business-owned workflow. They no
longer read the legacy generic JSON/Cashier stubs.

`--apply` remains available when Price IDs are supplied explicitly. It only
retrieves and verifies those Prices; it never creates or changes remote Stripe
objects. Provisioning with a live-mode secret additionally requires `--force`.

## Stripe catalog setup

The managed command creates one Stripe Product per public plan and immutable
recurring Prices for each supported interval. Current configuration expects
Starter monthly/annual and Pro monthly/annual prices in USD. Products and
Prices carry `application=clipperdesk` catalog metadata. Prices use stable
lookup keys such as `clipperdesk_starter_monthly`.

Plan names, ranks, approved amounts, interval mappings and entitlements live in
`config/billing.php`. Database rows remain effective-dated execution and
history records. Controllers and components must not compare plan names or
embed provider Price IDs.

To add a plan:

1. Add the plan, rank, interval amounts, optional existing Price IDs and
   entitlement values to `config/billing.php`.
2. Preview the catalog in every target environment.
3. Run `billing:sync-stripe-catalog --provision` in test mode and certify it.
4. Run the same command with `--force` only after explicitly reviewing a
   live-mode deployment.
5. Add lifecycle, pricing, checkout, upgrade and downgrade tests.

Stripe does not permit changing a Price amount in place. When an amount changes
in configuration, the provisioner creates a replacement with an idempotency
key, transfers the stable lookup key, commits the new local mapping, then
archives the prior managed Price. Existing subscriptions remain attached to
their historical Stripe Price and no invoice evidence is rewritten. A failed
or interrupted run can be rerun safely.

## Checkout and Customer Portal

An authorized billing owner reviews a selected local price, then the server:

1. verifies tenant membership plus `billing.manage` permission;
2. verifies an active trial or eligible non-paid state;
3. validates the local price against the configured Stripe catalog;
4. creates or reuses an expiring `BillingCheckoutAttempt`;
5. creates the Stripe Customer once with a stable idempotency key;
6. creates a hosted subscription Checkout Session with a stable attempt-based
   idempotency key and minimal Business/attempt metadata; and
7. redirects the browser to Stripe.

Repeating the same selection reuses its still-valid hosted Checkout. Selecting
a different price first expires every competing Stripe Checkout Session and
only then supersedes its local attempt; if Stripe cannot confirm expiry, the
replacement fails closed instead of leaving two payable sessions. Attempt
reservation is serialized under the local subscription row lock. A concurrent
request receives a retryable conflict while the first request is preparing its
Stripe Session rather than creating another attempt.

Signed Stripe webhooks are the primary confirmation path. The authenticated
status endpoint is scoped to the route Business and may also retrieve its
already-stored Checkout Session through the server-side Stripe API. That
provider-authenticated snapshot closes redirect or webhook-delivery gaps; it
never trusts browser-supplied customer, subscription, plan, or status data.
The scheduled `billing:reconcile-stripe-checkouts` command provides the same
retry-safe recovery for pending attempts.

The Stripe Customer Portal owns payment methods, billing details and invoice
history. The application owns plan comparison, entitlement impact, reasoned
plan changes, cancellation/resumption UX and local audit history. Portal access
requires the Business's mapped Stripe Customer ID.

## Webhook configuration

Configure this endpoint in the matching Stripe mode:

```text
POST /billing/webhooks/stripe
```

Subscribe only to the events projected by the application:

- `checkout.session.completed`
- `checkout.session.expired`
- `checkout.session.async_payment_failed`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.created`
- `invoice.finalized`
- `invoice.updated`
- `invoice.paid`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

The handler verifies the Stripe signature before persistence. The provider
event inbox stores the event ID, payload hash, occurrence time, attempt count
and processing result. Duplicate payloads are no-ops, reused IDs with different
content are rejected, failed events remain retryable, and provider occurrence
time prevents old events from rewinding newer subscription state. Subscription
and Checkout events may arrive in either order: both bind only through the
tenant-owned attempt metadata and converge that attempt to `confirmed`. A late
event for an expired, failed, or superseded attempt is retained as evidence but
cannot activate the local subscription. Invoice projection accepts Stripe's
direct subscription field and its current nested
`parent.subscription_details.subscription` representation.

For local testing, use the Stripe CLI in test mode and forward events to the
local endpoint:

```bash
stripe listen --forward-to http://barber_app.test/billing/webhooks/stripe
```

Put the CLI-provided signing secret in the local
`STRIPE_WEBHOOK_SECRET`, clear configuration cache, and exercise Checkout,
renewal failure, recovery and cancellation. Do not copy a live signing secret
into local configuration.

New Checkout sessions fail closed unless both the Stripe server credential and
the subscription webhook signing secret are configured. This prevents taking a
payment in an environment that cannot verify lifecycle events.

If a signed delivery was unavailable after an already completed Checkout, an
operator can reconcile one Business without copying provider IDs into the
database:

```bash
php artisan billing:reconcile-stripe-checkouts --business=BUSINESS_PUBLIC_ID
```

The scheduler runs that reconciliation every five minutes for remaining
pending/processing attempts. It uses the stored Checkout Session ID and Stripe
API authentication, preserves event ordering, and is safe to retry.

## Lifecycle and access behavior

The normal lifecycle is:

```text
verified signup -> Business + trial -> Stripe Checkout -> signed webhook
-> active -> renewal / plan changes -> cancel scheduled -> period end
-> terminated -> limited export window
```

Alternative paths are:

- Trial expiry: the account becomes read-only; users can sign in, view existing
  information, manage profile/billing and subscribe. Protected writes return
  HTTP 402 for JSON requests and a clear billing redirect for web requests.
- Renewal failure: the subscription becomes `past_due`, then `grace`. Stripe's
  retry policy remains authoritative. When the local grace deadline passes, the
  account becomes read-only; successful invoice payment restores active access.
- Cancellation: access remains active until the recorded billing-period end and
  can be resumed before that boundary.
- Downgrade: plan-rank reductions and annual-to-monthly changes occur at period
  end. If current usage exceeds the target limit, records are preserved and the
  change stores a usage/limit snapshot; increasing operations remain blocked
  after the lower entitlement becomes effective.

`EnforceSubscriptionAccess` is the broad write boundary. `RequireEntitlement`,
policies, domain services and job/import guards enforce individual capabilities
and limits. Frontend badges, hidden actions and navigation are explanatory UX,
not security controls.

`WorkspaceAccessService` is the shared decision source for authenticated
navigation and feature-entry middleware. Its order is Membership permission,
plan entitlement, then required active Location access. A role denial is hidden
from navigation; an upgrade or incomplete setup remains visible with an
explanation and an action only when that Membership can perform it. Expected
denials render `Access/Unavailable` rather than a raw framework 403.

Verified owner onboarding idempotently provisions one default active Location
and assigns the Owner Membership. The configuration workflow completes that
same row instead of creating a competing location. The corrective migration
backfills a default Location and active Owner assignments for existing
Businesses that were created before this invariant. It can safely reactivate an
existing inactive default Location, but deliberately does not grant an
unassigned employee access to every Location.

Numeric usage must be consumed at the domain boundary, not merely displayed.
`EntitlementUsageManager` atomically reserves the current subscription-period
allowance under a database lock. Mobile communication delivery records one
reservation per message, reuses it across provider retries, releases known
terminal pre-send failures, and fails closed with `plan_limit` before the
provider is called. Ambiguous mobile transport failures retain the reservation
because the provider may have accepted the message.

To add a restricted feature:

1. Add a stable entitlement definition migration; use capability language such
   as `reporting.advanced`, never a plan name.
2. Add a value for every plan in `config/billing.php` and sync the catalog.
3. Enforce the key at the closest backend boundary (route middleware, policy,
   domain service, job or import guard) with the correct read/use/consume action.
4. Share only the minimum boolean/value required by the frontend and add a
   helpful upgrade or read-only state.
5. Test active, trial, expired, role-denied, lower-plan and over-limit cases.

## Scheduling, queues and notifications

The application scheduler runs the billing lifecycle every five minutes. It
advances trial notices/expiry, scheduled plan changes and dunning restrictions.
Provider-event reconciliation retries verified pending/failed events through
the same idempotent processor. Pending Stripe Checkout snapshots are also
reconciled every five minutes. Billing notices are deduplicated before delivery.

Application notifications cover product-specific trial ending/expiry and local
restriction state. Stripe should own payment receipts and card-retry emails
where configured, avoiding duplicate messages. Provider and queue failures must
be logged without secrets, full payload dumps or sensitive card data.

## Migration and launch runbook

The migration retires active Paddle catalog rows and changes only clean trial
subscriptions with no external customer/subscription identifiers to Stripe.
Historical Paddle prices, invoices, payments, provider events and applied
migrations remain evidence and are not deleted.

A Business with a Paddle external identifier must not be relabeled
automatically. Export and reconcile its provider state, decide whether to let
the old paid period end or perform a consented Stripe migration, and record the
decision before enabling Stripe checkout for that Business.

Before production launch, verify all of the following:

- Stripe test/live keys and any explicitly configured Price IDs belong to the
  same intended mode;
- catalog provisioning or `--apply` passes and public pricing is complete;
- the webhook endpoint has the correct signing secret and required events;
- duplicate, delayed, out-of-order and retry delivery tests pass;
- Customer Portal cancellation, payment method and invoice settings match the
  application UX;
- Stripe tax, invoice, retry/dunning and email settings are reviewed;
- Finance/Legal approve seller identity, taxes, refund ownership and support;
- queues, scheduler, monitoring and alerting are running; and
- a full test-mode checkout, renewal failure, recovery, plan switch,
  cancellation, grace-period expiry and reactivation is reconciled locally.
