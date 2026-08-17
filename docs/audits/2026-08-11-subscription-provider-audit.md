# Subscription provider and billing foundation audit

Date: 2026-08-11  
Scope: FR-01 SaaS subscription billing only. Appointment deposits, sale checkout,
refunds, and payment processing remain outside this audit.

## Evidence inspected

- `composer.json` and `composer.lock` install Laravel Cashier 16.5.3 and the
  Stripe PHP SDK, plus a development-main Lemon Squeezy package. No Paddle PHP
  package is installed.
- The legacy `User` model used Cashier `Billable`; Stripe checkout, portal,
  product, price, order, event-listener, and Filament resources existed.
- Lemon Squeezy customer, subscription, order, license, controller, package
  webhook, listener, views, factory, seeder, and Filament resources existed in
  parallel.
- Paddle checkout/swap/cancel routes and a controller existed without the
  matching package.
- Cashier's retained `subscriptions` table is User-owned. Lemon Squeezy uses a
  polymorphic billable. Neither represents the accepted Business-owned billing
  aggregate in ADR-006/ADR-007.
- The generic invoice implementation is User-owned and manually generated. It
  is retained as legacy boilerplate and is not SaaS invoice evidence.

## Provider decision

Stripe is the launch SaaS subscription provider. It has the strongest local
foundation, a locked supported SDK through Cashier, hosted Checkout and billing
portal support, subscription schedules for period-end changes, promotion-code
support, invoice/payment webhooks, and local signature-verification code.

The application does not adopt Cashier's User-owned subscription records as its
domain model. Stripe is behind `SubscriptionProvider`; provider IDs and payload
evidence terminate at the adapter and event processor. Business-owned normalized
records remain authoritative for product behavior.

## Competing path disposition

| Surface | Disposition | Reason |
| --- | --- | --- |
| Legacy authenticated Stripe checkout/product routes | Disabled | User-owned and bypass the normalized Business contract |
| Cashier package webhook route | Disabled | Would write the legacy User-owned subscription schema |
| User `Billable` trait | Removed from runtime | Prevents accidental use of User as the SaaS customer |
| Lemon Squeezy application/package routes and listener | Disabled | Competing production ownership and webhook path |
| Paddle routes | Removed | Package absent and route behavior was unapproved/broken |
| Legacy tables, models, resources, commands, packages | Retained but unapproved | Destructive cleanup/backfill needs separate data evidence; no unknown data was deleted |

## Verification boundary

Automated evidence exercises the installed Stripe SDK's signature verifier,
normalized event contract, duplicate/out-of-order delivery, invoice/payment
mapping, retries, and replay. No sandbox credentials are present, so no claim is
made for a live Stripe Checkout or test-clock run. Before paid launch,
Operations must configure test products/prices, webhook secret, customer portal,
promotion codes, retry rules, email delivery, tax/receipt settings, and run an
end-to-end Stripe sandbox/test-clock reconciliation.
