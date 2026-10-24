# Setup, catalogue, workforce, billing and account documentation audit

Date: 2026-10-24. Evidence type: repository source/UI-label review. This inventory does **not** certify deployed behavior, provider delivery/payment, browser rendering or a production-ready legal/operational launch. Screenshot capture/render evidence is owned by the accompanying Help Center audit.

Prerequisites reviewed: `docs/README.md`, current `project-status.md` entries, affected module specifications, linked PRD requirements and accepted decisions through ADR-069. Installed application code, route/controller authorization and current Vue labels take priority over older audit screenshots and stale marketing tier names.

## Audited workflow and dependency map

| Domain | Source evidence | Requirements | Actual workflow / access / dependencies |
| --- | --- | --- | --- |
| Registration and introduction | `Pages/Auth/Register.vue`; `BusinessConfigurationController`; `Pages/Configuration/Onboarding.vue`; `StarterWorkspaceProvisioner` | FR-01/02/05/19 | Email verification or Google verified signup → four saved decisions → explicit workspace preparation. No payment at signup. Owner profile created only for owner-provider choice. Business type personalizes categories and selected services, not capabilities. New preset is 09:00–21:00 daily; existing catalogues/hours not overwritten. |
| Readiness and publication | `BusinessSetupAccess`; `ReadinessEvaluator`; `Components/Setup/SetupHome.vue`; `BookingPreviewController` | FR-02/03/04/06/09/19 | All-location settings scope; current essentials separate from optional work and online publication. Preview renders real saved public catalogue privately and cannot book. Explicit review and go-live; public contacts/policies/online eligibility still required. |
| Locations and hours | `LocationSetupController`; `BusinessActivationManager`; `LocationEditor.vue`; `SetupHours.vue` | FR-02/03/06/09/19 | Active capacity + all-location settings authority; name/address/time zone required. Google/manual address; optional entrance/arrival notes; primary branch; initial hours. Reviewed split weekly hours; retains affected booked visits. Upcoming visits block branch time-zone change. |
| Profile, policies and branding | `Pages/Configuration/Onboarding.vue`; `BusinessConfigurationController`; `BusinessConfigurationController::updatePublicBookingPolicy` | FR-02/09/13/14/19 | Identity, public contact, region/currency/week start/interval/tax, cancellation/terms/privacy/website, accent; logo/cover entitlement and uploads. Public booking interval, staff selection, new-client rules, cancellation cutoff, waitlist batch and secure-link expiry. |
| Imports | `ConfigurationImportService`; import controller endpoints; Onboarding Import records section | FR-02/04/05/10/16 | Clients/staff/services/products CSV templates + explicit mapping + preview + duplicate decisions + commit. Staff capacity checked. Existing service rows must be skipped; price/time edits require catalogue review. Price template expects minor currency units. Imports do not complete qualification/hours/publication. |
| Workforce directory and profiles | `TeamManagementController`; `TeamWorkspaceQuery`; `StaffProfileDrawer.vue` | FR-03/05/06/08/17/19 | Team/Weekly coverage/Time off/manager Access. Management branch scope; own staff vs branch-wide read scopes. Name/email/location required for new member, login optional. Title does not grant permission. Working branch ≠ login access branch. |
| Invitations and permissions | `AccessPanel.vue`; `StaffInvitationController`; `Pages/Access/StaffInvitation.vue`; `StarterRole` | FR-05/19 | Five starter roles (Owner, Manager, Receptionist, Barber/Stylist, Accountant) plus exact custom capabilities; cannot grant beyond authority. Email-bound single-use seven-day invitation; optional password registration. Cancel/reinvite; disable with reason terminates sessions, restore with reason. Own/other-owner protections. |
| Team schedules and services | `StaffOperationsController`; `StaffProfileDrawer.vue`; `AvailabilityConfiguration` | FR-03/04/05/06/17/19 | Weekly/split shifts; branch-local hours; recurring/dated breaks; full/partial leave; temporary hours replace date. Exact review expires in 15 minutes; future changes/holds can invalidate; retained conflicts need explicit reason. Service qualification and price/duration/online variants versioned, existing snapshots unchanged. |
| Service catalogue | `ServiceManagementController`; `ServiceCatalogManager`; `Pages/Services/Index.vue` | FR-02/04/06/09/14/18/19 | Overview, Team & locations, Booking & rules; settings permission and all affected staff/branch scope. Required name/price/active duration; incomplete draft is allowed. Staff > branch > base price; active + processing + cleanup; online/client/notice/window/deposit/tax-reference. Categories and add-ons; safe duplicate inactive/internal; activate/deactivate preserves history. Pending exact save replay, stale revisions and hold protection. |
| Capacity subscription | `BusinessBillingController`; `CapacityBillingController`; `CapacityCalculator.vue`; `CapacityBillingPanel.vue` | FR-01/13/19 | Billing authority independent of working profile. One capacity plan; USD current catalogue; whole-business count once; included location/person, progressive staff bands; annual upfront; review and provider-verification gates. Expansion paid; reductions/cycle/current-price migration at renewal. Existing terms retained. |
| Recovery, invoices, credits and cancellation | `CapacityBillingPanel.vue`; `Overview.vue`; `billingRecovery` | FR-01/13/19 | Continue original checkout; close original before restart; paid/ambiguous protected; status cooldown/support reference; portal recovery. Recorded subscription invoices/documents and payment-method portal. Term-end cancel/keep until end, outstanding invoices survive. Monthly allowance independent of annual cadence; optional pack only if published/approved, no auto purchase. |
| Personal account and security | `Pages/Profile/Show.vue`; profile partials; Fortify actions/config; `Auth/*` | FR-01/05/19 | Name/email/photo, email re-verification, current-password update, confirmed authenticator + recovery codes, other-session logout, password recovery, permanent personal login deletion. Account deletion is separate from business closure/cancellation/retention. API and legacy Jetstream Teams are disabled and not documented as available. |
| Activity | `Pages/Team/Activity.vue`; `TeamManagementController::activity`; `AuditEventPresentation` | FR-19 | Permissioned search/category/pagination, business-zone times and redacted meaningful summaries. Read-only; applied changes rather than navigation clicks. |

