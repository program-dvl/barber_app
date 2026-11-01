# Discount presentation refinement

2026-11-01. FR-01/19/20, ADR-078. Owner-provided deployed screenshots are the
failure evidence: the offer/coupon block sat outside the calculator inset,
procedural copy dominated public pricing, and the estimate label touched the
promotion. The owner then requested breathing room and an appealing icon.

## Changes

- Keep calculator inputs and summary top-aligned. Move offer/coupon presentation
  inside the summary, sharing its inset, width and action alignment.
- Promotional strip has decorative Heroicons GiftIcon, compact campaign name,
  indigo percentage badge and a restrained mint/indigo surface. No image download,
  fabricated urgency, animated decoration or new checkout step.
- Public hero/estimator and owner calculator lead with discounted first payment;
  regular renewal and tax remain visible. Gross component lines plus welcome
  saving explain the amount. Annual amounts remain annual, not monthly equivalents.
- Coupon entry is a native details disclosure above Review subscription. It stays
  open while editing/removing text, retains input through failure/cancellation and
  contains shared AppNotice errors. It does not claim a code is applied before
  server review; short helper says Savings confirmed at review.
- Remove public expiry-time, coupon-location and procedural reassurance paragraphs.
  Promotional strips use shared price styling; errors/status still use AppNotice.
- Scope hero large-price CSS to its actual price node so it cannot enlarge badges.
  Estimated subscription has an actual measured 18px bottom gap.

## Verification

Real application at local 127.0.0.1:8031, prior disposable synthetic SQLite fixture.
No production writes, billing confirmation, provider calls, migrations, price
changes or financial behavior changes in this presentation task.

- Desktop and 390×844 layouts inspected. Public/owner document widths remain
  390px. Gift strip stays readable on one row on phone; names can wrap safely.
- Real owner summary: $47 recurring → $37.60 first payment, $9.40 saving.
  Invalid code retained counts/input and showed correct inline error. Correcting
  to lowercase invite10 produced actual server review for $33.84, named $9.40
  and $3.76 savings and $47 renewal. Cancel retained entry and selection.
- Phone coupon disclosure/review action 44px high; input 46px. Persistent errors
  use shared notice layout. Existing payment return, blocking and cooldown logic
  was retained; no live recovery or payment certification is claimed.
- Public monthly hero: $23.20 first month then $29/month. Yearly estimator:
  $232 first year then $290/year, $58 welcome saving. Measured 18px between
  Estimated subscription and its offer strip; mobile has no horizontal overflow.
- Full frontend: **189 passed**. Actual calculator test covers monthly/yearly
  first-payment presentation, removal of offers and unchanged gross review quote.
- Pricing backend: **16 passed / 239 assertions**. Client and SSR build pass on
  Node 24.19.0 (existing DaisyUI/font warnings). Whitespace passes. The backend
  full-suite communication failures recorded in the prior audit were not rerun
  or claimed fixed by this visual-only task.

Evidence: [subscription desktop](subscription-desktop.png),
[subscription phone](subscription-mobile.png),
[phone coupon error](subscription-error-mobile.png),
[public hero](pricing-hero-desktop.png),
[public calculator](pricing-calculator-desktop.png),
[public phone](pricing-mobile.png).

Owner reports deploying the preceding module. This correction is prepared locally;
redeploy source plus rebuilt client/SSR assets. No migration or seeding is required.
Deployed appearance and real financial journeys remain unverified by this task.
