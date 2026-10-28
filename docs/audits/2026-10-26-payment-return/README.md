# Automatic subscription payment return — 2026-10-26

Requirements: FR-01, FR-19/20; ADR-072. This follows the owner-confirmed repair
of missing test prices on 2026-10-25. Opening Stripe is verified on the deployed
site; completion of a deployed payment is still not established by that evidence.

## Reported failure and cause

The screenshot labels the saved request **You have an unfinished checkout**.
The capacity panel checked only once on entry and used that heading for every
submitted request. It did not follow a processing result, and a webhook that
finished before return removed the pending request without supplying a success
notice. Its partial refresh also omitted the shared tenant status used by the
header's trial badge and workspace access, plus invoice/attention projections.

## Changes

- Start with **Checking your payment**; do not flash abandoned-checkout language
  or offer premature restart while the result is being checked.
- Return hints require the current business's saved attempt, exact session and
  associated request. Stored confirmed/applied state shows success immediately;
  URL markers never activate access. Page GETs still make no provider calls.
- Use a dedicated verification-only path for automatic checks: retrieve the known
  owned Stripe session, subscription and invoice using the existing projector.
  It cannot create/replay customers or Checkout, expire a payable session or
  submit subscription changes. Existing guarded unpaid test retirement remains.
- Follow processing with five spaced checks after the entry read: 2, 3, 5, 8 and
  12 seconds. One request at a time; stop on error, navigation, open checkout,
  actionable invoice or terminal state. Retain Retry-After and financial history.
- On payment confirmation show **Your subscription is active**, then refresh the
  current plan, quantities, allowances, shared header/access, invoices and debt.
  A real processing delay is **Your payment is pending**. Open/unpaid checkout
  retains continuation and safe restart; an error keeps entered work and the
  existing contextual recovery/support actions.
- AGENTS.md, the shared design system, module specification and decision record
  carry the automatic-result rule into future tasks.

## Verification

Local automated results are recorded in the accompanying text files. The complete
backend suite passes 888 tests / 9,594 assertions with 30 existing skips. Four
signing-key tests were initially blocked by restricted OpenSSL random-state writes;
the authorized unrestricted rerun passes. Final focused billing: 242 tests / 1,603 assertions, including shared header/access
assertions added after the full run. The frontend suite passes 122 tests.
Production client/SSR builds and scoped Pint pass.

New backend regressions cover monthly/annual completion before webhooks, webhooks
before browser return, processing-to-active, fractional observation time, unpaid
return flags, mismatched/foreign session hints, absent/uncertain preparation,
provider failure, verified expiry and safe test-to-live recovery. Existing Stripe
SDK transport tests verify no automatic provider writes or duplicate attempts.

The actual Vue billing panel and AppNotice are compiled and mounted using Vue's
host renderer. Tests verify initial checking without unfinished-warning flash,
monthly/annual automatic success with no button click, webhook-first success,
unpaid continuation, 429 wait/disabled actions, shared account refresh fields and
ignored late responses after navigation. Separate fake-timer tests prove bounded,
non-overlapping checks, actionable-invoice/open-checkout stop, error stop and timer
cancellation. This is component behavior/accessibility-markup evidence, not a
rendered desktop/mobile visual review or a real Stripe payment.

## Deployment and remaining evidence

Deploy application changes and rebuilt client/SSR assets together. No database
migration, new environment variable or Stripe catalog change is required by this
follow-up. Retain the verified monthly/yearly mappings from the earlier repair.
No existing paid subscription is repriced or reset.

A new desktop/mobile visual run and a completed sandbox payment on the deployed
site remain required. Browser access was disabled and Forge access declined in
the preceding work; neither restriction was bypassed. No real payment was made,
no production customer/secret was changed, and no deployed success is claimed.