## Article inventory

All listed article files have customer-facing copy, access/prerequisites, actual available labels, verification guidance and relevant recovery/limitations. No task IDs, technical requirement IDs, credentials, account-specific data or fake images are embedded in public copy.

| Article | Category | Action key | Screenshots requested |
| --- | --- | --- | --- |
| `account-delete` — Understand account deletion and business closure | account | profile |  |
| `account-profile` — Update your name, email, photo or password | account | profile |  |
| `account-recovery` — Recover a forgotten password or lost authenticator | account | — |  |
| `account-security` — Set up two-factor authentication and review devices | account | profile |  |
| `account-sign-in` — Sign in to the correct workspace | account | profile |  |
| `billing-access` — Why is a feature missing or the workspace read-only? | billing | billing |  |
| `billing-cancel` — Cancel renewal or keep your subscription | billing | billing |  |
| `billing-capacity` — Understand locations, bookable staff and pricing | billing | billing |  |
| `billing-change-capacity` — Change capacity or switch monthly and yearly billing | billing | billing |  |
| `billing-invoices` — View subscription invoices and manage your payment method | billing | billing |  |
| `billing-recovery` — Recover an unfinished checkout or delayed billing update | billing | billing |  |
| `billing-start-subscription` — Review and start a paid subscription | billing | billing | billing-overview |
| `billing-text-credits` — Understand monthly text credits and optional credit packs | billing | billing |  |
| `business-booking-link` — Change and share your booking link | business | setup |  |
| `business-booking-preferences` — Configure online booking preferences | business | setup |  |
| `business-branding` — Add your logo, cover image and accent colour | business | setup |  |
| `business-hours` — Set opening hours and split opening periods | business | setup | business-hours |
| `business-location` — Add or edit a location | business | locations | business-locations |
| `business-profile` — Edit your business profile and regional settings | business | setup |  |
| `business-publish` — Preview, publish or resume your booking page | business | setup | business-preview |
| `services-addons` — Create and attach an optional add-on | services | services |  |
| `services-assignments` — Choose service locations and qualified staff | services | services |  |
| `services-categories` — Organize, reorder or archive service categories | services | services |  |
| `services-create` — Create a service | services | services | services-catalogue |
| `services-online-availability` — Why is a service missing online or showing no times? | services | services |  |
| `services-prices-duration` — Understand service prices and appointment timing | services | services |  |
| `services-update-retire` — Update, duplicate, activate or deactivate a service | services | services |  |
| `setup-create-account` — Create your owner account | getting-started | — |  |
| `setup-first-workspace` — Prepare your first workspace | getting-started | setup |  |
| `setup-import-records` — Import clients, staff, services or products | getting-started | setup |  |
| `setup-readiness` — Finish setup and understand readiness | getting-started | setup | setup-readiness |
| `team-accept-invitation` — Accept a team invitation | team | — |  |
| `team-activity` — Review the business activity log | team | activity |  |
| `team-add-member` — Add a team member | team | team | team-directory |
| `team-coverage` — Read live availability and weekly coverage | team | team |  |
| `team-disable-restore-login` — Disable or restore a team login | team | team |  |
| `team-edit-profile` — Edit a working profile or make a person inactive | team | team |  |
| `team-invite-login` — Invite an existing team member to sign in | team | team |  |
| `team-roles-permissions` — Understand roles, capabilities and location access | team | team |  |
| `team-service-assignments` — Assign services and staff-specific prices or durations | team | team |  |
| `team-time-off` — Add time off, breaks or temporary hours | team | team |  |
| `team-weekly-hours` — Set a person’s weekly hours and split shifts | team | team |  |

