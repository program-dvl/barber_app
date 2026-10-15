# Stripe subscription billing setup

Updated 2026-10-15. Applies to FR-01 and ADR-029/054/056/061/068/069. Run from the application root with the PHP version required by composer.lock. Existing subscriptions and invoices are preserved. Commands create catalog/delivery configuration, never charges or subscriptions.

## Correct route and credentials

This app uses **`/billing/webhooks/stripe`** for Business-owned SaaS subscriptions. Appointment payments use a separate `/payments/webhooks/stripe` route and signing secret. Do not substitute Larafast's example `/stripe/webhook` or run `cashier:webhook`: Cashier routes/listeners are intentionally disabled under ADR-029. The installed Stripe SDK and existing checkout, portal, signature verification, event journal and reconcilers are reused.

Use a dedicated environment/database for sandbox work. Configure `STRIPE_KEY=pk_test_…` and `STRIPE_SECRET=sk_test_…` locally/staging. Live deployments need that account's live keys. Never paste keys into tickets, source control or shell history. The publishable key is public; secret keys and `whsec_` values are private. Keep the existing APP_KEY when deploying.

Approved software pricing is USD, independent of the business's operating country/currency:

| Component | Monthly | Yearly, paid upfront |
| --- | --- | --- |
| Base: 1 location + 1 bookable staff | $29 | $290 |
| Each extra location | $19 | $190 |
| Bookable staff 2–5, each | $9 | $90 |
| Bookable staff 6–10, each | $6 | $60 |
| Bookable staff 11 onward, each | $4 | $40 |

The included allowance remains 100 text credits per bookable staff per month on supported destinations; verify actual delivery costs and routes before SMS launch. Paid text packs remain unavailable.

## Local development

Set these values in `.env` (USD commercial approval is recorded in ADR-056):

```dotenv
CAPACITY_PRICING_MODE=usd
CAPACITY_US_APPROVED=true
CAPACITY_BILLING_ENABLED=false
CAPACITY_SMS_PURCHASES_ENABLED=false
```

Then run:

```sh
php artisan config:clear
php artisan migrate
php artisan billing:verify-capacity-rates US --provision
```

`--provision` creates/reuses three managed products and six immutable, tax-exclusive licensed recurring prices, verifies all six remote amounts/cadences, and installs local rate cards in one transaction. No manual Price IDs are needed. Repeated runs reuse lookup keys. Explicit component Price IDs remain supported with `--apply`, which only reads Stripe. A changed commercial revision needs new immutable base prices; historical cards are retained. Keys from a different Stripe account/mode cannot reuse the database's old IDs during provisioning.

Install the official Stripe CLI if needed, then sign in to the same sandbox as the API keys:

```sh
brew install stripe/stripe-cli/stripe
stripe login
php artisan billing:configure-stripe-webhook --local
```

The last command prints the exact `stripe listen --events … --forward-to http://127.0.0.1:8000/billing/webhooks/stripe` command. Run it in a second terminal and leave it running. Change the forwarding port if your Laravel server uses another port. Copy the listener's **own** signing secret into `STRIPE_WEBHOOK_SECRET` in `.env`; a Dashboard endpoint secret will not verify CLI deliveries. Then set `CAPACITY_BILLING_ENABLED=true` and run `php artisan config:clear`. Start the app with `php artisan serve` and the frontend with the project-supported Node version. Restart persistent workers after configuration changes.

Local catalog setup was completed against the configured test account on 2026-10-11. The listener must still be running for automatic local renewal/event delivery. A prefix-valid secret by itself does not prove delivery.

## Deployed server

1. Deploy source and built assets, keep the existing APP_KEY, back up the database, and apply additive migrations. Ensure HTTPS, `APP_URL`, non-debug production settings, queue workers and the scheduler are correctly configured.
2. Start with `CAPACITY_BILLING_ENABLED=false`. Set the intended Stripe account/mode keys, `CAPACITY_PRICING_MODE=usd`, `CAPACITY_US_APPROVED=true`, and `CAPACITY_SMS_PURCHASES_ENABLED=false`. Clear stale cached configuration.
3. Preview, then provision the approved catalog. Live catalog mutations require the explicit `--force` flag; preview never mutates Stripe.

