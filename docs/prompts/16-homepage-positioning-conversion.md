# Prompt 16 — Homepage Positioning and Conversion

Execute after Prompt 15. Build the Good Hours homepage as a truthful product narrative and acquisition path—not a generic template or an appointment-booking-only landing page.

## 1. Mission

Create a premium, fast, accessible homepage centered on: “Run your salon or barbershop from booking to checkout.” Demonstrate how one operational system helps owners get booked, protect the calendar, run the day, get paid, know clients and know the business, then provide a clear path to the actual trial/signup flow.

## 2. Why This Phase Exists

The current `Home.vue` is Larafast boilerplate with generic integrations, partners and claims. The homepage must establish Good Hours positioning, product truth and conversion hierarchy before deeper feature and solution pages are built.

## 3. Prerequisites

- Prompt 14 IA/claim ledger and Prompt 15 marketing shell are complete and verified.
- Registration/trial destination and current billing state are confirmed in code.
- Approved product screenshots exist or can be captured from seeded, non-sensitive local data.

## 4. Read Before Changing Anything

Read mandatory project docs; Prompt 14/15 outputs; PRD requirements for booking, calendar, staff, clients, payments, inventory/reporting and communications; current `Home.vue`; shared marketing primitives; register/auth controllers/pages; relevant status evidence; and screenshot evidence under `docs/evidence`. Inspect current rendered homepage and production build.

## 5. Scope

- Replace the homepage with a complete narrative: hero, real product proof, outcome groups, representative operational workflow, industry bridge, switching/migration reassurance only if supportable, pricing teaser, truthful FAQs and final CTA.
- Cover appropriate combinations of online booking, calendar, staff, walk-ins, resources, clients, reminders, deposits/no-show protection, checkout, reporting and controlled inventory/commissions references.
- Add homepage-specific metadata, semantic content, responsive composition and tests.

## 6. Out of Scope

Detailed feature pages, industry pages, comparison pages, public pricing implementation, mass articles, a demo backend or changes to authenticated modules. Do not make the homepage a complete feature catalog.

## 7. Product Truth

Lead with the full operating system, not “booking software” alone. Only use capabilities verified in current Phase 1 code/status. Qualify payments, reminders and production-dependent behavior accurately. Do not represent Prompt 13 provider certification, legal approval, domain/trademark clearance or status/uptime as complete. Phase 2 features are excluded.

## 8. Information Architecture

Use one `h1` and a deliberate sequence: problem/outcome → visible product proof → connected workflow/outcomes → fit/industries → evaluation support → pricing/trial bridge → FAQ → final CTA. Each section must have a distinct job and lead to approved deeper routes; omit any section that repeats without advancing understanding.

## 9. UX Requirements

Within the first viewport, explain what Good Hours is, who it serves and the primary next action. Let scanning visitors understand the six outcomes through descriptive headings. Keep product screenshots readable and expandable/accessibly described where needed. CTA destinations must work; returning authenticated users see an appropriate product action.

## 10. UI / Design Requirements

Use Prompt 15 primitives and brand tokens. Favor authentic UI crops, restrained editorial typography, useful diagrams and calm whitespace over stock-photo/template ornament. Every visual supports a claim. Alternate section rhythm carefully without creating a patchwork. Provide purposeful empty/loading/error handling only where interactive UI is real.

## 11. Content Requirements

Write original, plain-English copy for owners and managers. Recommended lead is “Run your salon or barbershop from booking to checkout”; supporting copy must connect bookings, schedules, clients and payments without hype. Avoid clichés, blame, fear/urgency and absolute outcomes. No testimonials, customer logos, ratings, usage numbers, “save X hours,” ROI or integration logos without auditable evidence. Explain jargon such as resources and waitlist.

## 12. SEO Requirements

Set a unique, natural title, description and canonical aligned to Good Hours and salon/barbershop management intent. Use semantic headings and crawlable links. Avoid stuffing “salon software” into every section. Image names/alt text should describe actual UI. Ensure SSR output contains core copy and links.

## 13. GEO / AEO Requirements

Include concise visible answers to: What is Good Hours? Who is it for? What does it manage? How does it differ from a booking-only tool? Use factual, self-contained sections and FAQs that a person can quote without losing qualifiers. Do not mention AI-search optimization to visitors.

