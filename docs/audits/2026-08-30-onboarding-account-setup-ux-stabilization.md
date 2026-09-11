# Onboarding, account setup, and billing UX stabilization audit

Date: 2026-08-30

Related requirements: FR-01, FR-02, FR-03, FR-04, FR-05, FR-11, FR-14,
FR-15, and FR-19.

## Evidence and root causes

The supplied registration evidence showed an Inertia error overlay immediately
after account creation and a `401` response from a previously visited Business
configuration URL. The registration endpoint did authenticate the new User, but
Fortify's default response honored the guest session's stale `url.intended`
value. That redirected the just-created User to a Business URL belonging to an
earlier session before verified-owner provisioning had created the new Business.
The authenticated shop route group also used Sanctum's mixed guard for normal
browser navigation, which made this handoff less predictable than the intended
web-session guard.

The account-configuration surface had the correct domain objects but presented
them as one long form plus separate Staff and Availability steps that repeated
the same foundation editor. The launch-readiness panel gave the checklist too
much visual weight and did not clearly separate required blockers from optional
improvements. Country and currency options were a small manual list. The
Business tax posture was stored, while the commerce tax rate could not be
edited from the setup surface and regional values were not consistently shared
with all money and phone-entry surfaces.

The billing plan-change endpoint reached Stripe, but the browser immediately
performed a full reload. Because signed Stripe events intentionally remain the
authority for local entitlement changes, the old plan could still be visible
during the confirmation window and look like a failed button. Concurrent clicks
could also create competing pending-change requests, and plan changes did not
fail closed when signed webhook delivery was not ready.

The Client directory had no manual-create route or action even though the
identity service already supported conservative matching. Phone fields used raw
text inputs with inconsistent country-code guidance.

## Implemented architecture

- Registration and verified-email responses now deliberately regenerate the
  session, discard stale protected destinations, and route through email
  verification before the idempotent owner-onboarding service sends the owner
  to Salon setup.
- Browser shop routes use the web-session guard. Inertia navigation receives a
  hard location handoff only after authentication is established.
- `CountryCatalog` derives the complete 249-country ISO list, likely currency,
  and valid IANA time zones from ICU. These are suggestions: the owner retains
  control until save.
- The Salon setup controller synchronizes Business currency/tax posture and the
  commerce setting's currency, inclusive/exclusive behavior, tax rate, and
  cancellation cutoff transactionally. Currency changes fail safely after
  commercial history exists instead of rewriting historical amounts.
- Tenant regional context is shared with Vue money formatters. Inventory
  creation ignores browser-supplied currency and uses the Business currency.
  Public booking carries the Business country and currency.
- `PhoneInput` is the shared international phone control. It defaults from the
  Business country, permits another country, emits E.164, and is used in Salon
  setup, Clients, Calendar, Walk-in Queue, public booking/waitlist, client
  corrections, and secure appointment contact updates. `E164Phone` provides
  matching server-side enforcement.
- Settings is now named **Salon setup** and grouped into Overview, Business
  details, Bookable foundation, Booking experience, Import, and Preview.
  Readiness uses six actionable areas with explicit Ready/Required states and a
  separate optional-improvements section. Staff and Availability are summarized
  as part of the bookable foundation and link to their real workflows.
- The Client directory now has an authorized **Add client** workflow for
  walk-ins, phone bookings, and migrated clients. Exact same-name/contact input
  reopens the existing client; possible non-exact matches remain review
  candidates.
- Plan changes now require a ready Stripe/webhook configuration, reject a
  second unresolved change, expose processing versus scheduled state, disable
  repeat actions, and show success/error feedback inside the confirmation flow.
  Signed provider evidence remains authoritative for the eventual active plan.

## Verification

- Registration coverage proves a stale protected intended URL cannot escape
  signup and that the authenticated session exists before Inertia navigation.
- Salon setup route-integrity coverage prevents a Vue render from referencing
  a Ziggy route name that is not registered; the verified-email destination
  now routes to the dedicated Team & availability workspace introduced by
  Wave 2; Services and Location setup use their own canonical workspaces too.
- Business-configuration coverage proves all 249 ISO countries are available
  and that India derives INR/Asia-Kolkata while tax/currency settings synchronize
  to commerce state.
- Client coverage proves manual creation, exact-duplicate reuse, and role denial.
- Billing coverage proves one authenticated immediate upgrade reaches Stripe,
  returns a stable processing state, and blocks a duplicate request.
- Focused registration, configuration, client, billing, communications,
  booking, Calendar, and Walk-in Queue suites pass.
- The production client and SSR builds pass on Node 24. Desktop and 390 px
  registration checks show no horizontal overflow and no browser-console errors.
- The complete PHP run reports 257 passed / 2,306 assertions / 28 intentional
  skips. Its only failure is the pre-existing frontsite budget test referencing
  two product-shell evidence images that were already deleted in the working
  tree and are unrelated to this implementation.

## Remaining launch requirements

- The inspected local environment still needs `STRIPE_WEBHOOK_SECRET` before
  Checkout or plan changes can be certified against live signed delivery.
- A real Stripe test-mode upgrade, cancellation, failed-payment recovery, and
  Customer Portal pass remains required after webhook configuration. Automated
  provider-contract coverage does not substitute for that certification.
- Country-derived currency and time-zone suggestions do not constitute legal or
  tax advice. Final tax categories, invoice wording, rates, privacy regime, and
  receipt obligations still require the responsible launch-market reviewers.
- The overall production launch remains subject to the existing launch gates in
  `docs/project-status.md`; this audit does not change the NO-GO decision.
