# Subscription welcome discounts and coupons

2026-11-01. FR-01/19/20, PRD §9, ADR-078.

## Delivered behavior

Platform administrator-only Payments → Discounts & coupons supports automatic
seasonal offers and private reusable codes, enabled switches, 1–90% percentages,
optional UTC windows and explicit sequential combination. Highest seasonal saving
wins. Standalone coupons replace it; combination applies the coupon to the reduced
amount. Discount only the first payment of new software subscriptions. The owner
explicitly confirmed both boundaries in this chat. Existing paid subscriptions,
renewals, SMS and salon sales are outside this promotion engine.

The form hides code-specific controls for automatic offers. Require a coupon code
reveals manual input and an explicit Generate code button; opening/editing never
regenerates. Save validation normalizes codes before uniqueness checking. Admin
creation/edit and their audit event share a transaction. No delete action exists;
the model and migration also refuse destructive deletion/rollback.

Public pricing advertises automatic savings with first-payment and renewal
wording. Owner Plan accepts a code before review; invalid code feedback retains
all inputs and allows correction/removal. Review snapshots named savings, exact
first payment and gross recurring total. Claim rechecks eligibility and rejects
changed offers before any submission. Submitted checkouts keep their reviewed
terms, including during recovery or after an offer is disabled.

The Stripe SDK receives a checkout-specific amount_off coupon, duration once,
max_redemptions 1 and a stable attempt key, followed by Checkout with its original
stable key. This is not a campaign redemption quota. Actual signed discounted
Checkout projection checks gross subtotal, currency, discount, nonnegative tax and
net total before binding. Existing signed subscription projection remains the
access authority. Financial rates, invoices and purchased quantities retain their
existing identities/history. No real provider call or charge was made here.

Provider references: [Stripe subscription discounts](https://docs.stripe.com/billing/subscriptions/discounts)
and [Coupon create parameters](https://docs.stripe.com/api/coupons/create).
The review rejects combinations below USD 0.50; see [Stripe minimum amounts](https://docs.stripe.com/currencies#minimum-and-maximum-charge-amounts).

## Browser verification

Real app at 127.0.0.1:8031, disposable SQLite copied from an existing synthetic
fixture, fake test credential and no checkout confirmation/provider network call.

- New menu and 20% seeded Holiday welcome offer visible.
- Created an enabled combinable invitation, tested the explicit generator,
  manually entered INVITE10, saved and reloaded it.
- Verified phone admin layout at 390×844: document width 390; Generate code,
  Save changes and Cancel are 44px tall. Customer review buttons are 316×44px.
- Real customer review for $47/month shows $9.40 seasonal saving, $3.76 coupon
  saving, $33.84 first payment, then $47/month before tax.
- Invalid code returned actual server validation, correct Check your coupon code
  heading, retained code/counts and successful correction to a lowercase code.
- Switched seasonal offer off in admin and verified it disappeared from public
  pricing, then re-enabled and verified it returned. Public estimator showed
  $23.20 first payment versus $29/month regular renewal; phone public width 390.
- Desktop/mobile saved evidence: [admin](admin-desktop.png),
  [admin phone](admin-mobile.png), [customer review](customer-review-desktop.png),
  [customer phone](customer-review-mobile.png), [coupon error](coupon-error-mobile.png),
  [public pricing](pricing-desktop.png), [public phone](pricing-mobile.png).

## Automated verification

- Final billing/admin/pricing focused suite: 247 passed / 1,784 assertions,
  including end-only dates, normalized duplicate codes and discounted Checkout
  with a microsecond-precision clock and a below-minimum combination guard.
- Full frontend: 188 passed. Production client and SSR build pass on Node 24.19.0.
  Build reports existing DaisyUI @property and unresolved-at-build font warnings.
- Full backend run: 1,033 passed / 30 skipped / 2 failed, 10,810 assertions.
  Remaining failures are ReliableCommunicationsTest lines 206 and 292 (expected
  default email/reminder counts). They also fail in an isolated communication run;
  no communication implementation was changed. The full suite is not green.
- Initial four OpenSSL failures were local random-state file access; setting
  RANDFILE to a writable temporary path cleared them. No security setting changed.
- Scoped Pint and whitespace checks pass. SDK tests simulate Stripe; existing
  capacity regressions cover checkout abandonment/replay, provider mismatch/outage,
  payment return and out-of-order subscription state. These are not live evidence.

## Local installation and production steps

Applied only the new promotion migration to local MySQL barber_app at 127.0.0.1,
then successfully ran LaunchPromotionSeeder. No existing business or subscription
record was changed in that database. Browser writes were isolated fixture writes.

For production, deploy the source/build and additive migration before exposing
this revision, then run `php artisan db:seed --class=LaunchPromotionSeeder --force`.
Do not run the boilerplate DatabaseSeeder. The fixed seed identity installs 20%
Holiday welcome, 2026-11-01 inclusive to 2027-01-01 exclusive UTC. Reruns preserve
admin changes, including rename, percentage, dates and disabled status.

Restricted Stripe live credentials must permit Coupon writes, in addition to
existing Checkout access. Confirm permission and complete a separately authorized
live discount/tax/payment-return/renewal rehearsal before declaring production
ready. No production deployment, provider permission change, live discounted
payment, refund, real-device matrix or production concurrency was verified.
