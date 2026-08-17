# Prompt 20 — Pricing, Trial and Purchase Conversion

Execute after Prompt 19. Treat the server-owned billing catalog and entitlements—not marketing notes—as authoritative.

## 1. Mission

Build a truthful public Pricing experience that explains available plans, billing intervals, trial, limits and entitlements, then carries a visitor safely into the existing registration and subscription journey.

## 2. Why This Phase Exists

Pricing is a high-trust conversion surface and a common source of drift. Good Hours has normalized plans/effective-dated prices and a selectable Paddle adapter, while live provider certification remains a critical launch blocker. Public display must never invent or stale-copy commercial terms.

## 3. Prerequisites

Prompts 14–19 are verified. Inspect current billing state before work. Product approval must exist for public plan names, amounts, currencies, taxes/fees wording, entitlements and trial. If no public-safe active catalog is available in the environment, implement an honest unavailable/contact state rather than placeholder prices.

## 4. Read Before Changing Anything

Read mandatory docs, billing/subscription module docs and requirements, ADR-021 and superseded decisions, `config/billing.php`, provider adapters, catalog sync definition, plan/price/entitlement models/migrations, billing controllers/pages/tests, registration/trial creation and Prompt 13 provider evidence. Inspect actual active plan data and Paddle/Stripe separation.

## 5. Scope

- Create/refine public Pricing route/page with monthly/annual selection, savings, trial explanation, plan limits and entitlement comparison.
- Explain staff/location limits, messaging allowances and payment-processing disclosures only as represented by authoritative data/policy.
- Connect CTAs to signup and preserve selected plan/interval through a server-validated intent.
- Add pricing FAQ, unavailable/error states, metadata, tests and documentation.

## 6. Out of Scope

Changing plan economics, entitlement enforcement, provider checkout internals, live Paddle/Stripe certification, coupons, custom enterprise sales or Phase 2 packaging. Do not show sandbox checkout to public production users.

## 7. Product Truth

Read price/currency/effective dates from normalized server-owned catalog wherever possible. Trial currently defaults in configuration but must be verified end-to-end before advertised. Distinguish SaaS subscription billing via Paddle from salon client appointment payments via Stripe/local tenders. Do not imply payment processing is included/free or available everywhere. Taxes, refunds and fees need approved wording.

## 8. Information Architecture

Pricing is one durable top-level route linked from global navigation, homepage and evaluation pages. Page order: decision context → interval control → plan cards → full comparison → commercial/provider disclosures → FAQ → CTA. Keep account billing routes authenticated/noindex and distinct.

## 9. UX Requirements

Default interval and savings must be clear and non-deceptive. Plan cards show exact price cadence/currency, trial/billing transition and key limits. Comparison rows need plain-language explanations and unavailable/not-included states. Selection persists into signup where supported, validates server-side and handles expired/unavailable prices gracefully.

## 10. UI / Design Requirements

Use shared cards/buttons/tables/FAQ. Avoid manipulative “most popular,” false scarcity, countdowns, preselected add-ons or visual hiding of exclusions. Monthly/annual control must be keyboard-accessible. Responsive comparison must retain plan headers and row meaning.

## 11. Content Requirements

Use approved plan and entitlement names, define allowances and say what happens on limits only if verified. Explain annual savings mathematically from current prices and rounding policy; do not hard-code marketing values separately. FAQ covers trial, billing interval, cancellation, payment methods, taxes/processing and data access only where policy/code supports the answer.

## 12. SEO Requirements

Provide unique title/description/canonical and one `h1`. Ensure SSR exposes useful pricing content when safe, while avoiding stale cached values. Do not put checkout/session/provider return URLs in sitemap. If catalog cannot be publicly rendered, keep truthful indexable explanatory content and clear state.

## 13. GEO / AEO Requirements

Answer plainly: how Good Hours is priced, what each plan includes, whether a trial exists, monthly versus annual differences and what is separate. Preserve currency/jurisdiction qualifiers so answer engines cannot detach a misleading universal price.

## 14. Structured Data Requirements

