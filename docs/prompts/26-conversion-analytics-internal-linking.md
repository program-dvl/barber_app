# Prompt 26 — Conversion, Analytics and Internal Linking

Execute after Prompt 25. Connect the complete public website into one measurable, privacy-respecting acquisition journey using application-owned conventions and a tested internal link graph.

## 1. Mission

Standardize CTA hierarchy and destinations, carry consent-safe attribution through registration, define reliable funnel events, and make every approved public page discoverable through useful navigation/contextual links without turning the site into an over-tracked sales funnel.

## 2. Why This Phase Exists

Individually sound pages can still produce dead ends, inconsistent trial language, lost attribution, duplicate analytics and orphan content. Current application instrumentation is operational/product-focused and no marketing analytics vendor is approved, so the acquisition contract must remain vendor-neutral and privacy-owned.

## 3. Prerequisites

Prompts 14–25 are verified. Registration/trial/pricing destinations, indexation matrix, consent/legal posture and content graph are stable. If Product has not approved a demo/contact funnel or analytics vendor, do not invent one; record the decision and implement the application-owned contract only.

## 4. Read Before Changing Anything

Read mandatory docs, prior Phase 1.5 reports, `InstrumentationService`, event model/catalog and booking analytics conventions, registration/business/trial flow, session/cookie middleware, pricing selection, current CTA components, every public route/link, privacy/cookie decisions and tests. Inspect SSR/hydration behavior and event payload logs using synthetic data.

## 5. Scope

- Define site-wide primary/secondary/tertiary CTA rules and repair destinations/copy.
- Implement privacy-safe landing/referrer/UTM attribution persistence through signup/business creation and verified completion where approved.
- Define bounded funnel events and properties, consent/loading/deduplication rules and vendor adapter boundary.
- Audit/fix global/contextual/related/breadcrumb links, redirect hops, broken links and orphan indexable pages.
- Build funnel/link validation and reporting documentation.

## 6. Out of Scope

Buying/configuring an analytics, CRM or ad platform; ad pixels; cross-device identity; session replay; fingerprinting; lead scoring; fake demo funnel; changing subscription economics; emailing leads; conversion-rate promises or redesigning complete pages without evidence.

## 7. Product Truth

CTA wording reflects actual signup/trial mechanics and availability. “Start trial” is used only when registration creates the verified trial; “Get a demo” exists only with staffed fulfillment. Event names describe observed application state—click is not signup, checkout is not paid subscription, and browser callback is not provider verification.

## 8. Information Architecture

Maintain a journey/link matrix by audience and stage: discovery (Home/industry/use-case/resources) → evaluation (features/pricing/trust) → action (register/login) → verified product state. Preserve intentional non-discovery of private/transactional routes. Identify page hubs, related clusters and exit/recovery paths.

## 9. UX Requirements

One clear primary action per decision context, consistent labels and predictable destinations. Preserve selected plan/interval and attribution without surprising users. Forms explain consent and recover gracefully. Returning/authenticated/subscribed users receive relevant actions; do not trap them in signup loops.

## 10. UI / Design Requirements

Use existing CTA/navigation/link components and hierarchy. Remove visually competing CTAs, deceptive sticky bars and inconsistent button/link semantics. Related content should be editorially useful, not an SEO list. Consent UI, if required, must use documented brand components and allow equivalent reject/manage choices.

## 11. Content Requirements

Create a CTA copy matrix with page/stage, primary/secondary label, destination, eligibility and qualifier. Keep trial/pricing copy aligned to Prompt 20. Event/property documentation uses stable definitions and examples. Avoid dark patterns, urgency, hidden conditions or vague “Get started” when a more precise action is available.

## 12. SEO Requirements

Analytics parameters do not create indexable duplicates; canonicals exclude tracking parameters per Prompt 23. Links are crawlable anchors where navigation is intended. Do not add `nofollow` to internal acquisition links, hide orphan fixes in scripts or create redirect chains for tracking.

## 13. GEO / AEO Requirements

Internal links connect answer pages to authoritative definitions/evidence, reinforcing consistent entity relationships. CTA additions cannot displace or gate answer content. Do not insert machine-targeted exact-match anchors.

## 14. Structured Data Requirements

Analytics and CTA variants must not change the factual schema graph inconsistently. If experiments are not an approved system, do not add them. Breadcrumb/entity URLs remain canonical without UTM parameters; offer/action markup uses verified destinations only.

## 15. Internal Linking

