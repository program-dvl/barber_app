# Confirmed subscription and stale checkout consistency — 2026-10-28

Requirements: FR-01, FR-19/20; ADR-072 follow-up.

## Customer report and scope

The owner reports a successful purchase and supplies screenshots of the active
subscription, matching paid invoice and a Plan-tab unfinished-checkout notice.
This establishes the reported conflict; the deployed database, asset version and
Stripe account were not independently inspected. Do not infer a second payment
is required or reset the existing subscription.

The code treated a submitted workflow record as unfinished regardless of already
confirmed local purchase evidence. Existing projection skipped completion when
it rejected an older observation, so legacy/inconsistent records could survive
later payment checks. The UI also retained return data across partial reloads;
an older pending response must not resurrect a purchase already confirmed.

## Fix and safety

- Close only a submitted checkout with its confirmed, dated Stripe attempt,
  matching business/provider subscription/plan-price binding, active or
  cancel-scheduled subscription, and exact request/attempt/current-snapshot
  agreement for rate card/fingerprint, currency, cadence, quantities and total.
  Active status or a paid invoice alone cannot dismiss an unrelated checkout.
- Under the existing lock order, mark only the workflow applied, clear its old
  payment URL and append one audit event. Keep subscription terms, version,
  provider timestamps, allowance and invoice/payment history unchanged. Repeated
  presentation is idempotent. No Stripe call or new payment is needed.
- Reuse this repair on workspace presentation, status/restart/confirm and new
  review. A repaired visit reports success without needing redirect parameters
  or a status click; future edits are no longer blocked by the stale request.
- Prefer an explicit confirmed/applied result over the same stale pending prop,
  refresh return data with account details, and ignore delayed same-request
  rollback. A different unpaid request stays visible. Confirmed attempts never
  offer Start again; verified open checkout offers Complete your subscription.
- The active customer's heading is Change your subscription. Shared AppNotice,
  spacing, status semantics and existing buttons remain in use. No CSS dependency
  or separate pricing/billing system was introduced.

## Verification

- Full backend: **910 passed / 9,750 assertions**, 30 existing skips.
- Focused capacity billing: **187 passed / 1,268 assertions** before the final
  additional tenant/provider guard; the complete backend run includes that guard.
- Frontend: **128 passed**, including the actual compiled panel and AppNotice
  mounted with Vue's host renderer. Covers monthly/yearly automatic confirmation,
  stale same-request props, refreshed error-to-success, older partial responses,
  different unpaid requests and the absence of obsolete payment actions.
- Backend regressions reproduce active + paid invoice + stale submitted checkout
  with fractional clock precision and a newer provider timestamp. Verify ordinary
  visits, repeated visits/audit deduplication, status/restart/confirm, fresh review,
  no provider reads/writes and unchanged subscription/invoice records. Negative
  cases cover unconfirmed attempts, missing confirmation time, different request,
  provider subscription, plan price, rate fingerprint, quantities, cadence,
  currency, attempt quote, past-due status and renewal requests.
- Production client/SSR build, scoped Pint and whitespace validation pass.

Raw local results accompany this record. Host-renderer assertions prove state,
copy and accessibility markup; they are not desktop/mobile browser screenshots.

## Rollout and limits

Deploy matching backend and rebuilt client/SSR assets. No migration, environment
variable or Stripe Dashboard action is required. Eligible stale records repair
on the next authorized billing visit. Do not delete payment evidence, change
quantities, reprice subscriptions, or ask the owner to buy again.

The new fix has not been deployed or independently verified on the reported
business. No production data, credentials or real payment was changed. Browser
access was previously disabled and Forge access declined; those restrictions
were not bypassed. New desktop/mobile rendered checks and verification of the
reported account after deployment remain outstanding.