```sh
php artisan config:clear
php artisan migrate --force
php artisan billing:verify-capacity-rates US
php artisan billing:verify-capacity-rates US --provision --force
php artisan billing:configure-stripe-webhook https://clipperdesk.com/billing/webhooks/stripe
php artisan billing:configure-stripe-webhook https://clipperdesk.com/billing/webhooks/stripe --apply --force
```

Use the actual deployed hostname if different. For a sandbox server, omit `--force`. Provisioning uses the current API account and mode; never copy test Price IDs to live. Do not replace this capacity catalog with the old Starter/Pro sync command.

4. For a newly created endpoint, the command writes its one-time signing secret to the reported **private recovery file** under `storage/app/private/stripe-billing-webhook-*.json` with mode 0600. Read it privately on the server and set `STRIPE_WEBHOOK_SECRET` in your deployment secret manager or `.env`. The command never prints or logs it. Existing managed endpoints are updated in place and keep their original signing secret; retrieve that endpoint's secret privately in Stripe if the recovery file is absent. Unmanaged or duplicate endpoints at the same URL are refused.
5. In Stripe Workbench → Webhooks/Event destinations, verify that this endpoint is enabled and belongs to the intended live/sandbox mode. The command selects exactly the checkout/subscription/schedule/invoice events consumed by `StripeWebhookProcessor` and pins the installed SDK's API version. No `*`, appointment events or legacy catalog listener events are necessary. If creation timed out or the recovery file is incomplete, inspect Stripe and recover/rotate that endpoint's secret before proceeding; do not create a second destination blindly.
6. Verify signed delivery receives HTTP 204, and a completed sandbox purchase projects the matching business's subscription and invoice once. Invalid signatures must receive HTTP 400. Resend the same event and verify no duplicate invoice/payment/access change. Use the sandbox counterpart of the deployment before live rollout.
7. Configure the Stripe account's branding, support/business identity, receipt emails and billing portal. The app already creates restricted management portal configurations for card/contact/invoice tasks, and a separate focused legacy plan-change portal. Keep generic quantity/currency/plan switching off: capacity changes use the app's reviewed flow.
8. Confirm tax posture and required registrations with the business owner. `CAPACITY_AUTOMATIC_TAX=true` enables Stripe Tax on new capacity Checkouts and retains it in schedules; it requires the Stripe Tax/account setup. This local run used `false`, so tax collection is **not certified**. Client prices disclose “before tax.”
9. Once the environment checks pass, set `CAPACITY_BILLING_ENABLED=true`, then rebuild config and restart workers:

```sh
php artisan config:cache
php artisan queue:restart
```

The normal scheduler must run every minute, for example with the appropriate PHP/application paths:

```cron
* * * * * cd /path/to/barber_app && php artisan schedule:run >> /dev/null 2>&1
```

Existing jobs handle lifecycle, notices, failed-event replay and Checkout recovery. Check worker/scheduler health and failed provider events. A browser success URL is never payment evidence.

## Forge scheduler and delivery verification

In the site's Overview application panel, enable **Laravel Scheduler**. Forge
creates the once-per-minute scheduler using the site's PHP version. This replaces
manually adding the example cron line above; keep only one scheduler per app.
Alternatively use Site → Processes → Scheduler → Add scheduled job, with the
site user, every-minute frequency and the actual active application's Artisan
path. Do not put the five leading cron fields in Forge's command field.

From Site → Commands, `php artisan schedule:list` should list:

- `billing:advance-lifecycle`: every five minutes;
- `billing:reconcile-provider-events`: every five minutes;
- `billing:reconcile-stripe-checkouts`: every five minutes;
- `billing:send-notices`: every minute.

The cron entry is not a shell command to run once and is not an environment value.
The scheduler runs trial lifecycle, notices and recovery. This app processes
signed subscription events immediately in the webhook HTTP request, journals
them idempotently and returns 204 only after processing succeeds. The scheduler
replays retained failed/pending verified events; it does not receive Stripe events.

If production uses an asynchronous `QUEUE_CONNECTION`, use the site's Queues
panel to run workers for that existing connection and queues `default,communications`.
Billing notices use the default queue; operational messages use communications.
With the current `sync` fallback, execution is synchronous and a queue worker is
not used. Do not switch to a queue backend without provisioning its storage.
Restart persistent workers after code/environment changes; `queue:restart` does
not create a missing worker.

