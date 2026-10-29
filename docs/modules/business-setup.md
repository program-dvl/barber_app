# Business setup and operational readiness

Requirements: [FR-01–FR-05, FR-09, FR-13–FR-15, FR-19](../product-requirements.md).
Existing foundation: ADR-030/031/032/035/050/053. This specification restores the
affected onboarding boundary under OPEN-14; Phase 2 remains deferred.

## Lifecycle and starter records

Registration records an owner intent; verified email provisions one Business,
Owner membership, default Location, trial, starter roles and client-form templates
transactionally. Four resumable decisions personalize the workspace; public phone/address may be added before online publication. The existing
business-first transaction prepares type-specific categories, selected services,
regular location hours, one reused owner StaffProfile, working hours and service
assignments. Generated public IDs retain provenance; they are ordinary editable
tenant records. No fake clients, appointments, sales or payments are created.
Completed sessions and existing catalogues are never reseeded. Retry must preserve
customization and cannot duplicate initialization. Defaults are internally versioned.

## Readiness center

One backend projection powers the page and shared setup drawer. Operational
essentials are profile/region, active location/hours, valid active services, active
team, a qualified staff/service/location combination with overlapping usable hours,
and booking interval. Offline operation does not require online visibility,
publication, a logo, an additional employee or a notification/payment provider.
Readiness recomputes from current configuration, including dated assignments and
actual branch membership, rather than retained completion flags. Configured
readiness does not promise an unoccupied appointment slot.

Online publication retains public contacts, policies, slug, valid online delivery
paths and desktop/mobile review. Preparing starter records does not count as that
review and does not publish. Owners explicitly review and publish. Existing live
businesses stay live; new setup never publishes implicitly. Optional branding,
imports, notification channels and online payments never reduce essential progress.

## Editing and access

The overview recommends one incomplete essential and exposes direct navigation.
Starter provenance, prices, duration and owner identity are explicit. Compact
service review reuses the reviewed catalogue command, revisions, hold protection,
reasoned future impact and exact replay. Deactivation retains history. Weekly hours
allow different days and split periods, explicit save and copy actions. Established
schedule changes retain impact review. Staff assignment/availability uses the Team
domain; onboarding must not introduce a competing permission or schedule engine.

Settings authority is enforced server-side. Business-wide settings require an
Owner or settings-capable member assigned to every business location. Branch-only
managers retain their scoped Services/Team workspaces. Tenant binding and explicit
business IDs apply to every read and command. Meaningful applied configuration
changes produce existing Activity events; navigation and review clicks do not.

## UX and evidence

ADR-074 adds optional salon photos and explicit logo/cover previews/saves. Owners
edit one photo at a time and can return without losing drafts. Image selection
does not upload automatically, and no photo is a readiness requirement. Saved
media and private previews reuse existing settings authority. See
[booking presentation](booking-presentation.md) and its dated local evidence.

ADR-075 places **Photos & branding** directly in setup navigation, alongside the
existing operational sections. It remains optional and never reduces readiness
or blocks appointments/publication. Saved images and selected previews make
explicit save/remove actions understandable without technical setup knowledge.
Gallery loading, empty and unavailable states are distinct. Infrastructure
failures retain setup work, stop ineffective retries and offer support with a
safe reference; a missing gallery table is not an empty gallery. Source updates
require the matching additive photo/review migrations before using the new
public/preview projection. See the [setup recovery audit](../audits/2026-10-29-setup-recovery/README.md).

Setup remains accessible after completion. Required and optional work are separated;
online states distinguish not published, ready to publish, paused and live. Profile
groups use progressive disclosure, explicit save and dirty-navigation protection.
Local preview is labeled; it never fabricates public availability or payment
connection status. Cash/manual checkout can work without connecting Stripe;
subscription billing is a separate context. SMS/email transport state comes from
existing transport configuration and simulation state. “Sending configured” is not a live delivery certificate. Unsupported mobile
channels and Phase 2 memberships/packages are absent.

Verification and six-perspective review live in the [dated Business Setup audit](../audits/2026-10-04-business-setup/README.md).
Browser evidence, backend isolation/rollback/replay tests and production builds
must be recorded before the project-status document claims verification.

## Public booking link integration (2026-10-10, FR-01/03/09; ADR-055)

Starter provisioning generates a readable name-based slug, with numeric suffixes
only for collisions. Owner edits retain the existing permission/version/review
workflow and validate length, URL safety, reserved routes and current/historical
ownership. A single database reservation namespace prevents concurrent claims
across businesses; deleted ownership remains reserved. Historical aliases
redirect to the business's current primary link.

The setup preview/copy action, services page, publication notification and
rebook/payment return destinations use centralized primary booking configuration.
The unpromoted main-domain fallback remains public and renders the same journey.
Publication, active locations/services/staff, dated qualified assignments,
location-local hours/breaks/exceptions/time off, lead times and advance windows
continue through the existing readiness and scheduling services; public booking
never substitutes a second schedule. New-client restrictions are rechecked from
submitted contact classification, including the applicable deposit before final
consent. Provider readiness still does not imply a usable public paid interface;
OPEN-16 remains a launch blocker for required-deposit online booking.

Readable starter booking links depend on the slug reservation migration being
applied before onboarding completion. A source/schema mismatch can fail the
final step while preserving saved answers. See the
[deployment recovery instructions](../deployment/public-booking.md#last-onboarding-step-returns-500-after-a-source-update).
`GuidedOnboardingExperienceTest` reproduces the missing table, applies the
migration twice, and verifies saved-answer recovery and duplicate-free completion.

Google address search and entrance setup share the existing guided location step and Locations editor; the sole/primary branch maintains the business address summary. See [Locations](location-intelligence.md) and ADR-064. Coordinate entry is deliberately absent.


## Introduction and private preview refinement (2026-10-14; ADR-066)

FR-01/02/03/05/09/19: Google registration enters the introduction directly,
ignoring stale intended navigation. Password registration retains verification
and the existing verified-email setup redirect. Four saved steps remain. The
owner-provider question explains bookable team/calendar preparation; the online
provider question is removed without erasing historical answers. Imports remain
optional for all authorized businesses.

New unsaved introductions use `daily_9_21`: all seven days, 09:00–21:00, applied
consistently to starter location and owner working hours. Saved legacy presets
and all completed/established schedules remain unchanged. There is no hours
questionnaire; the visible note directs exact editing before accepting bookings.
Every step shares the go-back/edit-later reminder.

Address entry chooses Google search or manual entry; the selected address is a
review summary, not another competing input. Entrance maps open only on request.
The Business profile shows its address/action above the profile groups.

Private desktop/mobile preview uses the real public services component and shared
catalog/business presentation, including private branding assets. It shows saved
services, branch choices, prices and address; it cannot continue to availability,
client details or payment. No browser booking session is restored, no commerce
record is initialized and no appointment is reserved. A successful preview may
be framed only by the same origin; branding and preview retain business-wide
settings authorization, tenant isolation, no-store and noindex. Preview viewing
and explicit “I reviewed this preview” remain separate. Publication stays explicit.

[Evidence and limitations](../audits/2026-10-14-setup-refinement/README.md).
