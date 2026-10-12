# Public capacity pricing review

Date: 2026-10-12. Requirements: FR-01, FR-13, PRD §9; ADR-061.

## Research and design choice

Reviewed the current first-party [Fresha pricing page](https://www.fresha.com/pricing),
[Square Appointments pricing](https://squareup.com/us/en/appointments/pricing) and
[Calendly seat definitions](https://calendly.com/help/company-admin-guide).
Fresha makes bookable team members explicit, Square labels location billing, and
Calendly distinguishes purchased capacity from the people occupying it. Those
patterns informed clear unit definitions, cadence, total and inclusions; their
prices, tiers, fees, feature promises and marketplace models were not copied.

ClipperDesk combines two quantities rather than offering a single per-seat rate.
One calculator therefore leads with the complete recurring total, alongside the
base and incremental unit rates. Yearly billing emphasizes the upfront annual
amount, with a secondary monthly equivalent and calculated savings. Example
buttons only change the inputs. The page uses existing public navigation/typography,
a calm neutral/sage palette, six focused core-tool categories and native FAQ
controls. It replaces the large photographic hero, legacy-tier cards/comparison
and technical payment-provider paragraphs.

## Implementation and guardrails

- `PublicPricingCatalog` uses the approved capacity catalog, current active base
  mappings and remote-verification evidence. Public amounts/currency/allowance
  are whitelisted; provider identifiers and fingerprints are not exposed.
- Public and owner calculators share the existing capacity arithmetic helpers.
  No Stripe read/write occurs on a pricing visit. Unavailable mappings never
  fall back to Starter/Pro or illustrative prices.
- Email and Google registration preserve bounded locations/staff/cadence preferences.
  Google completion retains its server-saved selection. Initial unpaid owner
  billing can prefill those values, bounded below by actual usage. Preferences
  never become a paid quote, provider payment, entitlement or created resource.
- The additive signup migration was applied to local MySQL after a private 0600
  signup-intent snapshot; all original values across 22 rows were preserved.
  Production deployment still requires the normal migration step.
- Search metadata and `CompoundPriceSpecification`/`UnitPriceSpecification` describe
  the same published components and cadence, following [Schema.org compound
  pricing](https://schema.org/CompoundPriceSpecification) and [unit pricing](https://schema.org/UnitPriceSpecification).
  Unavailable pricing has no offer markup. This does not guarantee search features.

## Verified evidence

- Marketing, registration, Google authentication and billing regressions:
  **196 passed / 2,207 assertions**, one pre-existing intentional registration skip.
  Includes tampered quantities/tier rejection, private payloads, stale/disabled
  rates, saved preferences, Google ownership and component schema.
- Frontend helper tests: **75 passed**. Node 24 client/SSR production builds and
  **17 public-route asset budgets** passed. Existing DaisyUI/font build warnings
  remain; no dependency changes were required.
- Browser verified default USD 29/month and 1-location/4-staff USD 56/month or
  USD 560/year, USD 112 savings and 400 monthly text credits. The registration
  page and Google link retain the selected cadence/counts. No account was
  created through the browser and no terms/payment were submitted.
- Invalid zero staff removes the signup action and explains the allowed counts;
  native arrow keys switch cadence. 320/390/768 px document widths matched their
  viewports; desktop 1440 px and mobile presentation were inspected.
- [Desktop yearly view](desktop-yearly.jpg), [mobile yearly view](mobile-yearly.jpg).
  Temporary viewport overrides were reset. PHP formatting and whitespace passed.

No live deployment, real payment, provider mutation, actual message or independent
accessibility/security/payment certification was performed. Existing release and
Stripe infrastructure gates remain unchanged. Concurrent unrelated Forge setup
repairs in this shared checkout were preserved.
