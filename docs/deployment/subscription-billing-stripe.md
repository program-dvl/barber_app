# Stripe subscription billing setup

Updated 2026-10-12. Applies to FR-01 and ADR-029/054/056. Run from the application root with the PHP version required by composer.lock. Existing subscriptions and invoices are preserved. Commands create catalog/delivery configuration, never charges or subscriptions.

## Correct route and credentials

This app uses **`/billing/webhooks/stripe`** for Business-owned SaaS subscriptions. Appointment payments use a separate `/payments/webhooks/stripe` route and signing secret. Do not substitute Larafast's example `/stripe/webhook` or run `cashier:webhook`: Cashier routes/listeners are intentionally disabled under ADR-029. The installed Stripe SDK and existing checkout, portal, signature verification, event journal and reconcilers are reused.

Use a dedicated environment/database for sandbox work. Configure `STRIPE_KEY=pk_test_…` and `STRIPE_SECRET=sk_test_…` locally/staging. Live deployments need that account's live keys. Never paste keys into tickets, source control or shell history. The publishable key is public; secret keys and `whsec_` values are private. Keep the existing APP_KEY when deploying.

Approved software pricing is USD, independent of the business's operating country/currency:

| Component | Monthly | Yearly, paid upfront |
| --- | --- | --- |
| Base: 1 location + 1 bookable staff | $29 | $290 |
| Each extra location | $19 | $190 |
| Each extra bookable staff | $9 | $90 |

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
