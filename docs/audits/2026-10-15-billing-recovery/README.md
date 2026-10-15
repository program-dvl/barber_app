# Abandoned checkout recovery — 2026-10-15

Requirements FR-01/19/20; [ADR-068](../../decisions.md#adr-068--provider-verified-abandoned-checkout-recovery).

## Findings and changes

The submitted capacity request disabled quantities/review indefinitely, status
and continuation could throw unhandled provider errors, and the portal's direct
redirect surfaced the full 503 marketing page. A local two-hour attempt lifetime
could also stop recovery before Stripe's actual session expiration. Confirming
an already-submitted request could reuse a stored URL without checking Stripe.

The repair commits attempts before provider dispatch, retains original keys and
customer bindings after uncertain responses, and adds provider-verified restart.
Only an expired owned session or verified absence of the original creation can
release the request. Completion, including a race with expiration, reconciles the
existing subscription. Account/mode mismatch and provider outages remain safe
and retryable; no blind reset is implemented. Late expiration is scoped to the
original request. Portal errors remain in billing, direct URL failures redirect
back with a message, and browser history returns refresh the workspace.

## Automated verification

- `php artisan test --compact`: **774 passed, 7,146 assertions**, 30 pre-existing
  intentional skips. SQLite and mocked providers; not production concurrency.
- Focused Billing suite: **165 passed, 708 assertions**. Covers expire-before-
  unlock/idempotent restart, API outage/restoration, missing object, mode mismatch, recovery while new purchases/webhook readiness are paused,
  owned metadata/customer validation, authoritative expiration, uncertain create
  recovery, incomplete lookup, completion/expire races, tenant/permission checks,
  persisted customer binding, claim/attempt gap, late old expiry, portal JSON
  success/failure and direct navigation fallback. No real Stripe calls.
- Node 24.19.0: **98 frontend tests pass**. Default shell Node 20 is below the
  manifest requirement; verification used the installed supported version.
- Client/SSR production builds pass; scoped Pint and `git diff --check` pass.

## Browser verification

Isolated temporary SQLite database, synthetic North Studio owner/trial, fake SDK
transport and no email/payment delivery. The production business in the screenshot
was not present in the local database (read-only check). No production repair
claim is made.

Verified: pending quantities editable (4 locations/20 staff = $192), checks do not
reset edits, Review remains blocked until the old checkout closes, provider
failure preserves Start again retry, restored status and Continue reach the
local checkout stand-in, browser-back recovers, Start again expires the old
session and preserves edits, and Review opens the existing explicit confirmation
for $192. Portal failure stays in the Overview with an inline error. Recovery controls also remain visible and work with new purchases paused and
webhook readiness missing. At 390px and
320px, document width equals viewport width, recovery buttons wrap and controls
remain usable. Real device/screen-reader or Stripe hosted-page certification is
not implied. Full-page captures include fixed navigation at the viewport boundary.

- [Pending desktop](pending-desktop.png), [pending mobile](pending-mobile.png)
- [Restarted desktop](restarted-desktop.png), [restarted mobile](restarted-mobile.png)
- [Fresh mobile review](review-mobile.png)
- [Inline portal error](portal-error-desktop.png)

## Rollout and limits

No schema or new Stripe Price changes. Deploy source/assets, restore the original
complete Stripe environment when keys were switched, rebuild config/restart
workers and persistent processes, then recheck or Start again. See the
[deployment recovery runbook](../../deployment/subscription-billing-stripe.md#abandoned-checkout-and-credential-recovery-2026-10-15).
A wrong account cannot read the original customer's session; ambiguous/no-customer
creation beyond the safe replay window needs operator verification. No paid
customer reset, financial-history rewrite, real charge, production deployment or
live provider/mode-switch certification was performed.
