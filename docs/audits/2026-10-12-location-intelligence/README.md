# Google Maps and location intelligence delivery

Started 2026-10-12; final verification 2026-10-13.
Requirements: FR-02/03/05/06/09/13/19; ADR-064.

## Audit findings and implementation plan

- Locked stack: Laravel 13.25.0, Vue 3.5.34, Inertia Laravel 2.0.25 / Vue 3.6.1,
  Vite 8.2.1, Tailwind 4.1.14 and DaisyUI 5.1.27. Jetstream 5.5.3, Livewire 4.4.0
  and Filament 5.7.6 remain the LaraFast/admin foundation; customer and owner salon
  workflows use Inertia/Vue, not a new Livewire or standalone application.
- Verified-email onboarding already provisions a tenant, owner membership and
  default Location. Four guided decisions, readiness, publication, starter staff /
  services and reviewed hours are implemented. Integrate into those flows.
- Location already contains address/contact/time zone and decimal(10,7) coordinates.
  Branch-specific staff/service pivots, dated qualifications, resources, UTC/local
  scheduling and permission scopes exist. Add only missing entrance/address fields,
  use the existing activation command and protect existing schedules and history.
- Public primary-host and fallback routes already share BookingBusinessController,
  PublicBookingService and Welcome.vue. Availability, holds, idempotent confirmation,
  self-service cancellation/rescheduling and policy/deposit checks are implemented.
  Improve discovery and location presentation without introducing another engine.
- Communications already use immutable intents/template versions, SES email, Twilio
  SMS, consent, quiet hours and reliable retries. WhatsApp adapters are dormant under
  ADR-041. Add snapshot variables/captured arrival footer and calendar details to
  those pipelines. Account/security mail is a separate existing context.
- Existing Google integration was authentication only. Location address was free
  text; no supported Maps search/pin frontend existed. Customer confirmations used
  live Location address, allowing an edit to change historical arrival details.
- Paid branch capacity uses the existing EntitlementEvaluator/Stripe projections.
  The Maps change does not modify prices, subscription rules or purchased capacity.
- Existing Product components, PublicBookingLayout/AppLayout and semantic tokens
  supply the presentation system. Owner location entry had duplicate single-branch
  selection, repeated business address and a manual map-link field; those are now
  streamlined around one canonical editor.
- The global Permissions-Policy previously disabled geolocation, including public
  booking. Only successful named booking documents now permit `(self)`; geolocation
  remains an explicit customer action and other surfaces retain denial.

Implementation followed this order: shared data/provenance and safe migration;
owner setup/location editor; customer branch/card integration; appointment snapshots /
notifications/calendar; regression/build/browser checks and Cloud/deployment guide.

## Delivered improvements

1. Supported Places (New) widget, automatic session ownership, one selected-details
   fetch with four required fields, required Google attribution and error fallback.
2. Smart address/business search, manual global-address support, optional corrections,
   readable country names, owner-positioned keyboard/touch entrance marker, unit /
   floor/landmark/parking notes and customer preview. No coordinate-entry controls.
3. Sole/primary address summary compatibility, business-scoped revision/authorization,
   duplicate-name protection, primary selection, untouched legacy geometry, preserved
   split hours and protection against upcoming-appointment timezone changes.
4. Eligible-branch-only public catalog; automatic single-location choice; inline
   multi-branch cards, name/city/postal search and optional memory-only nearby sort.
   Branch switching retains common services/add-ons and rechecks actual staffing.
5. Responsive location card with clear branch/address hierarchy, compact arrival
   disclosure, reusable lazy interactive map and Google/Apple directions. Mobile
   single-branch booking shows the address naturally; multi-branch mobile has an
   arrival/directions disclosure. Desktop uses the appointment sidebar.
6. New/legacy appointment snapshots; same-branch rescheduling preserves arrival
   truth; deliberate new branch uses its actual location; changed held addresses
   require current review before public confirmation.
7. Snapshot address/arrival/directions in confirmation, management/cancellation /
   reschedule screens, confirmation/reminder email and consented SMS, and calendar.
   Existing queued messages/templates keep their captured content. No Google calls
   are made during booking commits, reminder creation or message generation.
8. Updated public Maps privacy/terms disclosures, PRD, accepted decision, module,
   environment example and deployment/rollback runbook. Relocation-amendment policy
   is explicitly OPEN-23; no history rewrite was inferred.

## Migration and local data evidence

`2026_10_12_000002_add_location_intelligence.php` adds 12 Location columns:
Place ID, five address components plus country, two decimal(10,7) entrance fields,
two instructions and `is_primary`; one nullable JSON `location_snapshot` column
is added to appointments. Existing `address`, `time_zone`, latitude/longitude and
relations are reused. No installed package was added.

The normal local MySQL database had 23 locations and 141 appointment assignments.
After a private snapshot, the migration completed; comparison confirmed every
legacy location field and assignment unchanged and 141 snapshots populated.
The running 8027 server uses a separate local SQLite pricing-preview database;
it also received the migration after a private SQLite backup. A dedicated
`maps-flow-review` synthetic tenant there supplies two branches, different staff
and differing services for browser QA. It is not a production customer account.
No external message, real appointment or Stripe object was created in browser QA.

## Automated verification

- Complete backend suite: **730 passed, 30 existing intentional skips, 6,545
  assertions** on PHP 8.4.8. These skips retain the existing disabled-feature /
  MySQL-concurrency boundaries; they were not increased to pass this task.