Only attach Offer/price data to a verified Good Hours Product/SoftwareApplication entity when visible values, availability, currency and URLs exactly match authoritative active catalog data. Do not emit historical/sandbox prices, aggregate ratings, invented `priceValidUntil` or offers for unavailable regions. Prompt 23 performs final validation.

## 15. Internal Linking

Link global/home/features/industries/use cases → Pricing; comparison entitlements → explanatory feature pages; Pricing → terms/privacy/support and verified signup. No link should bypass server validation into raw provider checkout.

## 16. Conversion Requirements

CTA passes only approved plan/interval identifiers, never client-trusted price/entitlement values. Define anonymous, authenticated-unsubscribed and already-subscribed behavior. Track pricing view/interval/plan/CTA with privacy-safe context. Confirm signup completion and checkout are separate stages; do not count button click as purchase.

## 17. Responsive Requirements

At 360px, interval control, prices, qualifiers, plan actions and comparison remain understandable with no horizontal trap. Use stacked cards or an accessible table strategy; validate sticky headers if used. Test tablet and large-text zoom.

## 18. Accessibility

Meet WCAG 2.2 AA. Use a real fieldset/radio or accessible tab pattern for interval selection, semantic table headers, screen-reader-readable price cadence, visible focus, contrast and non-color inclusion markers. Announce dynamic price changes appropriately without excessive chatter.

## 19. Performance Requirements

Fetch/render catalog efficiently without provider browser calls on initial page. Avoid layout shift when data resolves, large comparison JS and redundant Paddle.js on the public informational page. Load provider SDK only at the owned checkout step when needed.

## 20. Analytics

Adapt to the existing instrumentation contract: pricing viewed, interval changed, plan selected, signup started and later verified completion. Use internal stable plan/interval keys—not raw provider IDs, email or payment data. Respect consent and avoid duplicate SSR/hydration events.

## 21. Security / Privacy Considerations

Server validates catalog availability, currency, price and entitlement. Never expose API keys, webhook secrets, raw provider errors or signed customer URLs. Maintain CSRF/rate limits and prevent query tampering/open redirects. Payment details remain provider-handled under existing architecture.

## 22. Implementation Instructions

1. Trace catalog → controller/view → signup intent → billing checkout and document authority at each boundary.
2. Reuse or extract a server-owned public pricing presenter; never duplicate amounts in Vue/content files.
3. Implement empty/expired/misconfigured states and environment-safe behavior.
4. Build page/comparison/FAQ and validated CTA handoff.
5. Add tests for active/effective prices, intervals, savings, limits, tampering, unavailable catalog and user states.
6. Update IA, claims, commercial decisions and status; do not change economics without explicit decision.

## 23. Do Not

- Do not invent/hard-code prices, savings, limits, trial, features, fees or “popular” labels.
- Do not expose sandbox IDs, call provider APIs from unauthenticated UI, or use Paddle for client appointment payments.
- Do not claim live checkout certification, worldwide availability, tax/legal conclusions or guaranteed message delivery.
- Do not change plan catalog/business rules as a marketing convenience.

## 24. Acceptance Criteria

- Every displayed commercial value derives from an approved authoritative source and has tested effective-date behavior.
- Monthly/annual, savings, trial, limits, entitlements and processing disclosures are clear and accurate.
- CTA handoff is server-validated and works for relevant auth/subscription states.
- No sandbox/provider secret or unsupported launch claim leaks.
- Metadata/schema/linking/responsive/accessibility/tests/production build pass.

## 25. Validation / Testing

Run billing-focused PHP tests plus public page/CTA tests, seed/catalog fixture tests, tampering/effective-date/unavailable-state cases, route/link/canonical/schema checks, SSR and `npm run build`, accessibility/keyboard checks, screenshots at 360/tablet/desktop, console/hydration review and `git diff --check`. Do not run destructive provider sync or live charges.

## 26. Completion Report

Return these exact fields:

- Files changed
- Routes created
- Routes modified
- Components created
- Components reused
- SEO changes
- Schema changes
- Analytics changes
- Tests run
- Build result
- Warnings
- Assumptions
- Deferred items

Include catalog authority/evidence, displayed plans/currencies, external certification blockers and whether Prompt 21 is unblocked.
