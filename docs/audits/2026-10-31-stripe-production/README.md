# Stripe production subscription audit — 2026-10-31

Scope: FR-01 monthly/annual checkout, automatic renewal, failed-payment recovery,
invoices, capacity changes, cancellation, webhook delivery and live configuration.
This is not certification of a real charged renewal or tax compliance.

## Repository verification

- Final full backend: **1,019 passed / 10,732 assertions / 30 existing skips**.
- Billing suite before adding the readiness command: **279 passed / 2,034 assertions**.
  Five command cases also pass, including GET-only requests, secret-free error
  output, incompatible API versions, missing renewal events and test prices.
- Existing coverage includes monthly/annual initial payments, failed first
  invoices, lost responses, paid browser returns, fractional observation times,
  reversed webhook order, duplicates, foreign tenant/customer/session rejection,
  graduated staff pricing, renewal schedules, unpaid expansion, dunning/recovery
  and retained immutable commercial history. Tests use isolated data/fake providers.
- Reproduced and fixed active/deleted events sharing Stripe's timestamp second
  in either arrival order. Cancellation wins that tie; duplicate terminal events
  and later active snapshots cannot reopen that ID.
- Reproduced and fixed a later failed/paid outstanding-invoice pair reopening
  an already terminated subscription. Invoice/payment evidence remains saved;
  terminal access and version remain unchanged.
- Live portal setup reproduced `parameter_unknown: active`: configuration
  creation rejects `active`, while updates accept it. Removed that parameter
  only from creation, with SDK-level regression cases for first creation,
  configuration update and reuse without duplicates.
- Added `billing:check-production [--provider-read] [--webhook-url=...]`.
  It inspects settings, declared schedules, event journal, live account,
  endpoint events/API compatibility and six mapped prices. It never charges,
  replays events, sends notices or installs mappings. A pass does not establish
  scheduler liveness, signing-secret match, tax posture or a charged renewal.

## Initial deployed observations

Read through logged-in Chrome/Forge. No credential values are retained here.

- Forge displayed revision `1257ad1`, PHP 8.5, SDK 20.3.1,
  API `2026-06-24.dahlia`, production environment, debug off and cached config.
- Installed minute scheduler points to the active release's Artisan path.
  Queue is `sync`; that mode does not need an asynchronous worker.
- Effective billing keys were **test mode**, authenticating to
  `acct_1U7mzx2MWgVhuli0`. Intended live account is `acct_1U7mzlRyELnyE93k`.
  Initial current monthly/annual cards contained test-account price IDs.
- Journal had 16 ignored / one processed Stripe event, 17 with `livemode=true`,
  zero pending/failed. This alone does not establish current account alignment.
- `APP_URL` used www. Unsigned apex callback POST returned 400; www returned
  301 to apex. The callback must use the apex directly.
- Live own-account snapshot endpoint `we_1UPZPhRyELnyE93kvDVTO0Wl` was active
  at `https://clipperdesk.com/billing/webhooks/stripe`. No deliveries this week.
- Endpoint July API is a later additive Dahlia release, compatible with June
  SDK API. Replacing the endpoint or changing its API family is unnecessary.

## Authorized configuration work

- Corrected broad 251-event endpoint selection to the exact 18 handled billing
  events and verified the saved destination.
- Live catalogue has six active USD licensed recurring prices. Monthly
  base/location 2900/1900 cents; yearly 29000/19000. Expanded graduated staff
  tiers are 900/600/400 monthly, 9000/6000/4000 yearly, bounds 4/9/unlimited.
- User explicitly approved switching production to live and creating a dedicated
  restricted billing key stored only in Forge. Completed Stripe owner verification.
  No bank-transfer, payout or refund access was granted.
- Saved live publishable/server keys and the live endpoint signing secret in
  Forge, corrected canonical APP_URL, and paused new capacity checkout during
  mapping installation. Forge rebuilds cached configuration on environment save.
- Verified and installed the six existing live price mappings using temporary
  command configuration overrides and `billing:verify-capacity-rates --apply`.
  Exit result 0: both verified cards installed; existing subscriptions preserved.
  No new Stripe products/prices, customers, subscriptions or charges created.
- Stripe card Smart Retries already enabled: eight attempts within two weeks;
  first renewal failure stays overdue, exhaustion cancels, invoice stays overdue.
  App access restriction still follows its separate configured grace period.
- Effective read-back confirmed live key prefixes, canonical apex APP_URL and
  a SHA-256 comparison matching the live destination's signing secret. Endpoint
  read returned 200 with the intended destination and required events.
  The billing-only key cannot GET account details (403); the live Dashboard
  Account status confirms Payments/Payouts active and no outstanding tasks.
- Two existing locally active subscription IDs returned 404 under the live
  credential. Those records were preserved; test setup cannot silently become
  a real paid subscription. Their account/financial history needs explicit
  review before any conversion or fresh charge.
- Created live app-managed portal `bpc_1UWciiRyELnyE93khrXcL65W` using the supported
  configuration create request. Payment/contact/invoice features enabled;
  subscription cancellation/update disabled so reviewed app flows remain the
  authority. Both public terms/privacy URLs returned HTTP 200 and were included.