After the endpoint command, privately obtain the **same live billing endpoint's**
`whsec_` value and save it as `STRIPE_WEBHOOK_SECRET` in Forge Environment. A local
Stripe CLI secret or an appointment-payment endpoint secret is different. Rebuild
configuration with `php artisan config:cache`, then restart persistent workers.

In Stripe live mode, open Workbench → Webhooks → the billing endpoint → Event
deliveries. A matching app-owned event must return 204 and project the expected
business subscription/invoice. Generic sample or unrelated-account events may
be ignored, so a success response alone is not financial projection evidence.
Resend the same event and confirm that records are not duplicated. Use the
sandbox deployment first, then an owner-authorized live payment smoke test.

An unsigned `{}` POST to the deployed `https://clipperdesk.com/billing/webhooks/stripe`
returned **HTTP 400** on 2026-10-12. This establishes public route reachability
and unsigned-request rejection, not the correctness of the live endpoint signing
secret, signed delivery, prices, scheduler, workers or payment projection.
No production environment or Forge configuration was changed by that check.

Sources: [Forge Laravel integration](https://laravel.com/forge/docs/sites/laravel),
[Forge scheduler](https://laravel.com/forge/docs/resources/scheduler),
[Forge queues](https://laravel.com/forge/docs/sites/queues),
[Stripe event delivery diagnostics](https://docs.stripe.com/webhooks#view-event-deliveries).

## Forge setup troubleshooting

If `billing:verify-capacity-rates US --provision --force` prints the rate preview
then refuses installation, check the loaded approval and server credential.
`--force` permits live catalog provisioning; it does not override commercial
approval or missing credentials. The command now identifies each missing
prerequisite without printing key values.

In Forge's Environment editor, set `CAPACITY_US_APPROVED=true` for the USD terms
approved in ADR-056 and configure `STRIPE_SECRET` with the intended account's
server-side key. `STRIPE_KEY` alone is the publishable credential and does not
satisfy this command. Keep `CAPACITY_BILLING_ENABLED=false` during setup.
After saving the environment, run from the active application release:

```sh
php artisan config:clear
php artisan billing:verify-capacity-rates US --provision --force
php artisan config:cache
php artisan queue:restart
```

The earlier generic “Approve the country rates and configure Stripe” message
means approval was false or the secret was absent in loaded configuration.
Cached configuration can retain either old value after an environment edit.

The `PDO::MYSQL_ATTR_SSL_CA` deprecation warning is independent of that refusal.
The database config uses `Pdo\Mysql::ATTR_SSL_CA` on PHP 8.4 and newer, preserving
the legacy constant on older PHP versions. Deploy this source fix before
rebuilding config; the existing `MYSQL_ATTR_SSL_CA` environment name and TLS
certificate setting are preserved. See the
[PHP driver documentation](https://www.php.net/manual/en/ref.pdo-mysql.php).
Then continue webhook setup and environment verification above before enabling
billing. These recovery steps do not certify a live deployment.

## Release verification

Before real customers pay, complete sandbox cases on the deployed environment: paid conversion, decline/3DS, duplicated/out-of-order signed events, pending upgrade payment/expiry, prorated increase, renewal reduction/yearly switch, cancellation/reactivation, invoice and portal recovery, and simultaneous requests on production MySQL. Check the same business's normalized records and entitlement limits against Stripe. Complete the approved live smoke test with the owner handling the actual payment. No real payment, refund or customer message was performed by this task.

Local evidence: six test prices verified, sandbox Checkout visibly opened with $56/month for 1 location + 4 staff, tenant/currency and recovery regressions passed. This does not certify live credentials, deployed webhook reachability, tax collection, MySQL parallel billing races, real SMS delivery or credit reversals. Paid SMS packs stay off until separate pricing/refund/dispute certification.

## Reference documentation

- [Larafast multi-tenancy Stripe setup](https://docs.larafast.com/multi-tenancy/pricing/stripe-setup)
- [Larafast base Stripe integration](https://docs.larafast.com/features/payments/stripe)
- [Stripe endpoint API](https://docs.stripe.com/api/webhook_endpoints/create)
- [Official Stripe CLI](https://docs.stripe.com/cli)

These references explain the provider primitives; the repository's Business ownership, route and event contracts above are authoritative for this app.

## Public pricing and signup preferences (2026-10-12)

The public calculator uses these same verified rates; it needs no separate Stripe
products or webhook. Deploy `2026_10_12_000001_save_capacity_signup_preferences`
with the usual `php artisan migrate --force` before serving the updated signup
and billing code. It adds two nullable quantity preferences and leaves old
intent/subscription/invoice values unchanged. See the
[public pricing contract](../modules/public-pricing.md).


## Progressive pricing upgrade (2026-10-12)

Revision `2026-10-12-progressive-1` requires new immutable Stripe Prices. Do not
edit the old staff Price or swap customers directly in Stripe Dashboard.

1. Deploy source/builds with the existing capacity billing gates. Preserve old
   rate cards, provider price IDs and snapshots. No new database migration is
   required for this pricing revision; existing signup migrations still apply.
2. Check the intended Stripe account/mode privately. Clear any six explicit
   `CAPACITY_US_{MONTHLY,ANNUAL}_{BASE,LOCATION,STAFF}_PRICE_ID` values pointing at
   the old revision. They must either be absent for managed provisioning or map
   to **new** prices for every component, including the unchanged base amounts.
3. Clear cached config. Preview with `php artisan billing:verify-capacity-rates US`.
   Provision/test with `php artisan billing:verify-capacity-rates US --provision`.
   Live provisioning requires the deliberately selected live key and `--force`.
   The command creates catalog objects only; it never charges/migrates customers.
4. Verify two new base Prices (USD 29/month, 290/year), two location Prices
   (USD 19/month, 190/year) and two staff Prices. Staff uses `billing_scheme=tiered`,
   `tiers_mode=graduated`, licensed recurring quantities and tax exclusive.
   Cumulative **additional-staff** tiers are 4/9/infinity, with 900/600/400 cents
   monthly and 9000/6000/4000 cents annually. Do not use volume pricing. Stripe
   Dashboard manual creation must use those exact fields; then configure all six
   new IDs and run `--apply` for expanded-tier verification.
5. Run sandbox checkout, fixed-date proration across 5/6 and 10/11 staff, annual
   renewal, failed-payment/pending update, cancellation, and signed-event replay.
   Verify invoice subtotals against the documented examples and tax separately.
   Confirm the existing webhook event set and scheduler/worker setup above.
6. Rebuild config/restart workers. New public prices become visible only after
   the immutable local mappings pass verification and publication gates. A source
   deploy alone intentionally leaves pricing unavailable when mappings are absent.

Existing-customer migration: owners retain old flat rates and monthly allowances.
In Plan, explicitly select “Review a switch to current pricing at renewal,”
review the server quote and confirm the dated change. Current resource-fit,
currency, outstanding-debt, discount, schedule, expiry and version checks still
apply. Migration creates the existing two-phase schedule with no proration today;
signed renewal evidence switches the snapshot/entitlements. If a provider call
is uncertain, reconcile the existing request before retrying. Never bulk-update
rate-card foreign keys or stored snapshots. Keep old Prices active while retained
customers use them. Reverting source does not reverse already-confirmed schedules;
resolve those through the reviewed support/provider recovery procedure.

This task verified mocked SDK contracts and isolated local UI fixtures. It did
not create real Stripe Prices, change a deployed catalog, charge a customer or
certify tax/live provider behavior. Complete the sandbox steps before production.

## Abandoned checkout and credential recovery (2026-10-15)

Deploy the source and rebuilt client/SSR assets together. This repair needs no
schema migration or Stripe price change. It does not migrate or replace paid
subscriptions. Retain all attempt/customer/subscription IDs and payment history.

1. Keep test/sandbox and live work on separate application databases. If keys
   were changed on a database with unfinished live checkout, restore the original
   Stripe account's live secret/publishable keys, subscription webhook signing
   secret and verified rate-card mappings together. Do not provision another
   account's prices over these records to clear an error. Check values privately;
   never copy credentials into logs, tickets or commands.
2. Rebuild cached configuration with `php artisan config:cache`, restart queue
   workers with `php artisan queue:restart`, and reload any persistent application
   server/SSR process through the deployment's normal process manager. Editing
   `.env` alone does not refresh cached configuration. For uncached local work,
   use `php artisan config:clear` instead. Verify scheduler/webhook health under
   the restored configuration.
3. In Subscription & billing → Plan, use Check billing status. Continue secure
   checkout verifies the stored original session before redirecting. To change
   quantities, edit the estimate and choose Start again. Stripe must confirm
   expiration of an open session before Review subscription becomes available.
   A completed payment updates the existing subscription instead.
4. An outage, missing object under another account/mode, or ambiguous creation
   remains pending with an actionable error. Restore the original environment
   and retry. Never delete local attempts, clear customer IDs or mark a request
   unpaid merely to unlock the UI: the original URL could still accept payment.
5. If a create response was lost, recovery reads the original customer and its
   sessions using the attempt metadata. An incomplete session list is not proof
   of absence. With no saved customer, only the original credential fingerprint
   and original idempotency keys within 23 hours can be replayed. Older or
   ambiguous cases require support to verify the original Stripe request/event
   history; do not manually reopen payment without proving the old session is
   no longer payable and checking for a completed subscription/invoice.
6. Manage billing uses the existing restricted portal configuration. It opens
   normally when that Stripe account/customer/configuration is available; an
   error is displayed within billing. Verify portal permissions/configuration in
   the same account, then retry. No paid customer is silently recreated.

Verify sandbox abandonment, restored configuration, expiration, continuation,
completion races, portal success/failure and duplicate webhook delivery before
release. Real live/test account certification and production MySQL concurrency
remain deployment checks. Local synthetic browser verification and mocked SDK
transport tests are recorded in the [recovery audit](../audits/2026-10-15-billing-recovery/README.md).

Provider contracts: [expire Checkout](https://docs.stripe.com/api/checkout/sessions/expire)
only closes open sessions; [Stripe API keys](https://docs.stripe.com/keys) isolate
live and sandbox objects. These constraints are why an API failure cannot safely
be treated as cancellation.

## Customer recovery refinement (2026-10-15)

The earlier recovery guard still blocked a stale test Checkout after returning to
live credentials; unprefixed numeric throttles also shared the same user counter.
Deploy the ADR-069 source and rebuilt assets together, rebuild cached routes and
configuration, and restart persistent processes/workers using the deployment's
normal procedure. This refinement needs no database migration or new Stripe Prices.

First inspect the affected business on the deployed server (read only):

```sh
php artisan billing:inspect-checkout BUSINESS_PUBLIC_ID
```

The report prints configured mode, saved checkout mode, status/reference,
configuration-cache state and whether the configured secret equals the private
`.env` secret. It never prints credentials, hashes, Stripe customer/session IDs or
customer contact details. `unknown` or null file comparison means a managed secret
source may be authoritative; it is not evidence that no secret exists.

- `checkout_mode=test`, `configured_mode=live`: an unconverted trial with no paid
  records can recover via Check billing status or Start again. The test attempt
  is retained as superseded, its former customer reference is stored privately,
  and the customer can review/confirm a fresh selection. No remote test session
  expiry is claimed. New Checkout uses a new live customer binding.
- `checkout_mode=live`, `configured_mode=test`: restore the **original** intended
  live account's deployment configuration. Customers cannot repair this. If the
  `.env` file is live but configured mode is test, investigate cache/process or
  managed-environment overrides. After verifying the intended source, rebuild
  with `php artisan config:cache`, refresh the deployment's route cache, restart
  workers (`php artisan queue:restart`) and persistent servers, then check/close
  the original Checkout. Never clear live attempt/customer IDs to unlock payment.
- Same-mode missing object: confirm the original Stripe account/sandbox and
  request history. A key prefix alone does not identify the account. Use original
  provider evidence before reopening a potentially payable purchase.
- Paid history, uncertain creation or another unresolved attempt: the safe guard
  remains. Use the original-account verification procedure above. No mass reset,
  credential guessing, silent paid migration or automatic duplicate payment.

Keep BRAND_SUPPORT_EMAIL pointed at the monitored support inbox. The default is
support@clipperdesk.com, consistent with the existing operator contact. Contact
support opens a draft with the safe checkout reference; it does not automatically
send mail or create a ticket. Operators correlate that reference with the
redacted recovery log and the retained business/change records. Inbox delivery
and response handling must be verified by operations, not inferred from the UI.

Capacity status (30/min), restart (5/min), review (10/min) and confirm (5/min)
have independent named limits. A 429 returns Retry-After plus customer-safe JSON.
The client checks once on entry, avoids repeat polling and displays a countdown
before enabling retry. These changes retain abuse protection. Repeated page
reloads are not an operator recovery procedure.

[Evidence and limitations](../audits/2026-10-15-customer-billing/README.md).