- **97 frontend tests pass.** Helpers and compiled real Vue components cover Places selection masks,
  manual fallback, quota/network failure, stale results, entrance click/drag,
  coordinate-free editor, nearby ordering, opt-in geolocation, branch switching,
  service/add-on retention and lazy map reuse. Privacy/SEO checks after updating
  the Maps disclosure to privacy v1.1 pass **33 tests / 542 assertions**.
- Backend additions cover guided setup persistence, coordinate/country/address
  validation, stale writes, authorization/tenant isolation, primary/duplicate
  behavior, unchanged hours/legacy data, assigned snapshots, timezone edits,
  single-branch eligibility, other-branch service/staff rejection, reschedule,
  changed held addresses, confirmation/reminder **email and SMS**, Google/Apple
  links, calendar and migration rollback/backfill.
- External Google interactions are mocked in component tests; PHP tests prohibit
  stray provider calls for new location tests. Existing route compatibility and
  communication consent/idempotency checks continue passing.
- Client and SSR builds, 17-route public asset budgets, scoped Pint and diff
  whitespace checks pass. Existing build warnings remain the DaisyUI `@property`
  optimization notice and unresolved-at-build-time Manrope font paths.
- One intermediate complete-suite attempt overlapped a rebuild and the existing
  login test saw a temporarily missing Vite manifest. The final complete run was
  made after build completion and passes without changing or skipping that test.

## Browser steps and evidence

1. **Owner search — working.** Google initially rejected the 8027 referrer; after
   the owner changed Cloud configuration, real suggestions and keyboard selection
   populated the address and map. A public CN Tower address was used only as an
   unsaved interaction sample; the owner's actual branch was not replaced.
2. **Entrance placement — working.** Keyboard marker movement updates the customer
   preview's destination to owner coordinates and exposes Apple directions. Optional
   notes/corrections stay closed and coordinate inputs are absent. Owner widths
   320/390/1440 had no horizontal overflow.
3. **Single branch — working.** The synthetic second branch was temporarily inactive:
   Downtown selected itself without a selector, with address, optional notes, map
   control and directions integrated into the mobile services step. It was restored
   to active after the check.
4. **Multi-branch selection — working.** At 320px, select a shared haircut plus a
   Downtown-only treatment, switch to Riverside: haircut remains; unavailable
   treatment disappears with clear feedback. Postal-code search returns matching
   branches without navigating away. No geolocation prompt appeared on load.
5. **Branch availability/review — working.** Riverside returns location-local times;
   its held review identifies **Sam**, the Riverside branch/address and its exact
   navigation destination. Customer contact fields stay blank. The hold was released
   through Change time; no terms acceptance or final booking was submitted.
6. **Lazy customer map — working.** No Google Maps script exists on initial booking
   load. View map loads the saved entrance with visible native attribution; hiding /
   reopening reuses the map. Tablet 768px and desktop 1440px also have no overflow.
   No browser permission was granted or personal geolocation collected during QA.
7. **History/notifications — automated evidence.** Actual assigned snapshot details,
   later branch/primary edits, reminder/confirmation email/SMS, self-service and
   calendar are proven by backend tests. Live provider delivery is not certified.

Accepted final captures:
- [Owner entrance workflow](05-final-owner-desktop.jpg)
- [Owner at 320px](06-final-owner-320.jpg)
- [Branch switch at 320px](07-branch-switch-320.jpg)
- [Riverside review / Sam](08-riverside-review-320.jpg)
- [Single branch at 390px](10-single-branch-390.jpg)
- [Tablet booking](11-booking-tablet.jpg)
- [Desktop booking](12-final-booking-desktop.jpg)
- [Customer map alongside address](13-booking-map-desktop.jpg)
- [Final booking review with live map](14-final-booking-review.jpg)

Earlier numbered captures document implementation iterations; the later public
location-card styling replaces the plain card seen in the early owner captures.
Full-page captures can show fixed/sticky bars at the captured viewport position.
Normal viewport observations and DOM width measurements were also checked.
A later owner-save browser attempt met an expired sign-in session; authenticated
save/revision/isolation behavior is covered by automated tests. The isolated 8041
server was stopped after browser access was declined; QA continued on the owner's
explicitly authorized existing 8027 origin.

## Manual production steps and limits

Use the existing `VITE_GOOGLE_MAPS_API_KEY` with separate target-environment browser
keys; provide a project-owned `VITE_GOOGLE_MAPS_MAP_ID`. Enable Maps JavaScript API
and Places API (New), attach billing, set API/referrer restrictions, exact main /
dashboard / booking hosts, quotas/alerts, and build the matching assets. See the
[complete runbook](../../deployment/google-maps.md).

Google setup was verified only on the authorized local 8027 origin. Production /
staging restrictions, DNS/TLS, EEA/account-specific storage/legal review, independent
accessibility and browser/device coverage, actual customer geolocation permission,
and real email/SMS delivery need environment-specific certification. Privacy v1.1
requires review under the existing legal approval gates. No claim of guaranteed
routing to a building entrance is made; arrival notes supply the practical context.

Rollback source/assets while retaining the additive schema/snapshots. Dropping the
new columns destroys entrance/arrival/history data and requires an intentional
export/recovery decision. Legacy snapshots cannot recover addresses changed before
this migration. WhatsApp, paid travel-time routing and an intentional relocation /
appointment-address amendment interface remain unimplemented under their recorded
scope boundaries. Existing unrelated pricing/brand work was preserved.