- Temporarily selected `billing_portal.configuration.created` while creating
  this required portal. Stripe delivery `evt_1UWciiRyELnyE93khNajp6Bh` returned
  **HTTP 204, Delivered** on 2026-10-31. It is transport/signature evidence only;
  the app intentionally ignores portal configuration events for subscription
  projection. No customer or payment was created.

## Remaining evidence

- Cleanup is complete: removed `billing_portal.configuration.created`, saved
  the destination, and read back **18 events**, active, at the canonical apex.
  Saved `CAPACITY_BILLING_ENABLED=true`; Forge confirmed the environment update,
  and effective server read-back confirmed checkout enabled and cached config.
- The same effective read-back exposed a remaining catalog-selection problem:
  new live cards existed, but DB fallback still chose retained test cards 5/6.
  All six provider reads for those cards returned 404. Pinned the six previously
  expanded/verified live Prices using the existing `CAPACITY_US_*_PRICE_ID`
  environment options. No commercial terms/history were rewritten. Forge
  confirmed this environment update. Submitted a final `--apply` verification
  against the effective pinned configuration; the browser disconnected before
  its retained result could be read. That invocation remains unread; the
  owner-supplied production readiness result below subsequently verifies both
  effective rate cards and all six selected live prices.
- On **2026-11-01 Asia/Kolkata**, an independent unauthenticated GET of
  `https://clipperdesk.com/pricing` returned **200**. Its server-rendered Inertia
  catalogue reports `available=true`, monthly `ready=true` and annual
  `ready=true`, with the approved USD amounts and progressive staff bands.
  This verifies current public readiness and that checkout is no longer paused;
  it does not substitute for an actual payment.
- Direct SSH fallback was attempted read-only and rejected with public-key
  authentication failure. No server keys or authentication settings changed.
- Browser diagnostics on 2026-10-31 confirm Chrome is running, the ChatGPT
  extension is installed/enabled, and its native-host manifest/name/origins are
  valid. The user also supplied the Chrome permissions screenshot. The browser
  tool still rejects with `Local browser use is disabled in Work settings`
  before listing Chrome, including after resetting its session. Do not describe
  this as the user failing to install or enable the extension. Requested a
  desktop-app restart to refresh the conflicting connection state. Later Chrome
  discovery recovered; original tabs reported an unattached debugger. Opening
  fresh tabs restored access and allowed the final saves above. On the next
  continuation the browser gateway again rejected access before tab discovery.
- Verify an owner-authorized actual live payment and later renewal projection.
  Signed transport success does not certify a charged monthly/annual journey.
- Complete tax review and record any remaining launch limits. The managed live
  portal configuration was created and inspected as recorded above; its customer
  journey still needs an actual live subscription rehearsal.
- A final integration review must cover subscription charge refund, dispute and
  early-fraud-warning operations. The current billing dispatcher has no handlers
  for `charge.refunded`, `charge.dispute.created` or
  `radar.early_fraud_warning.created`. Existing lifecycle/transport test results
  do not certify those paths. Do not add unhandled events to the live destination
  or silently decide refund/access policy. Resolve ownership through Stripe's
  subscription/invoice graph and preserve financial/audit evidence when this
  recovery is implemented.
- On 2026-11-01 the owner explicitly chose **“Leave the fixes for me to deploy.”**
  The owner subsequently reported deployment; local commit `78fcc14` contains
  the reviewed fixes, command, tests and documentation. A fresh production
  revision could not be independently inspected because browser access again
  rejects the connection. Public pricing was rechecked
  after that report: HTTP 200, available, monthly/annual ready. No agent push or
  deployment was performed, and no customer charge/renewal was attempted.

## Owner-supplied post-deployment evidence — 2026-11-01

The owner supplied terminal output from the production `current` directory for
`php artisan billing:check-production --provider-read`. Every required check
passed: production settings, live keys, signing-secret presence, enabled
checkout, HTTPS callback, four declared billing schedules, zero pending/failed
billing events, recent signed-event journal evidence, exactly one matching
enabled endpoint, required events, both verified rate cards and all six live
prices. Endpoint API `2026-07-29.dahlia` passed compatibility with the expected
`2026-06-24.dahlia` release family. Queue connection remains `sync`.

The account-details REVIEW reflects the deliberately restricted key's inability
to read account details; Payments/Payouts activation was separately checked in
the intended live dashboard. The command's generic signing-secret reminder is
also covered by the separately observed signed **204 Delivered** event above.
No additional credential or endpoint correction is indicated by this output.
This closes the outstanding current configuration check; it does not establish
ongoing scheduler liveness or paid checkout, renewal, collection, cancellation,
refund/dispute/fraud or tax outcomes. No records or charges were changed by this
read-only command. Its output is owner-supplied evidence, not an independently
observed command run or deployed revision inspection.

Sources: [subscription webhooks](https://docs.stripe.com/billing/subscriptions/webhooks),
[event delivery](https://docs.stripe.com/webhooks),
[version compatibility](https://docs.stripe.com/webhooks/versioning),
[cancellation](https://docs.stripe.com/billing/subscriptions/cancel).