- Crawl from Home and each hub; compare discovered indexable pages with sitemap/IA.
- Fix broken links, dead ends, redirects, misleading anchors and orphan pages.
- Ensure hub ↔ spoke, sibling contextual and resource ↔ product relationships are relevant.
- Cap automated related links and use deterministic/editorial relevance.
- Never expose signed/private/auth/admin/webhook routes for crawl coverage.

## 16. Conversion Requirements

Define events such as `homepage_start_trial`, `feature_start_trial`, `industry_start_trial`, `pricing_view`, `pricing_start_trial`, `demo_request`, `signup_started` and `signup_completed` only after adapting names to existing conventions. Prefer a bounded event such as CTA clicked with allowed page/placement properties if that matches architecture. Specify trigger, authoritative layer, once/exactly-once expectations, required/forbidden properties, consent and validation. Signup completion is server-authoritative after verified owner/business/trial creation; billing success requires trusted provider state.

## 17. Responsive Requirements

CTAs, forms, consent controls and related links remain usable at 360px, tablet, desktop, zoom and touch with no obscured content. Sticky conversion UI must not cover focus/content or violate target spacing. Maintain action priority when buttons stack.

## 18. Accessibility

CTA labels make sense out of context, controls use correct semantics, errors/status are announced, focus moves only when appropriate and consent is keyboard/screen-reader complete. Do not encode tracking in inaccessible click handlers or intercept modified/new-tab navigation.

## 19. Performance Requirements

Keep event dispatch asynchronous/non-blocking, small and failure-tolerant. Do not delay navigation or duplicate payloads during SSR hydration. Attribution storage is bounded and expiring. No heavy tag manager/session replay by default; measure any approved script impact.

## 20. Analytics

Create an event dictionary with purpose, trigger, source of truth, properties, consent class, retention, deduplication key and owner. Use first/last landing/referrer/approved UTM fields only as decided; validate allowlists/lengths and strip PII. Store server-side completion where accuracy matters. Add adapter boundary for a future provider without coupling domain events to vendor payloads. Define QA/debug mode that never logs secrets.

## 21. Security / Privacy Considerations

Treat query/referrer/UTM values as untrusted. Allowlist, length-limit, encode and expire them; block script/header injection and open redirects. Never capture email/name/phone, rich form text, booking tokens, provider IDs, secure URLs or tenant/client identifiers. Honor consent/withdrawal and retention authority; OPEN-10 remains unresolved.

## 22. Implementation Instructions

1. Inventory CTAs, destinations, link graph and existing events before changing them.
2. Approve/document CTA and event dictionaries plus attribution data flow and privacy boundaries.
3. Reuse the application-owned instrumentation service/adapter patterns; do not install a vendor by default.
4. Implement server/client triggers at their authoritative stages with idempotency/deduplication tests.
5. Repair CTA/link inconsistencies and orphans; preserve deliberate private-route isolation.
6. Add synthetic end-to-end funnel and crawl assertions plus event payload inspection.
7. Update privacy/architecture/IA/status and unresolved decisions accurately.

## 23. Do Not

- Do not equate clicks with completed signup/payment, fire duplicate hydration events or trust browser provider callbacks.
- Do not collect PII/secrets/private URLs, fingerprint visitors, add session replay or load unapproved pixels/vendors.
- Do not invent a demo/contact workflow, force consent, create redirect chains or expose private routes through linking.
- Do not optimize for event volume at the expense of user clarity.

## 24. Acceptance Criteria

- CTA copy/destinations/hierarchy are consistent and valid for anonymous/authenticated/subscribed states.
- Event dictionary and attribution flow are bounded, consent-aware, privacy-safe, deduplicated and tested at authoritative stages.
- Every indexable page is intentionally linked and crawlable; no broken/orphan/redirect-chain issue remains.
- Tracking parameters do not create canonical/indexation duplicates or leak sensitive data.
- Funnels fail gracefully, remain performant/accessibile/responsive, and tests/build pass.

## 25. Validation / Testing

Run event unit/feature tests, deduplication/idempotency/consent/withdrawal/expiry/input-validation tests, anonymous→register→verified completion synthetic flow, pricing selection flow, no-PII payload assertions, SSR/hydration duplicate checks, full link crawl/orphan/redirect/canonical scan, CTA state tests, production build, performance/accessibility/mobile checks, console/network inspection and `git diff --check`.

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

Attach CTA matrix, event dictionary, attribution/consent flow, crawl/orphan results and whether Prompt 27 is unblocked.
