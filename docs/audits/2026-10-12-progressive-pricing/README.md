# Progressive pricing and public-page review

Date: 2026-10-12. Requirements: FR-01, FR-13, PRD §9; ADR-063.

## Delivered behavior

One capacity plan: USD 29 monthly base including one location/person, USD 19
per extra location, and business-wide progressive bookable-staff positions 2–5
at USD 9, 6–10 at USD 6, 11+ at USD 4. Annual components are derived by an exact
integer ten-month multiplier. Full annual charges and rounded display-only
monthly equivalents are separate. All six requested examples and every staff
boundary are tested. Invalid counts and malformed commercial tiers fail closed.

The public page reuses the shared SEO hero, breadcrumbs, heading style,
container, navigation, FAQs and brand tokens. The price panel uses deep navy,
white pricing/action text and a restrained indigo surface. Features and growth
rates precede the closed estimator. Repeated rate lists, promotional sublabels,
generic section kickers and redundant FAQs were removed during the owner's
copy review. Each remaining section explains value, cost, counting, commitment
or a customer decision. No new feature tier, unsupported feature claim or
unlimited allowance was introduced.

The main trial link goes directly to existing signup. Five presets and reset
change only local inputs; optional estimator signup saves bounded non-binding
preferences. Actual billing still requires an authenticated, server-priced
review and separate confirmation. One staff member remains included across the
whole business, not independently in every location.

## Stripe and retained subscriptions

The existing three recurring subscription components remain. New staff Prices
use licensed `tiered`/`graduated` pricing; additional staff quantities have
cumulative bounds 4, 9, infinity. The provisioner creates new immutable base,
location and staff Prices for both cadences. Verification expands tiers and
rejects wrong units, bounds, flat extras, volume pricing and mismatched cadence,
currency or tax behavior. Local fingerprints never mutate existing cards.

Checkout, fixed-date proration, pending-if-incomplete increases, renewal
schedules, signed-event reconciliation, cancellation, failed payment, invoices
and monthly SMS windows stay in the existing billing boundary. Provider objects
without expanded tiers must match remotely verified immutable price IDs and the
graduated scheme. Per-unit staff pricing is explicitly retained for old cards.

Existing owners can opt into current verified prices in the Plan calculator,
then review/confirm a renewal migration. No current charges or access change from
checking the option or reviewing it; rate-card switches always schedule at renewal.
No bulk migration or customer/provider mutation was executed. Staff quantities
remain globally counted; resource-fit and tenant/permission/version checks remain.

Current capacity SMS remains 100 credits per bookable staff per month, including
2,000 for a 20-person team. Retained subscription/override allowances remain.
Credits reset monthly, do not roll over and support current US/Canada destinations;
segment length affects use. Paid/automatic text purchases remain gated.

## Verification

- Complete repository suite: **711 passed / 6,410 assertions**, 30 existing
  disabled-feature/database-integration skips, 56.35 seconds.
- Focused pricing/billing/auth/marketing run before final added coverage:
  247 passed / 2,447 assertions, one existing registration skip.
- Final capacity domain/SDK/retained-migration tests: 72 passed / 209 assertions.
  Coverage includes annual Checkout items, fixed-date proration across bands,
  immutable legacy terms, explicit renewal migration, signed trial conversion,
  paid/pending/stale events, invalid quantities and tier verification.
- All frontend helper tests: **86 passed**. Client and SSR production builds,
  17 public-route asset budgets, scoped PHP formatting and whitespace checks pass.
- Isolated SQLite browser catalog used synthetic Stripe IDs. No production/local
  business data was replaced. New commercial amounts correctly remain unavailable
  in the normal database until real mappings pass the existing publication gate.
- Chrome browser checks at 320/390/768/1440 px: no horizontal overflow, one h1,
  semantic cadence radios, 44×48 px counter controls, defaults 1/1/monthly,
  all five correct preset amounts, reset and zero-count validation. The 20-staff
  estimate is USD 135/month or USD 1,350/year, USD 112.50 monthly equivalent,
  USD 270 savings and 2,000 monthly credits. The 5/50 annual charge is USD 3,310.
- Hero stays USD 29 while estimates change; its trial URL has no quantities.
  Estimator signup/Google preserves 5 locations, 50 staff and annual cadence,
  with an explicit no-charge preference notice. No account/terms were submitted.
- No browser warning/error logs. Chrome viewport override was reset. The in-app
  browser's sizing API timed out, so responsive checks used available Chrome.
- [Desktop page](desktop-page.png), [mobile page](mobile-page.png),
  [desktop hero](desktop-hero.png), [responsive measurements](responsive-checks.json).

## Required activation and limits

Follow the [Stripe upgrade procedure](../../deployment/subscription-billing-stripe.md#progressive-pricing-upgrade-2026-10-12).
Provision/verify six new immutable Price mappings in the intended account/mode;
remove stale explicit IDs rather than reusing the old base/staff Prices. The new
catalog revision does not require a schema migration. Keep existing signup
migrations and all original webhook/worker setup.

No actual Stripe Price, subscription, charge, deployed environment or existing
financial record was changed by this task. SDK HTTP contracts are mocked;
live/sandbox invoice amounts, tier-crossing prorations, taxes, renewal timelines,
refund/recovery and target-database concurrency still need external certification.
Local responsive checks are not independent WCAG or a full browser/device matrix.
All earlier release gates remain applicable. Concurrent public-logo edits were
preserved.
