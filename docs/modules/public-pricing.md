# Public pricing

Updated: 2026-10-12. Requirements: [FR-01](../product-requirements.md#fr-01-saas-registration-and-subscription-management), FR-13, [PRD §9](../product-requirements.md#9-subscription-packaging-and-commercial-rules); ADR-054/056/057/061/063.

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

- Shared public SEO hero/breadcrumbs/typography with starting USD 29/month and
  a trial panel. Primary signup carries no quantities; trial is 14 days by
  current config, starts after verification, requires no card and never charges
  automatically. Publish prices only when current mappings are verified.
- Six concise, implemented capability groups precede transparent base/location
  rates and a progressive staff-band disclosure. No artificial feature tiers,
  unlimited messaging or unimplemented enterprise capabilities.
- Optional native estimator disclosure below growth pricing. Default one
  location/person/monthly, five example presets, reset, labelled whole-number
  controls, disabled boundary buttons, live integer-cent breakdown and validation.
- Yearly estimate shows the full amount charged upfront, rounded monthly
  equivalent and exact savings. Ten monthly amounts purchase a full year.
- Staff bands apply across the entire business. Non-bookable users do not count.
  Inputs/presets never reserve resources, change a subscription or authorize payment.
- Text allowance resets monthly and is pooled; segments and supported US/Canada
  destinations affect use. Current capacity allowance is 100 per bookable staff,
  including 2,000 for 20 staff; retained allowances remain unchanged.
- USD, taxes and separate client payment fees are explained at the calculator and
  in focused commercial questions. Incomplete Stripe setup retains a trial path.

## Signup handoff

Optional estimator CTA passes `plan=capacity`, cadence and bounded location/staff counts.
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
are not rewritten. The progressive revision requires new verified Stripe Price mappings; follow
[the upgrade guide](../deployment/subscription-billing-stripe.md#progressive-pricing-upgrade-2026-10-12).

Search metadata and starting-price offer specifications describe the same published
base/cadence, with explicit additional progressive charge descriptions. No hidden legacy offers, invented reviews or fixed total for arbitrary
capacity appears in structured data. Schema markup is not a rich-result guarantee.

Evidence: `PricingTest`, billing/auth/marketing regressions, frontend helper tests,
client/SSR builds, public asset budgets and the
[dated review](../audits/2026-10-12-public-pricing/README.md).


Current verification: [progressive-pricing audit](../audits/2026-10-12-progressive-pricing/README.md).
Earlier calculator-led evidence above is historical and superseded by ADR-063.

## 2026-10-13 content and SEO review

Pricing copy now distinguishes the paid software subscription from received
client-payment recording and unavailable online deposit collection. Notification
copy requires enabled delivery providers, consent and available SMS credits. An
unavailable catalog does not imply a zero published allowance or invent a sales
contact. Yearly-only rates select a usable yearly estimate, including on reset.
Server metadata and current offers update through the shared SSR/client head;
primary production canonicals use the configured brand host. Commercial rates
and billing rules are unchanged by this editorial review. See the
[public website contract](public-website.md) and
[dated evidence](../audits/2026-10-13-public-content/README.md).
