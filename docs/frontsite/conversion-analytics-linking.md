# Conversion, telemetry and public link contract

Status: Accepted Prompt 26 implementation record (2026-08-16)

## CTA hierarchy

| Context | Primary | Secondary | Destination / qualification |
| --- | --- | --- | --- |
| Global/product/solution/use case | Start your trial | Relevant feature/resource/pricing link | Anonymous `register`; authenticated `dashboard`; verified trial occurs only after email verification |
| Pricing plan | Choose Starter / Choose Pro | Interval radio and comparison | Server-validated plan/interval preference; never a raw provider checkout |
| Company/security | Start your trial | Review product/security | Restrained evaluation action after the trust answer |
| Resource/guide/article | Read the guide/article | Exact supporting feature | Core advice is never gated |
| Legal | None | Normal public navigation | No marketing insertion into review-draft legal text |

Demo, contact, newsletter, chat and sales-callback actions remain absent because
no staffed, consent-owned receiving workflow exists.

## Event dictionary and consent boundary

| Event | Trigger/source | Allowed properties | Authority/deduplication | State |
| --- | --- | --- | --- | --- |
| `marketing_cta_clicked` | Passive browser click on a declared CTA | bounded context, action, query-free path | Observed click only; one DOM listener; never conversion evidence | Vendor-neutral DOM event; no storage/export consumer enabled |
| `marketing_pricing_interval_changed` | User changes the semantic pricing radio | interval, query-free path | User interaction only; no initial hydration event | Vendor-neutral DOM event; no storage/export consumer enabled |
| `trial.qualified_started` | Verified onboarding transaction creates Business + trial | source only | Server event keyed by registration-intent ID; exactly once | Persisted in existing first-party append-only instrumentation |
| `subscription.paid` | Trusted provider lifecycle state | existing privacy-safe catalog fields | Signed/API-verified provider identity | Existing application metric; browser checkout is not authority |

There is no analytics vendor, pixel, tag manager, fingerprinting, replay, bot
classification or UTM/referrer store. The DOM adapter provides a small future
boundary but intentionally has no network consumer while OPEN-12 lacks provider,
purpose, consent, retention and deletion approval. Query strings are untrusted,
never included in canonical/schema/event path and are not persisted through
registration. Plan/interval preference is functional checkout state, not
marketing attribution.

## Link graph

The global shell links every top-level hub plus Company/Security/legal. Each
feature, solution, use case and guide is linked by its curated hub; Resources
links Blog and guides; guides/use cases link exact product evidence. The
deterministic sitemap contains 23 base indexable URLs plus only eligible
articles. The automated crawl requests every listed URL, requires 200,
index/follow and exact self-canonical, and verifies UTM-like query values do not
alter metadata. Private, tenant, secure, auth, legal-draft, provider and admin
routes remain intentionally outside the graph.

## Prompt 27 journey result

Synthetic pricing interaction changed the semantic monthly/annual control and
kept plan/interval values within the server allow-list. Registration tamper
tests reject unavailable, expired, and modified values; verified onboarding
records one qualified-trial event. The public event adapter still has one
passive listener, sends no request, stores no identifier, and never includes a
query string. The 23-page link/canonical crawl passes. No provider-side
analytics journey was attempted because OPEN-12 deliberately leaves no vendor
or consent/retention authority.
