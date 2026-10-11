# Billing redesign and Stripe foundation audit

Date: 2026-10-11. Scope: FR-01, FR-13, PRD §9, ADR-029/054/056.

## Sources reviewed and reuse decision

Reviewed the [complete Larafast documentation index](https://docs.larafast.com/llms.txt), then the full relevant chapters: [multi-tenancy subscription overview](https://docs.larafast.com/multi-tenancy/pricing/overview), [Stripe setup including webhooks and live transition](https://docs.larafast.com/multi-tenancy/pricing/stripe-setup), [plan configuration](https://docs.larafast.com/multi-tenancy/pricing/plans-configuration), [base Stripe integration including catalog commands, portal and webhooks](https://docs.larafast.com/features/payments/stripe), [tenant-scoped tables](https://docs.larafast.com/multi-tenancy/database/tenant-scoped-tables), [user model tenancy](https://docs.larafast.com/multi-tenancy/configuration/user-model), [environment management](https://docs.larafast.com/multi-tenancy/settings/environment-management), and [production deployment](https://docs.larafast.com/deploy/deploy-to-prod). The index distinguishes VILT/TALL, Filament Multi-Tenancy and Fast Projects; these are different variants, not interchangeable files in this repository.

Larafast documents Cashier, team subscriptions, provider keys, hosted checkout/portal, catalog provisioning and signing secrets. Its example `/stripe/webhook` and Team/User billable ownership differ from this app's Business billing contract. Source audit confirmed Laravel Cashier v16.7.0 and stripe-php v20.3.1 in composer.lock, existing StripeSubscriptionProvider, webhook signature verification, provider-event journal, tenant-scoped invoice projections and recovery jobs. Cashier::ignoreRoutes(), disabled legacy event discovery/listener and the Business subscription projection are intentional under ADR-029. Reenabling the boilerplate listener would create a second, incompatible billing authority.

Reused those installed provider primitives and domain services. Added narrowly scoped six-component provisioning with immutable verified mapping, and a managed webhook command for the actual route. Existing legacy catalog wrappers remain unchanged. No second provider SDK, listener or ledger was installed. Appointment payment setup is separate.

UI reference study: [Slack billing](https://slack.com/help/articles/218915087-Manage-your-Slack-plan-and-billing-details), [Notion billing](https://www.notion.com/help/billing), and [Linear billing and plans](https://linear.app/docs/billing-and-plans). These organize plan, payment and invoice tasks separately. Our design adapts that organization to salon locations/bookable staff and monthly texts, rather than copying seat-only semantics or unrelated product features. Overview focuses on status/end or renewal date, total and usage; billing details have a single portal action; detailed pricing and invoices occupy separate keyboard-accessible tabs. No draft country selector, duplicate tiers for new businesses, empty invoice sidebar or ordinary rollout explanation remains.

Provider references checked: [price creation](https://docs.stripe.com/api/prices/create), [endpoint creation](https://docs.stripe.com/api/webhook_endpoints/create), [endpoint listing](https://docs.stripe.com/api/webhook_endpoints/list), and [CLI forwarding](https://docs.stripe.com/cli). Events come from the actual processor dispatch contract; endpoints pin the installed SDK API version and do not subscribe to all events.

## Verified implementation

- Owner-approved USD 29/19/9 monthly and 290/190/90 yearly, with no sales/operating currency conversion. Canadian trial review succeeds with USD; unapproved market injection and existing CAD paid migration are rejected.
- Only exact verified catalog fingerprints are publicly presented. Provisioning resolves objects in the current Stripe account/mode, reuses managed products/lookup keys, and verifies all six amounts, recurring cadence, quantity model and exclusive tax behavior before one local transaction. Price mismatches install no mappings. Live mutation needs `--force`.
- Endpoint setup defaults to preview. It rejects insecure/wrong routes, foreign managed ownership and duplicate destinations. New secrets use a reserved 0600 private recovery file and are never printed. Managed repeats preserve secrets. Local setup prints the correct CLI route/events.
- Preserved signed event processing, stale-state protection, provider-authorized read recovery, actor/version/expiry review, tenant isolation and financial histories. Pending checkout return now exposes its exact existing URL and retains the reviewed quantities. Authenticated provider expiry releases its capacity request; URL cancellation alone does not.
- Scoped Pint and whitespace checks passed. Backend: **598 passed, 30 skipped, 5,724 assertions**. Billing subset: **96 passed, 417 assertions**. Frontend helpers: **75 passed**. Node 24 client and SSR builds passed; existing DaisyUI `@property` and frontsite font-resolution warnings remain. No dependency changes were needed.
- The three old time-sensitive billing failures were fixed by making billing test fixtures use a second-aligned current time plus five minutes, after migration-created entitlement versions become effective. This also avoids fixture price uniqueness collisions and precision mismatches; production entitlement timestamps were not rewritten.

## Provider/browser evidence

The configured local Stripe account is **test mode**. Initial read found four legacy Starter/Pro prices, no capacity mappings and no remote webhook endpoints. Six approved test capacity prices were then provisioned and verified. The application database received two additive immutable rate mappings; no existing subscription/invoice/customer was changed by catalog setup. Local rollout switches were enabled and the previous .env saved privately in the ignored work folder.

Browser verification used an isolated SQLite database and synthetic owner/business (Canada/CAD). The real existing provider opened Stripe sandbox Checkout at **USD 56/month**, with the USD 29 base and three USD 9 staff units. No card was entered and no payment was submitted. The unused session was explicitly expired through the provider; the local attempt/request became expired and the subscription stayed trialing. The synthetic Stripe customer remains test data.

Desktop trial and retained paid views, month/year selection, input totals, review, invoices, keyboard tab movement, pending resume and expiry were inspected. At 390 px, document width matched viewport (no horizontal overflow); controls and pricing stack vertically. Screenshot evidence uses synthetic preview data: [overview](trial-overview.jpg), [plan](subscription-plan.jpg), [mobile](mobile-plan.jpg). The existing paid workspace was viewed read-only; its cancel/change/payment actions were not submitted. The isolated preview initially shared the file permission cache; it was cleared after the preview stopped, and existing owner billing access was reverified. Future disposable environments must isolate CACHE_STORE/CACHE_DRIVER as well as database and session cookies.

## Remaining deployment facts

No live endpoint, live key, real charge, tax calculation, Stripe CLI listener, SMS/email send, refund/dispute reversal or production MySQL parallel billing test was certified here. Those require the deployed environment, as described in [the executable setup guide](../../deployment/subscription-billing-stripe.md). Existing MySQL public-booking tests do not establish billing concurrency. Paid SMS packs remain disabled and unpriced. Other countries and SMS cost approval remain in OPEN-22; USD software prices are resolved by ADR-056.

## Prelaunch follow-up: remove retained tier choices (ADR-057)

The owner confirmed that there are no live customers requiring the Starter/Pro
selection. Removed that disclosure, cards, feature comparison, tier review dialog,
unused Vue handlers and server offer query. Existing checkout GET links redirect
to Plan; tier purchase/change POSTs return 410 after authorization and cannot
create provider sessions. The capacity path remains tested, including provider
confirmation, recovery, proration and scheduled renewal.

Replaced obsolete tier purchase HTTP tests with four cadence/tier rejection cases,
stale-link redirect, cross-tenant/staff denial and retained subscription/invoice
protection. Existing webhook/lifecycle/reconciliation domain regressions remain.
Billing tests passed **95 tests / 422 assertions**; scoped formatting, whitespace,
and Node 24 client/SSR builds passed (existing CSS/font warnings remain).

An isolated synthetic trial loaded the rebuilt Plan tab with the single USD
location/bookable-staff calculator, monthly/yearly choices and a USD 29 base.
No Existing plan options, Starter/Pro offers or comparison were present.
[Capacity-only page](capacity-only-plan.jpg). The preview used array cache and a
separate session cookie/database; no actual payment or application data reset
was needed. Existing deployment certification gates are unchanged.
