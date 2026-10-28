# Billing payment recovery review — 2026-10-25

Requirements: FR-01, FR-19, FR-20. Decisions: ADR-068/069/071.

## Reported production evidence

The owner supplied the affected billing URL and command output. The saved change
was submitted, its attempt pending, both configured and saved modes were test,
configuration was cached and matched the environment file. A separate read-only
provider probe returned `session_saved: false`. This establishes an unfinished
local preparation, not proof that Stripe never received a request. It does not
identify which customer/account/price lookup originally failed. No production
records or Stripe credentials were changed during this review.

The old inspector expected a Business ID while the UI supplied a checkout Support
reference. It now accepts either, preserves exact historical reference identity,
and provides actionable not-found output. Optional `--provider` reads customer
ownership and both cadence price mappings without exposing provider identifiers,
keys, hashes or raw API errors. It never creates catalog objects or payments.

## Changes and local evidence

| Journey | Evidence |
| --- | --- |
| Progressive pricing, expected examples, monthly/annual amounts, tier boundaries, validation and existing subscriptions | Existing pricing and billing tests remain passing. Prices and immutable historical terms were not changed by this recovery patch. |
| Stale same-mode test preparation, expired preparation and changed test credentials | Four monthly/annual SDK transport cases restart locally, retain test evidence, require a new review and create one new checkout. A repeat confirmation does not create another session. |
| Paid/history protection and live unknown outcomes | Paid subscription, invoice, capacity, saved card and other pending attempt exclusions cover both saved sessions and unsaved preparations. Live preparation listing/mode checks still require authoritative evidence. |
| First card decline → retry → payment success | Monthly/annual cases keep the same attempt/session, apply purchased quantities, retain one paid invoice and tolerate duplicate/late failure delivery. |
| Legacy prematurely failed attempt | Owned open/completed/expired session reads repair only the exact still-submitted request; no new provider write. |
| Initial subscription permanently unpaid and expired | Monthly/annual terminal-state tests release only the unconverted binding. Paid invoice/status, different subscription and newer snapshot guards preserve financial history. |
| Completed checkout with payment still due | Only the same open invoice with matching customer, subscription, currency and quoted subtotal exposes an approved HTTPS URL. Foreign ownership, wrong amount and unsafe host are rejected. |
| Delayed-method failure | Keeps the existing purchase pending; no duplicate new subscription is permitted. |
| Paid expansion/renewal update timeout | Four monthly/annual cases resume the original confirmed operation under identical keys and parameters, including a schedule created before phase update failure. Existing capacity stays until applied/renewal. |
| Unsafe replay | Old requests, changed renewal dates and existing pending payment do not produce another POST; payment due returns the Invoices recovery action. |
| Real clock / successful payment still pending | Browser testing reproduced active access with missing capacity. Fractional observation timestamps did not equal stored whole seconds. Normalize API timestamps, apply activation/capacity atomically, allow current same-second reads, and reject stale equal-second events. Monthly/annual regression tests cover this. |
| Portal failures, authorization, tenant isolation, renewal/cancellation/proration and trial transitions | Existing billing workspace/lifecycle/Stripe SDK suites passed. These were fixture checks, not live Stripe certification. |

## Browser journey and wording review

An isolated SQLite database, synthetic owner/business and local fake Stripe
transport were used. No real payment form, production customer or message was
submitted. Actual Vue/Inertia routes were exercised at desktop width 1512px and
mobile width 390px:

1. Recreated the unsaved stale test preparation and its recovery error.
2. Confirmed Start again and Check again were available without a pointless wait.
   At 390px controls were 44px tall, had 10px gaps and no horizontal overflow.
3. Edited annual 5 locations / 10 staff to monthly 3 / 9 before restarting.
   Restart unlocked Review subscription and preserved the edits and $127 estimate.
4. Reviewed annual 3 / 9 at $1,270 upfront, then opened the local provider fixture.
5. Returned unpaid, continued the same checkout, used browser Back, restarted and
   selected monthly again without losing quantities or duplicating the request.
6. Simulated monthly success; initially reproduced active access with stuck
   purchased quantities. After the precision fix, Check billing status cleared
   the pending request and applied the correct 3 / 9 subscription.

Screenshots here capture these steps **before the final wording cleanup**; they
are historical failure/recovery evidence, not current copy approval. The owner's
subsequent feedback led to a copy review across public pricing, billing overview,
calculator, review dialogs, recovery help and the legacy checkout page:

- First payment: **Your subscription is active**.
- Paid change: **Your subscription has been updated**.
- Scheduled change: **Your updated subscription starts on [date]**.
- Credit purchase: **Your text credits have been added**, or explicitly pending.
- Removed internal capacity/provider-verification language, repeated reassurance,
  and unnecessary implementation details; preserved price, tax, date, trial,
  allowance, region, credit-consent and payment processing limitations.
- Shared AppNotice also replaces touched legacy checkout/invoice recovery blocks.
- AGENTS.md and the design system require every visible sentence to serve a
  non-technical customer's understanding, decision or next action on future tasks.

Browser access was then disabled in Work settings. Final copy states and the new
Open invoices notice could not receive another visual pass; automated checks and
client/SSR builds passed. Forge origin access had previously been declined, so no
deployment or production mutations were attempted through another surface.

## Verification

- Full backend after lifecycle fixes: **866 passed / 9,219 assertions**, 30 existing
  skips. [Output](backend-tests.txt).
- Focused billing: **220 passed / 1,220 assertions**. [Output](billing-tests.txt).
- After wording changes: billing, Marketing and Help Center **373 passed / 4,909
  assertions**. [Output](copy-review-tests.txt).
- Frontend **106 passed**. [Output](frontend-tests.txt).
- Production client/SSR builds passed. [Output](build.txt).
- Scoped Pint and whitespace checks passed. All 80 published help articles across
  14 categories validated. [Formatting](formatting.txt), [help](help-validation.txt).

## Deployment and remaining verification

No database migration or automatic paid-customer repricing is introduced. Deploy
code and built assets normally, rebuild the configured application/route caches,
and restart persistent workers. Run the enhanced read-only inspector in the
intended Stripe account; local readiness alone cannot prove that its prices and
old customer belong to that account. Use existing catalog verification/provisioning
commands only as described in the [runbook](../../deployment/subscription-billing-stripe.md#payment-recovery-follow-up-2026-10-25).

The affected owner can then use Start again, review the retained selection and
confirm payment. The deployed monthly/annual/card-authentication/decline/retry,
invoice and renewal flows still require actual sandbox checks with the intended
Stripe keys/webhooks. A live payment and deployment were not verified here.
An unknown live payment, a cached provider failure or an original key older than
23 hours still needs operator evidence; do not bypass it by resetting paid rows
or generating a second payment key. No claim of 100% production certification.

## Subsequent checkout creation follow-up

The owner's deployed inspection subsequently confirmed all six subscription
prices were missing from the current test account despite a valid owned customer.
The owner ran the existing provisioning command successfully; it verified and
installed current monthly/annual mappings while preserving subscriptions. The
owner then confirmed that a fresh restart/review opens the Stripe payment page.
Payment completion remains unverified. Local diagnostic and setup
hardening plus monthly/annual catalog-repair tests are documented in the
[follow-up](checkout-creation-follow-up.md), with **874 full backend tests passing**.
