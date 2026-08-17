# Public pricing and signup-selection contract

Status: Accepted Prompt 20 implementation record (2026-08-16)

`billing_plans`, current `billing_plan_prices` and current
`billing_plan_entitlements` are the only public commercial authority. The
public presenter requires both approved Starter/Pro plans, monthly and annual
Paddle `pri_…` mappings, effective active records and one supported USD
currency. Any missing, expired, mixed or non-Paddle state produces a truthful
unavailable panel instead of partial or placeholder prices.

The approved catalog remains Starter USD 50/month or USD 500/year and Pro USD
100/month or USD 1,000/year under ADR-021. Annual savings are calculated at
request time from `monthly × 12 − annual`; Vue contains no commercial amount.
Limits and capabilities likewise come from effective entitlement rows.

An anonymous plan action passes only `starter|pro` and `monthly|annual` to
registration. The server revalidates that pair against the current complete
catalog, persists it on the owner registration intent, and exposes it later to
the owner billing review. It does not choose a provider price, charge a card or
change the default trial. Authenticated visitors enter the dashboard.

Live Paddle checkout, production seller identity, domain approval, credentials,
catalog mappings, webhook and settlement certification remain Prompt 13 launch
blockers. Paddle subscription billing remains separate from salon-client
appointment payments. Tax and provider fee outcomes are presented only inside
the provider-owned checkout context.