## Route and action contracts

- `setup` → `business.configuration.show`; `locations` → `business.locations.index`; `team` → `business.team.index`; `services` → `business.services.index`; `billing` → `business.billing.show`; `activity` → `business.activity.index`; `profile` → `profile.show`.
- Public articles do not contain tenant identifiers. Action resolution authenticates and re-evaluates the selected business’s permission/feature access; destination middleware remains authoritative. Public documentation is not authority to run an operation.
- Cross-domain links are curated rather than guessed from user-entered URLs. Related links in this article set were checked against the complete local article set.

## Honest coverage and UI gaps

1. Online deposit-required public confirmation remains blocked by OPEN-16. Help explicitly explains the limitation and routes the customer to the business; it does not document a payment element as available.
2. Physical rooms/chairs/equipment and service segment/resource requirements exist in the scheduling engine and preserved service details, but the current Services/Business setup composition does not provide a complete reviewed advanced resource/layout editor. Do not publish invented resource-creation steps. OPEN-20 owns the product contract. Actual service troubleshooting explains resource fit.
3. New overnight shifts, travel buffers and future-effective variant editing lack an approved current editor (OPEN-19). Help covers supported split shifts and dated exceptions, not those commands.
4. Existing service imports cannot update or create over a matched existing service; the import guide directs Skip row and catalogue review. The CSV `price_minor` unit convention is explained without advertising a conversion wizard. Import error-export storage exists but no verified customer download control is documented.
5. Branding UI retains legacy “Pro” wording even though new purchases are one capacity plan. Help documents current entitlement checks without advertising the retired tier selection. This pre-existing product wording mismatch needs a separate synchronized UI follow-up.
6. Subscription amounts and SMS economics come from the current reviewed catalogue. Help avoids a second hardcoded price list. Optional credit-pack instructions are conditional on an actual visible offer; country economics/live reversals remain OPEN-22.
7. Public screenshot requests are in article metadata only. Existence/approved provenance must be checked by the capture manifest and validation command. No pre-existing production screenshot is silently reused.
8. Account deletion is the installed personal-account action. It does not certify tenant closure, destructive client privacy execution, refund execution or retention approval (OPEN-10/13). Help explicitly distinguishes these.
9. Business types personalize starter catalogue/title suggestions and regional formatting. No plan, country, business-type clinical/regulated-service readiness or different permission authority is inferred. API tokens and legacy Jetstream Teams are disabled, so no how-to is published for them.
10. Provider outages, inbox/carrier delivery, real charges, customer deployments and legal/regional launch approvals remain outside source-review evidence. Live fixes and provider recovery must use their existing certification gates.

## Screenshot coverage

Current assets and exact article references are in `content/help-media.json` and
the article JSON. The completed local capture includes introduction, readiness,
locations, opening hours, booking preferences, preview, team directory/add-member/
weekly coverage/Schedule/Access, service catalogue/editor/assignments/rules,
billing overview and account security. Instructions use genuine unmodified browser
captures from isolated synthetic tenants. See the [final audit](README.md) for
counts, visual verification and explicit remaining text-only workflows. Impact
review, invitation acceptance, MFA enrollment, imports and invoice/provider-return
screens should receive further captures when safe, representative fixtures are
available; credentials/private links must never be published.
