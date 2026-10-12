# Public pricing

Updated: 2026-10-12. Requirements: [FR-01](../product-requirements.md#fr-01-saas-registration-and-subscription-management), FR-13, [PRD §9](../product-requirements.md#9-subscription-packaging-and-commercial-rules); ADR-054/056/057/061.

The `/pricing` acquisition page offers the same single location/bookable-staff
subscription as [Subscription & billing](subscription-billing.md). It does not
fall back to Starter/Pro when capacity billing is unavailable. Publish only
approved, enabled, remotely verified immutable rates with a current active base
price. Ordinary pricing visits perform local reads, without calling Stripe.

`PublicPricingCatalog` exposes only currency, cadence, component amounts and
monthly text allowance; provider IDs, fingerprints, rate-card IDs and credentials
are excluded. Both public and owner calculators use `capacityEstimate` and
`capacityAnnualSaving`. The server review and provider evidence remain the
financial authority. Public estimates never reserve capacity, create a payment,
change a trial, or grant an entitlement.

## Presentation

- One headline and a clearly dated trial promise, with no card required.
- One calculator with whole-number locations and globally counted bookable staff,
  monthly/yearly cadence, visible base/additional rates and exact component totals.
- Yearly billing leads with the full upfront yearly total; a rounded monthly
  equivalent is secondary. Savings are derived from complete monthly/yearly sums.
- Solo, growing team and two-location examples are input shortcuts, not tiers.
- Non-bookable admins/receptionists are included; chairs/inactive staff do not count.
- A focused six-category list covers implemented appointments/walk-ins, online
  booking, client/team management, checkout/sales, reporting and notifications.
  No unlimited text, marketplace, native app, campaign, priority-support or deferred
  payment capability is promoted.
- Text allowance resets monthly, is pooled, and depends on actual segments and
  supported destinations. No automatic purchase or paid pack is advertised.
- USD, before-tax pricing and separate client payment fees remain visible.
- Missing rates use a trial/support path, without sample prices or alternate tiers.

## Signup handoff

Guest CTA passes `plan=capacity`, cadence and bounded location/staff counts.
Email and Google signup validate current public availability and persist those
preferences in `OwnerRegistrationIntent`. Google uses its server-saved OAuth
selection rather than replacement submitted fields. Plain signup remains valid
without a pricing preference. Old Starter/Pro and malformed selections are rejected.

The verified owner's own business retrieves its saved preference. Initial owner
calculator defaults to at least the saved counts and actual resource usage, only
when no provider subscription exists. Purchased/pending quantities remain authoritative
and do not use these preferences. Owners must still review current prices and
confirm a separate secure checkout. Signup does not create branches or people.

Deploy the additive `2026_10_12_000001_save_capacity_signup_preferences` migration
before activating the new signup code. Existing columns and historical selections
are not rewritten. No new Stripe setup is needed for this page; the existing
[deployment guide](../deployment/subscription-billing-stripe.md) applies.

Search metadata and component price specifications describe the same published
rates. No hidden legacy offers, invented reviews or fixed total for arbitrary
capacity appears in structured data. Schema markup is not a rich-result guarantee.

Evidence: `PricingTest`, billing/auth/marketing regressions, frontend helper tests,
client/SSR builds, public asset budgets and the
[dated review](../audits/2026-10-12-public-pricing/README.md).