## 14. Structured Data Requirements

Add only schema approved by the Prompt 14/23 plan and supported by visible content. WebPage and verified Organization/Product/SoftwareApplication may be candidates, but do not invent ratings, price offers, operating address, awards or reviews. FAQ markup is allowed only for visible, eligible FAQs and must match them exactly; defer uncertain markup to Prompt 23.

## 15. Internal Linking

Link each outcome to the strongest approved feature or solution cluster, industries to differentiated pages when available, pricing teaser to Pricing, educational questions to resources, and CTAs to real signup. During sequential rollout, do not publish dead links; use only existing/created routes and leave a documented follow-up.

## 16. Conversion Requirements

Define one primary trial/signup CTA label based on actual trial behavior, one low-friction evaluation action such as Explore features or See pricing, and an existing-customer login. Repeat CTAs at decision points without turning every section into a banner. Preserve honest billing/trial qualifiers. Do not offer demos, consultations or migration services unless an owned fulfillment flow is approved.

## 17. Responsive Requirements

Design content order mobile-first. At 360px, hero copy, CTA stack, proof images, feature grids, FAQs and footer must remain readable with 44px targets and no crop/overflow. Do not shrink desktop screenshots to illegibility; use responsive crops or separate verified views. Validate tablet and wide desktop balance.

## 18. Accessibility

Use landmarks, one `h1`, logical headings, descriptive links, meaningful alt text or empty alt for decoration, keyboard-accessible disclosure/media controls, visible focus and sufficient contrast. Respect reduced motion and avoid autoplay. Product imagery needs nearby text conveying the same substantive information.

## 19. Performance Requirements

Protect the hero LCP: properly size/compress the primary asset, preload only if measured, reserve dimensions and avoid heavy video/carousels. Lazy-load below-fold screenshots, minimize component JS and prevent font/layout shift. Measure SSR production output and bundle change.

## 20. Analytics

Use only the approved analytics contract. Give CTA and key navigation events stable page/placement context; never include free text or PII. Do not claim conversion improvement from unmeasured changes. Prompt 26 will complete funnel attribution.

## 21. Security / Privacy Considerations

Use synthetic/demo screenshot data with no customer PII, booking secrets or real payment identifiers. Do not render unsafe HTML or expose environment/provider state. Signup remains protected by its existing validation, CSRF and rate limits.

## 22. Implementation Instructions

1. Map each proposed section and claim to requirement/status evidence before writing it.
2. Remove the old Partners/Integrations/AboutMe/generic content from the rendered homepage unless a component is repurposed truthfully.
3. Implement with Prompt 15 primitives; add page-specific components only when reusable structure does not fit.
4. Capture current product UI from approved local seed state or use existing verified evidence; record source/date/view.
5. Add metadata and focused page/CTA tests.
6. Verify anonymous/authenticated, mobile/desktop and SSR behavior; update docs/status with evidence.

## 23. Do Not

- Do not fabricate proof, feature availability, integrations, quotes, metrics or “all-in-one” absolutes.
- Do not use old Amazon/Netflix/Spotify/OpenAI/PayPal assets as partners.
- Do not turn the page into a keyword list, copy competitor copy, introduce a carousel, or hide essential copy in animation.
- Do not alter operational product modules or booking routes.

## 24. Acceptance Criteria

- Above the fold states product, audience and working primary CTA clearly.
- The page communicates the connected booking-to-checkout system and six outcomes using verified evidence.
- No Larafast/placeholder/unsupported content remains on the homepage.
- Metadata, semantics, links, images and CTA destinations are valid.
- 360px/tablet/desktop, keyboard, accessibility, SSR and production build checks pass.

## 25. Validation / Testing

Run focused feature/component tests, route/CTA checks, `npm run build`, SSR HTML inspection, link and metadata checks, image audit, console/hydration review, automated accessibility scan plus keyboard/manual checks, and visual screenshots at representative widths. Run `git diff --check` and relevant regression tests for login/register/public booking.

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

Include claim-to-evidence and screenshot references and whether Prompt 17 is unblocked.
