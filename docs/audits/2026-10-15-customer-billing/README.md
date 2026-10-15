# Customer-first billing recovery and notification refinement

Date: 2026-10-15. Requirements FR-01/19/20; shared experience under FR-01–20.
[ADR-069](../../decisions.md#adr-069--customer-first-recovery-and-shared-product-notices).

## What the earlier repair missed

The production screenshot shows the new recovery controls, so simply attributing
this report to undeployed earlier code is incorrect. A live/test guard still
returned the same non-actionable configuration instruction for both Start again
and status. An unconverted trial's test-only session could never charge money but
blocked a first live checkout. Unprefixed numeric Laravel throttles share a user/
domain signature: status polling could spend restart and other action allowances.
The original UI placed adjacent buttons without an action layout and stacked a
large yellow error above the pending banner. Happy-path tests missed the outcome
from the customer's perspective.

## Changes

- AGENTS.md requires customer-first completion and shared notice/action standards
  for **every prompt**. The documentation entry point, quality gate, design system
  and product-experience specification link and define the same contract.
- An unconverted trial under live credentials can retire a stored cs_test_
  checkout, retaining all history and its former customer reference privately.
  No paid subscription, invoice, saved card, capacity, balance, confirmed or other
  pending checkout may exist. Status or restart releases only this test setup.
  The next explicit review/confirmation creates a live customer/session through
  the existing provider. Unknown/live payment outcomes are never discarded.
- Independent named capacity action rate limits; one entry check instead of six
  background polls. Duplicate actions are disabled. Typed errors and Retry-After
  yield a visible countdown; field validation remains immediately correctable.
- Operator-required failures stop offering an ineffective restart. Contact
  support opens a draft with a safe reference; no server configuration instructions
  or raw provider messages are shown to customers. The configured monitored brand
  inbox is used, with the existing operator contact as default. No message is sent.
- `billing:inspect-checkout` is a read-only redacted operator diagnostic: modes,
  config cache, status and private .env/config comparison, never credentials,
  hashes, provider IDs or contact data. It does not alter billing/configuration.
- AppNotice supplies neutral semantic inline feedback with short heading/body,
  small icon and a wrapping action row (10px gap / 14px separation). Billing,
  shared Feedback alerts, workspace access/platform/support/flash notices and
  ToastRegion reuse it. Bespoke existing module banners are not claimed migrated.
- Checkout departure closes the review dialog before leaving so browser-back
  does not resurrect an old confirmation over the current pending state. A transient
  browser marker forces one authoritative document load on returning from Stripe,
  avoiding stale Inertia history props; no quantities/payment details are stored.

## Automated evidence

- Full backend suite: **785 passed / 7,286 assertions**, **30 existing intentional
  skips**. SQLite and fake Stripe SDK transport, not production locking evidence.
- Focused Billing: **176 passed / 848 assertions**. New coverage includes legacy
  and current test-to-live recovery through status/restart; retained audit/customer
  history; fresh live customer/session, idempotent confirm and late test webhook;
  paid/invoice/capacity/card/other-attempt refusal; independent status/restart/
  review rate limits and 429 Retry-After; redacted mode/cache diagnostics.
- **104 frontend tests pass** on Node 24.19.0, including seconds/date Retry-After,
  default waits, provider-diagnostic suppression, safe support fields and immediate
  validation correction and one-use, tenant-specific return markers with blocked-storage fallback. Client/SSR production builds and scoped Pint pass.
- `git diff --check` passes. No new dependency or schema migration.

## Browser evidence

An isolated temporary SQLite database, synthetic owner/trial, and fake Stripe
transport reproduce the failing sequence without real payments or customer mail.
Desktop plus 390px/320px inspections verified:

1. Pending checkout has one neutral notice, separated primary/secondary/quiet
   actions and a compact original purchase summary.
2. Changing quantities to 4 locations / 20 staff shows $192; a simulated provider
   outage keeps both inputs and displays a countdown before retry. No background
   polling repeatedly consumes attempts.
3. Switching the fixture from test to live and choosing Start again releases the
   stale test setup, preserves quantities and enables Review subscription.
4. Explicit review/confirmation opens the local checkout stand-in. Fake transport
   records exactly one new customer and one fresh checkout for this recovery.
5. Genuine live checkout under test credentials shows one support notice and
   draft link/reference, removes ineffective Start again, and explains retry wait.
6. Computed action gap is 10px and text/action separation is 14px. Mobile controls
   measure 44px high; document width equals 390/320px with no horizontal overflow.
7. After the browser-back refinement, the review dialog is closed before leaving;
   returning shows the current pending state rather than the old modal.

Screenshots (synthetic data): [pending desktop](pending-desktop.png), [pending mobile](pending-mobile.png),
[retry desktop](retry-desktop.png), [retry mobile](retry-mobile.png),
[restarted mobile](restarted-mobile.png), [support desktop](support-desktop.png),
[support mobile](support-mobile.png), [recovered desktop](recovered-desktop.png).
Full-page captures may show fixed navigation at the viewport boundary.

## Deployment and honest limits

This change was not deployed from this workspace. The production screenshot's
checkout mode, original account and active cached secret were not available here.
The screenshot alone cannot distinguish a stale test session from a live session
under the wrong cached configuration. Run the new redacted diagnostic on the
server and follow [the operator runbook](../../deployment/subscription-billing-stripe.md#customer-recovery-refinement-2026-10-15).
A potentially paid live session cannot safely be unlocked using test credentials;
the platform operator must restore original-account access and verify it. A code
change cannot recover an inaccessible Stripe account by guessing credentials.

No primary database mutation, real charge, refund, subscription cancellation,
credential change, production deployment, provider sandbox/live certification,
real inbox delivery or independent accessibility/device certification performed.
Existing release gates remain. The neutral shared component is adopted by changed
shared surfaces; this audit does not certify every bespoke notice in every module.
